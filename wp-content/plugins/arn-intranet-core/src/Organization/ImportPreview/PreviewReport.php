<?php
/**
 * Resultado efémero do ensaio.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\ImportPreview;

/** A identidade do lote exclui actor e instante da apresentação. */
final readonly class PreviewReport {
	/**
	 * Conserva os valores da análise apenas em memória.
	 *
	 * @param array  $rows Linhas analisadas.
	 * @param array  $totals Contagens por proposta.
	 * @param bool   $valid Validade global.
	 * @param array  $global_errors Erros de formato.
	 * @param string $csv_hash Digest do CSV.
	 * @param string $catalog_hash Digest do catálogo.
	 * @param string $units_hash Digest das unidades.
	 * @param int    $catalog_revision Revisão do catálogo.
	 * @param int    $units_revision Revisão das unidades.
	 * @param string $validator_version Versão do validador.
	 * @param string $batch_id Identificador determinístico.
	 */
	public function __construct(
		public array $rows,
		public array $totals,
		public bool $valid,
		public array $global_errors,
		public string $csv_hash,
		public string $catalog_hash,
		public string $units_hash,
		public int $catalog_revision,
		public int $units_revision,
		public string $validator_version,
		public string $batch_id,
	) {}
}
