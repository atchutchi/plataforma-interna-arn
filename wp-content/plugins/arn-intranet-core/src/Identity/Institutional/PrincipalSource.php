<?php
/** Fonte corrente de identidade e autorização, sem cache entre decisões.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\Institutional;

use Arn\Intranet\Identity\IdentityLinkRepository;
use Arn\Intranet\Authorization\Storage\AuthorizationRepository;
use Arn\Intranet\Authorization\AccountState;
use Closure;
use Throwable;
use WP_Session_Tokens;

// phpcs:disable Squiz.Commenting.FunctionComment
/** Dependências definidas na composição do servidor, nunca num pedido HTTP. */
final class PrincipalSource {
	/** Leitura nativa validada.
	 *
	 * @var Closure
	 */
	private readonly Closure $session_reader;
	public function __construct(
		private readonly IdentityLinkRepository $links,
		private readonly AuthorizationRepository $authorization,
		private readonly Closure $enabled,
		private readonly Closure $clock,
		?Closure $session_reader = null,
	) {
		$this->session_reader = $session_reader ?? static function ( int $user_id, string $token ): ?array {
			$manager = WP_Session_Tokens::get_instance( $user_id );
			return $manager->verify( $token ) ? $manager->get( $token ) : null;
		};
	}
	/** Leituras sem autorização de sessão, apenas para preparar emissão. */
	public function state( int $user_id ): ?array {
		try {
			if ( $user_id < 1 || true !== ( $this->enabled )() ) {
				return null; }
			$link     = $this->links->findForUser( $user_id );
			$snapshot = $this->authorization->find( $user_id );
			if ( null === $link || null === $snapshot || $link->user_id !== $user_id || $snapshot->user_id !== $user_id || ! $link->active || ! $link->authorized || $link->generation < 1 || AccountState::Active !== $snapshot->state ) {
				return null; }
			return array( $link, $snapshot );
		} catch ( Throwable ) {
			return null; }
	}
	/** Revalida depois da leitura do token, sem reutilizar a decisão anterior. */
	public function read( int $user_id, string $token ): ?array {
		try {
			$before = '' === $token ? null : $this->state( $user_id );
			if ( null === $before ) {
				return null; }
			$session = ( $this->session_reader )( $user_id, $token );
			$current = $this->state( $user_id );
			if ( ! is_array( $session ) || null === $current || $before[0]->generation !== $current[0]->generation || $before[0]->issuer !== $current[0]->issuer || $before[0]->subject !== $current[0]->subject || $before[1]->revision !== $current[1]->revision ) {
				return null; }
			[$link, $snapshot] = $current;
			$principal         = SessionPrincipalPolicy::resolve( $snapshot->toAccount( true ), $link, $link->generation, $snapshot->revision, $session, ( $this->clock )(), true );
			return null === $principal ? null : array( $principal, $snapshot, $session );
		} catch ( Throwable ) {
			return null; }
	}
	/** Não aceita o identificador ou o token através de parâmetros do browser. */
	public function current(): ?array {
		return $this->read( get_current_user_id(), wp_get_session_token() );
	}
}
