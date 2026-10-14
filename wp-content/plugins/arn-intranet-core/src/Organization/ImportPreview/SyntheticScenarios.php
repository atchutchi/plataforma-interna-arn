<?php
/**
 * Lotes fechados do ensaio.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\ImportPreview;

use RuntimeException;

/** Nenhum dado deste catálogo vem do browser ou de empregados reais. */
final class SyntheticScenarios {
	public const CATALOG_REVISION = 1;

	/** Devolve as duas pessoas fictícias existentes. */
	public static function catalog(): array {
		return array(
			'LAB-PER-001' => array(
				'name'    => 'Pessoa fictícia 001',
				'email'   => 'pessoa001@example.invalid',
				'unit_id' => 'LAB-A',
			),
			'LAB-PER-002' => array(
				'name'    => 'Pessoa fictícia 002',
				'email'   => 'pessoa002@example.invalid',
				'unit_id' => 'LAB-A',
			),
		);
	}

	/** Devolve as opções fechadas do formulário. */
	public static function labels(): array {
		return array(
			'valid'      => 'Lote válido: sem mudança, alteração e criação',
			'duplicates' => 'Identificadores e emails repetidos',
			'units'      => 'Unidade desconhecida e LAB-B',
			'malformed'  => 'Aspas CSV malformadas',
			'oversize'   => 'Tamanho máximo excedido',
		);
	}

	/**
	 * Obtém o CSV incorporado no servidor.
	 *
	 * @param string $scenario Chave fechada do cenário.
	 * @throws RuntimeException Se o cenário não existir.
	 */
	public static function csv( string $scenario ): string {
		$header = "professional_id,name,email,unit_id\n";
		return match ( $scenario ) {
			'valid' => $header . "LAB-PER-001,Pessoa fictícia 001,pessoa001@example.invalid,LAB-A\nLAB-PER-002,Pessoa fictícia 002 revista,pessoa002@example.invalid,LAB-A\nLAB-PER-003,Pessoa fictícia 003,pessoa003@example.invalid,LAB-ROOT\n",
			'duplicates' => $header . "LAB-PER-003,Pessoa fictícia 003,pessoa003@example.invalid,LAB-A\nLAB-PER-003,Pessoa fictícia 003,pessoa004@example.invalid,LAB-A\nLAB-PER-004,Pessoa fictícia 004,pessoa001@example.invalid,LAB-A\nLAB-PER-005,Pessoa fictícia 005,pessoa001@example.invalid,LAB-A\n",
			'units' => $header . "LAB-PER-003,Pessoa fictícia 003,pessoa003@example.invalid,LAB-MISSING\nLAB-PER-004,Pessoa fictícia 004,pessoa004@example.invalid,LAB-B\n",
			'malformed' => $header . "LAB-PER-003,\"Pessoa fictícia 003\"lixo,pessoa003@example.invalid,LAB-A\n",
			'oversize' => $header . str_repeat( 'X', StrictCsv::MAX_BYTES ),
			default => throw new RuntimeException( 'Cenário sintético inválido.' ),
		};
	}
}
