<?php
/** Fictional register validation, proof and transport boundary.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\Organization\Register\{Catalog, Validator, Confirmation, Authority, Page, Repository};
use Arn\Intranet\Organization\Storage\{LocalUnitSnapshot, LocalUnitScenarios};
use Arn\Intranet\Authorization\{AccountState, Grant, Role, Scope};
use Arn\Intranet\Authorization\Storage\{AuthorizationSnapshot, StoredGrant};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions.json_encode_json_encode
final class RegisterTest extends TestCase {
	private const CSV = "professional_id,name,email,unit_id\nLAB-PER-001,Pessoa fictícia Alfa,alfa@example.invalid,LAB-A\n";
	private function units( int $revision = 1 ): LocalUnitSnapshot {
		return new LocalUnitSnapshot( $revision, LocalUnitScenarios::apply( 'initialize', array() ) );
	}
	public function testPreviewUsesCurrentCatalogueAndRevisions(): void {
		$empty = new Catalog( 0, array() );
		$r     = Validator::validate( self::CSV, $this->units(), $empty );
		self::assertTrue( $r->valid );
		self::assertSame( 1, $r->totals['create'] );
		$p    = array(
			'LAB-PER-001' => array(
				'name'    => 'Pessoa fictícia Alfa',
				'email'   => 'alfa@example.invalid',
				'unit_id' => 'LAB-A',
			),
		);
		$same = Validator::validate( self::CSV, $this->units(), new Catalog( 1, $p ) );
		self::assertSame( 1, $same->totals['unchanged'] );
		self::assertNotSame( $r->batch_id, $same->batch_id );
		self::assertNotSame( $r->batch_id, Validator::validate( self::CSV, $this->units( 2 ), $empty )->batch_id );
		$update = Validator::validate( str_replace( 'Alfa', 'Alfa revista', self::CSV ), $this->units(), new Catalog( 1, $p ) );
		self::assertSame( 1, $update->totals['update'] );
		self::assertSame( array( 'name' ), $update->rows[0]['changed_fields'] );
		self::assertSame( $p, Catalog::decode( 1, ( new Catalog( 1, $p ) )->encode() )->people );
	}
	#[DataProvider( 'invalidCsv' )]
	public function testWholeBatchRefusesInvalidValues( string $csv ): void {
		self::assertFalse( Validator::validate( $csv, $this->units(), new Catalog( 0, array() ) )->valid );
	}
	public static function invalidCsv(): array {
		return array(
			array( self::CSV . "LAB-PER-001,Pessoa fictícia Outro,outro@example.invalid,LAB-A\n" ),
			array( self::CSV . "LAB-PER-002,Pessoa fictícia Outro,alfa@example.invalid,LAB-A\n" ),
			array( str_replace( 'example.invalid', 'arn.gw', self::CSV ) ),
			array( str_replace( 'Pessoa fictícia Alfa', 'Pessoa Real', self::CSV ) ),
			array( str_replace( 'Alfa', '<script>', self::CSV ) ),
			array( str_replace( 'LAB-A', 'UNKNOWN', self::CSV ) ),
			array( str_replace( 'professional_id,name,email,unit_id', 'professional_id,name,email,unit_id,role', self::CSV ) ),
			array( str_repeat( 'x', 16385 ) ),
			array( "professional_id,name,email,unit_id\n" ),
		);
	}
	public function testStoredConflictAndInactiveUnitAreRefused(): void {
		$p = new Catalog(
			1,
			array(
				'LAB-PER-002' => array(
					'name'    => 'Pessoa fictícia Beta',
					'email'   => 'alfa@example.invalid',
					'unit_id' => 'LAB-A',
				),
			)
		);
		self::assertFalse( Validator::validate( self::CSV, $this->units(), $p )->valid );
		$units = new LocalUnitSnapshot( 2, LocalUnitScenarios::apply( 'deactivate-b', $this->units()->units ) );
		self::assertFalse( Validator::validate( str_replace( 'LAB-A', 'LAB-B', self::CSV ), $units, new Catalog( 0, array() ) )->valid );
	}
	public function testProofBindsActorSessionBatchAndExpiry(): void {
		$c     = new Confirmation( str_repeat( 's', 32 ) );
		$batch = hash( 'sha256', 'batch' );
		$token = str_repeat( 't', 32 );
		$proof = $c->issue( $batch, 7, $token, 1000 );
		self::assertSame( $batch, $c->validate( $proof, 7, $token, 1600 ) );
		foreach ( array( array( $proof, 8, $token, 1100 ), array( $proof, 7, str_repeat( 'z', 32 ), 1100 ), array( $proof, 7, $token, 1601 ), array( $proof, 7, $token, 999 ), array( str_replace( $batch, hash( 'sha256', 'other' ), $proof ), 7, $token, 1100 ) ) as $args ) {
			try {
				$c->validate( ...$args );
				self::fail( 'Proof accepted' );
			} catch ( RuntimeException ) {
				self::assertTrue( true ); }
		}
	}
	public function testOnlyActiveInstitutionalHrWithCurrentSessionCanImport(): void {
		$session = array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => 7,
			'arn_authz_revision' => 3,
		);
		$make    = static fn( Role $role, Scope $scope, AccountState $state = AccountState::Active, ?int $end = null ): AuthorizationSnapshot => new AuthorizationSnapshot( 7, $state, 3, array( new StoredGrant( 1, new Grant( 7, $role, $scope, 0, $end ) ) ) );
		self::assertTrue( Authority::permits( $make( Role::HumanResources, Scope::institutional() ), $session, 1000 ) );
		self::assertFalse( Authority::permits( $make( Role::HumanResources, Scope::units( array( 'LAB-A', 'LAB-B', 'LAB-ROOT' ) ) ), $session, 1000 ) );
		foreach ( array( Role::TechnicalAdministrator, Role::Employee, Role::AccessAdministrator ) as $role ) {
			self::assertFalse( Authority::permits( $make( $role, Scope::institutional() ), $session, 1000 ) ); }
		self::assertFalse( Authority::permits( $make( Role::HumanResources, Scope::institutional(), AccountState::Suspended ), $session, 1000 ) );
		self::assertFalse( Authority::permits( $make( Role::HumanResources, Scope::institutional(), AccountState::Active, 1000 ), $session, 1000 ) );
		self::assertFalse( Authority::permits( $make( Role::HumanResources, Scope::institutional() ), array_replace( $session, array( 'arn_authz_revision' => 2 ) ), 1000 ) );
	}
	public function testHttpBoundaryHasNoForgedActionOrProposalPath(): void {
		$get   = array( 'page' => 'arn-register' );
		$nonce = static fn( string $s ): bool => 'good' === $s;
		$post  = array(
			'action' => 'preview',
			'csv'    => self::CSV,
			'nonce'  => 'good',
		);
		self::assertNull( Page::request( 'GET', $get, array(), array(), $nonce ) );
		self::assertSame( $post, Page::request( 'POST', $get, $post, array(), $nonce ) );
		foreach ( array( array( 'HEAD', $get, array(), array() ), array( 'PUT', $get, $post, array() ), array( 'POST', $get, $post + array( 'actor' => 7 ), array() ), array( 'POST', $get, array_replace( $post, array( 'nonce' => 'bad' ) ), array() ), array( 'POST', $get, $post, array( 'file' => array() ) ), array( 'POST', $get, array_replace( $post, array( 'csv' => array() ) ), array() ), array( 'GET', $get + array( 'other' => '1' ), array(), array() ) ) as $args ) {
			try {
				Page::request( ...array_merge( $args, array( $nonce ) ) );
				self::fail( 'Request accepted' );
			} catch ( RuntimeException ) {
				self::assertTrue( true ); }
		}
	}
	public function testStoredRecordsRejectAdditionalIdentityFields(): void {
		$this->expectException( RuntimeException::class );
		new Catalog(
			1,
			array(
				'LAB-PER-001' => array(
					'name'    => 'Pessoa fictícia Alfa',
					'email'   => 'alfa@example.invalid',
					'unit_id' => 'LAB-A',
					'wp_user' => 1,
				),
			)
		);
	}
	public function testLiteralEntitiesAndQuotesSurviveHtmlParsing(): void {
		$value   = "Pessoa fictícia A &amp; B &#65; \"C\" 'D'";
		$encoded = Page::escapeLiteral( $value );
		self::assertSame( $value, html_entity_decode( $encoded, ENT_QUOTES, 'UTF-8' ) );
		self::assertStringNotContainsString( '"', $encoded );
		self::assertStringContainsString( '&amp;amp;', $encoded );
	}
}
