<?php
/**
 * Decisão pura de atribuição sob autorização e delegação explícitas.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization;

/** Impede promoção própria e alargamentos parciais ou administrativos. */
final class DelegationPolicy {

	/**
	 * Exige ManageGrants sobre o alvo e uma única delegação suficiente.
	 *
	 * @param Account           $actor          Conta autenticada no servidor.
	 * @param array<Grant>      $grants         Concessões do actor.
	 * @param array<Delegation> $delegations    Delegações do mesmo actor.
	 * @param int               $target_user_id Destinatário conhecido.
	 * @param Role              $requested_role Pacote pedido.
	 * @param Scope             $requested_scope Âmbito completo pedido.
	 * @param int               $now            Relógio Unix do servidor.
	 * @param bool              $module_ready   Módulo disponível.
	 * @return bool
	 */
	public function canGrant( Account $actor, array $grants, array $delegations, int $target_user_id, Role $requested_role, Scope $requested_scope, int $now, bool $module_ready ): bool {
		foreach ( $delegations as $delegation ) {
			if ( ! $delegation instanceof Delegation || $delegation->actor_id !== $actor->user_id ) {
				return false;
			}
		}
		if ( $target_user_id <= 0 || $target_user_id === $actor->user_id || in_array( $requested_role, array( Role::AccessAdministrator, Role::TechnicalAdministrator ), true ) ) {
			return false;
		}
		$request = new Request( Action::ManageGrants, Scope::people( array( $target_user_id ) ), $target_user_id, 0, $module_ready, true, true, true );
		if ( ! ( new AuthorizationPolicy() )->allows( $actor, $request, $grants, $now ) ) {
			return false;
		}
		foreach ( $delegations as $delegation ) {
			if ( $delegation->validAt( $now ) && in_array( $target_user_id, $delegation->manageable_user_ids, true ) && in_array( $requested_role, $delegation->allowed_roles, true ) && $delegation->allowed_scope->covers( $requested_scope, $target_user_id, $target_user_id ) ) {
				return true;
			}
		}
		return false;
	}
}
