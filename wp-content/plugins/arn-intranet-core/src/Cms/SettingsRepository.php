<?php
/** Settings and immutable before/after audit on one dedicated connection.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting -- Contract and locking order are described in the F1-15 plan.
// phpcs:disable WordPress.DB, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
/** No DDL in requests. Account/session locks precede the singleton settings lock. */
final class SettingsRepository {
	private readonly SettingsSchema $schema;

	public function __construct( private readonly IdentityWpdb $database, private readonly SettingsAuthority $authority, private readonly Closure $clock ) {
		if ( $authority->connection() !== $database ) {
			throw new InvalidArgumentException( 'Autoridade e parâmetros exigem a mesma ligação.' );
		}
		$this->schema = new SettingsSchema( $database );
	}

	/** Explicit seed of fixed defaults, never called from reads or HTTP. */
	public function initialize(): void {
		if ( ! Configuration::current() || ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) ) {
			throw new DomainException( 'Preparação de parâmetros recusada.' );
		}
		$this->begin();
		try {
			$rows = $this->rows( $this->database->prepare( 'SELECT id FROM %i FOR UPDATE', $this->schema->table( 'state' ) ) );
			if ( array() === $rows ) {
				if ( $this->rows( $this->database->prepare( 'SELECT revision FROM %i LIMIT 1', $this->schema->table( 'audit' ) ) ) ) {
					throw new RuntimeException( 'Estado inicial incompatível.' );
				}
				$state = SettingsState::initial();
				$json  = SettingsReader::json( $state );
				$this->write(
					$this->database->insert(
						$this->schema->table( 'state' ),
						array(
							'id'       => 1,
							'revision' => 1,
							'payload'  => $json,
							'digest'   => hash( 'sha256', $json ),
						),
						array( '%d', '%d', '%s', '%s' )
					)
				);
			}
			$this->load();
			$this->execute( 'COMMIT' );
		} catch ( Throwable $error ) {
			$this->database->query( 'ROLLBACK' );
			throw new RuntimeException( 'Preparação de parâmetros indisponível.', 0, $error );
		}
	}

	public function read(): SettingsState {
		return $this->transaction( fn(): SettingsState => $this->load() );
	}

	public function change( UploadPolicy $policy, int $revision, string $reason ): SettingsState {
		return $this->transaction(
			function ( SettingsActor $actor, int $now ) use ( $policy, $revision, $reason ): SettingsState {
				$before = $this->load();
				$after  = $before->change( $policy, $revision, $actor->id(), $now, $reason );
				if ( $after === $before ) {
					return $before;
				}
				$before_json = SettingsReader::json( $before );
				$after_json  = SettingsReader::json( $after );
				$this->write(
					$this->database->update(
						$this->schema->table( 'state' ),
						array(
							'revision' => $after->revision,
							'payload'  => $after_json,
							'digest'   => hash( 'sha256', $after_json ),
						),
						array(
							'id'       => 1,
							'revision' => $before->revision,
						),
						array( '%d', '%s', '%s' ),
						array( '%d', '%d' )
					)
				);
				$this->write(
					$this->database->insert(
						$this->schema->table( 'audit' ),
						array(
							'revision'       => $after->revision,
							'actor_id'       => $after->actor_id,
							'occurred_at'    => $after->occurred_at,
							'before_payload' => $before_json,
							'after_payload'  => $after_json,
							'before_hash'    => hash( 'sha256', $before_json ),
							'after_hash'     => hash( 'sha256', $after_json ),
						),
						array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
					)
				);
				return $after;
			}
		);
	}

	/** Exclusive cursor and bounded page, available only to current managers. */
	public function history( int $before_revision = PHP_INT_MAX, int $limit = 20 ): array {
		if ( $before_revision < 1 || $limit < 1 || $limit > 50 ) {
			throw new InvalidArgumentException( 'Página de histórico inválida.' );
		}
		return $this->transaction(
			function () use ( $before_revision, $limit ): array {
				$this->load();
				$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE revision < %d ORDER BY revision DESC LIMIT %d', $this->schema->table( 'audit' ), $before_revision, $limit ) );
				return array_map( fn( object $row ): array => ( new SettingsReader( $this->database ) )->event( $row ), $rows );
			}
		);
	}

	private function transaction( Closure $operation ): mixed {
		$this->begin();
		try {
			$actor = $this->authority->locked();
			$now   = $this->now();
			if ( ! $actor->allows( $now ) ) {
				throw new DomainException( 'Gestão de parâmetros indisponível.' );
			}
			$result = $operation( $actor, $now );
			if ( ! $actor->allows( $this->now() ) ) {
				throw new DomainException( 'A sessão ou concessão expirou.' );
			}
			$this->execute( 'COMMIT' );
			return $result;
		} catch ( Throwable $error ) {
			$this->database->query( 'ROLLBACK' );
			if ( $error instanceof DomainException || $error instanceof InvalidArgumentException ) {
				throw $error;
			}
			throw new RuntimeException( 'Parâmetros indisponíveis.', 0, $error );
		}
	}

	private function begin(): void {
		if ( ! Configuration::current() || $this->database->inTransaction() ) {
			throw new RuntimeException( 'Ligação de parâmetros indisponível.' );
		}
		$this->schema->assertReady();
		$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
		$this->execute( 'START TRANSACTION' );
	}

	private function load(): SettingsState {
		return ( new SettingsReader( $this->database ) )->locked();
	}

	private function now(): int {
		$now = ( $this->clock )();
		if ( ! is_int( $now ) || $now < 0 ) {
			throw new RuntimeException( 'Relógio de parâmetros indisponível.' );
		}
		return $now;
	}

	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Leitura de parâmetros indisponível.' );
		}
		return $rows;
	}

	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Transacção de parâmetros indisponível.' );
		}
	}

	private function write( mixed $result ): void {
		if ( 1 !== $result || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Escrita de parâmetros incompleta.' );
		}
	}
}
