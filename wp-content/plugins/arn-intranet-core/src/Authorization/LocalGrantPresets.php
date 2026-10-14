<?php
/** Catálogo fechado de concessões fictícias.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization;

use InvalidArgumentException;

/** Não aceita papéis, âmbitos ou datas fornecidos pelo browser. */
final class LocalGrantPresets {
	/** Rótulos comuns ao formulário e ao catálogo. */
	public static function labels(): array {
		return array(
			'employee-own'   => 'Funcionário, âmbito próprio',
			'unit-manager-a' => 'Responsável da unidade fictícia A',
		);
	}
	/** Constrói uma concessão nova a partir de uma chave fechada.
	 *
	 * @param string $key Preset autorizado.
	 * @param int    $user_id Fixture resolvida no servidor.
	 * @param int    $now Instante do servidor.
	 * @throws InvalidArgumentException Quando o preset é desconhecido.
	 */
	public static function grant( string $key, int $user_id, int $now ): Grant {
		return match ( $key ) {
			'employee-own' => new Grant( $user_id, Role::Employee, Scope::own(), $now, null ),
			'unit-manager-a' => new Grant( $user_id, Role::UnitManager, Scope::units( array( 'unidade-ficticia-a' ) ), $now, null ),
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			default => throw new InvalidArgumentException( 'Concessão fictícia desconhecida.' ),
		};
	}
	/** Apresenta históricos conhecidos sem confundir âmbitos diferentes.
	 *
	 * @param Grant $grant Concessão persistente.
	 */
	public static function describe( Grant $grant ): string {
		foreach ( self::labels() as $key => $label ) {
			$preset = self::grant( $key, $grant->user_id, $grant->starts_at );
			if ( $preset->role === $grant->role && $preset->scope->type === $grant->scope->type && $preset->scope->targets === $grant->scope->targets ) {
				return $label;
			}
		}
		return 'Outro cenário fictício, consultar auditoria';
	}
}
