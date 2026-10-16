<?php
/**
 * Selecção fechada e validade temporal da origem.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity\PreRegistration;

use Arn\Intranet\Identity\OidcConfiguration;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Erro constante de domínio, sem payload.
/**
 * O lote validado não é uma ordem de criação ou de concessão.
 */
final readonly class WorkspaceBatch {
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param WorkspaceEntry[] $entries Entradas seleccionadas.
	 * @param string           $source_sha256 Hash da origem.
	 * @param string           $inventory_sha256 Hash da selecção.
	 * @param int              $observed_at Observação UTC.
	 * @param int              $expires_at Limite exclusivo.
	 */
	private function __construct( public array $entries, public string $source_sha256, public string $inventory_sha256, public int $observed_at, public int $expires_at ) {}
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param array  $inventory Selecção privada.
	 * @param array  $source Exportação privada.
	 * @param string $source_hash Hash já verificado dos bytes da origem.
	 * @param string $inventory_hash Hash já verificado da selecção.
	 * @param string $domain Domínio configurado.
	 * @param int    $expected_count População autorizada.
	 * @param int    $now Relógio UTC.
	 * @return self Lote imutável.
	 * @throws \RuntimeException Quando a validação ou a leitura protegida falha.
	 */
	public static function validate( array $inventory, array $source, string $source_hash, string $inventory_hash, string $domain, int $expected_count, int $now ): self {
		try {
			if ( ! OidcConfiguration::validDomain( $domain ) || $expected_count < 1 || $expected_count > 10000 || $now < 1 || 1 !== preg_match( '/\A[a-f0-9]{64}\z/D', $source_hash ) || 1 !== preg_match( '/\A[a-f0-9]{64}\z/D', $inventory_hash ) || ( $inventory['source_sha256'] ?? null ) !== $source_hash ) {
				throw new RuntimeException();
			}
			$observed = self::observation( $inventory['observed_at_utc'] ?? null );
			if ( $observed > $now ) {
				throw new RuntimeException(); }
			$rows = $inventory['users'] ?? null;
			$raw  = $source['users'] ?? null;
			if ( ! is_array( $rows ) || ! array_is_list( $rows ) || ! is_array( $raw ) || ! array_is_list( $raw ) || count( $rows ) !== $expected_count + 4 || count( $rows ) !== count( $raw ) || ( $inventory['count'] ?? null ) !== count( $rows ) ) {
				throw new RuntimeException(); }
			$indexed = array();
			foreach ( $raw as $row ) {
				$email = $row['Email Address [Required]'] ?? null;
				if ( ! is_string( $email ) || isset( $indexed[ $email ] ) ) {
					throw new RuntimeException(); }
				$indexed[ $email ] = $row;
			}
			$entries  = array();
			$seen     = array();
			$excluded = 0;
			foreach ( $rows as $row ) {
				$name  = $row['display_name'] ?? null;
				$email = $row['primary_email'] ?? null;
				if ( ! is_string( $name ) || ! is_string( $email ) || isset( $seen[ $email ] ) || ! str_ends_with( $email, '@' . $domain ) ) {
					throw new RuntimeException(); }
				$entry = new WorkspaceEntry( $name, $email );
				$exact = $indexed[ $email ] ?? null;
				if ( ! is_array( $exact ) || ! is_string( $exact['First Name [Required]'] ?? null ) || ! is_string( $exact['Last Name [Required]'] ?? null ) || trim( $exact['First Name [Required]'] . ' ' . $exact['Last Name [Required]'] ) !== $name || ( $exact['Status [READ ONLY]'] ?? null ) !== 'Active' || ( $row['workspace_status'] ?? null ) !== 'Active' || ( $row['application_account'] ?? null ) !== 'not_provisioned_by_this_inventory' ) {
					throw new RuntimeException(); }
				$seen[ $email ] = true;
				if ( ( $row['classification'] ?? null ) === 'generic_pending_review' ) {
					++$excluded;
					continue; }
				if ( ( $row['classification'] ?? null ) !== 'nominal_selected_for_pre_registration' ) {
					throw new RuntimeException(); }
				$entries[] = $entry;
			}
			if ( count( $entries ) !== $expected_count || 4 !== $excluded ) {
				throw new RuntimeException(); }
			return new self( $entries, $source_hash, $inventory_hash, $observed, $observed + 86400 );
		} catch ( Throwable ) {
			throw new RuntimeException( 'Lote Workspace inválido.' );
		}
	}
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param mixed $value Instante observado, apenas UTC.
	 * @return int Segundos UTC arredondados para baixo.
	 * @throws \RuntimeException Quando a validação ou a leitura protegida falha.
	 */
	private static function observation( mixed $value ): int {
		if ( ! is_string( $value ) || 1 !== preg_match( '/\A(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.\d{1,9})?(?:Z|\+00:00)\z/D', $value, $matches ) ) {
			throw new RuntimeException(); }
		$date   = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s\Z', $matches[1] . 'Z', new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();
		if ( false === $date || ( false !== $errors && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) || $date->format( 'Y-m-d\TH:i:s' ) !== $matches[1] || $date->getTimestamp() < 1 || $date->getTimestamp() > PHP_INT_MAX - 86400 ) {
			throw new RuntimeException(); }
		return $date->getTimestamp();
	}
}
