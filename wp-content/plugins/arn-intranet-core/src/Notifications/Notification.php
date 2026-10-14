<?php
/** Mensagens públicas e entradas canónicas.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Notifications;

/** Catálogo fechado sem payloads de auditoria. */
final class Notification {
	public const EVENTS = array( 'state_changed', 'grant_created', 'grant_replaced', 'grant_revoked' );
	/** Devolve mensagem constante do catálogo fechado.
	 *
	 * @param string $event Nome interno do evento.
	 */
	public static function title( string $event ): ?string {
		return match ( $event ) {
			'state_changed' => 'O estado da tua conta foi alterado.',
			'grant_created' => 'Foi criado um acesso para a tua conta.',
			'grant_replaced' => 'Um acesso da tua conta foi substituído.',
			'grant_revoked' => 'Um acesso da tua conta foi revogado.',
			default => null,
		};
	}
	/** Aceita apenas inteiro positivo canónico dentro do domínio PHP.
	 *
	 * @param mixed $value Valor recebido sem normalização.
	 */
	public static function positive( mixed $value ): ?int {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) || strlen( $value ) > strlen( (string) PHP_INT_MAX ) || ( strlen( $value ) === strlen( (string) PHP_INT_MAX ) && strcmp( $value, (string) PHP_INT_MAX ) > 0 ) ) {
			return null;
		}
		return (int) $value;
	}
	/** Reduz o evento ao envelope público permitido.
	 *
	 * @param object $row Envelope SQL filtrado.
	 */
	public static function project( object $row ): array {
		return array(
			'id'          => (int) $row->id,
			'occurred_at' => (int) $row->occurred_at,
			'title'       => self::title( $row->event ),
			'read_at'     => null === $row->read_at ? null : (int) $row->read_at,
		);
	}
}
