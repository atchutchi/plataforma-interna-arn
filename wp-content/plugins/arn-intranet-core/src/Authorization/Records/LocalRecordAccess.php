<?php
/** Leitura dedicada dos registos através da conta corrente.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Records;

use Closure;
use Throwable;
/** Preserva a composição interna dos registos. */
final class LocalRecordAccess {
	/** Dependência interna fixada.
	 *
	 * @var CurrentLocalAccount
	 */
	private readonly CurrentLocalAccount $current;
	/** Recebe dependências internas e fixa a fronteira.
	 *
	 * @param Closure|null $repository_factory Fábrica interna da persistência.
	 *
	 * @param Closure|null $principal Titular resolvido no servidor.
	 */
	public function __construct( ?Closure $repository_factory = null, ?Closure $principal = null ) {
		$this->current = new CurrentLocalAccount( $repository_factory, $principal );
	}
	/** Revalida o gate efectivo sem consultar SQL.
	 */
	public function allowed(): bool {
		return $this->current->allowed();
	}
	/** Resolve registos através da prova de sessão corrente.
	 */
	public function read(): ?array {
		try {
			$current = $this->current->read();
			return null === $current ? null : LocalRecordSelection::visible( $current[0], $current[1], $current[2], time() );
		} catch ( Throwable ) {
			return null;
		}
	}
}
