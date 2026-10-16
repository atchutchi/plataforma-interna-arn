<?php
/** Regressões da revisão independente, apenas em tabelas fictícias.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\{LocalIdentityLink, WordPressFederatedSession, WpdbIdentityLinkRepository};
use Arn\Intranet\Identity\PreRegistration\{CurrentPreRegistration, WorkspaceBatch, WpdbPreRegistrationRepository, WordPressPreRegistrationAccess, WorkspacePreRegistrationCommand};
use Arn\Intranet\Tests\Doubles\WorkspaceInventoryFixture;

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- SQL preparado, prefixo exclusivo e nenhuma identidade real.
require_once __DIR__ . '/workspace-fixture.php';
require_once dirname( __DIR__ ) . '/phpunit/doubles/WorkspaceInventoryFixture.php';
if ( 'development' === wp_get_environment_type() ) {
	if ( WorkspacePreRegistrationCommand::permitted() ) {
		throw new RuntimeException( 'Gate Workspace inválido.' );
	}WP_CLI::success( 'Workspace review: development negativo.' );
	return;
}
$fixture = new ArnWorkspaceSqlFixture();
$db      = $fixture->database;
$checks  = 0;
$assert  = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Workspace review: ' . $label );
	}++$checks;
};
$deny    = static function ( callable $action ) use ( $assert ): void {
	try {
		$action();
	} catch ( Throwable ) {
		$assert( true, 'recusa' );
		return; }
	$assert( false, 'devia recusar' );
};
$block   = ArnWorkspaceSqlFixture::normalWriteGuard();
add_filter( 'query', $block, PHP_INT_MAX );
global $wpdb;
$prefix = $wpdb->prefix;
$actor  = get_current_user_id();
try {
	$repository = new WpdbPreRegistrationRepository( $db );
	$repository->installSchema();
	$f      = WorkspaceInventoryFixture::make( 2 );
	$batch  = WorkspaceBatch::validate( $f['inventory'], $f['source'], $f['source_hash'], $f['inventory_hash'], 'example.invalid', 2, 1894705200 );
	$record = $repository->provision( $batch->entries[0], $batch, $actor, 1894705200 );
	$db->insert(
		$fixture->prefix . 'users',
		array(
			'ID'              => 2000000000,
			'user_login'      => 'previous.fixture',
			'user_pass'       => wp_hash_password( 'synthetic-only' ),
			'user_email'      => 'previous@example.invalid',
			'display_name'    => 'Conta Fictícia Anterior',
			'user_registered' => '2030-01-15 10:00:00',
		)
	);
	$wpdb->set_prefix( $fixture->prefix );
	$manager = WP_Session_Tokens::get_instance( 2000000000 );
	$token   = $manager->create( time() + 3600 );
	$guard   = new WordPressPreRegistrationAccess();
	foreach ( array( 'audit', 'version' ) as $failure ) {
		if ( 'audit' === $failure ) {
			$db->query( $db->prepare( 'RENAME TABLE %i TO %i', $fixture->prefix . 'arn_workspace_audit', $fixture->prefix . 'review_hidden_audit' ) ); } else {
			$db->query( $db->prepare( 'UPDATE %i SET version=99', $fixture->prefix . 'arn_workspace_schema' ) ); }
			try {
				$assert( null === CurrentPreRegistration::lookup( 2000000000 ), 'conta anterior preservada com esquema parcial' );
				$assert( 2000000000 === $guard->filterCurrentUser( 2000000000 ) && $manager->verify( $token ), 'sessão anterior conservada' );
				$assert( $guard->filterAuthentication( get_userdata( 2000000000 ) ) instanceof WP_User, 'autenticação anterior preservada' );
				$deny( static fn()=>CurrentPreRegistration::lookup( $record->user_id ) );
				$db->query( $db->prepare( 'DELETE FROM %i WHERE user_id=%d AND meta_key=%s', $fixture->prefix . 'usermeta', $record->user_id, WpdbPreRegistrationRepository::MARKER ) );
				wp_cache_delete( $record->user_id, 'user_meta' );
				$deny( static fn()=>CurrentPreRegistration::lookup( $record->user_id ) );
				$db->insert(
					$fixture->prefix . 'usermeta',
					array(
						'user_id'    => $record->user_id,
						'meta_key'   => WpdbPreRegistrationRepository::MARKER,
						'meta_value' => $record->id,
					)
				);
				wp_cache_delete( $record->user_id, 'user_meta' );
			} finally {
				if ( 'audit' === $failure ) {
					$db->query( $db->prepare( 'RENAME TABLE %i TO %i', $fixture->prefix . 'review_hidden_audit', $fixture->prefix . 'arn_workspace_audit' ) ); } else {
								$db->query( $db->prepare( 'UPDATE %i SET version=1', $fixture->prefix . 'arn_workspace_schema' ) ); }
			}
	}
	$wpdb->set_prefix( $prefix );
	$db->insert(
		$fixture->prefix . 'users',
		array(
			'user_login'      => 'last.collision',
			'user_pass'       => wp_hash_password( 'synthetic-only' ),
			'user_email'      => $batch->entries[1]->primary_email,
			'display_name'    => 'Colisão Fictícia',
			'user_registered' => '2030-01-15 10:00:00',
		)
	);
	$before = array( $fixture->count( 'users' ), $fixture->count( 'arn_workspace_accounts' ), $fixture->count( 'arn_workspace_audit' ) );
	$report = $repository->preflight( $batch, 1894705200 );
	$assert(
		array(
			'create'   => 0,
			'resume'   => 1,
			'conflict' => 1,
		) === $report['totals'],
		'colisão no fim e retoma exacta'
	);
	$assert( $report['entries'][1]['primary_email'] === $batch->entries[1]->primary_email && 'native_collision' === $report['entries'][1]['code'], 'conflito identificado apenas no relatório privado' );
	$assert( array( $fixture->count( 'users' ), $fixture->count( 'arn_workspace_accounts' ), $fixture->count( 'arn_workspace_audit' ) ) === $before, 'preflight sem escrita SQL' );
	$review_links = new WpdbIdentityLinkRepository( $db );
	$review_links->installSchema();
	$review_link = new LocalIdentityLink( $record->user_id, 'https://accounts.google.com', 'synthetic-review-link', true, true );
	// Emissão com revisão exige releitura persistente mesmo se o objecto anterior diz activo.
	$wpdb->set_prefix( $fixture->prefix );
	$db->query( $db->prepare( "UPDATE %i SET state='Linked',revision=2 WHERE user_id=%d", $fixture->prefix . 'arn_workspace_accounts', $record->user_id ) );
	$reg_manager = WP_Session_Tokens::get_instance( $record->user_id );
	$reads       = 0;
	$issuer      = new WordPressFederatedSession(
		static function () use ( &$reads, $review_link ): LocalIdentityLink {
			++$reads;
			return new LocalIdentityLink( $review_link->user_id, $review_link->issuer, $review_link->subject, false, false );
		},
		static fn(): bool=>true
	);
	$deny( static fn()=>$issuer->issue( $review_link, 2 ) );
	$assert( 1 === $reads && array() === $reg_manager->get_all(), 'revogação anterior impede token' );
	$reads          = 0;
	$issuer         = new WordPressFederatedSession(
		static function () use ( &$reads, $review_link ): LocalIdentityLink {
			++$reads;
			return 1 === $reads ? $review_link : new LocalIdentityLink( $review_link->user_id, $review_link->issuer, $review_link->subject, false, false );
		},
		static fn(): bool=>true
	);
	$cookies        = 0;
	$logins         = 0;
	$cookie_capture = static function () use ( &$cookies ) {
		++$cookies;
		return false;
	};
	$login_capture  = static function () use ( &$logins ): void {
		++$logins;
	};
	add_filter( 'send_auth_cookies', $cookie_capture, PHP_INT_MAX );
	add_action( 'wp_login', $login_capture );
	try {
		$deny( static fn()=>$issuer->issue( $review_link, 2 ) );
		$assert( 2 === $reads && array() === $reg_manager->get_all() && 0 === $cookies && 0 === $logins, 'revogação durante token elimina-o sem cookie ou wp_login' );
	} finally {
		remove_filter( 'send_auth_cookies', $cookie_capture, PHP_INT_MAX );
		remove_action( 'wp_login', $login_capture ); }
	WP_CLI::success( 'Workspace review: ' . $checks . ' verificações.' );
} finally {
	$wpdb->set_prefix( $prefix );
	wp_cache_flush();
	remove_filter( 'query', $block, PHP_INT_MAX );
	$fixture->close();
}
