<?php
/** People controller decisions.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Test doubles are deliberately compact.
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors -- Disposable loopback HTTP harness without WordPress filesystem or HTTP APIs.
declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\People\PeopleController;
use Arn\Intranet\People\PeopleQuery;
use Arn\Intranet\People\SelfService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PeopleHttpTest extends TestCase {
	public function test_loopback_private_denials_and_protected_post(): void {
		$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
		self::assertIsResource( $socket );
		$address = stream_socket_get_name( $socket, false );
		fclose( $socket );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Disposable loopback server, terminated below.
		$process = proc_open(
			array( PHP_BINARY, '-S', $address, __DIR__ . '/fixtures/people-http-router.php' ),
			array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes
		);
		self::assertIsResource( $process );
		try {
			$ready = false;
			for ( $attempt = 0; $attempt < 100; ++$attempt ) {
				$probe = @stream_socket_client( 'tcp://' . $address, $code, $message, 0.1 );
				if ( is_resource( $probe ) ) {
					fclose( $probe );
					$ready = true;
					break;
				}
				usleep( 10000 );
			}
			self::assertTrue( $ready );
			$valid = http_build_query(
				array(
					'operation'         => 'update_own',
					'nonce'             => 'valid',
					'expected_revision' => '1',
					'phone'             => '+245 111',
					'office'            => '',
					'bio'               => '',
				)
			);
			foreach ( array(
				array( 'GET', '/off/meu-perfil/', '' ),
				array( 'GET', '/funcionarios/?person=01', '' ),
				array( 'GET', '/funcionarios/?person=9', '' ),
				array( 'GET', '/funcionarios/?actor_test=1', '' ),
				array( 'GET', '/minha-equipa/', '' ),
				array( 'HEAD', '/meu-perfil/', '' ),
				array( 'POST', '/meu-perfil/?actor_test=1', $valid ),
				array( 'POST', '/meu-perfil/', str_replace( 'nonce=valid', 'nonce=bad', $valid ) ),
				array( 'POST', '/meu-perfil/', $valid . '&role=admin' ),
			) as [$method, $route, $body] ) {
				$context  = stream_context_create(
					array(
						'http' => array(
							'method'          => $method,
							'content'         => $body,
							'header'          => 'Content-Type: application/x-www-form-urlencoded',
							'ignore_errors'   => true,
							'follow_location' => 0,
							'timeout'         => 3,
						),
					)
				);
				$response = file_get_contents( 'http://' . $address . $route, false, $context );
				$headers  = implode( "\n", $http_response_header );
				self::assertSame( '', $response, $method . ' ' . $route );
				self::assertStringContainsString( '404 Not Found', $headers );
				self::assertStringContainsString( 'private, no-store', $headers );
				self::assertStringNotContainsString( 'Location:', $headers );
			}
			$context = stream_context_create(
				array(
					'http' => array(
						'method'          => 'POST',
						'content'         => $valid,
						'header'          => 'Content-Type: application/x-www-form-urlencoded',
						'ignore_errors'   => true,
						'follow_location' => 0,
						'timeout'         => 3,
					),
				)
			);
			file_get_contents( 'http://' . $address . '/meu-perfil/', false, $context );
			self::assertStringContainsString( '303 See Other', implode( "\n", $http_response_header ) );
		} finally {
			proc_terminate( $process );
			foreach ( $pipes as $pipe ) {
				fclose( $pipe ); }
			proc_close( $process );
		}
	}
	public function test_invalid_route_and_filters_do_not_reach_source(): void {
		$source = new class() {
			public int $calls = 0;
			public function contactList( PeopleQuery $query ): ?array {
				++$this->calls;
				return array(); }
		};
		$nonce  = static fn(): bool => true;
		self::assertNull( PeopleController::decide( 'PUT', 'directory', array(), array(), $source, 5, $nonce ) );
		self::assertNull( PeopleController::decide( 'GET', 'directory', array( 'unit' => 'OTHER' ), array(), $source, 5, $nonce ) );
		self::assertNull( PeopleController::decide( 'GET', 'directory', array( 'person' => '01' ), array(), $source, 5, $nonce ) );
		self::assertNull( PeopleController::decide( 'GET', 'directory', array( 'person' => null ), array(), $source, 5, $nonce ) );
		self::assertNull( PeopleController::decide( 'GET', 'team', array( 'person' => '1' ), array(), $source, 5, $nonce ) );
		self::assertSame( 0, $source->calls );
	}

	public function test_post_nonce_and_forbidden_fields_precede_source_and_cas_is_recoverable(): void {
		$source = new class() {
			public int $reads  = 0;
			public int $writes = 0;
			public function own(): array {
				++$this->reads;
				return array(
					'phone'    => '',
					'office'   => '',
					'bio'      => '',
					'revision' => 2,
				); }
			public function ownHistory(): array {
				return array(); }
			public function updateOwn( SelfService $service, int $revision ): void {
				++$this->writes;
				throw new RuntimeException( 'CAS' ); }
		};
		$post   = array(
			'operation'         => 'update_own',
			'nonce'             => 'token',
			'expected_revision' => '1',
			'phone'             => '+245 111',
			'office'            => 'A',
			'bio'               => 'Texto',
		);
		self::assertNull( PeopleController::decide( 'POST', 'profile', array(), $post + array( 'actor' => '8' ), $source, 5, static fn(): bool => true ) );
		self::assertNull( PeopleController::decide( 'POST', 'profile', array(), $post, $source, 5, static fn(): bool => false ) );
		self::assertSame( 0, $source->reads );
		$model = PeopleController::decide( 'POST', 'profile', array(), $post, $source, 5, static fn(): bool => true );
		self::assertSame( 1, $source->writes );
		self::assertSame( '+245 111', $model['fields']['phone'] );
		self::assertSame( 2, $model['own']['revision'] );
	}
}
