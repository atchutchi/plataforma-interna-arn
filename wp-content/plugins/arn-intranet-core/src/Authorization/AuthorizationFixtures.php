<?php
/**
 * Snapshots fictícios do laboratório de autorização.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization;

/** Executa exemplos fechados sem consultar contas ou persistir dados. */
final class AuthorizationFixtures {
	/**
	 * Devolve resultados estáveis para o relógio recebido.
	 *
	 * @param int $now Instante Unix fornecido pelo servidor.
	 * @return array<array{id:string,text:string,expected:bool,actual:bool}>
	 */
	public static function results( int $now ): array {
		$account  = new Account( 101, AccountState::Active, true );
		$unit     = Scope::units( array( 'unidade-ficticia-a' ) );
		$own      = Scope::own();
		$employee = new Grant( 101, Role::Employee, $own, 0, null );
		$manager  = new Grant( 101, Role::UnitManager, $unit, 0, null );
		$hr       = new Grant( 101, Role::HumanResources, Scope::people( array( 202 ) ), 0, null );
		$editor   = new Grant( 101, Role::Editorial, Scope::queues( array( 'fila-ficticia-a' ) ), 0, null );
		$policy   = new AuthorizationPolicy();
		$results  = array();
		$add      = static function ( string $id, string $text, bool $expected, Action $action, Scope $scope, int $owner, array $grants, bool $ready = true, int $author = 202, bool $version = true, ?Account $actor = null ) use ( &$results, $policy, $now, $account ): void {
			$results[] = array(
				'id'       => $id,
				'text'     => $text,
				'expected' => $expected,
				'actual'   => $policy->allows( $actor ?? $account, new Request( $action, $scope, $owner, $author, $ready, true, true, $version ), $grants, $now ),
			);
		};
		$add( 'own-profile', 'Funcionário actualiza o próprio perfil.', true, Action::UpdateOwnProfile, $own, 101, array( $employee ) );
		$add( 'colleague-profile', 'Funcionário tenta actualizar o perfil de um colega.', false, Action::UpdateOwnProfile, $own, 202, array( $employee ) );
		$add( 'unit-task', 'Responsável lê tarefa da unidade fictícia autorizada.', true, Action::ReadUnitTask, $unit, 202, array( $manager ) );
		$add( 'external-unit', 'Responsável tenta ler tarefa de outra unidade fictícia.', false, Action::ReadUnitTask, Scope::units( array( 'unidade-ficticia-b' ) ), 202, array( $manager ) );
		$add( 'hr-limited', 'RH lê registo da pessoa fictícia explicitamente abrangida.', true, Action::ReadHrRecord, Scope::people( array( 202 ) ), 202, array( $hr ) );
		$add( 'hr-outside', 'RH tenta ler registo de pessoa fora do âmbito concedido.', false, Action::ReadHrRecord, Scope::people( array( 303 ) ), 303, array( $hr ) );
		$admin             = array( new Grant( 101, Role::AccessAdministrator, Scope::people( array( 202 ) ), 0, null ) );
		$delegations       = array( new Delegation( 101, array( 202 ), array( Role::UnitManager ), $unit, 0, null ) );
		$delegation_policy = new DelegationPolicy();
		foreach ( array(
			array( 'delegation', 'Administrador atribui função e unidade dentro da delegação explícita.', true, 202, Role::UnitManager, $unit ),
			array( 'delegation-expanded', 'Administrador tenta ampliar a delegação a outra unidade.', false, 202, Role::UnitManager, Scope::units( array( 'unidade-ficticia-a', 'unidade-ficticia-b' ) ) ),
			array( 'self-promotion', 'Administrador tenta atribuir a si próprio um pacote administrativo.', false, 101, Role::AccessAdministrator, Scope::institutional() ),
		) as [$id, $text, $expected, $target, $role, $scope] ) {
			$results[] = array(
				'id'       => $id,
				'text'     => $text,
				'expected' => $expected,
				'actual'   => $delegation_policy->canGrant( $account, $admin, $delegations, $target, $role, $scope, $now, true ),
			);
		}
		$add( 'expired', 'Concessão no instante de expiração, com fim exclusivo. No instante Unix zero ainda está vigente.', 0 === $now, Action::UpdateOwnProfile, $own, 101, array( new Grant( 101, Role::Employee, $own, 0, max( 1, $now ) ) ) );
		$add( 'revoked', 'Concessão revogada não autoriza o próprio perfil.', false, Action::UpdateOwnProfile, $own, 101, array( new Grant( 101, Role::Employee, $own, 0, null, true ) ) );
		$add( 'suspended', 'Conta suspensa não lê tarefas próprias.', false, Action::ReadOwnTask, $own, 101, array( $employee ), actor: new Account( 101, AccountState::Suspended, true ) );
		$add( 'module-unavailable', 'Módulo indisponível recusa uma concessão válida.', false, Action::ReadOwnTask, $own, 101, array( $employee ), ready: false );
		$queue = Scope::queues( array( 'fila-ficticia-a' ) );
		$add( 'own-news', 'Editor tenta publicar notícia de que é autor.', false, Action::PublishNews, $queue, 202, array( $editor ), author: 101 );
		$add( 'changed-version', 'Editor tenta publicar uma versão que já foi alterada.', false, Action::PublishNews, $queue, 202, array( $editor ), version: false );
		return $results;
	}
}
