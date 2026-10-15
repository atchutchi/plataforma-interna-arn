<?php
/** Pure People value and revision tests.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\People\ProfessionalDetails;
use Arn\Intranet\People\Profile;
use Arn\Intranet\People\SelfService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Contacts remain separate from fixed professional data. */
final class PeopleModelTest extends TestCase {
	/** Unicode limits count code points, with no control or markup accepted. */
	public function test_professional_fields_validate_exact_bounds_and_utf8(): void {
		$valid = new ProfessionalDetails( str_repeat( 'A', 64 ), str_repeat( 'Á', 120 ), 'x@example.invalid', str_repeat( 'É', 100 ), 'LAB-A' );
		self::assertSame( 120, preg_match_all( '/./us', $valid->name ) );
		foreach ( array(
			array( '', 'Nome', 'x@example.invalid', 'Cargo', 'LAB-A' ),
			array( str_repeat( 'A', 65 ), 'Nome', 'x@example.invalid', 'Cargo', 'LAB-A' ),
			array( 'A', '', 'x@example.invalid', 'Cargo', 'LAB-A' ),
			array( 'A', str_repeat( 'Á', 121 ), 'x@example.invalid', 'Cargo', 'LAB-A' ),
			array( 'A', "Nome\nOutro", 'x@example.invalid', 'Cargo', 'LAB-A' ),
			array( 'A', ' Nome', 'x@example.invalid', 'Cargo', 'LAB-A' ),
			array( 'A', "\xFF", 'x@example.invalid', 'Cargo', 'LAB-A' ),
			array( 'A', 'Nome', ' x@example.invalid', 'Cargo', 'LAB-A' ),
			array( 'A', 'Nome', 'inválido', 'Cargo', 'LAB-A' ),
			array( 'A', 'Nome', 'x@example.invalid', str_repeat( 'É', 101 ), 'LAB-A' ),
			array( 'A', 'Nome', 'x@example.invalid', 'Cargo', 'LAB A' ),
		) as $fields ) {
			try {
				new ProfessionalDetails( ...$fields );
				self::fail( 'Dados profissionais inválidos aceites.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	/** Optional contacts allow exact Unicode limits and LF only in biography. */
	public function test_self_service_validates_contacts_and_controls(): void {
		$valid = new SelfService( str_repeat( '1', 32 ), str_repeat( 'Á', 100 ), str_repeat( 'É', 499 ) . "\n" );
		self::assertSame( 500, preg_match_all( '/./us', $valid->bio ) );
		self::assertSame( '', ( new SelfService( '', '', '' ) )->phone );
		foreach ( array(
			array( str_repeat( '1', 33 ), '', '' ),
			array( '+++', '', '' ),
			array( '１２３', '', '' ),
			array( '1 ext', '', '' ),
			array( '1', str_repeat( 'Á', 101 ), '' ),
			array( '1', "Sala\n2", '' ),
			array( '1', '', str_repeat( 'É', 501 ) ),
			array( '1', '', "Linha\r\nOutra" ),
			array( '1', '', "\x00" ),
			array( '1', "\xFF", '' ),
			array( '1', '', '<script>' ),
		) as $fields ) {
			try {
				new SelfService( ...$fields );
				self::fail( 'Contacto inválido aceite.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	/** CAS runs before no-op, and a real edit cannot replace professional data. */
	public function test_revision_requires_owner_cas_and_monotonic_time(): void {
		$professional = new ProfessionalDetails( 'LAB-PEO-A', 'Alfa Teste', 'alfa@example.invalid', 'Técnico de teste', 'LAB-A' );
		$initial      = new SelfService( '123', 'Sala A', 'Texto' );
		$profile      = new Profile( 7, $professional, $initial, 1, 10 );
		self::assertSame( $profile, $profile->revise( $initial, 1, 7, 10 ) );
		self::assertSame( array( 'phone', 'bio' ), $profile->changedFields( new SelfService( '456', 'Sala A', 'Novo' ) ) );
		$revised = $profile->revise( new SelfService( '456', 'Sala A', 'Novo' ), 1, 7, 11 );
		self::assertSame( 2, $revised->revision );
		self::assertSame( 11, $revised->updated_at );
		self::assertSame( $professional, $revised->professional );
		self::assertSame( 1, $profile->revision );
		self::assertNotSame( $profile->digest(), $revised->digest() );
		self::assertSame( $revised->digest(), ( new Profile( 7, $professional, $revised->self_service, 2, 11 ) )->digest() );
		foreach ( array( array( 0, 7, 12 ), array( 2, 7, 12 ), array( 1, 8, 12 ), array( 1, 7, 9 ) ) as $args ) {
			try {
				$profile->revise( $initial, ...$args );
				self::fail( 'Edição inválida aceite.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
		foreach ( array( array( 0, 1, 0 ), array( 7, 0, 0 ), array( 7, 1, -1 ) ) as $args ) {
			try {
				new Profile( $args[0], $professional, $initial, $args[1], $args[2] );
				self::fail( 'Perfil inválido aceite.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}
}
