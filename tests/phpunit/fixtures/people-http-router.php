<?php
/** Minimal People controller loopback, without a WordPress bootstrap.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.Security.NonceVerification, Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Minimal disposable HTTP harness.
declare(strict_types=1);

require dirname( __DIR__, 3 ) . '/vendor/autoload.php';
require dirname( __DIR__, 3 ) . '/wp-content/plugins/arn-intranet-core/src/Autoloader.php';
\Arn\Intranet\Autoloader::register( dirname( __DIR__, 3 ) . '/wp-content/plugins/arn-intranet-core/src' );

function status_header( int $status ): void {
	http_response_code( $status ); }
function wp_get_environment_type(): string {
	return 'local'; }

$request_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( ! str_starts_with( (string) $request_path, '/off/' ) ) {
	define( 'ARN_ALLOW_LOCAL_PEOPLE', true );
}
define( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', true );
define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', true );
define( 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE', true );

$views  = array(
	'/funcionarios/'   => 'directory',
	'/meu-perfil/'     => 'profile',
	'/minha-equipa/'   => 'team',
	'/off/meu-perfil/' => 'profile',
);
$view   = $views[ $request_path ] ?? null;
$actor  = isset( $_GET['actor_test'] ) ? 0 : 7;
$source = new class( $actor ) {
	public function __construct( private readonly int $actor ) {}
	public function own(): ?array {
		return $this->actor <= 0 ? null : array(
			'phone'    => '',
			'office'   => '',
			'bio'      => '',
			'revision' => 1,
		); }
	public function ownHistory(): array {
		return array(); }
	public function contact( int $id ): ?array {
		return $this->actor > 0 && 7 === $id ? array(
			'id'   => 7,
			'name' => 'Alfa',
		) : null; }
	public function contactList( \Arn\Intranet\People\PeopleQuery $query ): ?array {
		return $this->actor <= 0 ? null : array(
			'items' => array(),
			'total' => 0,
			'page'  => $query->page,
			'pages' => 0,
		); }
	public function team( \Arn\Intranet\People\PeopleQuery $query ): ?array {
		return null; }
	public function updateOwn( \Arn\Intranet\People\SelfService $service, int $revision ): void {
		if ( 1 !== $revision ) {
			throw new RuntimeException( 'CAS' ); } }
};

if ( null === $view || ! \Arn\Intranet\People\PeopleConfiguration::current() ) {
	\Arn\Intranet\People\PeopleController::refuse();
}
$get = $_GET;
unset( $get['actor_test'] );
$model = \Arn\Intranet\People\PeopleController::decide( $_SERVER['REQUEST_METHOD'], $view, $get, $_POST, $source, $actor, static fn( string $nonce ): bool => 'valid' === $nonce );
if ( null === $model ) {
	\Arn\Intranet\People\PeopleController::refuse();
}
\Arn\Intranet\People\PeopleController::privateHeaders();
if ( ! empty( $model['saved'] ) ) {
	header( 'Location: /meu-perfil/?saved=1', true, 303 );
} else {
	echo 'ok';
}
