<?php
/** Internal settings reader on the caller-owned transaction.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
/** No user-facing authority: callers must authorize their own protected operation. */
final class SettingsReader {
	private readonly SettingsSchema $schema;
	public function __construct( private readonly IdentityWpdb $database ) {
		$this->schema = new SettingsSchema( $database );
	}

	public function locked(): SettingsState {
		if ( ! Configuration::current() || ! $this->database->inTransaction() ) {
			throw new RuntimeException( 'Leitura de parâmetros exige transacção local.' );
		}
		$this->schema->assertReady();
		$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i FOR UPDATE', $this->schema->table( 'state' ) ) );
		if ( 1 !== count( $rows ) || '1' !== (string) $rows[0]->id ) {
			throw new RuntimeException( 'Estado de parâmetros ausente ou incompatível.' );
		}
		$row   = $rows[0];
		$state = self::decode( $row->payload, $row->digest );
		$count = $this->rows( $this->database->prepare( 'SELECT COUNT(*) AS total FROM %i', $this->schema->table( 'audit' ) ) );
		if ( (string) $state->revision !== (string) $row->revision || 1 !== count( $count ) || (string) ( $state->revision - 1 ) !== (string) $count[0]->total ) {
			throw new RuntimeException( 'Histórico de parâmetros incompleto.' );
		}
		if ( $state->revision > 1 ) {
			$latest = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE revision=%d', $this->schema->table( 'audit' ), $state->revision ) );
			if ( 1 !== count( $latest ) || $this->event( $latest[0] )['after'] !== $state->toArray() ) {
				throw new RuntimeException( 'Histórico de parâmetros divergente.' );
			}
		}
		return $state;
	}

	public function event( object $row ): array {
		$before = self::decode( $row->before_payload, $row->before_hash );
		$after  = self::decode( $row->after_payload, $row->after_hash );
		if ( $after->revision !== $before->revision + 1 || (string) $after->revision !== (string) $row->revision || (string) $after->actor_id !== (string) $row->actor_id || (string) $after->occurred_at !== (string) $row->occurred_at || $before->change( $after->policy, $before->revision, $after->actor_id, $after->occurred_at, $after->reason )->toArray() !== $after->toArray() ) {
			throw new RuntimeException( 'Evento de parâmetros incompatível.' );
		}
		return array(
			'before' => $before->toArray(),
			'after'  => $after->toArray(),
		);
	}

	private static function decode( mixed $payload, mixed $digest ): SettingsState {
		if ( ! is_string( $payload ) || ! is_string( $digest ) || ! hash_equals( hash( 'sha256', $payload ), $digest ) ) {
			throw new RuntimeException( 'Integridade de parâmetros inválida.' );
		}
		$value = json_decode( $payload, true, 16, JSON_THROW_ON_ERROR );
		if ( ! is_array( $value ) ) {
			throw new RuntimeException( 'Payload de parâmetros inválido.' );
		}
		$state = SettingsState::fromArray( $value );
		if ( self::json( $state ) !== $payload ) {
			throw new RuntimeException( 'Representação de parâmetros incompatível.' );
		}
		return $state;
	}

	public static function json( SettingsState $state ): string {
		return json_encode( $state->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Leitura de parâmetros indisponível.' );
		}
		return $rows;
	}
}
