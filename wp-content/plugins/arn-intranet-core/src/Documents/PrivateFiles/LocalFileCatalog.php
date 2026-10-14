<?php
/** Catálogo fechado de ficheiros sintéticos.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\AuthorizationPolicy;
use Arn\Intranet\Authorization\Request;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;

/** Resolve apenas objectos e provas definidos no servidor. */
final class LocalFileCatalog {
	/**
	 * Constrói o pedido da versão e variante corrente.
	 *
	 * @param FileRecord $record Referência auditada.
	 * @param int        $owner_id Ana validada no servidor.
	 */
	public static function request( FileRecord $record, int $owner_id ): ?Request {
		$mimes = array(
			'original'  => 'application/pdf',
			'thumbnail' => 'image/png',
			'preview'   => 'image/jpeg',
		);
		if ( $owner_id < 1 || 'local_document' !== $record->object_type || 2 !== $record->version || ! in_array( $record->object_id, array( 1, 2, 3 ), true ) || $mimes[ $record->variant ] !== $record->blob->mime ) {
			return null;
		}
		$scope = match ( $record->object_id ) {
			1 => Scope::own(),
			2 => Scope::units( array( 'unidade-ficticia-a' ) ),
			3 => Scope::units( array( 'unidade-ficticia-b' ) ),
		};
		return new Request( Action::ReadContent, $scope, $owner_id, $owner_id, true, true, true, true );
	}

	/**
	 * Confirma sessão e concessões de novo em cada pedido.
	 *
	 * @param FileRecord                 $record Referência auditada.
	 * @param AuthorizationSnapshot|null $snapshot Autorizações correntes.
	 * @param int                        $user_id Actor corrente.
	 * @param array                      $session Metadados da sessão.
	 * @param int                        $now Relógio do servidor.
	 */
	public static function allows( FileRecord $record, ?AuthorizationSnapshot $snapshot, int $user_id, array $session, int $now ): bool {
		if ( ! LocalAccountSessionPolicy::valid( $snapshot, $user_id, $session, true ) ) {
			return false;
		}
		$request = self::request( $record, $user_id );
		return null !== $request && ( new AuthorizationPolicy() )->allows( $snapshot->toAccount( true ), $request, $snapshot->policyGrants(), $now );
	}
}
