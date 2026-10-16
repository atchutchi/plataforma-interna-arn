<?php
/**
 * Validação do lote nominativo fechado.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Identity\PreRegistration\WorkspaceBatch;
use Arn\Intranet\Tests\Doubles\WorkspaceInventoryFixture;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/doubles/WorkspaceInventoryFixture.php';
/**
 * A fonte seleccionada não confere competências.
 */
final class WorkspaceBatchTest extends TestCase {
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param array|null $fixture Fonte fictícia.
	 * @return WorkspaceBatch Lote validado.
	 */
	private function batch( ?array $fixture = null ): WorkspaceBatch {
		$f = $fixture ?? WorkspaceInventoryFixture::make();
		return WorkspaceBatch::validate( $f['inventory'], $f['source'], $f['source_hash'], $f['inventory_hash'], 'example.invalid', 73, 1894705200 );
	}
	/**
	 * Conserva a selecção e os nomes exactos da origem.
	 */
	public function testSelectedPopulationAndExactNames(): void {
		$batch = $this->batch();
		self::assertCount( 73, $batch->entries );
		self::assertSame( 'Pessoa Fictícia 0', $batch->entries[0]->display_name );
		self::assertSame( 'fixture.0@example.invalid', $batch->entries[0]->primary_email );
		self::assertSame( 1894701600, $batch->observed_at );
		self::assertSame( $batch->observed_at + 86400, $batch->expires_at );
	}
	/**
	 * Não converte divergências ou duplicados em dados utilizáveis.
	 */
	public function testEveryMalformedSourceIsRejectedWithoutPayloadInException(): void {
		$cases = array(
			static function ( &$f ) {
				$f['inventory']['count'] = '77'; },
			static function ( &$f ) {
				$f['inventory']['users'][0]['display_name'] = ''; },
			static function ( &$f ) {
				$f['inventory']['users'][0]['primary_email'] = 'fixture.0@other.invalid'; },
			static function ( &$f ) {
				$f['inventory']['users'][0]['primary_email'] = 'Fixture.0@example.invalid'; },
			static function ( &$f ) {
				$f['inventory']['users'][0]['display_name'] = 'Outro nome privado'; },
			static function ( &$f ) {
				$f['inventory']['users'][1]['primary_email'] = 'fixture.0@example.invalid'; },
			static function ( &$f ) {
				$f['inventory']['users'][0]['classification'] = 'unknown'; },
			static function ( &$f ) {
				$f['inventory']['users'][0]['workspace_status'] = 'Suspended'; },
			static function ( &$f ) {
				$f['source']['users'][0]['Status [READ ONLY]'] = 'Suspended'; },
			static function ( &$f ) {
				$f['source']['users'][] = $f['source']['users'][0]; },
			static function ( &$f ) {
				$f['inventory']['observed_at_utc'] = '2030-02-30T10:00:00Z'; },
			static function ( &$f ) {
				$f['inventory']['observed_at_utc'] = '2030-01-16T10:00:00Z'; },
			static function ( &$f ) {
				$f['inventory']['observed_at_utc'] = '2030-01-15T11:00:00+01:00'; },
			static function ( &$f ) {
				$f['inventory']['users'][0]['display_name'] = "Pessoa\xFF"; },
			static function ( &$f ) {
				$f['inventory']['users'][0]['application_account'] = 'already_provisioned'; },
			static function ( &$f ) {
				$f['inventory']['source_sha256'] = str_repeat( '0', 64 ); },
		);
		foreach ( $cases as $mutate ) {
			$f = WorkspaceInventoryFixture::make();
			$mutate( $f );
			try {
				$this->batch( $f );
				self::fail( 'Fonte inválida aceite.' );
			} catch ( RuntimeException $error ) {
				self::assertSame( 'Lote Workspace inválido.', $error->getMessage() );
			}
		}
	}
}
