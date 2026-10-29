<?php
/** Delegation state, validity bounds and strict HTTP decisions.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\Authorization\Delegations\{Command, State, Page};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions.json_encode_json_encode
final class PersistentDelegationTest extends TestCase {
	private function command( string $action, int $revision, int $expires = 2000 ): Command {
		return new Command( $action, 'issue' === $action ? '' : 'LAB-DEL-000001', 2, 3, 'Employee', 'own', 1000, $expires, $revision, 'grant' === $action ? 2 : 0, 'Decisão fictícia explícita', str_repeat( 'a', 64 ) );
	}
	public function testRevocationPreservesIssuedGrantsAndTheirIndependentValidity(): void {
		$issued   = ( new State( 0, array() ) )->apply( $this->command( 'issue', 0 ), 1, 0 );
		$grant    = $this->command( 'grant', 1, 5000 );
		$assigned = $issued->apply( $grant, 2, 9 );
		$revoked  = $assigned->apply( $this->command( 'revoke', 2 ), 1, 0 );
		self::assertTrue( $revoked->records['LAB-DEL-000001']['revoked'] );
		self::assertSame( array( 9 ), $revoked->records['LAB-DEL-000001']['grants'] );
		self::assertTrue( $grant->grant()->validAt( 3000 ) );
		self::assertFalse( State::delegation( $revoked->records['LAB-DEL-000001'] )->validAt( 1500 ) );
		self::assertSame( $revoked->records, State::decode( 3, $revoked->encode() )->records );
	}
	public function testRevokedDelegationCannotRecordAnotherGrant(): void {
		$s = ( new State( 0, array() ) )->apply( $this->command( 'issue', 0 ), 1, 0 )->apply( $this->command( 'revoke', 1 ), 1, 0 );
		$this->expectException( RuntimeException::class );
		$s->apply( $this->command( 'grant', 2 ), 2, 10 );
	}
	public function testDesignatedIssuerCannotActAsTheDelegate(): void {
		$s = ( new State( 0, array() ) )->apply( $this->command( 'issue', 0 ), 1, 0 );
		$this->expectException( RuntimeException::class );
		$s->apply( $this->command( 'grant', 1 ), 1, 10 );
	}
	public function testDelegateCannotRevokeIssuerDecision(): void {
		$s = ( new State( 0, array() ) )->apply( $this->command( 'issue', 0 ), 1, 0 );
		$this->expectException( RuntimeException::class );
		$s->apply( $this->command( 'revoke', 1 ), 2, 0 );
	}
	public function testNoSelfDelegation(): void {
		$this->expectException( RuntimeException::class );
		( new State( 0, array() ) )->apply( $this->command( 'issue', 0 ), 2, 0 );
	}
	public function testOldRevisionCannotApplyAgain(): void {
		$s = ( new State( 0, array() ) )->apply( $this->command( 'issue', 0 ), 1, 0 );
		$this->expectException( RuntimeException::class );
		$s->apply( $this->command( 'issue', 0 ), 1, 0 );
	}
	public function testNoSilentScopeExpansion(): void {
		$s = ( new State( 0, array() ) )->apply( $this->command( 'issue', 0 ), 1, 0 );
		$c = new Command( 'grant', 'LAB-DEL-000001', 2, 3, 'UnitManager', 'LAB-B', 1000, 3000, 1, 2, 'Alargamento indevido', str_repeat( 'b', 64 ) );
		$this->expectException( RuntimeException::class );
		$s->apply( $c, 2, 10 );
	}
	public function testBoundsUseInclusiveStartAndExclusiveEnd(): void {
		$s = ( new State( 0, array() ) )->apply( $this->command( 'issue', 0 ), 1, 0 );
		$d = State::delegation( $s->records['LAB-DEL-000001'] );
		self::assertFalse( $d->validAt( 999 ) );
		self::assertTrue( $d->validAt( 1000 ) );
		self::assertTrue( $d->validAt( 1999 ) );
		self::assertFalse( $d->validAt( 2000 ) );
	}
	private static function post(): array {
		return array(
			'action'          => 'issue',
			'delegation'      => '',
			'delegate'        => '2',
			'target'          => '3',
			'role'            => 'Employee',
			'scope'           => 'own',
			'starts'          => '2026-10-29T12:00:00',
			'expires'         => '2026-10-29T13:00',
			'revision'        => '0',
			'target_revision' => '0',
			'reason'          => 'Motivo &amp; &#65;',
			'request_id'      => str_repeat( 'a', 64 ),
			'nonce'           => 'valid',
		);
	}
	public function testStrictPostPreservesLiteralReasonAndUtcSeconds(): void {
		$c = Page::request( 'POST', array( 'page' => 'arn-access-delegations' ), self::post(), array(), static fn( string $n ): bool => 'valid' === $n );
		self::assertSame( 'Motivo &amp; &#65;', $c->reason );
		self::assertSame( 3600, $c->expires - $c->starts );
		self::assertSame( 1, Page::utc( '2026-10-29T12:00:01' ) - Page::utc( '2026-10-29T12:00' ) );
		self::assertSame( $c->values(), Command::from( $c->values() )->values() );
		self::assertNull( Page::request( 'GET', array( 'page' => 'arn-access-delegations' ), array(), array(), static fn(): bool => false ) );
	}
	public static function invalidPosts(): array {
		$out = array();
		foreach ( array(
			'nonce'      => '',
			'revision'   => '00',
			'role'       => 'AccessAdministrator',
			'target'     => '2',
			'starts'     => '2026-02-30T12:00',
			'expires'    => '2026-10-29T11:00',
			'scope'      => 'LAB-A',
			'request_id' => 'x',
			'reason'     => "A\nB",
		) as $key => $value ) {
			$p           = self::post();
			$p[ $key ]   = $value;
			$out[ $key ] = array( $p );
		}
		$p            = self::post();
		$p['extra']   = '1';
		$out['extra'] = array( $p );
		return $out;
	}
	#[DataProvider( 'invalidPosts' )]
	public function testInvalidPostsAreRejected( array $post ): void {
		$this->expectException( \Exception::class );
		Page::request( 'POST', array( 'page' => 'arn-access-delegations' ), $post, array(), static fn( string $n ): bool => 'valid' === $n );
	}
}
