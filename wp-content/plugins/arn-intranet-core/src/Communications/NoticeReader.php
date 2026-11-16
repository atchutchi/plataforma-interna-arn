<?php
/** Validated aggregate reader shared by local and institutional consumers.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Communications;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class NoticeReader {
	private readonly NoticeSchema $schema;
	public function __construct( private readonly IdentityWpdb $database ) {
		$this->schema = new NoticeSchema( $database );
	}
	public function load( int $id, bool $lock = false ): ?Notice {
		if ( $id < 1 ) {
			return null;
		}
		$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE id=%d', $this->schema->table( 'objects' ), $id ) . ( $lock ? ' FOR UPDATE' : '' ) );
		if ( ! $rows ) {
			return null;
		}
		$row  = $rows[0];
		$data = json_decode( $row->payload, true, 64, JSON_THROW_ON_ERROR );
		if ( 1 !== count( $rows ) || ! is_array( $data ) ) {
			throw new RuntimeException( 'Agregado de comunicados inválido.' );
		}
		$notice = Notice::fromArray( $data );
		$digest = hash( 'sha256', self::encode( $notice->toArray() ) );
		if ( $notice->id !== $id || self::integer( $row->author_id ) !== $notice->author_id || $row->unit_id !== $notice->unit_id || self::integer( $row->revision ) !== $notice->revision || $row->digest !== $digest || self::encode( $notice->toArray() ) !== $row->payload ) {
			throw new RuntimeException( 'Digest de comunicados inválido.' );
		}
		$anchor = $this->rows( $this->database->prepare( 'SELECT post_author,post_type,post_status,post_content,post_title,post_excerpt FROM %i WHERE ID=%d', $GLOBALS['wpdb']->posts, $id ) );
		if ( 1 !== count( $anchor ) || self::integer( $anchor[0]->post_author ) !== $notice->author_id || 'arn_comunicado' !== $anchor[0]->post_type || 'private' !== $anchor[0]->post_status || '' !== $anchor[0]->post_content || 'Comunicado privado' !== $anchor[0]->post_title || 'arn-f112:' . $this->database->prefix . ':communications' !== $anchor[0]->post_excerpt ) {
			throw new RuntimeException( 'Âncora de comunicado incompatível.' );
		}
		$versions = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE object_id=%d ORDER BY `number`', $this->schema->table( 'versions' ), $id ) );
		if ( count( $versions ) !== count( $notice->versions ) ) {
			throw new RuntimeException( 'Versões de comunicado incompletas.' );
		}
		foreach ( $versions as $i => $version ) {
			if ( self::integer( $version->object_id ) !== $id || self::integer( $version->number ) !== $i + 1 || self::encode( $notice->versions[ $i ]->toArray() ) !== $version->payload ) {
				throw new RuntimeException( 'Versões de comunicado incompatíveis.' );
			}
		}
		$audit = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE object_id=%d ORDER BY revision', $this->schema->table( 'audit' ), $id ) );
		if ( count( $audit ) !== $notice->revision ) {
			throw new RuntimeException( 'Auditoria de comunicado incompleta.' );
		}
		$previous = str_repeat( '0', 64 );
		foreach ( $audit as $i => $event ) {
			$entry = $notice->history[ $i ];
			if ( self::integer( $event->object_id ) !== $id || self::integer( $event->revision ) !== $i + 1 || $event->event !== $entry['operation'] || self::integer( $event->actor_id ) !== $entry['actor_id'] || self::integer( $event->occurred_at, 0 ) !== $entry['occurred_at'] || $event->reason !== $entry['reason'] || self::encode( $entry ) !== $event->payload || $event->before_hash !== $previous || 1 !== preg_match( '/\A[a-f0-9]{64}\z/', $event->after_hash ) ) {
				throw new RuntimeException( 'Cadeia de auditoria de comunicado inválida.' );
			}
			$previous = $event->after_hash;
		}
		if ( $previous !== $digest ) {
			throw new RuntimeException( 'Última auditoria de comunicado inválida.' );
		}
		return $notice;
	}

	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Leitura de comunicados indisponível.' );
		}
		return $rows;
	}

	private static function integer( mixed $value, int $minimum = 1 ): int {
		if ( is_int( $value ) ) {
			if ( $value >= $minimum ) {
				return $value;
			}
		} elseif ( is_string( $value ) && 1 === preg_match( '/\A(?:0|[1-9][0-9]*)\z/', $value ) && strlen( $value ) <= strlen( (string) PHP_INT_MAX ) && ( strlen( $value ) < strlen( (string) PHP_INT_MAX ) || strcmp( $value, (string) PHP_INT_MAX ) <= 0 ) && (int) $value >= $minimum ) {
			return (int) $value;
		}
		throw new RuntimeException( 'Inteiro de comunicado inválido.' );
	}

	private static function encode( array $value ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Canonical persisted domain bytes must not be transformed.
		return json_encode( $value, JSON_THROW_ON_ERROR );
	}
}
