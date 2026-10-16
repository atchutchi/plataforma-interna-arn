<?php
/**
 * Resolve o par estável antes de considerar o primeiro vínculo seleccionado.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\PreRegistration;

use Arn\Intranet\Identity\{AuthorizedIdentityClaim, GoogleAccountPolicy, IdentityWpdb, LocalIdentityLink, OidcConfiguration, WpdbIdentityLinkRepository};
use Closure;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Contrato interno, erros constantes sem claims.
final class PreRegistrationLoginResolver {
	private readonly Closure $find;
	private readonly Closure $lookup;
	private readonly Closure $claim;
	private readonly Closure $enabled;
	private readonly ?Closure $conflict;
	public function __construct( callable $find, callable $lookup, callable $claim, callable $enabled, ?callable $conflict = null ) {
		$this->find     = Closure::fromCallable( $find );
		$this->lookup   = Closure::fromCallable( $lookup );
		$this->claim    = Closure::fromCallable( $claim );
		$this->enabled  = Closure::fromCallable( $enabled );
		$this->conflict = null === $conflict ? null : Closure::fromCallable( $conflict );
	}
	public static function forRepositories( WpdbIdentityLinkRepository $links, WpdbPreRegistrationRepository $registrations, callable $enabled, ?callable $conflict = null, ?callable $lookup = null ): self {
		return new self( array( $links, 'find' ), $lookup ?? array( $registrations, 'findForUser' ), static fn( array $claims, OidcConfiguration $config, int $now ): LocalIdentityLink=>$links->claimActive( static fn( IdentityWpdb $same ): AuthorizedIdentityClaim=>( new WpdbPreRegistrationRepository( $same ) )->claim( $claims, $config, $now ) ), $enabled, $conflict );
	}
	public function resolve( array $validated_claims, OidcConfiguration $configuration, int $now ): LocalIdentityLink {
		try {
			$issuer = $validated_claims['iss'] ?? null;
			$sub    = $validated_claims['sub'] ?? null;
			if ( ! is_string( $issuer ) || ! is_string( $sub ) || null === LocalIdentityLink::canonicalIssuer( $issuer ) || ! LocalIdentityLink::validSubject( $sub ) || true !== ( $validated_claims['email_verified'] ?? null ) || ( $validated_claims['hd'] ?? null ) !== $configuration->workspace_domain || $now < 1 ) {
				throw new RuntimeException();}
			$existing = ( $this->find )( $issuer, $sub );
			if ( null !== $existing ) {
				if ( ! $existing instanceof LocalIdentityLink || GoogleAccountPolicy::localUserId( $validated_claims, $configuration->workspace_domain, array( $existing ) ) !== $existing->user_id ) {
					throw new RuntimeException();}
				$record = ( $this->lookup )( $existing->user_id );
				if ( null !== $record && ( PreRegistrationState::Linked !== $record->state || true !== ( $this->enabled )() ) ) {
					throw new RuntimeException();}
				if ( null !== $this->conflict ) {
					( $this->conflict )( $existing, $validated_claims, $now );}
				return $existing;
			}
			if ( true !== ( $this->enabled )() ) {
				throw new RuntimeException();}
			$link = ( $this->claim )( $validated_claims, $configuration, $now );
			if ( ! $link instanceof LocalIdentityLink || GoogleAccountPolicy::localUserId( $validated_claims, $configuration->workspace_domain, array( $link ) ) !== $link->user_id ) {
				throw new RuntimeException();}
			$record = ( $this->lookup )( $link->user_id );
			if ( null === $record || PreRegistrationState::Linked !== $record->state || true !== ( $this->enabled )() ) {
				throw new RuntimeException();}
			return $link;
		} catch ( Throwable ) {
			throw new RuntimeException( 'Entrada Workspace não autorizada.' );}
	}
}
