<?php
/**
 * Escritor concorrente exclusivamente fictício e CLI.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\{IdentityWpdb, OidcConfiguration, WpdbIdentityLinkRepository};
use Arn\Intranet\Identity\PreRegistration\{WpdbPreRegistrationRepository, PreRegistrationLoginResolver};

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Apenas ficheiros locais de ensaio e tabelas fictícias.
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || 'local' !== wp_get_environment_type() || ! current_user_can( 'manage_options' ) || 4 !== count( $args ) ) {
	throw new RuntimeException( 'Worker Workspace recusado.' );}
require_once __DIR__ . '/workspace-fixture.php';
add_filter( 'query', ArnWorkspaceSqlFixture::normalWriteGuard(), PHP_INT_MAX );
[$payload, $ready, $result, $index] = $args;
$directory                          = realpath( dirname( $payload ) );
if ( false === $directory || 1 !== preg_match( '/\Aarn_workspace_claim_[a-f0-9]{16}\z/D', basename( $directory ) ) || realpath( dirname( $ready ) ) !== $directory || realpath( dirname( $result ) ) !== $directory || ! in_array( $index, array( '0', '1' ), true ) ) {
	throw new RuntimeException( 'Worker Workspace inválido.' );}
$exit    = 5;
$outcome = 'technical_failure';
$db      = null;
try {
	$data = json_decode( file_get_contents( $payload ), true, 32, JSON_THROW_ON_ERROR );
	if ( 1 !== preg_match( '/\Aarn_ws_[a-f0-9]{12}_\z/D', $data['prefix'] ?? '' ) || ! is_int( $data['user_id'] ?? null ) || $data['user_id'] < 1000000000 || ! is_string( $data['email'] ?? null ) || ! str_ends_with( $data['email'], '@example.invalid' ) ) {
		throw new RuntimeException();}
	$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$db->set_prefix( $data['prefix'] );
	$db->suppress_errors( true );
	$links   = new WpdbIdentityLinkRepository( $db );
	$records = new WpdbPreRegistrationRepository( $db );
	$values  = array(
		'ARN_OIDC_ENABLED'          => '1',
		'ARN_OIDC_CLIENT_ID'        => 'fixture',
		'ARN_OIDC_CLIENT_SECRET'    => 'fixture-secret',
		'ARN_OIDC_REDIRECT_URI'     => 'https://fixture.example.invalid/callback',
		'ARN_OIDC_WORKSPACE_DOMAIN' => 'example.invalid',
	);
	$config  = OidcConfiguration::fromEnvironment( static fn( string $key )=>$values[ $key ] ?? false );
	$claims  = array(
		'iss'            => 'https://accounts.google.com',
		'sub'            => 'synthetic-concurrent-' . $index,
		'email'          => $data['email'],
		'hd'             => 'example.invalid',
		'email_verified' => true,
	);
	file_put_contents( $ready, (string) $db->get_var( 'SELECT CONNECTION_ID()' ), LOCK_EX );
	try {
		$resolved_link = PreRegistrationLoginResolver::forRepositories( $links, $records, static fn(): bool=>true )->resolve( $claims, $config, 1894705201 );
		if ( $resolved_link->user_id !== $data['user_id'] ) {
			throw new RuntimeException();
		}$outcome = 'committed';
		$exit     = 0;
	} catch ( Throwable ) {
		$record = $records->findForUser( $data['user_id'] );
		$winner = $links->findForUser( $data['user_id'] );
		if ( null !== $record && 'Linked' === $record->state->value && 2 === $record->revision && null !== $winner && $winner->active && $winner->authorized && $winner->subject !== $claims['sub'] && str_starts_with( $winner->subject, 'synthetic-concurrent-' ) ) {
			$outcome = 'conflict';
			$exit    = 3;}
	}
} catch ( Throwable ) {
	$outcome = 'technical_failure';
	$exit    = 5;}
if ( null !== $db ) {
	$db->close();
}file_put_contents( $result, json_encode( array( 'result' => $outcome ), JSON_THROW_ON_ERROR ), LOCK_EX );
exit( $exit ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Código de saída de processo CLI, sem HTML.
