<?php
/** Callback institucional após consumo único da tentativa privada.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\Institutional;

use Arn\Intranet\Identity\{IdentityLinkRepository, LocalIdentityLink, MaintainedGoogleClient, OidcConfiguration, GoogleAccountPolicy};
use RuntimeException;
use Throwable;
// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** Nunca associa por email, activa contas ou concede competências. */
final class CallbackLogin {
	public function __construct( private readonly MaintainedGoogleClient $client, private readonly IdentityLinkRepository $links, private readonly PrincipalSource $source ) {}
	/** O chamador consome state/nonce/PKCE no armazenamento privado antes desta chamada.
	 *
	 * @throws RuntimeException Quando a entrada e recusada.
	 */
	public function complete( OidcConfiguration $config, array $params, array $context ): string {
		try {
			if ( ! is_ssl() || ! str_starts_with( $config->redirect_uri, 'https://' ) || 'login' !== ( $context['purpose'] ?? null ) || 0 !== ( $context['target_user_id'] ?? null ) || 0 !== ( $context['actor_id'] ?? null ) ) {
				throw new RuntimeException();
			}
			$claims = $this->client->complete( $config, $params, $context );
			$link   = $this->resolveValidated( $claims, $config );
			if ( null !== \Arn\Intranet\Identity\PreRegistration\CurrentPreRegistration::lookup( $link->user_id ) ) {
				throw new RuntimeException(); }
			return ( new SessionIssuer( $this->source ) )->issue( $link );
		} catch ( Throwable ) {
			throw new RuntimeException( 'Entrada institucional recusada.' );
		}
	}
	/** Apenas claims já validadas pelo cliente mantido. Não emite sessão.
	 *
	 * @throws RuntimeException Quando falta um vinculo valido.
	 */
	public function resolveValidated( array $claims, OidcConfiguration $config ): LocalIdentityLink {
		try {
			$issuer  = is_string( $claims['iss'] ?? null ) ? LocalIdentityLink::canonicalIssuer( $claims['iss'] ) : null;
			$subject = $claims['sub'] ?? null;
			if ( null === $issuer || ! is_string( $subject ) || ! LocalIdentityLink::validSubject( $subject ) ) {
				throw new RuntimeException(); }
			$link = $this->links->find( $issuer, $subject );
			if ( null === $link || 1 > $link->generation || GoogleAccountPolicy::localUserId( $claims, $config->workspace_domain, array( $link ) ) !== $link->user_id ) {
				throw new RuntimeException(); }
			return $link;
		} catch ( Throwable ) {
			throw new RuntimeException( 'Entrada institucional recusada.' );
		}
	}
}
