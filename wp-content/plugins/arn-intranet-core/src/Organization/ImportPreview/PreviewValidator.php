<?php
/**
 * Validação de dados fictícios sem persistência.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\ImportPreview;

use Arn\Intranet\Organization\Storage\LocalUnitSnapshot;
use RuntimeException;

/** Produz propostas informativas, sem operação de aplicação. */
final class PreviewValidator {
	public const VERSION = '1';

	/**
	 * Analisa todas as linhas e produz apenas propostas.
	 *
	 * @param string            $csv Lote sintético.
	 * @param LocalUnitSnapshot $snapshot Unidades lidas.
	 */
	public static function validate( string $csv, LocalUnitSnapshot $snapshot ): PreviewReport {
		$catalog = SyntheticScenarios::catalog();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Hash estrito também sem WordPress.
		$catalog_hash  = hash( 'sha256', json_encode( $catalog, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE ) );
		$units_hash    = hash( 'sha256', LocalUnitSnapshot::encode( $snapshot->units ) );
		$csv_hash      = hash( 'sha256', $csv );
		$batch_id      = hash( 'sha256', implode( ':', array( self::VERSION, $csv_hash, $catalog_hash, (string) SyntheticScenarios::CATALOG_REVISION, $units_hash, (string) $snapshot->revision ) ) );
		$rows          = array();
		$global_errors = array();
		$totals        = array(
			'create'    => 0,
			'update'    => 0,
			'unchanged' => 0,
			'invalid'   => 0,
		);
		try {
			$parsed = StrictCsv::parse( $csv );
		} catch ( RuntimeException ) {
			$parsed          = array();
			$global_errors[] = 'CSV sintético inválido.';
		}
		$ids    = array();
		$emails = array();
		foreach ( $parsed as $record ) {
			$fields = $record['fields'];
			if ( isset( $fields[0] ) ) {
				$ids[ $fields[0] ] = ( $ids[ $fields[0] ] ?? 0 ) + 1;
			}
			if ( isset( $fields[2] ) ) {
				$emails[ $fields[2] ] = ( $emails[ $fields[2] ] ?? 0 ) + 1;
			}
		}
		$units = array_column( $snapshot->units, null, 'id' );
		foreach ( $parsed as $record ) {
			$values   = $record['fields'];
			$errors   = array();
			$proposal = 'invalid';
			$changed  = array();
			if ( 4 !== count( $values ) ) {
				$errors['row'] = 'São necessários quatro campos.';
				if ( isset( $values[0] ) && ( $ids[ $values[0] ] ?? 0 ) > 1 ) {
					$errors['professional_id'] = 'Identificador repetido no lote.';
				}
				if ( isset( $values[2] ) && ( $emails[ $values[2] ] ?? 0 ) > 1 ) {
					$errors['email'] = 'Email em conflito.';
				}
			} else {
				[ $id, $name, $email, $unit_id ] = $values;
				if ( ! preg_match( '/^LAB-PER-(?!000)[0-9]{3}$/D', $id ) ) {
					$errors['professional_id'] = 'Identificador fictício inválido.';
				} elseif ( $ids[ $id ] > 1 ) {
					$errors['professional_id'] = 'Identificador repetido no lote.';
				}
				if ( '' === $name || mb_strlen( $name, 'UTF-8' ) > 100 || ! str_starts_with( $name, 'Pessoa fictícia ' ) || preg_match( '/(?:\A[\p{Z}\s]|[\p{Z}\s]\z|\p{C})/u', $name ) ) {
					$errors['name'] = 'Nome fictício inválido.';
				}
				if ( trim( $email ) !== $email || ! preg_match( '/^(?![^@]*\.\.)[a-z0-9](?:[a-z0-9._+-]*[a-z0-9])?@example\.invalid$/D', $email ) ) {
					$errors['email'] = 'Email fictício inválido.';
				} elseif ( $emails[ $email ] > 1 || self::belongsToOther( $catalog, $email, $id ) ) {
					$errors['email'] = 'Email em conflito.';
				}
				if ( ! isset( $units[ $unit_id ] ) || ! $units[ $unit_id ]->active ) {
					$errors['unit_id'] = 'Unidade inexistente ou inactiva.';
				}
				if ( array() === $errors ) {
					if ( ! isset( $catalog[ $id ] ) ) {
						$proposal = 'create';
					} else {
						foreach ( array(
							'name'    => $name,
							'email'   => $email,
							'unit_id' => $unit_id,
						) as $key => $value ) {
							if ( $catalog[ $id ][ $key ] !== $value ) {
								$changed[] = $key;
							}
						}
						$proposal = array() === $changed ? 'unchanged' : 'update';
					}
				}
			}
			++$totals[ $proposal ];
			$rows[] = array(
				'line'           => $record['line'],
				'values'         => $values,
				'errors'         => $errors,
				'proposal'       => $proposal,
				'changed_fields' => $changed,
			);
		}
		return new PreviewReport( $rows, $totals, array() === $global_errors && 0 === $totals['invalid'], $global_errors, $csv_hash, $catalog_hash, $units_hash, SyntheticScenarios::CATALOG_REVISION, $snapshot->revision, self::VERSION, $batch_id );
	}

	/**
	 * Detecta conflito com uma identidade fictícia diferente.
	 *
	 * @param array  $catalog Pessoas fictícias existentes.
	 * @param string $email Email analisado.
	 * @param string $id Identificador da linha.
	 */
	private static function belongsToOther( array $catalog, string $email, string $id ): bool {
		foreach ( $catalog as $known_id => $person ) {
			if ( $known_id !== $id && $person['email'] === $email ) {
				return true;
			}
		}
		return false;
	}
}
