<?php
/** Isolated SQL, session and interrupted panel migration checks.
 *
 * @package ArnIntranet
 */

// phpcs:disable WordPress.DB, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped, Squiz.Commenting
use Arn\Intranet\Identity\{IdentityWpdb, LocalIdentityLink, WpdbIdentityLinkRepository};
use Arn\Intranet\Identity\Institutional\{MaintenanceGate, MigrationReceipts, MigrationRunner, PrincipalSource, SessionIssuer};
use Arn\Intranet\Authorization\{AccountState, Grant, Role, Scope};
use Arn\Intranet\Authorization\Storage\{WpdbAuthorizationRepository, ChangeContext};
use Arn\Intranet\Communications\{InstitutionalPanel, NoticeSchema, Notice, NoticeContent};
use Arn\Intranet\F112\Audience;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Apenas laboratório.' );
}
define( 'ARN_INSTITUTIONAL_PANEL_ENABLED', true );
$checks = 0;
$assert = static function ( bool $ok ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Painel: asserção ' . ( $checks + 1 ) ); }
	++$checks;
};
$refuse = static function ( callable $call ) use ( $assert ): void {
	try {
		$call();
	} catch ( Throwable ) {
		$assert( true );
		return; }
	$assert( false );
};
$actor  = get_current_user_id();
$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
$https  = $_SERVER['HTTPS'] ?? null;
$db     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$prefix = 'arn_panel_' . bin2hex( random_bytes( 6 ) ) . '_';
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$dir = sys_get_temp_dir() . '/' . $prefix;
mkdir( $dir, 0700 );
$gate    = null;
$filter  = null;
$user    = 0;
$anchors = array();
try {
	$gate = new MaintenanceGate( $dir, ABSPATH, true );
	$gate->exclusive();
	$receipts = new MigrationReceipts( $gate, hash( 'sha256', $prefix ) );
	$runner   = new MigrationRunner( $db, $gate, $receipts );
	// Force a real partial DDL: objects exists but versions cannot be created.
	$filter = static fn( string $sql ): string => str_starts_with( $sql, 'CREATE TABLE `' . $prefix . 'arn_comm_versions`' ) ? 'INVALID PANEL DDL' : $sql;
	add_filter( 'query', $filter );
	$refuse( static fn() => $runner->apply( $actor, 'synthetic-backup' ) );
	remove_filter( 'query', $filter );
	$filter = null;
	$assert( file_exists( $dir . '/maintenance' ) );
	$assert( 4 === count( ( new NoticeSchema( $db ) )->missingTables( true ) ) );
	$gate->close();
	$gate = new MaintenanceGate( $dir, ABSPATH, true );
	$gate->exclusive();
	$receipts = new MigrationReceipts( $gate, hash( 'sha256', $prefix ) );
	$runner   = new MigrationRunner( $db, $gate, $receipts );
	$refuse( static fn() => $runner->release( $actor ) );
	$refuse( static fn() => $runner->apply( $actor, 'different-backup' ) );
	$runner->apply( $actor, 'synthetic-backup' );
	$runner->apply( $actor, 'synthetic-backup' );
	( new NoticeSchema( $db ) )->assertReady();
	$assert( in_array( 'communications', array_column( $receipts->all(), 'phase' ), true ) );
	$runner->release( $actor );
	$assert( ! file_exists( $dir . '/maintenance' ) );
	$assert( '0' === (string) $db->get_var( "SELECT COUNT(*) FROM {$prefix}arn_comm_objects" ) );
	$links = new WpdbIdentityLinkRepository( $db );
	$auth  = new WpdbAuthorizationRepository( $db );
	$user  = wp_insert_user(
		array(
			'user_login' => $prefix . 'synthetic',
			'user_email' => $prefix . '@example.invalid',
			'user_pass'  => wp_generate_password( 40 ),
			'role'       => 'administrator',
		)
	);
	$assert( is_int( $user ) );
	$context = new ChangeContext( $actor, 'Ensaio fictício', 'fixture/panel', time() );
	$auth->createAccount( $user, $context );
	$auth->changeState( $user, AccountState::Active, 1, $context );
	$links->create( new LocalIdentityLink( $user, 'accounts.google.com', 'synthetic-panel', false, false ), $actor );
	$links->changeState( $user, true, true, $actor );
	$source           = new PrincipalSource( $links, $auth, static fn(): bool => true, static fn(): int => time() );
	$issuer           = new SessionIssuer( $source );
	$_SERVER['HTTPS'] = 'on';
	wp_set_current_user( $user );
	$issue = static function () use ( $issuer, $links, $user ): void {
		$token                       = $issuer->issue( $links->findForUser( $user ) );
		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user, time() + 3600, 'logged_in', $token );
	};
	$issue();
	$panel = new InstitutionalPanel( $db, $source, static fn(): int => time() );
	$refuse( static fn() => $panel->read() ); // Native administrator is not business authority.
	$grant = $auth->createGrant( new Grant( $user, Role::Employee, Scope::institutional(), time() - 1, null ), 2, $context );
	$refuse( static fn() => $panel->read() ); // Revision invalidates old session.
	$issue();
	$assert( array() === $panel->read()['notices'] );
	// Synthetic persisted histories only in this random prefix. No copying of any database.
	$seed      = static function ( string $title, Audience $audience, bool $publish, ?int $expiry = null ) use ( $db, $prefix, $user, $actor, &$anchors, $assert ): int {
		global $wpdb;
		$id = wp_insert_post(
			array(
				'post_type'    => 'arn_comunicado',
				'post_status'  => 'private',
				'post_author'  => $user,
				'post_title'   => 'Comunicado privado',
				'post_content' => '',
				'post_excerpt' => 'arn-f112:' . $prefix . ':communications',
			)
		);
		$assert( is_int( $id ) && $id > 0 );
		$anchors[] = $id;
		$n         = Notice::draft( $id, $user, new NoticeContent( $title, 'Texto fictício <script> nunca executável.', $audience, 'institutional-review', $expiry ), time() - 10 );
		$states    = array( $n );
		if ( $publish ) {
			$n        = $n->submit( $user, 1, time() - 9, 'Submeter.' );
			$states[] = $n;
			$n        = $n->decide( 'approve', 1, $actor, 2, time() - 8, 'Aprovar.' );
			$states[] = $n; }
		$payload = json_encode( $n->toArray(), JSON_THROW_ON_ERROR );
		$assert(
			1 === $db->insert(
				$prefix . 'arn_comm_objects',
				array(
					'id'        => $id,
					'author_id' => $user,
					'unit_id'   => null,
					'revision'  => $n->revision,
					'payload'   => $payload,
					'digest'    => hash( 'sha256', $payload ),
				)
			)
		);
		$assert(
			1 === $db->insert(
				$prefix . 'arn_comm_versions',
				array(
					'object_id' => $id,
					'number'    => 1,
					'payload'   => json_encode( $n->latest()->toArray(), JSON_THROW_ON_ERROR ),
				)
			)
		);
		$previous = str_repeat( '0', 64 );
		foreach ( $states as $state ) {
			$event  = $state->history[ $state->revision - 1 ];
			$digest = hash( 'sha256', json_encode( $state->toArray(), JSON_THROW_ON_ERROR ) );
			$assert(
				1 === $db->insert(
					$prefix . 'arn_comm_audit',
					array(
						'object_id'   => $id,
						'revision'    => $state->revision,
						'event'       => $event['operation'],
						'actor_id'    => $event['actor_id'],
						'occurred_at' => $event['occurred_at'],
						'reason'      => $event['reason'],
						'before_hash' => $previous,
						'after_hash'  => $digest,
						'payload'     => json_encode( $event, JSON_THROW_ON_ERROR ),
					)
				)
			);
			$previous = $digest;
		}
		return $id;
	};
	$published = $seed( 'Publicado institucional', Audience::all(), true );
	$seed( 'Rascunho oculto', Audience::all(), false );
	$seed( 'Unidade oculta', Audience::unit( 'LAB-A' ), true );
	$seed( 'Expirado oculto', Audience::all(), true, time() - 1 );
	$result = $panel->read();
	$assert( 1 === count( $result['notices'] ) && $published === $result['notices'][0]['id'] );
	$assert( array() === $panel->read( $published )['notices'] );
	$refuse( static fn() => $panel->read( 0 ) );
	$db->query( $db->prepare( "UPDATE {$prefix}arn_comm_objects SET digest=%s WHERE id=%d", str_repeat( '0', 64 ), $published ) );
	$refuse( static fn() => $panel->read() );
	$db->query( $db->prepare( "UPDATE {$prefix}arn_comm_objects SET digest=SHA2(payload,256) WHERE id=%d", $published ) );
	// Revoke during the content read, on the independent authorization connection.
	$raced  = false;
	$filter = static function ( string $sql ) use ( &$raced, $prefix, $auth, $user, $grant, $context ): string {
		if ( ! $raced && str_contains( $sql, 'SELECT id FROM `' . $prefix . 'arn_comm_objects`' ) ) {
			$raced = true;
			$auth->revokeGrant( $user, $grant, 3, $context );
		}
		return $sql;
	};
	// The source and read model need separate connections to preserve their transaction boundaries.
	$other = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$other->set_prefix( $prefix );
	try {
		$panel = new InstitutionalPanel( $other, $source, static fn(): int => time() );
		add_filter( 'query', $filter );
		$refuse( static fn() => $panel->read() );
		remove_filter( 'query', $filter );
		$filter = null;
		$assert( $raced );
	} finally {
		$other->close(); }
	$panel = new InstitutionalPanel( $db, $source, static fn(): int => time() );
	$issue();
	$refuse( static fn() => $panel->read() );
	$auth->changeState( $user, AccountState::Suspended, 4, $context );
	$assert( null === $source->current() );
} finally {
	if ( null !== $filter ) {
		remove_filter( 'query', $filter ); }
	wp_set_current_user( $actor );
	if ( null === $cookie ) {
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	} else {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie; }
	if ( null === $https ) {
		unset( $_SERVER['HTTPS'] );
	} else {
		$_SERVER['HTTPS'] = $https; }
	foreach ( $anchors as $anchor_id ) {
		wp_delete_post( $anchor_id, true ); }
	if ( is_int( $user ) && $user > 0 ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user ); }
	foreach ( array( 'arn_comm_objects', 'arn_comm_versions', 'arn_comm_audit', 'arn_comm_notifications', 'arn_comm_schema', 'arn_authz_grants', 'arn_authz_audit', 'arn_authz_accounts', 'arn_authz_schema', 'arn_identity_audit', 'arn_identity_links' ) as $suffix ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . $suffix ) ); }
	$db->close();
	$gate?->close();
	foreach ( glob( $dir . '/*' ) as $file ) {
		unlink( $file );
	} rmdir( $dir );
}
WP_CLI::success( 'Painel institucional: ' . $checks . ' verificações. Fixtures removidas.' );
