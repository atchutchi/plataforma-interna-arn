<?php
/** Selecção pura antes de devolver conteúdo ou contagens.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Records;

use Arn\Intranet\Authorization\AuthorizationPolicy;
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;

/** Lista e detalhe partilham a mesma decisão por objecto. */
final class LocalRecordSelection {
	/** Devolve exclusivamente os objectos permitidos.
	 *
	 * @param AuthorizationSnapshot|null $snapshot Snapshot corrente.
	 * @param int                        $user_id Actor corrente.
	 * @param array                      $session Metadados nativos.
	 * @param int                        $now Relógio do servidor.
	 * @return array
	 */
	public static function visible( ?AuthorizationSnapshot $snapshot, int $user_id, array $session, int $now ): array {
		$valid = LocalAccountSessionPolicy::valid( $snapshot, $user_id, $session, true );
		if ( ! $valid ) {
			return array();
		}
		$policy = new AuthorizationPolicy();
		$rows   = array();
		foreach ( LocalRecordCatalog::records( $user_id ) as $key => $record ) {
			if ( $policy->allows( $snapshot->toAccount( $valid ), $record['request'], $snapshot->policyGrants(), $now ) ) {
				$rows[ $key ] = array(
					'title'   => $record['title'],
					'content' => $record['content'],
				);
			}
		}
		return $rows;
	}
	/** Chave exacta sem normalização ou enumeração de recusados.
	 *
	 * @param array $rows Linhas já autorizadas.
	 * @param mixed $key Chave recebida.
	 * @return array|null
	 */
	public static function detail( array $rows, mixed $key ): ?array {
		return is_string( $key ) ? ( $rows[ $key ] ?? null ) : null;
	}
}
