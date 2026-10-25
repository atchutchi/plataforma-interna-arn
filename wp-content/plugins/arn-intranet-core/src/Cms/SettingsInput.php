<?php
/** Closed HTTP contract with bounded recovery fields.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

use InvalidArgumentException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class SettingsInput {
	public const LABELS = array(
		'news_image'      => 'Imagens de notícias',
		'news_attachment' => 'Anexos de notícias',
		'document'        => 'Documentos e anexos de tarefas',
	);
	public static function nonce( int $actor ): string {
		return 'arn-cms-settings-change-' . $actor;
	}
	public static function positive( mixed $value ): ?int {
		return is_string( $value ) && 1 === preg_match( '/\A[1-9][0-9]{0,18}\z/D', $value ) && false !== filter_var( $value, FILTER_VALIDATE_INT ) ? (int) $value : null;
	}
	public static function shape( array $input ): bool {
		$keys = array( 'nonce', 'revision', 'reason' );
		foreach ( array_keys( self::LABELS ) as $key ) {
			$keys[] = $key . '_bytes';
			$keys[] = $key . '_mimes';
			if ( ! is_string( $input[ $key . '_bytes' ] ?? null ) || strlen( $input[ $key . '_bytes' ] ) > 20 ) {
				return false;
			}
			$mimes = $input[ $key . '_mimes' ] ?? array();
			if ( ! is_array( $mimes ) || ! array_is_list( $mimes ) || count( $mimes ) > 3 ) {
				return false;
			}
			foreach ( $mimes as $mime ) {
				if ( ! is_string( $mime ) || strlen( $mime ) > 64 ) {
					return false;
				}
			}
		}
		return ! array_diff( array_keys( $input ), $keys ) && is_string( $input['nonce'] ?? null ) && strlen( $input['nonce'] ) <= 128
			&& null !== self::positive( $input['revision'] ?? null ) && is_string( $input['reason'] ?? null ) && strlen( $input['reason'] ) <= 4000;
	}
	public static function policy( array $input ): UploadPolicy {
		$values = array();
		foreach ( array_keys( self::LABELS ) as $key ) {
			$bytes = self::positive( $input[ $key . '_bytes' ] ?? null );
			if ( null === $bytes ) {
				throw new InvalidArgumentException( 'Limite inválido.' );
			}
			$values[ $key ] = array(
				'max_bytes' => $bytes,
				'mimes'     => $input[ $key . '_mimes' ] ?? array(),
			);
		}
		return UploadPolicy::fromArray( $values );
	}
	public static function fields( SettingsState $state ): array {
		$fields = array( 'reason' => '' );
		foreach ( $state->policy->toArray() as $key => $value ) {
			$fields[ $key . '_bytes' ] = (string) $value['max_bytes'];
			$fields[ $key . '_mimes' ] = $value['mimes'];
		}
		return $fields;
	}
}
