<?php
/** Recibos locais separados da autorização.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Notifications;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
// Metadata sem cache e identificadores preparados. Erros constantes.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
/** Nunca repara tabelas existentes. */
final class NotificationSchema {
	/** Dependência interna fixada.
	 *
	 * @var string
	 */
	public readonly string $table;
	/** Recebe dependências internas e fixa a fronteira.
	 *
	 * @param IdentityWpdb $database Ligação exclusiva sem reconnect.
	 *
	 * @throws RuntimeException Quando a fronteira ou a persistência recusa a operação.
	 */
	public function __construct( private readonly IdentityWpdb $database ) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]{1,40}$/D', $database->prefix ) ) {
			throw new RuntimeException( 'Notificações indisponíveis.' );
		}
		$this->table = $database->prefix . 'arn_notification_reads';
	}
	/** Instala apenas o contrato ausente e recusa incompatibilidades.
	 *
	 * @throws RuntimeException Quando a fronteira ou a persistência recusa a operação.
	 */
	public function install(): void {
		$exists = $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->table ) );
		if ( array() === $exists ) {
			if ( false === $this->database->query( $this->database->prepare( 'CREATE TABLE %i (user_id BIGINT UNSIGNED NOT NULL, event_id BIGINT UNSIGNED NOT NULL, read_at BIGINT UNSIGNED NOT NULL, PRIMARY KEY(user_id,event_id)) ENGINE=InnoDB', $this->table ) ) || '' !== $this->database->last_error ) {
				throw new RuntimeException( 'Notificações indisponíveis.' );
			}
		}
		$this->validate();
	}
	/** Confirma colunas, tipos, índices e motor.
	 *
	 * @throws RuntimeException Quando a fronteira ou a persistência recusa a operação.
	 */
	public function validate(): void {
		$engine = $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->table ) );
		if ( 1 !== count( $engine ) || 'InnoDB' !== $engine[0]->ENGINE ) {
			throw new RuntimeException( 'Notificações indisponíveis.' );
		}
		$fields = $this->rows( $this->database->prepare( 'SHOW FULL COLUMNS FROM %i', $this->table ) );
		$names  = array();
		foreach ( $fields as $field ) {
			if ( 'bigint unsigned' !== preg_replace( '/bigint\([0-9]+\)/', 'bigint', $field->Type ) || 'NO' !== $field->Null || null !== $field->Collation || '' !== $field->Extra || null !== $field->Default ) {
				throw new RuntimeException( 'Notificações indisponíveis.' );
			}
			$names[] = $field->Field;
		}
		if ( array( 'user_id', 'event_id', 'read_at' ) !== $names ) {
			throw new RuntimeException( 'Notificações indisponíveis.' );
		}
		$indexes = $this->rows( $this->database->prepare( 'SHOW INDEX FROM %i', $this->table ) );
		if ( 2 !== count( $indexes ) ) {
			throw new RuntimeException( 'Notificações indisponíveis.' );
		}
		foreach ( $indexes as $i => $index ) {
			if ( 'PRIMARY' !== $index->Key_name || '0' !== (string) $index->Non_unique || (string) ( $i + 1 ) !== (string) $index->Seq_in_index || array( 'user_id', 'event_id' )[ $i ] !== $index->Column_name || null !== $index->Sub_part || 'BTREE' !== $index->Index_type ) {
				throw new RuntimeException( 'Notificações indisponíveis.' );
			}
		}
		$constraints = $this->rows( $this->database->prepare( 'SELECT CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->table ) );
		if ( 1 !== count( $constraints ) || 'PRIMARY KEY' !== $constraints[0]->CONSTRAINT_TYPE ) {
			throw new RuntimeException( 'Notificações indisponíveis.' );
		}
	}
	/** Lê resultados ou recusa erros SQL de forma estática.
	 *
	 * @param string $sql Comando interno preparado.
	 *
	 * @throws RuntimeException Quando a fronteira ou a persistência recusa a operação.
	 */
	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Notificações indisponíveis.' );
		}
		return $rows;
	}
}
