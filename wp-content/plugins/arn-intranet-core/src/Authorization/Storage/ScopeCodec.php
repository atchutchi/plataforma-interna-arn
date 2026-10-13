<?php
/**
 * Serialização estrita e canónica de âmbitos internos.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization\Storage;

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\ScopeType;
use InvalidArgumentException;
use JsonException;

// Mensagens constantes de domínio, sem conteúdo JSON externo.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/** JSON object nunca é convertido em lista de alvos. */
final class ScopeCodec {

	/**
	 * Ordena por valor sem modificar o âmbito.
	 *
	 * @param Scope $scope Âmbito validado.
	 * @return string
	 */
	public static function encode( Scope $scope ): string {
		$targets = $scope->targets;
		if ( ScopeType::People === $scope->type ) {
			usort( $targets, static fn( int $left, int $right ): int => $left <=> $right );
		} else {
			sort( $targets, SORT_STRING );
		}
		return json_encode( $targets, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Classe pura sem WordPress.
	}

	/**
	 * Exige nomes exactos, JSON array e tipos validados pelas fábricas.
	 *
	 * @param string $type         Nome exacto do enum.
	 * @param string $targets_json Lista JSON.
	 * @return Scope
	 * @throws InvalidArgumentException Quando o formato é inválido.
	 */
	public static function decode( string $type, string $targets_json ): Scope {
		try {
			$targets = json_decode( $targets_json, false, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			throw new InvalidArgumentException( 'Âmbito persistente inválido.' );
		}
		if ( ! is_array( $targets ) ) {
			throw new InvalidArgumentException( 'Âmbito persistente inválido.' );
		}
		return match ( $type ) {
			'Own' => array() === $targets ? Scope::own() : self::reject(),
			'Institutional' => array() === $targets ? Scope::institutional() : self::reject(),
			'People' => Scope::people( $targets ),
			'Units' => Scope::units( $targets ),
			'Queues' => Scope::queues( $targets ),
			'Audiences' => Scope::audiences( $targets ),
			default => self::reject(),
		};
	}

	/**
	 * Recusa formato sem devolver uma concessão por omissão.
	 *
	 * @return never
	 * @throws InvalidArgumentException Sempre.
	 */
	private static function reject(): never {
		throw new InvalidArgumentException( 'Âmbito persistente inválido.' );
	}
}
