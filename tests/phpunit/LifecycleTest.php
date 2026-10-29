<?php
/** Professional lifecycle domain and HTTP boundary.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\Organization\Lifecycle\{Command, State, Page};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions.json_encode_json_encode
final class LifecycleTest extends TestCase {
	private function command( string $action = 'state', string $state = 'Suspended', int $account = 0, int $revision = 0, string $person = 'LAB-PER-001' ): Command {
		return new Command( $action, $person, $state, $account, $revision, 1, $account > 0 ? 2 : 0, 'Decisão fictícia explícita', str_repeat( 'a', 64 ) );
	}
	public function testAbsenceDoesNotInferActiveStateOrAccount(): void {
		self::assertSame(
			array(
				'state'   => 'Undefined',
				'account' => 0,
			),
			( new State( 0, array() ) )->person( 'LAB-PER-001' )
		);
	}
	public function testStateAndBindingRemainSeparateAndSurviveReactivation(): void {
		$zero  = new State( 0, array() );
		$one   = $zero->apply( $this->command() );
		$two   = $one->apply( $this->command( 'link', '', 44, 1 ) );
		$three = $two->apply( $this->command( 'state', 'Active', 44, 2 ) );
		self::assertSame(
			array(
				'state'   => 'Active',
				'account' => 44,
			),
			$three->person( 'LAB-PER-001' )
		);
		self::assertSame(
			array(
				'state'   => 'Suspended',
				'account' => 44,
			),
			$two->person( 'LAB-PER-001' )
		);
		self::assertSame( $three->people, State::decode( 3, $three->encode() )->people );
	}
	public function testNoRebinding(): void {
		$s = ( new State( 0, array() ) )->apply( $this->command( 'link', '', 44 ) );
		$this->expectException( RuntimeException::class );
		$s->apply( $this->command( 'link', '', 45, 1 ) );
	}
	public function testOneAccountCannotIdentifyTwoPeople(): void {
		$s = ( new State( 0, array() ) )->apply( $this->command( 'link', '', 44 ) );
		$this->expectException( RuntimeException::class );
		$s->apply( $this->command( 'link', '', 44, 1, 'LAB-PER-002' ) );
	}
	public function testStaleDecisionIsDenied(): void {
		$this->expectException( RuntimeException::class );
		( new State( 1, array() ) )->apply( $this->command() );
	}
	public function testStateCannotSilentlyChangeLink(): void {
		$this->expectException( RuntimeException::class );
		( new State( 0, array() ) )->apply( $this->command( 'state', 'Active', 44 ) );
	}
	public function testNonCanonicalStateIsDenied(): void {
		$this->expectException( RuntimeException::class );
		State::decode( 0, '{}' );
	}
	private static function post(): array {
		return array(
			'action'           => 'state',
			'person'           => 'LAB-PER-001',
			'state'            => 'Suspended',
			'account'          => '0',
			'account_revision' => '0',
			'catalog_revision' => '1',
			'revision'         => '0',
			'reason'           => 'Ensaio fictício',
			'request_id'       => str_repeat( 'a', 64 ),
			'nonce'            => 'valid',
		);
	}
	public function testStrictPostAndReadOnlyGet(): void {
		self::assertNull( Page::request( 'GET', array( 'page' => 'arn-register-lifecycle' ), array(), array(), static fn(): bool => false ) );
		$c = Page::request( 'POST', array( 'page' => 'arn-register-lifecycle' ), self::post(), array(), static fn( string $n ): bool => 'valid' === $n );
		self::assertSame( 'Suspended', $c->state );
		self::assertSame( 0, $c->account );
	}
	public static function invalidPosts(): array {
		$cases = array();
		foreach ( array(
			'nonce'            => '',
			'revision'         => '01',
			'account'          => '-1',
			'catalog_revision' => '0',
			'state'            => 'Undefined',
			'action'           => 'activate_account',
			'person'           => 'real-001',
			'request_id'       => 'x',
			'reason'           => "A\nB",
		) as $key => $value ) {
			$p             = self::post();
			$p[ $key ]     = $value;
			$cases[ $key ] = array( $p );
		}
		$p              = self::post();
		$p['extra']     = '1';
		$cases['extra'] = array( $p );
		$p              = self::post();
		$p['reason']    = array();
		$cases['array'] = array( $p );
		return $cases;
	}
	#[DataProvider( 'invalidPosts' )]
	public function testRejectsMalformedPosts( array $post ): void {
		$this->expectException( \Exception::class );
		Page::request( 'POST', array( 'page' => 'arn-register-lifecycle' ), $post, array(), static fn( string $n ): bool => 'valid' === $n );
	}
	public function testRequestIdDoesNotHideChangedDecision(): void {
		self::assertNotSame( $this->command()->hash(), $this->command( 'state', 'Closed' )->hash() );
	}
}
