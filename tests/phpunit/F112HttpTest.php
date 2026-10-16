<?php
/**
 * Read/receipt controller decisions.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Controller contract tests.
declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\F112\Controller;
use Arn\Intranet\F112\ManagementPage;
use PHPUnit\Framework\TestCase;

final class F112HttpTest extends TestCase {
	public function test_cms_refuses_forbidden_fields_or_nonce_before_its_content_source(): void {
		$source = new class() {
			public int $writes = 0;
			public function manageable(): bool {
				return true; }
			public function creationOptions(): array {
				return array(); }
			public function mutate( array $input ): int {
				++$this->writes;
				return (int) $input['id']; }
		};
		$post   = array(
			'operation' => 'publish',
			'id'        => '7',
			'revision'  => '2',
			'reason'    => 'Teste',
			'nonce'     => 'valid',
		);
		self::assertNull( ManagementPage::decide( 'POST', 'communications', array( 'entry' => '8' ), $post, $source, 7, static fn(): bool => true ) );
		self::assertNull( ManagementPage::decide( 'POST', 'communications', array( 'entry' => '7' ), $post + array( 'author' => '8' ), $source, 7, static fn(): bool => true ) );
		self::assertNull( ManagementPage::decide( 'POST', 'communications', array( 'entry' => '7' ), $post, $source, 7, static fn(): bool => false ) );
		self::assertSame( 0, $source->writes );
		self::assertSame( array( 'saved_id' => 7 ), ManagementPage::decide( 'POST', 'communications', array( 'entry' => '7' ), $post, $source, 7, static fn(): bool => true ) );
		self::assertSame( 1, $source->writes );
	}

	// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors -- Disposable loopback process, stopped in finally.
	public function test_real_loopback_http_head_denials_and_receipt_prg(): void {
		$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
		self::assertIsResource( $socket );
		$address = stream_socket_get_name( $socket, false );
		fclose( $socket );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open
		$process = proc_open( array( PHP_BINARY, '-S', $address, __DIR__ . '/fixtures/f112-http-router.php' ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ), $pipes );
		self::assertIsResource( $process );
		try {
			$ready = false;
			for ( $attempt = 0; $attempt < 100; ++$attempt ) {
				$probe = @stream_socket_client( 'tcp://' . $address, $code, $message, 0.1 );
				if ( is_resource( $probe ) ) {
					fclose( $probe );
					$ready = true;
					break; }
				usleep( 10000 );
			}
			self::assertTrue( $ready );
			$valid = http_build_query(
				array(
					'operation' => 'mark-read',
					'id'        => '1',
					'nonce'     => 'valid-7-communications-mark-read-1',
				)
			);
			foreach ( array(
				array( 'GET', '/comunicados/', '', false, 404 ),
				array( 'GET', '/missing/comunicados/', '', true, 404 ),
				array( 'GET', '/comunicados/?entry=8', '', true, 404 ),
				array( 'GET', '/comunicados/?entry[]=7', '', true, 404 ),
				array( 'HEAD', '/comunicados/?actor=8', '', true, 404 ),
				array( 'DELETE', '/comunicados/', '', true, 404 ),
				array( 'POST', '/comunicados/?notices=1', $valid . '&actor=8', true, 404 ),
				array( 'POST', '/comunicados/?notices=1', str_replace( 'valid-7', 'valid-8', $valid ), true, 404 ),
				array( 'GET', '/comunicados/?entry=7', '', true, 200 ),
				array( 'HEAD', '/comunicados/?entry=7', '', true, 200 ),
				array( 'GET', '/calendario/?state=history', '', true, 200 ),
				array( 'POST', '/comunicados/?notices=1', $valid, true, 303 ),
			) as [$method, $path, $body, $authenticated, $status] ) {
				$context  = stream_context_create(
					array(
						'http' => array(
							'method'          => $method,
							'header'          => "Content-Type: application/x-www-form-urlencoded\r\nX-F112-Fixture: " . ( $authenticated ? 'yes' : 'no' ),
							'content'         => $body,
							'ignore_errors'   => true,
							'follow_location' => 0,
							'timeout'         => 3,
						),
					)
				);
				$response = file_get_contents( 'http://' . $address . $path, false, $context );
				$headers  = implode( "\n", $http_response_header );
				self::assertStringContainsString( (string) $status, $headers, $method . $path );
				self::assertStringContainsString( 'private, no-store', $headers );
				if ( 200 !== $status || 'HEAD' === $method ) {
					self::assertSame( '', $response ); }
				if ( 303 !== $status ) {
					self::assertStringNotContainsString( 'Location:', $headers ); }
				if ( 303 === $status ) {
					self::assertStringContainsString( 'X-F112-Writes: 1', $headers ); }
			}
		} finally {
			proc_terminate( $process );
			foreach ( $pipes as $pipe ) {
				fclose( $pipe ); }
			proc_close( $process );
		}
	}
	public function test_invalid_or_denied_requests_do_not_touch_the_content_source(): void {
		$source = new class() {
			public int $calls    = 0;
			public bool $allowed = false;
			public function readable(): bool {
				return $this->allowed; }
			public function listing( object $selection ): array {
				++$this->calls;
				return array(
					'items' => array(),
					'total' => 0,
					'page'  => 1,
					'pages' => 0,
				); }
		};
		self::assertNull( Controller::decide( 'GET', 'communications', array(), array(), $source, 1, static fn(): bool => true ) );
		self::assertSame( 0, $source->calls );
		$source->allowed = true;
		self::assertNull( Controller::decide( 'DELETE', 'communications', array(), array(), $source, 1, static fn(): bool => true ) );
		self::assertNull( Controller::decide( 'GET', 'communications', array( 'actor' => '2' ), array(), $source, 1, static fn(): bool => true ) );
		self::assertSame( 0, $source->calls );
		$get  = Controller::decide( 'GET', 'communications', array(), array(), $source, 1, static fn(): bool => true );
		$head = Controller::decide( 'HEAD', 'communications', array(), array(), $source, 1, static fn(): bool => true );
		self::assertEquals( $get, $head );
		self::assertSame( 2, $source->calls );
	}
}
