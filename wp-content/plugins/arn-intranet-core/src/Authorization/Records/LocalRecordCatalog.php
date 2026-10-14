<?php
/** Catálogo sintético fechado do laboratório.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Records;

use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\Request;
use Arn\Intranet\Authorization\Scope;

/** Todos os metadados de autorização são calculados no servidor. */
final class LocalRecordCatalog {
	/** Três objectos fixos sem dados institucionais.
	 *
	 * @param int $owner_id Titular resolvido pelo servidor.
	 * @return array
	 */
	public static function records( int $owner_id ): array {
		return array(
			'proprio'   => array(
				'title'   => 'Registo fictício próprio',
				'content' => 'Conteúdo sintético do registo próprio para ensaiar a leitura autorizada.',
				'request' => new Request( Action::ReadDirectory, Scope::own(), $owner_id, 0, true, true, true, true ),
			),
			'unidade-a' => array(
				'title'   => 'Registo fictício da unidade A',
				'content' => 'Conteúdo sintético da unidade fictícia A para ensaiar a leitura autorizada.',
				'request' => new Request( Action::ReadDirectory, Scope::units( array( 'unidade-ficticia-a' ) ), 0, 0, true, true, true, true ),
			),
			'unidade-b' => array(
				'title'   => 'Registo fictício da unidade B',
				'content' => 'Conteúdo sintético da unidade fictícia B para ensaiar a leitura autorizada.',
				'request' => new Request( Action::ReadDirectory, Scope::units( array( 'unidade-ficticia-b' ) ), 0, 0, true, true, true, true ),
			),
		);
	}
}
