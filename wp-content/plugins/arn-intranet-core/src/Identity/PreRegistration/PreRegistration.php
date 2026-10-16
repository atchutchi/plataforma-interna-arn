<?php
/**
 * Snapshot imutável do cadastro de identidade.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\PreRegistration;

use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Campos do snapshot estão especificados no plano e tipados neste valor, com erro constante.
/** Não representa estado funcional ou atribuição de permissões. */
final readonly class PreRegistration {
	private function __construct( public string $id, public int $user_id, public string $display_name, public string $primary_email, public PreRegistrationState $state, public int $revision, public int $authorized_by, public string $source_sha256, public string $inventory_sha256, public int $observed_at, public int $expires_at, public ?PreRegistrationState $disabled_from ) {}

	public static function fromRow( array $row ): self {
		try {
			foreach ( array( 'id', 'display_name', 'primary_email', 'state', 'source_sha256', 'inventory_sha256' ) as $key ) {
				if ( ! is_string( $row[ $key ] ?? null ) ) {
					throw new RuntimeException(); }
			}
			foreach ( array( 'user_id', 'revision', 'authorized_by', 'observed_at', 'expires_at' ) as $key ) {
				$row[ $key ] = self::positive( $row[ $key ] ?? null ); }
			if ( ! array_key_exists( 'disabled_from', $row ) || 1 !== preg_match( '/\A[a-f0-9]{32}\z/D', $row['id'] ) || 1 !== preg_match( '/\A[a-f0-9]{64}\z/D', $row['source_sha256'] ) || 1 !== preg_match( '/\A[a-f0-9]{64}\z/D', $row['inventory_sha256'] ) || $row['observed_at'] > PHP_INT_MAX - 86400 || $row['expires_at'] !== $row['observed_at'] + 86400 ) {
				throw new RuntimeException(); }
			new WorkspaceEntry( $row['display_name'], $row['primary_email'] );
			$state    = PreRegistrationState::from( $row['state'] );
			$previous = null === $row['disabled_from'] ? null : PreRegistrationState::from( $row['disabled_from'] );
			if ( ( PreRegistrationState::Disabled === $state && ! in_array( $previous, array( PreRegistrationState::AwaitingGoogle, PreRegistrationState::Linked ), true ) ) || ( PreRegistrationState::Disabled !== $state && null !== $previous ) ) {
				throw new RuntimeException(); }
			return new self( $row['id'], $row['user_id'], $row['display_name'], $row['primary_email'], $state, $row['revision'], $row['authorized_by'], $row['source_sha256'], $row['inventory_sha256'], $row['observed_at'], $row['expires_at'], $previous );
		} catch ( Throwable ) {
			throw new RuntimeException( 'Pré-cadastro indisponível.' ); }
	}

	public static function positive( mixed $value ): int {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			throw new RuntimeException( 'Pré-cadastro indisponível.' ); }
		$integer = filter_var(
			$value,
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'min_range' => 1,
					'max_range' => PHP_INT_MAX,
				),
			)
		);
		if ( false === $integer || (string) $integer !== (string) $value ) {
			throw new RuntimeException( 'Pré-cadastro indisponível.' ); }
		return $integer;
	}
}
