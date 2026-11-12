<?php
/**
 * Validação pura de metadados institucionais contra leituras correntes.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity\Institutional;

use Arn\Intranet\Authorization\Account;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Identity\FederatedSessionPolicy;
use Arn\Intranet\Identity\LocalIdentityLink;

/** Não lê SQL, não emite cookies e não abre os consumidores locais. */
final class SessionPrincipalPolicy {

	/**
	 * Os argumentos correntes vêm de fontes do servidor, nunca do browser.
	 * A fonte deve fechar em falha SQL e não reutilizar snapshots antigos.
	 * Geração/revisão só podem ser fornecidas após migração e leitura válidas.
	 *
	 * @param Account|null           $account                Conta corrente e prova nativa.
	 * @param LocalIdentityLink|null $link                   Vínculo corrente.
	 * @param int                    $link_generation        Geração corrente persistida.
	 * @param int                    $authorization_revision Revisão corrente persistida.
	 * @param array<string, mixed>   $session                Metadados do token nativo.
	 * @param int                    $now                    Instante corrente do servidor.
	 * @param bool                   $enabled                Composição explicitamente válida.
	 */
	public static function resolve(
		?Account $account,
		?LocalIdentityLink $link,
		int $link_generation,
		int $authorization_revision,
		array $session,
		int $now,
		bool $enabled,
	): ?Principal {
		if ( ! $enabled || null === $account || null === $link ||
			AccountState::Active !== $account->state || ! $account->session_valid ||
			$account->user_id !== $link->user_id || $link_generation < 1 || $authorization_revision < 1 ||
			! FederatedSessionPolicy::valid( $link, $session, $now, true ) ) {
			return null;
		}
		$metadata = $session['arn_institutional'] ?? null;
		if ( ! is_array( $metadata ) ||
			1 !== ( $metadata['version'] ?? null ) ||
			( $metadata['user_id'] ?? null ) !== $account->user_id ||
			( $metadata['issuer'] ?? null ) !== $link->issuer ||
			( $metadata['subject'] ?? null ) !== $link->subject ||
			( $metadata['link_generation'] ?? null ) !== $link_generation ||
			( $metadata['authorization_revision'] ?? null ) !== $authorization_revision ) {
			return null;
		}
		return new Principal( $link->user_id, $link->issuer, $link->subject, $link_generation, $authorization_revision );
	}
}
