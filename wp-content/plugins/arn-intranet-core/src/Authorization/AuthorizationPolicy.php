<?php
/**
 * Decisão pura por conta, operação, registo e concessão explícita.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization;

/** Não agrega concessões parciais nem altera contas ou sessões. */
final class AuthorizationPolicy {

	/**
	 * Avalia a colecção inteira antes de qualquer autorização.
	 *
	 * @param Account      $account Conta no estado corrente.
	 * @param Request      $request Registo e provas do servidor.
	 * @param array<Grant> $grants  Concessões do mesmo destinatário.
	 * @param int          $now     Relógio Unix do servidor.
	 * @return bool
	 */
	public function allows( Account $account, Request $request, array $grants, int $now ): bool {
		foreach ( $grants as $grant ) {
			if ( ! $grant instanceof Grant || $grant->user_id !== $account->user_id ) {
				return false;
			}
		}
		if ( AccountState::Active !== $account->state || ! $account->session_valid || $now < 0 || ! $request->module_ready || ! $request->state_allowed ) {
			return false;
		}
		if ( ! $this->contextAllows( $account, $request ) ) {
			return false;
		}
		foreach ( $grants as $grant ) {
			if ( $grant->validAt( $now ) && $this->roleAllows( $grant->role, $request->action ) && $grant->scope->covers( $request->scope, $account->user_id, $request->owner_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Impõe invariantes do registo independentemente do âmbito concedido.
	 *
	 * @param Account $account Actor autenticado.
	 * @param Request $request Contexto do registo.
	 * @return bool
	 */
	private function contextAllows( Account $account, Request $request ): bool {
		return match ( $request->action ) {
			Action::UpdateOwnProfile, Action::ReadOwnTask, Action::UpdateOwnTask, Action::CreateNewsDraft, Action::SubmitNews, Action::ManageInstitutionalNotice => $request->owner_id === $account->user_id,
			Action::ReadContent, Action::ReadDirectory => $request->audience_allowed,
			Action::ApproveNews, Action::PublishNews, Action::DecideInstitutionalNotice => $request->version_current && $request->author_id > 0 && $request->author_id !== $account->user_id,
			Action::ManageAccount, Action::ManageGrants => $request->owner_id > 0 && $request->owner_id !== $account->user_id,
			default => true,
		};
	}

	/**
	 * Catálogo fechado sem herança do pacote de funcionário.
	 *
	 * @param Role   $role   Pacote concedido.
	 * @param Action $action Operação pedida.
	 * @return bool
	 */
	private function roleAllows( Role $role, Action $action ): bool {
		$actions = match ( $role ) {
			Role::Employee => array( Action::ReadContent, Action::ReadDirectory, Action::UpdateOwnProfile, Action::ReadOwnTask, Action::UpdateOwnTask, Action::CreateNewsDraft, Action::SubmitNews ),
			Role::UnitManager => array( Action::ReadDirectory, Action::AssignUnitTask, Action::ReadUnitTask, Action::PublishTeamNotice ),
			Role::HumanResources => array( Action::ReadHrRecord, Action::WriteHrRecord, Action::ManageDocuments ),
			Role::Editorial => array( Action::ReviewNews, Action::ApproveNews, Action::PublishNews ),
			Role::AccessAdministrator => array( Action::ManageAccount, Action::ManageGrants ),
			Role::TechnicalAdministrator, Role::Leadership, Role::Support => array(),
			Role::NoticeAuthor => array( Action::ManageInstitutionalNotice ),
			Role::NoticeReviewer => array( Action::ReviewInstitutionalNotice, Action::DecideInstitutionalNotice ),
			Role::UnitEventManager => array( Action::ManageUnitEvent ),
			Role::InstitutionalEventManager => array( Action::ManageInstitutionalEvent ),
			Role::CmsAdministrator => array( Action::ManageCmsSettings ),
		};
		return in_array( $action, $actions, true );
	}
}
