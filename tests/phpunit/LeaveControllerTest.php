<?php
/** Leave transport denies invalid requests before contacting its source.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Leave\Controller;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting
final class LeaveControllerTest extends TestCase {

	public function testMalformedTransportCannotReachSource(): void {
		$source = new class() {
			public int $calls = 0;
			public function access( string $view ): array {
				++$this->calls;
				return array(
					'actor'     => 4,
					'configure' => true,
				);
			}
		};
		foreach ( array(
			array( 'DELETE', 'own', array(), array(), array() ),
			array( 'GET', 'own', array( 'owner' => '5' ), array(), array() ),
			array( 'GET', 'own', array(), array( 'operation' => 'create' ), array() ),
			array( 'GET', 'own', array(), array(), array( 'upload' => array() ) ),
			array( 'GET', 'team', array( 'new' => '1' ), array(), array() ),
			array( 'GET', 'own', array( 'holidays' => '1' ), array(), array() ),
		) as $case ) {
			self::assertNull( Controller::decide( $case[0], $case[1], $case[2], $case[3], $source, 4, static fn(): bool => true, $case[4] ) );
		}
		self::assertSame( 0, $source->calls );
	}

	public function testNonceBindsActorViewOperationAndRequestKeyBeforeSource(): void {
		$key  = str_repeat( 'a', 32 );
		$post = array(
			'operation' => 'read',
			'id'        => '1',
			'key'       => $key,
			'nonce'     => 'invalid',
		);
		$seen = array();
		self::assertNull(
			Controller::decide(
				'POST',
				'own',
				array( 'notices' => '1' ),
				$post,
				new stdClass(),
				4,
				static function ( string $nonce, string $action ) use ( &$seen ): bool {
					$seen = array( $nonce, $action );
					return false;
				}
			)
		);
		self::assertSame( array( 'invalid', 'arn-leave:4:own:read:' . $key ), $seen );
		self::assertNull( Controller::decide( 'POST', 'own', array( 'new' => '1' ), $post, new stdClass(), 4, static fn(): bool => true ) );
	}

	public function testHeadReadsAuthorizedModelWithoutMutation(): void {
		$source = new class() {
			public int $mutations = 0;
			public function access( string $view ): array {
				return array(
					'actor'     => 4,
					'configure' => false,
				);
			}
			public function listing( string $view, int $year, int $page ): array {
				return array( 'items' => array() );
			}
			public function mutate( array $input ): int {
				++$this->mutations;
				return 1;
			}
		};
		self::assertIsArray( Controller::decide( 'HEAD', 'own', array(), array(), $source, 4, static fn(): bool => false ) );
		self::assertNull( Controller::decide( 'HEAD', 'own', array(), array(), $source, 5, static fn(): bool => true ) );
		self::assertSame( 0, $source->mutations );
	}
}
