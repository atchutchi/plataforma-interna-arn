<?php
/**
 * DML auditado de identidades pré-cadastradas.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\PreRegistration;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Contrato interno tipado, SQL preparado e erros constantes.
/** A autoridade CLI é aplicada antes de construir esta fronteira interna. */
final class WpdbPreRegistrationRepository {
	public const MARKER = '_arn_workspace_pre_registration';
	private readonly PreRegistrationSchema $schema;

	public function __construct( private readonly IdentityWpdb $database ) {
		$this->schema = new PreRegistrationSchema( $database ); }
	public function installSchema(): void {
		$this->silently( fn() => $this->schema->install() ); }
	public function hasSchema(): bool {
		return $this->silently( fn() => $this->schema->hasSchema() ); }
	public function schema(): PreRegistrationSchema {
		return $this->schema; }

	public function findForUser( int $user_id ): ?PreRegistration {
		if ( $user_id < 1 ) {
			throw new RuntimeException( 'Pré-cadastro indisponível.' ); }
		return $this->silently(
			function () use ( $user_id ): ?PreRegistration {
				$this->schema->assertReady();
				return $this->record( $this->database->prepare( 'SELECT * FROM %i WHERE user_id=%d', $this->schema->table( 'accounts' ), $user_id ) );
			}
		);
	}

	public function findForEmail( string $email ): ?PreRegistration {
		return $this->silently(
			function () use ( $email ): ?PreRegistration {
				$this->schema->assertReady();
				return $this->record( $this->database->prepare( 'SELECT * FROM %i WHERE primary_email=%s', $this->schema->table( 'accounts' ), $email ) );
			}
		);
	}

	public function provision( WorkspaceEntry $entry, WorkspaceBatch $batch, int $actor_id, int $now ): PreRegistration {
		return $this->silently(
			function () use ( $entry, $batch, $actor_id, $now ): PreRegistration {
				$this->schema->assertReady();
				if ( $actor_id < 1 || $now < $batch->observed_at || 1 !== count( array_filter( $batch->entries, static fn( WorkspaceEntry $candidate ): bool => $candidate->primary_email === $entry->primary_email && $candidate->display_name === $entry->display_name ) ) ) {
					throw new RuntimeException(); }
				$lock = 'arn_workspace_' . substr( hash( 'sha256', DB_NAME . ':' . $this->database->prefix ), 0, 40 );
				if ( '1' !== (string) $this->database->get_var( $this->database->prepare( 'SELECT GET_LOCK(%s,5)', $lock ) ) ) {
					throw new RuntimeException(); }
				$id    = null;
				$login = null;
				try {
					$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
					$this->execute( 'START TRANSACTION' );
					try {
						$previous = $this->record( $this->database->prepare( 'SELECT * FROM %i WHERE primary_email=%s FOR UPDATE', $this->schema->table( 'accounts' ), $entry->primary_email ) );
						if ( null !== $previous ) {
							if ( $previous->display_name !== $entry->display_name || $previous->source_sha256 !== $batch->source_sha256 || $previous->inventory_sha256 !== $batch->inventory_sha256 || $previous->observed_at !== $batch->observed_at || $previous->expires_at !== $batch->expires_at ) {
								throw new RuntimeException(); }
							$this->execute( 'COMMIT' );
							return $previous;
						}
						if ( $now >= $batch->expires_at || $this->rows( $this->database->prepare( 'SELECT ID FROM %i WHERE user_email=%s FOR UPDATE', $this->schema->nativeTable( 'users' ), $entry->primary_email ) ) ) {
							throw new RuntimeException(); }
						$registration_id = bin2hex( random_bytes( 16 ) );
						$login           = 'arn.pre.' . bin2hex( random_bytes( 12 ) );
						$secret          = bin2hex( random_bytes( 24 ) );
						$hash            = wp_hash_password( $secret );
						if ( ! is_string( $hash ) || ! wp_check_password( $secret, $hash ) ) {
							throw new RuntimeException();
						} unset( $secret );
						$user = array(
							'user_login'          => $login,
							'user_pass'           => $hash,
							'user_nicename'       => $login,
							'user_email'          => $entry->primary_email,
							'user_url'            => '',
							'user_registered'     => gmdate( 'Y-m-d H:i:s', $now ),
							'user_activation_key' => '',
							'user_status'         => 0,
							'display_name'        => $entry->display_name,
						);
						$this->insert( $this->schema->nativeTable( 'users' ), $user );
						$id   = PreRegistration::positive( $this->database->insert_id );
						$meta = array(
							$this->database->prefix . 'capabilities' => 'a:0:{}',
							$this->database->prefix . 'user_level' => '0',
							'locale'     => 'pt_PT',
							self::MARKER => $registration_id,
						);
						foreach ( $meta as $key => $value ) {
							$this->insert(
								$this->schema->nativeTable( 'usermeta' ),
								array(
									'user_id'    => $id,
									'meta_key'   => $key,
									'meta_value' => $value,
								)
							); }
						$row = array(
							'id'               => $registration_id,
							'user_id'          => $id,
							'display_name'     => $entry->display_name,
							'primary_email'    => $entry->primary_email,
							'state'            => 'AwaitingGoogle',
							'revision'         => 1,
							'authorized_by'    => $actor_id,
							'source_sha256'    => $batch->source_sha256,
							'inventory_sha256' => $batch->inventory_sha256,
							'observed_at'      => $batch->observed_at,
							'expires_at'       => $batch->expires_at,
							'disabled_from'    => null,
						);
						$this->insert( $this->schema->table( 'accounts' ), $row );
						$record = PreRegistration::fromRow( $row );
						$this->audit( $record, $actor_id, 'created', null, 'Pré-cadastro local autorizado', $now );
						$this->assertNative( $record );
						$this->execute( 'COMMIT' );
						return $record;
					} catch ( Throwable ) {
						$this->database->query( 'ROLLBACK' );
						throw new RuntimeException(); }
				} finally {
					$this->database->query( $this->database->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
					if ( null !== $id ) {
						$this->clearCaches( $id, $login, $entry->primary_email ); }
				}
			}
		);
	}

	public function assertNative( PreRegistration $record ): void {
		$users = $this->rows( $this->database->prepare( 'SELECT ID,user_login,user_email,display_name,user_activation_key FROM %i WHERE ID=%d OR user_email=%s', $this->schema->nativeTable( 'users' ), $record->user_id, $record->primary_email ) );
		if ( 1 !== count( $users ) || PreRegistration::positive( $users[0]['ID'] ) !== $record->user_id || $users[0]['user_email'] !== $record->primary_email || $users[0]['display_name'] !== $record->display_name || '' !== $users[0]['user_activation_key'] ) {
			throw new RuntimeException();}
		$meta   = $this->rows( $this->database->prepare( 'SELECT meta_key,meta_value FROM %i WHERE user_id=%d', $this->schema->nativeTable( 'usermeta' ), $record->user_id ) );
		$values = array();
		foreach ( $meta as$row ) {
			if ( isset( $values[ $row['meta_key'] ] ) ) {
				throw new RuntimeException();
			}$values[ $row['meta_key'] ] = $row['meta_value'];}
		if ( ( $values[ self::MARKER ] ?? null ) !== $record->id || ( $values[ $this->database->prefix . 'capabilities' ] ?? null ) !== 'a:0:{}' || ( $values[ $this->database->prefix . 'user_level' ] ?? null ) !== '0' || isset( $values['_application_passwords'] ) ) {
			throw new RuntimeException();}
	}

	private function audit( PreRegistration $record, int $actor_id, string $action, ?PreRegistrationState $previous, string $reason, int $now ): void {
		if ( $actor_id < 1 || $now < 1 || ! in_array( $action, array( 'created', 'linked', 'disabled', 'enabled', 'renewed' ), true ) || '' === $reason || mb_strlen( $reason, 'UTF-8' ) > 500 || ! mb_check_encoding( $reason, 'UTF-8' ) ) {
			throw new RuntimeException();}
		$this->insert(
			$this->schema->table( 'audit' ),
			array(
				'registration_id'  => $record->id,
				'actor_id'         => $actor_id,
				'action'           => $action,
				'revision'         => $record->revision,
				'previous_state'   => $previous?->value,
				'new_state'        => $record->state->value,
				'source_sha256'    => $record->source_sha256,
				'inventory_sha256' => $record->inventory_sha256,
				'reason'           => $reason,
				'occurred_at'      => $now,
			)
		);
	}

	private function record( string $sql ): ?PreRegistration {
		$rows = $this->rows( $sql );
		if ( count( $rows ) > 1 ) {
			throw new RuntimeException();
		} if ( ! $rows ) {
			return null;
		} $record = PreRegistration::fromRow( $rows[0] );
		$this->assertNative( $record );
		return $record;
	}
	private function clearCaches( int $id, string $login, string $email ): void {
		foreach ( array( array( $id, 'users' ), array( $id, 'user_meta' ), array( $login, 'userlogins' ), array( $login, 'userslugs' ), array( $email, 'useremail' ) ) as[$key, $group] ) {
			wp_cache_delete( $key, $group );} }
	private function insert( string $table, array $data ): void {
		if ( 1 !== $this->database->insert( $table, $data ) || '' !== $this->database->last_error ) {
			throw new RuntimeException();}}
	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException();}}
	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException();
		}return $rows;}
	private function silently( callable $operation ): mixed {
		$previous = $this->database->suppress_errors( true );
		try {
			return $operation();
		} catch ( Throwable ) {
			throw new RuntimeException( 'Pré-cadastro indisponível.' );
		} finally {
			$this->database->suppress_errors( $previous );}}
}
