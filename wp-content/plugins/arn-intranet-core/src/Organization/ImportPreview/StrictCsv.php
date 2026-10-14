<?php
/**
 * Leitura lexical estrita de CSV sintético.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\ImportPreview;

use RuntimeException;

/** Conserva a linha física inicial de cada registo, mesmo com LF entre aspas. */
final class StrictCsv {
	public const HEADER    = array( 'professional_id', 'name', 'email', 'unit_id' );
	public const MAX_BYTES = 16384;
	public const MAX_ROWS  = 20;

	/**
	 * Lê registos com a linha física inicial.
	 *
	 * @param string $csv Conteúdo UTF-8 do lote.
	 * @return array<int,array{line:int,fields:array<int,string>}>
	 * @throws RuntimeException Quando a gramática ou os limites falham.
	 */
	public static function parse( string $csv ): array {
		if ( '' === $csv || strlen( $csv ) > self::MAX_BYTES || str_contains( $csv, "\0" ) || 1 !== preg_match( '//u', $csv ) ) {
			throw new RuntimeException( 'CSV sintético inválido.' );
		}
		if ( str_starts_with( $csv, "\xEF\xBB\xBF" ) ) {
			$csv = substr( $csv, 3 );
		}
		$rows   = array();
		$row    = array();
		$field  = '';
		$state  = 'start';
		$line   = 1;
		$start  = 1;
		$length = strlen( $csv );
		for ( $i = 0; $i < $length; ++$i ) {
			$c = $csv[ $i ];
			if ( 'quoted' === $state ) {
				if ( '"' === $c ) {
					if ( $i + 1 < $length && '"' === $csv[ $i + 1 ] ) {
						$field .= '"';
						++$i;
					} else {
						$state = 'closed';
					}
				} elseif ( "\r" === $c ) {
					if ( $i + 1 >= $length || "\n" !== $csv[ $i + 1 ] ) {
						throw new RuntimeException( 'CSV sintético inválido.' );
					}
					$field .= "\r\n";
					++$i;
					++$line;
				} else {
					$field .= $c;
					if ( "\n" === $c ) {
						++$line;
					}
				}
				continue;
			}
			if ( '"' === $c ) {
				if ( 'start' !== $state ) {
					throw new RuntimeException( 'CSV sintético inválido.' );
				}
				$state = 'quoted';
				continue;
			}
			if ( ',' === $c || "\n" === $c || "\r" === $c ) {
				$row[] = $field;
				$field = '';
				$state = 'start';
				if ( ',' === $c ) {
					continue;
				}
				if ( "\r" === $c ) {
					if ( $i + 1 >= $length || "\n" !== $csv[ $i + 1 ] ) {
						throw new RuntimeException( 'CSV sintético inválido.' );
					}
					++$i;
				}
				$rows[] = array(
					'line'   => $start,
					'fields' => $row,
				);
				if ( count( $rows ) > self::MAX_ROWS + 1 ) {
					throw new RuntimeException( 'CSV sintético inválido.' );
				}
				$row = array();
				++$line;
				$start = $line;
				continue;
			}
			if ( 'closed' === $state || "\0" === $c ) {
				throw new RuntimeException( 'CSV sintético inválido.' );
			}
			$field .= $c;
			$state  = 'plain';
		}
		if ( 'quoted' === $state ) {
			throw new RuntimeException( 'CSV sintético inválido.' );
		}
		if ( 'start' !== $state || '' !== $field || array() !== $row ) {
			$row[]  = $field;
			$rows[] = array(
				'line'   => $start,
				'fields' => $row,
			);
		}
		if ( count( $rows ) < 2 || self::HEADER !== $rows[0]['fields'] || count( $rows ) - 1 > self::MAX_ROWS ) {
			throw new RuntimeException( 'CSV sintético inválido.' );
		}
		return array_slice( $rows, 1 );
	}
}
