<?php
/**
 * Ensaios do parser e das propostas sintéticas.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Organization\ImportPreview\{StrictCsv, SyntheticScenarios, PreviewValidator, LocalImportPreviewPage};
use Arn\Intranet\Organization\Storage\{LocalUnitSnapshot, LocalUnitScenarios};
use PHPUnit\Framework\TestCase;

/** Testa a gramática e as decisões sem WordPress ou base. */
final class ImportPreviewTest extends TestCase {
	/** Cria a árvore de três unidades fictícias. */
	private function snapshot(): LocalUnitSnapshot {
		return new LocalUnitSnapshot( 1, LocalUnitScenarios::apply( 'initialize', array() ) );
	}

	/** Confirma aspas duplicadas e linha inicial física. */
	public function testQuotedNewlineAndEscapedQuotesKeepPhysicalStartLine(): void {
		$csv  = "\xEF\xBB\xBFprofessional_id,name,email,unit_id\r\nLAB-PER-003,\"Pessoa fictícia \"\"A\"\"\r\nextra\",pessoa003@example.invalid,LAB-A\r\nLAB-PER-004,Pessoa fictícia 004,pessoa004@example.invalid,LAB-A\r\n";
		$rows = StrictCsv::parse( $csv );
		self::assertSame( array( 2, 4 ), array_column( $rows, 'line' ) );
		self::assertSame( "Pessoa fictícia \"A\"\r\nextra", $rows[0]['fields'][1] );
	}

	/**
	 * Rejeita entradas lexicalmente inválidas.
	 *
	 * @param string $csv Lote inválido.
	 * @dataProvider malformedCsv
	 */
	public function testMalformedCsvIsRejected( string $csv ): void {
		$this->expectException( RuntimeException::class );
		StrictCsv::parse( $csv );
	}

	/** Lista casos de rejeição global. */
	public static function malformedCsv(): array {
		$h = "professional_id,name,email,unit_id\n";
		return array(
			'empty'                => array( '' ),
			'bad utf8'             => array( $h . "\xFF" ),
			'nul'                  => array( $h . "\0" ),
			'bad quote'            => array( $h . 'LAB-PER-003,"Pessoa fictícia 003"x,pessoa003@example.invalid,LAB-A' ),
			'unclosed'             => array( $h . '"LAB-PER-003' ),
			'bare carriage return' => array( $h . "LAB-PER-003\r" ),
			'wrong header'         => array( "name,professional_id,email,unit_id\n" ),
			'header only'          => array( "professional_id,name,email,unit_id\n" ),
			'oversize'             => array( $h . str_repeat( 'x', StrictCsv::MAX_BYTES ) ),
			'too many rows'        => array( $h . str_repeat( "a,b,c,d\n", 21 ) ),
		);
	}

	/** Confirma fronteiras inclusivas dos limites. */
	public function testByteAndRowLimitsAreInclusive(): void {
		$h    = "professional_id,name,email,unit_id\n";
		$rows = str_repeat( "a,b,c,d\n", 19 ) . 'a,b,c,d';
		self::assertCount( 20, StrictCsv::parse( $h . $rows ) );
		$csv = $h . 'a,b,c,' . str_repeat( 'x', StrictCsv::MAX_BYTES - strlen( $h ) - strlen( 'a,b,c,' ) );
		self::assertSame( StrictCsv::MAX_BYTES, strlen( $csv ) );
		self::assertCount( 1, StrictCsv::parse( $csv ) );
	}

	/** Confirma propostas e identidade reprodutível. */
	public function testProposalsAndDeterministicIdentity(): void {
		$r = PreviewValidator::validate( SyntheticScenarios::csv( 'valid' ), $this->snapshot() );
		self::assertTrue( $r->valid );
		self::assertSame(
			array(
				'create'    => 1,
				'update'    => 1,
				'unchanged' => 1,
				'invalid'   => 0,
			),
			$r->totals
		);
		self::assertSame( array( 'name' ), $r->rows[1]['changed_fields'] );
		self::assertSame( $r->batch_id, PreviewValidator::validate( SyntheticScenarios::csv( 'valid' ), $this->snapshot() )->batch_id );
	}

	/** Invalida todas as ocorrências dos conflitos. */
	public function testAllDuplicateOccurrencesAndCatalogConflictsAreInvalid(): void {
		$r = PreviewValidator::validate( SyntheticScenarios::csv( 'duplicates' ), $this->snapshot() );
		self::assertFalse( $r->valid );
		self::assertSame( 4, $r->totals['invalid'] );
		self::assertArrayHasKey( 'professional_id', $r->rows[0]['errors'] );
		self::assertArrayHasKey( 'professional_id', $r->rows[1]['errors'] );
		self::assertArrayHasKey( 'email', $r->rows[2]['errors'] );
		self::assertArrayHasKey( 'email', $r->rows[3]['errors'] );
	}

	/** Recusa unidades problemáticas e formato global. */
	public function testInactiveAndUnknownUnitsAndGlobalErrors(): void {
		$units = LocalUnitScenarios::apply( 'deactivate-b', $this->snapshot()->units );
		$r     = PreviewValidator::validate( SyntheticScenarios::csv( 'units' ), new LocalUnitSnapshot( 2, $units ) );
		self::assertSame( 2, $r->totals['invalid'] );
		self::assertSame( 1, count( PreviewValidator::validate( SyntheticScenarios::csv( 'malformed' ), $this->snapshot() )->global_errors ) );
	}

	/** Sem flags o adaptador administrativo fica fechado. */
	public function testDefaultGuardIsClosed(): void {
		self::assertFalse( LocalImportPreviewPage::allowed() );
	}

	/** Recusa espaços Unicode externos e controlos no nome. */
	public function testUnicodeBoundarySpaceAndControlNamesAreInvalid(): void {
		$header = "professional_id,name,email,unit_id\n";
		$csv    = $header . "LAB-PER-003,\u{00A0}Pessoa fictícia 003,pessoa003@example.invalid,LAB-A\nLAB-PER-004,Pessoa fictícia 004\u{200B},pessoa004@example.invalid,LAB-A\n";
		$report = PreviewValidator::validate( $csv, $this->snapshot() );
		self::assertSame( 2, $report->totals['invalid'] );
		self::assertArrayHasKey( 'name', $report->rows[0]['errors'] );
		self::assertArrayHasKey( 'name', $report->rows[1]['errors'] );
	}

	/** Rejeita pontos consecutivos e nas pontas da parte local. */
	public function testEmailLocalPartRejectsMisplacedDots(): void {
		$csv    = "professional_id,name,email,unit_id\nLAB-PER-003,Pessoa fictícia 003,a..b@example.invalid,LAB-A\nLAB-PER-004,Pessoa fictícia 004,.abc@example.invalid,LAB-A\nLAB-PER-005,Pessoa fictícia 005,abc.@example.invalid,LAB-A\n";
		$report = PreviewValidator::validate( $csv, $this->snapshot() );
		self::assertSame( 3, $report->totals['invalid'] );
		foreach ( $report->rows as $row ) {
			self::assertArrayHasKey( 'email', $row['errors'] );
		}
	}

	/** Conta um ID/email disponível mesmo numa linha com colunas extra. */
	public function testWrongArityStillInvalidatesMatchingCompleteRows(): void {
		$csv    = "professional_id,name,email,unit_id\nLAB-PER-003,Pessoa fictícia 003,pessoa003@example.invalid,LAB-A\nLAB-PER-003,Pessoa fictícia 003,pessoa003@example.invalid,LAB-A,extra\nLAB-PER-004,Pessoa fictícia 004,pessoa004@example.invalid,LAB-A\nLAB-PER-005,Pessoa fictícia 005,pessoa004@example.invalid,LAB-A,extra\n";
		$report = PreviewValidator::validate( $csv, $this->snapshot() );
		self::assertFalse( $report->valid );
		self::assertSame( 4, $report->totals['invalid'] );
		self::assertArrayHasKey( 'professional_id', $report->rows[0]['errors'] );
		self::assertArrayHasKey( 'row', $report->rows[1]['errors'] );
		self::assertArrayHasKey( 'email', $report->rows[2]['errors'] );
		self::assertArrayHasKey( 'row', $report->rows[3]['errors'] );
	}

	/** A parte local do email tem o limite canónico de 64 caracteres. */
	public function testEmailLocalPartBoundary(): void {
		$valid   = str_repeat( 'a', 64 ) . '@example.invalid';
		$invalid = str_repeat( 'b', 65 ) . '@example.invalid';
		$csv     = "professional_id,name,email,unit_id\nLAB-PER-003,Pessoa fictícia 003,$valid,LAB-A\nLAB-PER-004,Pessoa fictícia 004,$invalid,LAB-A\n";
		$report  = PreviewValidator::validate( $csv, $this->snapshot() );
		self::assertSame( 'create', $report->rows[0]['proposal'] );
		self::assertSame( 'invalid', $report->rows[1]['proposal'] );
		self::assertArrayHasKey( 'email', $report->rows[1]['errors'] );
	}
}
