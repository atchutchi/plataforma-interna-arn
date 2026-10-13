<?php
/**
 * Cenários fictícios verificáveis sem persistência.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\AuthorizationFixtures;
use PHPUnit\Framework\TestCase;
/** Verifica o contrato estável e as decisões dos cenários. */
final class AuthorizationFixturesTest extends TestCase {
	/** Todos os cenários previstos coincidem em relógios diferentes. */
	public function testScenariosAreDeterministicAndMatchExpectedDecisions(): void {
		$expected = array(
			'own-profile'         => true,
			'colleague-profile'   => false,
			'unit-task'           => true,
			'external-unit'       => false,
			'hr-limited'          => true,
			'hr-outside'          => false,
			'delegation'          => true,
			'delegation-expanded' => false,
			'self-promotion'      => false,
			'expired'             => false,
			'revoked'             => false,
			'suspended'           => false,
			'module-unavailable'  => false,
			'own-news'            => false,
			'changed-version'     => false,
		);
		foreach ( array( 0, 1, 1700000000 ) as $now ) {
			$results = AuthorizationFixtures::results( $now );
			self::assertSame( $results, AuthorizationFixtures::results( $now ) );
			self::assertCount( count( $expected ), $results );
			self::assertSame( array_keys( $expected ), array_column( $results, 'id' ) );
			foreach ( $results as $result ) {
				self::assertSame( 'expired' === $result['id'] && 0 === $now ? true : $expected[ $result['id'] ], $result['expected'] );
				self::assertSame( $result['expected'], $result['actual'], $result['id'] );
				self::assertNotEmpty( $result['text'] );
			}
		}
	}
}
