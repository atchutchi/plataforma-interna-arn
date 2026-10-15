<?php
/** Canonical storage codec for explicit audiences.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode

/** NULL is legacy absence; every other value is an exact canonical object. */
final class AudienceCodec {
	public static function encode( ?Audience $audience ): ?string {
		return null === $audience ? null : json_encode(
			array(
				'kind'    => $audience->kind,
				'targets' => $audience->targets,
			),
			JSON_THROW_ON_ERROR
		);
	}
	public static function decode( ?string $json ): ?Audience {
		if ( null === $json ) {
			return null;
		}
		try {
			$value = json_decode( $json, true, 16, JSON_THROW_ON_ERROR );
			if ( ! is_array( $value ) || array_keys( $value ) !== array( 'kind', 'targets' ) || ! is_array( $value['targets'] ) ) {
				throw new RuntimeException();
			}
			$audience = match ( $value['kind'] ) {
				'all' => array() === $value['targets'] ? Audience::all() : throw new RuntimeException(),
				'units' => Audience::units( $value['targets'] ),
				'people' => Audience::people( $value['targets'] ),
				default => throw new RuntimeException(),
			};
			if ( self::encode( $audience ) !== $json ) {
				throw new RuntimeException();
			}
			return $audience;
		} catch ( Throwable ) {
			throw new RuntimeException( 'Audiência persistida inválida.' );
		}
	}
}
