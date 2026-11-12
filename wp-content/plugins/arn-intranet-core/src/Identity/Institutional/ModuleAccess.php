<?php
/** Ligação explícita entre a sessão institucional e o motor dos módulos.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\Institutional;

use Arn\Intranet\Authorization\AuthorizationPolicy;
use Arn\Intranet\Authorization\Request;
use Closure;
// phpcs:disable Squiz.Commenting.FunctionComment
/** Não transforma autenticação em concessão e não tem excepção de administrador. */
final class ModuleAccess {
	public function __construct( private readonly PrincipalSource $source, private readonly Closure $clock ) {}
	/** O módulo constrói o pedido com dados correntes do objecto no servidor. */
	public function allows( Request $request ): bool {
		$current = $this->source->current();
		return null !== $current && ( new AuthorizationPolicy() )->allows( $current[1]->toAccount( true ), $request, $current[1]->policyGrants(), ( $this->clock )() );
	}
}
