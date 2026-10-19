<?php
/** Privacy and integrity of exclusive contention diagnostics.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Tests\Fixtures\EditorialContentionTrace;
use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions, Squiz.Commenting
if ( is_file( dirname( __DIR__ ) . '/integration/fixtures/EditorialContentionTrace.php' ) ) {
	require_once dirname( __DIR__ ) . '/integration/fixtures/EditorialContentionTrace.php';
}

/** Never turn arbitrary diagnostic payload into public output. */
final class EditorialContentionTraceTest extends TestCase {

	private array $paths = array();

	protected function tearDown(): void {
		foreach ( $this->paths as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}
	}

	private function trace(): EditorialContentionTrace {
		self::assertTrue( class_exists( EditorialContentionTrace::class ), 'Falta a observação segura da concorrência.' );
		$path          = sys_get_temp_dir() . '/arn_ed_' . bin2hex( random_bytes( 5 ) ) . '_' . bin2hex( random_bytes( 8 ) ) . '.trace';
		$this->paths[] = $path;
		return new EditorialContentionTrace( $path );
	}

	public function test_unknown_phase_is_rejected_before_writing(): void {
		$trace = $this->trace();
		try {
			$trace->record( 'private-sql-and-password' );
			self::fail( 'Não aceitar uma fase livre.' );
		} catch ( InvalidArgumentException ) {
			self::assertFalse( $trace->snapshot()['available'] );
		}
	}

	public function test_private_error_text_is_rejected_without_changing_existing_proof(): void {
		$trace = $this->trace();
		$trace->record( 'ready', array( 'connection' => 42 ) );
		$before = file_get_contents( $this->paths[0] );
		try {
			$trace->record( 'refused', array( 'message' => 'private-sql-password-cookie' ) );
			self::fail( 'Não aceitar texto de erro livre.' );
		} catch ( InvalidArgumentException ) {
			self::assertSame( $before, file_get_contents( $this->paths[0] ) );
			self::assertStringNotContainsString( 'private', json_encode( $trace->snapshot(), JSON_THROW_ON_ERROR ) );
		}
	}

	public function test_records_ordered_phases_and_typed_sql_failure(): void {
		$trace = $this->trace();
		$trace->record( 'ready', array( 'connection' => 42 ) );
		$trace->record( 'authority-enter' );
		$trace->record(
			'before-rollback',
			array(
				'errno'     => 1205,
				'sql_error' => true,
			)
		);
		$trace->record( 'refused' );
		$snapshot = $trace->snapshot();
		self::assertTrue( $snapshot['available'] );
		self::assertTrue( $snapshot['complete'] );
		self::assertSame( array( 'ready', 'authority-enter', 'before-rollback', 'refused' ), array_column( $snapshot['records'], 'phase' ) );
		self::assertSame(
			array(
				'errno'     => 1205,
				'sql_error' => true,
			),
			$snapshot['records'][2]['facts']
		);
		self::assertGreaterThanOrEqual( $snapshot['records'][0]['at_ns'], $snapshot['records'][3]['at_ns'] );
	}

	public function test_invalid_fact_types_never_become_valid_error_codes(): void {
		$trace = $this->trace();
		try {
			$trace->record(
				'before-rollback',
				array(
					'errno'     => '1205',
					'sql_error' => 'false',
				)
			);
			self::fail( 'Não converter tipos inválidos.' );
		} catch ( InvalidArgumentException ) {
			self::assertFalse( $trace->snapshot()['available'] );
		}
	}

	public function test_partial_or_untrusted_record_is_reported_without_publishing_it(): void {
		$trace = $this->trace();
		$trace->record( 'ready' );
		file_put_contents( $this->paths[0], "{\"phase\":\"private-cookie\"}\n{", FILE_APPEND );
		$snapshot = $trace->snapshot();
		self::assertFalse( $snapshot['complete'] );
		self::assertCount( 1, $snapshot['records'] );
		self::assertStringNotContainsString( 'private-cookie', json_encode( $snapshot, JSON_THROW_ON_ERROR ) );
	}

	public function test_only_owned_temporary_paths_are_accepted(): void {
		$this->trace();
		$this->expectException( InvalidArgumentException::class );
		new EditorialContentionTrace( sys_get_temp_dir() . '/unrelated-diagnostic.trace' );
	}
}
