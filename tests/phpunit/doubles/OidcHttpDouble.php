<?php
/**
 * Transporte controlado para ensaiar o protocolo real sem contas Google.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Closure;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Substitui apenas a rede, mantendo intacta a validação JOSE. */
final class OidcHttpDouble implements ClientInterface {

	/**
	 * Pedidos efectivamente emitidos pela biblioteca.
	 *
	 * @var array<RequestInterface>
	 */
	public array $requests = array();

	/**
	 * Recebe a resposta controlada para o endpoint solicitado.
	 *
	 * @param Closure $respond Resposta controlada.
	 */
	public function __construct( private Closure $respond ) {}

	/**
	 * Regista o pedido real e devolve a resposta da fixture.
	 *
	 * @param RequestInterface $request Pedido emitido pela biblioteca.
	 */
	public function sendRequest( RequestInterface $request ): ResponseInterface {
		$this->requests[] = $request;
		return ( $this->respond )( $request );
	}
}
