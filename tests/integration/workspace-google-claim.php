<?php
/**
 * Primeiro vínculo com identidade e auditorias na mesma transacção SQL.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\{IdentityWpdb, WpdbIdentityLinkRepository, LocalIdentityLink, OidcConfiguration};
use Arn\Intranet\Identity\PreRegistration\{WorkspaceBatch, WpdbPreRegistrationRepository, PreRegistrationLoginResolver};
use Arn\Intranet\Tests\Doubles\WorkspaceInventoryFixture;

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Laboratório SQL exclusivo com nomes preparados e claims fictícias.
require_once __DIR__ . '/workspace-fixture.php';
require_once dirname( __DIR__ ) . '/phpunit/doubles/WorkspaceInventoryFixture.php';
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio Workspace recusado.' );}
if ( 'development' === wp_get_environment_type() ) {
	if ( \Arn\Intranet\Identity\PreRegistration\WorkspacePreRegistrationCommand::permitted() ) {
		throw new RuntimeException( 'Gate Workspace inválido.' );}
	WP_CLI::success( 'Workspace claim: development negativo, sem escrita.' );
	return;
}
require_once __DIR__ . '/workspace-claim-concurrency.php';
$fixture            = new ArnWorkspaceSqlFixture();
$db                 = $fixture->database;
$checks             = 0;
$assert             = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Workspace claim: ' . $label );
	}++$checks;
};
$deny               = static function ( callable $operation ) use ( $assert ): void {
	try {
		$operation();
	} catch ( Throwable ) {
		$assert( true, 'recusa' );
		return;
	}$assert( false, 'devia recusar' );
};
$normal_write_guard = ArnWorkspaceSqlFixture::normalWriteGuard();
add_filter( 'query', $normal_write_guard, PHP_INT_MAX );
try {
	$repository = new WpdbPreRegistrationRepository( $db );
	$repository->installSchema();
	$links = new WpdbIdentityLinkRepository( $db );
	$links->installSchema();
	$f       = WorkspaceInventoryFixture::make( 6 );
	$batch   = WorkspaceBatch::validate( $f['inventory'], $f['source'], $f['source_hash'], $f['inventory_hash'], 'example.invalid', 6, 1894705200 );
	$records = array();
	foreach ( $batch->entries as$entry ) {
		$records[] = $repository->provision( $entry, $batch, get_current_user_id(), 1894705200 );}
	$values   = array(
		'ARN_OIDC_ENABLED'          => '1',
		'ARN_OIDC_CLIENT_ID'        => 'fixture',
		'ARN_OIDC_CLIENT_SECRET'    => 'fixture-secret',
		'ARN_OIDC_REDIRECT_URI'     => 'https://fixture.example.invalid/callback',
		'ARN_OIDC_WORKSPACE_DOMAIN' => 'example.invalid',
	);
	$config   = OidcConfiguration::fromEnvironment( static fn( string $key )=>$values[ $key ] ?? false );
	$claims   = static fn( int $i, string $sub ): array=>array(
		'iss'            => 'https://accounts.google.com',
		'sub'            => $sub,
		'email'          => $records[ $i ]->primary_email,
		'hd'             => 'example.invalid',
		'email_verified' => true,
	);
	$resolver = PreRegistrationLoginResolver::forRepositories( $links, $repository, static fn(): bool=>true );
	$first    = $resolver->resolve( $claims( 0, 'synthetic-first' ), $config, 1894705201 );
	$assert( $first->authorized && $first->active && $first->user_id === $records[0]->user_id, 'primeiro vínculo activo' );
	$assert( 'Linked' === $repository->findForUser( $first->user_id )->state->value && 2 === $repository->findForUser( $first->user_id )->revision, 'Linked na mesma decisão' );
	$assert( 2 === (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id=%d', $fixture->prefix . 'arn_identity_audit', $first->user_id ) ), 'create e activate auditados' );
	$assert( 2 === $repository->findForUser( $resolver->resolve( $claims( 0, 'synthetic-first' ), $config, 1894790000 )->user_id )->revision, 'login posterior não renova ou escreve revisão' );
	$deny( static fn()=>$resolver->resolve( $claims( 0, 'synthetic-other' ), $config, 1894705201 ) );
	$deny( static fn()=>$resolver->resolve( $claims( 1, 'synthetic-expired' ), $config, 1894788000 ) );
	$bad          = $claims( 1, 'synthetic-alias' );
	$bad['email'] = 'alias@example.invalid';
	$deny( static fn()=>$resolver->resolve( $bad, $config, 1894705201 ) );
	$db->query( $db->prepare( 'UPDATE %i SET meta_value=%s WHERE user_id=%d AND meta_key=%s', $fixture->prefix . 'usermeta', 'a:1:{s:13:"administrator";b:1;}', $records[1]->user_id, $fixture->prefix . 'capabilities' ) );
	$deny( static fn()=>$resolver->resolve( $claims( 1, 'synthetic-capability' ), $config, 1894705201 ) );
	$db->query( $db->prepare( 'UPDATE %i SET meta_value=%s WHERE user_id=%d AND meta_key=%s', $fixture->prefix . 'usermeta', 'a:0:{}', $records[1]->user_id, $fixture->prefix . 'capabilities' ) );
	$links->changeState( $first->user_id, false, false, get_current_user_id() );
	$deny( static fn()=>$resolver->resolve( $claims( 0, 'synthetic-first' ), $config, 1894705202 ) );
	$trigger = $fixture->prefix . 'claim_audit_deny';
	$db->query( $db->prepare( "CREATE TRIGGER %i BEFORE INSERT ON %i FOR EACH ROW BEGIN IF NEW.action='activate' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture final failure'; END IF; END", $trigger, $fixture->prefix . 'arn_identity_audit' ) );
	$deny( static fn()=>$resolver->resolve( $claims( 2, 'synthetic-rollback' ), $config, 1894705201 ) );
	$assert( 'AwaitingGoogle' === $repository->findForUser( $records[2]->user_id )->state->value && null === $links->find( 'https://accounts.google.com', 'synthetic-rollback' ), 'falha final reverte cadastro e vínculo' );
	$assert( 1 === (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i WHERE registration_id=%s', $fixture->prefix . 'arn_workspace_audit', $records[2]->id ) ), 'auditoria Workspace também revertida' );
	$db->query( $db->prepare( 'DROP TRIGGER %i', $trigger ) );
	$conflicts = 0;
	$resolver  = PreRegistrationLoginResolver::forRepositories(
		$links,
		$repository,
		static fn(): bool=>true,
		static function () use ( &$conflicts ): void {
			++$conflicts;
		}
	);
	$db->insert(
		$fixture->prefix . 'users',
		array(
			'ID'                  => 2000000000,
			'user_login'          => 'old.fixture',
			'user_pass'           => wp_hash_password( bin2hex( random_bytes( 24 ) ) ),
			'user_email'          => 'old@example.invalid',
			'display_name'        => 'Conta Fictícia Anterior',
			'user_registered'     => '2030-01-15 10:00:00',
			'user_activation_key' => '',
		)
	);
	$old = new LocalIdentityLink( 2000000000, 'https://accounts.google.com', 'synthetic-old-pair', false, false );
	$links->create( $old, get_current_user_id() );
	$links->changeState( $old->user_id, true, true, get_current_user_id() );
	$existing = $resolver->resolve( $claims( 4, 'synthetic-old-pair' ), $config, 1894705201 );
	$assert( $existing->user_id === $old->user_id && 'AwaitingGoogle' === $repository->findForUser( $records[4]->user_id )->state->value && 1 === $conflicts, 'par anterior preservado, destino pendente não adoptado' );
	// Simula a descoberta do par por outro pedido entre lookup e INSERT.
	$deny( static fn()=>$links->claimActive( static fn( IdentityWpdb $same )=>( new WpdbPreRegistrationRepository( $same ) )->claim( $claims( 4, 'synthetic-old-pair' ), $config, 1894705201 ) ) );
	$assert( 1 === $repository->findForUser( $records[4]->user_id )->revision && 'AwaitingGoogle' === $repository->findForUser( $records[4]->user_id )->state->value && $old->user_id === $links->find( $old->issuer, $old->subject )->user_id, 'colisão unique reverte Linked e preserva o par anterior' );
	arn_workspace_claim_concurrency( $db, $records[5], $assert );
	$assert( 2 === $repository->findForUser( $records[5]->user_id )->revision && null !== $links->findForUser( $records[5]->user_id ), 'só uma passagem para Linked' );
	$assert( 2 === (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id=%d', $fixture->prefix . 'arn_identity_audit', $records[5]->user_id ) ), 'concorrência conserva dois eventos da identidade' );
	WP_CLI::success( 'Workspace claim: ' . $checks . ' verificações.' );
} finally {
	remove_filter( 'query', $normal_write_guard, PHP_INT_MAX );
	$fixture->close();}
