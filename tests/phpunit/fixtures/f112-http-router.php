<?php
/**
 * Disposable loopback transport fixture, without WordPress or SQL.
 *
 * @package ArnIntranet
 */

// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput, WordPress.Security.EscapeOutput.OutputNotEscaped, Squiz.Commenting, WordPress.WP.AlternativeFunctions -- Local test-only router.
require dirname( __DIR__, 3 ) . '/vendor/autoload.php';
use Arn\Intranet\F112\Controller;
function status_header( int $status ): void {
	http_response_code( $status );
}
$request_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( ! in_array( $request_path, array( '/comunicados/', '/calendario/' ), true ) ) {
	Controller::refuse();
}
$module = '/calendario/' === $request_path ? 'calendar' : 'communications';
$source = new class( 'yes' === ( $_SERVER['HTTP_X_F112_FIXTURE'] ?? '' ) ) {
	public int $writes = 0;
	public function __construct( private readonly bool $allowed ) {}
	public function readable(): bool {
		return $this->allowed; }
	public function listing( object $selection ): array {
		return array(
			'items' => array(),
			'total' => 0,
			'page'  => $selection->page,
			'pages' => 0,
		); }
	public function detail( int $id ): ?array {
		return 7 === $id ? array(
			'id'    => 7,
			'title' => 'Publicado fictício',
		) : null; }
	public function notices( int $page ): array {
		return array(
			'items' => array(),
			'page'  => $page,
			'total' => 0,
			'pages' => 0,
		); }
	public function markRead( int $id ): void {
		if ( 1 !== $id ) {
			throw new DomainException( 'Recusado.' );
		} ++$this->writes; }
};
$result = Controller::decide( $_SERVER['REQUEST_METHOD'], $module, $_GET, $_POST, $source, 7, static fn( string $nonce, int $actor, string $name, string $operation, int $id ): bool => 'valid-7-' . $name . '-' . $operation . '-' . $id === $nonce );
if ( null === $result ) {
	Controller::refuse();
}
Controller::privateHeaders();
header( 'X-F112-Writes: ' . $source->writes );
if ( ! empty( $result['saved'] ) ) {
	header( 'Location: /comunicados/?notices=1', true, 303 );
	exit;
}
if ( 'HEAD' === $_SERVER['REQUEST_METHOD'] ) {
	header( 'Content-Length: 0' );
	exit;
}
header( 'Content-Type: application/json' );
echo json_encode( $result, JSON_THROW_ON_ERROR );
