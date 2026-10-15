<?php
/** Actual HTTP headers and empty denial before a later login redirect.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
/** Runs a disposable loopback PHP server without WordPress or database writes. */
final class NewsPublishedHttpTest extends TestCase {
	public function testDeniedDetailHasEmptyPrivate404BeforeLoginForAllInvalidPathsAndMethods(): void {
		$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
		self::assertIsResource( $socket );
		$address = stream_socket_get_name( $socket, false );
		fclose( $socket );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Disposable loopback test server, always terminated in finally.
		$process = proc_open(
			array( PHP_BINARY, '-S', $address, __DIR__ . '/fixtures/news-http-router.php' ),
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
			foreach ( array( array( 'GET', '/noticias/?arn_news=7' ), array( 'GET', '/noticias/?arn_news=0' ), array( 'GET', '/noticias/?arn_news[]=7' ), array( 'GET', '/noticias/?arn_news=7&arn_news_preview=7' ), array( 'GET', '/noticias/?arn_news=7&arn_to=2026-02-30' ), array( 'GET', '/?arn_news=7' ), array( 'POST', '/noticias/?arn_news=7' ), array( 'HEAD', '/noticias/?arn_news=7' ) ) as [$method, $path] ) {
				$context = stream_context_create(
					array(
						'http' => array(
							'method'          => $method,
							'ignore_errors'   => true,
							'follow_location' => 0,
							'timeout'         => 3,
						),
					)
				);
				$body    = file_get_contents( 'http://' . $address . $path, false, $context );
				$headers = implode( "\n", $http_response_header );
				self::assertSame( '', $body, $method . ' ' . $path );
				self::assertStringContainsString( '404 Not Found', $headers );
				self::assertStringContainsString( 'Cache-Control: no-store, private', $headers );
				self::assertStringContainsString( 'Content-Length: 0', $headers );
				self::assertStringContainsString( 'X-Content-Type-Options: nosniff', $headers );
				self::assertStringNotContainsString( 'Location:', $headers );
			}
			foreach ( array( '/', '/noticias/' ) as $path ) {
				$context = stream_context_create(
					array(
						'http' => array(
							'ignore_errors'   => true,
							'follow_location' => 0,
							'timeout'         => 3,
						),
					)
				);
				file_get_contents( 'http://' . $address . $path, false, $context );
				$headers = implode( "\n", $http_response_header );
				self::assertStringContainsString( 'Cache-Control: no-store, private', $headers );
				self::assertStringContainsString( 'X-Content-Type-Options: nosniff', $headers );
			}
		} finally {
			proc_terminate( $process );
			foreach ( $pipes as $pipe ) {
				fclose( $pipe );
			}
			proc_close( $process );
		}
	}
}
