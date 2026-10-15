<?php
/** Pure People search and projection tests.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\People\PeopleProjection;
use Arn\Intranet\People\PeopleQuery;
use Arn\Intranet\People\PeopleSelection;
use Arn\Intranet\People\ProfessionalDetails;
use Arn\Intranet\People\Profile;
use Arn\Intranet\People\SelfService;
use PHPUnit\Framework\TestCase;

/** Malformed filters never reach the profile source. */
final class PeopleSelectionTest extends TestCase {
	/** Only canonical scalar filters are parsed, with the WP-safe page key. */
	public function test_query_rejects_bad_types_utf8_limits_and_page_forms(): void {
		$query = PeopleQuery::parse(
			array(
				'q'               => 'Ana [A]',
				'unit'            => 'LAB-A',
				'job'             => 'Técnico',
				'arn_people_page' => '2',
				'page'            => 'native',
			)
		);
		self::assertSame( 'Ana [A]', $query->q );
		self::assertSame( 'LAB-A', $query->unit );
		self::assertSame( 'Técnico', $query->job );
		self::assertSame( 2, $query->page );
		self::assertSame( 1, PeopleQuery::parse( array() )->page );
		foreach ( array(
			array( 'q' => array( 'Ana' ) ),
			array( 'q' => null ),
			array( 'q' => "\xFF" ),
			array( 'q' => str_repeat( 'Á', 121 ) ),
			array( 'q' => "A\nB" ),
			array( 'job' => array() ),
			array( 'job' => str_repeat( 'É', 101 ) ),
			array( 'unit' => 'LAB-C' ),
			array( 'unit' => null ),
			array( 'arn_people_page' => array( '1' ) ),
			array( 'arn_people_page' => '0' ),
			array( 'arn_people_page' => '01' ),
			array( 'arn_people_page' => '+1' ),
			array( 'arn_people_page' => '1.0' ),
			array( 'arn_people_page' => '99999999999999999999999999999' ),
		) as $input ) {
			self::assertNull( PeopleQuery::parse( $input ) );
		}
	}

	/** Eligibility is applied before totals, filters and pagination. */
	public function test_selection_filters_before_count_and_sorts_by_name_then_id(): void {
		$profiles = array();
		for ( $id = 15; $id >= 1; --$id ) {
			$profiles[] = $this->profile( $id, sprintf( 'Pessoa %02d', $id ) );
		}
		$profiles[] = $this->profile( 16, 'Pessoa 10' );
		$eligible   = static fn( Profile $profile ): bool => 3 !== $profile->user_id;
		$first      = PeopleSelection::select( $profiles, PeopleQuery::parse( array() ), $eligible );
		self::assertSame( 15, $first['total'] );
		self::assertSame( 2, $first['pages'] );
		self::assertSame( 1, $first['page'] );
		self::assertSame( array( 1, 2, 4, 5, 6, 7, 8, 9, 10, 16 ), array_map( static fn( Profile $profile ): int => $profile->user_id, $first['items'] ) );
		$second = PeopleSelection::select( $profiles, PeopleQuery::parse( array( 'arn_people_page' => '2' ) ), $eligible );
		self::assertSame( array( 11, 12, 13, 14, 15 ), array_map( static fn( Profile $profile ): int => $profile->user_id, $second['items'] ) );
		self::assertSame( 0, PeopleSelection::select( $profiles, PeopleQuery::parse( array( 'q' => '[10]' ) ), $eligible )['total'] );
		self::assertSame(
			2,
			PeopleSelection::select(
				$profiles,
				PeopleQuery::parse(
					array(
						'q'    => 'Pessoa 10',
						'unit' => 'LAB-A',
						'job'  => 'Técnico',
					)
				),
				$eligible
			)['total']
		);
		self::assertSame( array(), PeopleSelection::select( $profiles, PeopleQuery::parse( array( 'arn_people_page' => '3' ) ), $eligible )['items'] );
		$huge_page = PeopleQuery::parse( array( 'arn_people_page' => (string) PHP_INT_MAX ) );
		self::assertSame( array(), PeopleSelection::select( $profiles, $huge_page, $eligible )['items'] );
	}

	/** Common projection has a closed field list and own adds only CAS. */
	public function test_projection_does_not_expose_revision_or_internal_state(): void {
		$profile = $this->profile( 7, 'Alfa' );
		self::assertSame(
			array(
				'id'      => 7,
				'code'    => 'P7',
				'name'    => 'Alfa',
				'email'   => 'p7@example.invalid',
				'job'     => 'Técnico',
				'unit_id' => 'LAB-A',
				'unit'    => 'Unidade A',
				'phone'   => '123',
				'office'  => 'Sala A',
				'bio'     => 'Nota',
			),
			PeopleProjection::contact( $profile, 'Unidade A' )
		);
		self::assertSame( array_merge( PeopleProjection::contact( $profile, 'Unidade A' ), array( 'revision' => 1 ) ), PeopleProjection::own( $profile, 'Unidade A' ) );
	}

	/** One purely synthetic profile, with no account creation.
	 *
	 * @param int    $id Synthetic ID.
	 * @param string $name Synthetic name.
	 */
	private function profile( int $id, string $name ): Profile {
		return new Profile( $id, new ProfessionalDetails( 'P' . $id, $name, 'p' . $id . '@example.invalid', 'Técnico', 'LAB-A' ), new SelfService( '123', 'Sala A', 'Nota' ), 1, 0 );
	}
}
