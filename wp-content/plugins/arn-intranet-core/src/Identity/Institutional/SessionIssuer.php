<?php
/** Emissão nativa após prova OIDC e vínculo persistente revalidado.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\Institutional;

use Arn\Intranet\Identity\LocalIdentityLink;
use Arn\Intranet\Identity\FederatedSessionPolicy;
use RuntimeException;
use Throwable;
use WP_Session_Tokens;
// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** Sem registo de hooks ou activação automática no controlador local. */
final class SessionIssuer {
	public function __construct( private readonly PrincipalSource $source ) {}
	/** O vínculo esperado provém exclusivamente da decisão OIDC do servidor.
	 *
	 * @throws RuntimeException Quando a sessão não é confirmada.
	 */
	public function issue( LocalIdentityLink $expected ): string {
		$state = $this->source->state( $expected->user_id );
		if ( ! is_ssl() || null === $state || false === get_userdata( $expected->user_id ) || ! $expected->active || ! $expected->authorized || $expected->generation < 1 || $state[0]->generation !== $expected->generation || $state[0]->issuer !== $expected->issuer || $state[0]->subject !== $expected->subject ) {
			throw new RuntimeException( 'Sessão institucional recusada.' );
		}
		$now     = time();
		$manager = WP_Session_Tokens::get_instance( $expected->user_id );
		$token   = $manager->create( $now + FederatedSessionPolicy::LIFETIME );
		try {
			$metadata = $manager->get( $token );
			if ( ! is_array( $metadata ) ) {
				throw new RuntimeException(); }
			$metadata['arn_oidc']           = true;
			$metadata['arn_oidc_issued_at'] = $now;
			$metadata['arn_oidc_last_seen'] = $now;
			$metadata['arn_institutional']  = array(
				'version'                => 1,
				'user_id'                => $expected->user_id,
				'issuer'                 => $expected->issuer,
				'subject'                => $expected->subject,
				'link_generation'        => $expected->generation,
				'authorization_revision' => $state[1]->revision,
			);
			$manager->update( $token, $metadata );
			$current = $this->source->read( $expected->user_id, $token );
			if ( null === $current || $current[0]->link_generation !== $expected->generation || $current[0]->authorization_revision !== $state[1]->revision || $current[2] !== $metadata ) {
				throw new RuntimeException(); }
			$expiration = static fn(): int => FederatedSessionPolicy::LIFETIME;
			add_filter( 'auth_cookie_expiration', $expiration );
			try {
				wp_set_auth_cookie( $expected->user_id, false, true, $token );
			} finally {
				remove_filter( 'auth_cookie_expiration', $expiration ); }
			return $token;
		} catch ( Throwable ) {
			$manager->destroy( $token );
			throw new RuntimeException( 'Sessão institucional não confirmada.' );
		}
	}
}
