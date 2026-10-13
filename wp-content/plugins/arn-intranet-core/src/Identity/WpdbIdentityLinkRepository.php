<?php
/**
 * Vínculos Google e auditoria transaccional em tabelas próprias por site.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

// Erros estáticos de domínio. Não expor SQL, last_error ou excepções anteriores.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
// SQL composto apenas com prepare, incluindo identificadores %i.
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// Tabelas próprias, sem cache de autorização ou invalidation no object cache.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
// Metadata SQL conserva os nomes de campos exactos definidos pelo servidor.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

/**
 * Adaptador interno. Operações administrativas passam por IdentityLinkService.
 * Não oferece reassociação, eliminação, registo por email ou promoção de funções.
 */
final class WpdbIdentityLinkRepository implements IdentityLinkRepository {

	/**

	 * Tabela própria de vínculos.
	 *
	 * @var string
	 */
	private readonly string $links_table;
	/**
	 * Tabela própria de auditoria.
	 *
	 * @var string
	 */
	private readonly string $audit_table;

	/**
	 * A ligação tem de estar dedicada e não pode reconectar automaticamente.
	 *
	 * @param IdentityWpdb $database Ligação explicitamente configurada.
	 * @throws InvalidArgumentException Quando o prefixo é inválido.
	 */
	public function __construct( private readonly IdentityWpdb $database ) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]{1,40}$/D', $database->prefix ) ) {
			throw new InvalidArgumentException( 'Prefixo WordPress inválido para as tabelas de identidade.' );
		}
		$this->links_table = $database->prefix . 'arn_identity_links';
		$this->audit_table = $database->prefix . 'arn_identity_audit';
	}

	/**
	 * Cria apenas tabelas ausentes, nunca migra silenciosamente schema existente.
	 * DDL não é transaccional. Não chamar dentro de outra transacção.
	 */
	public function installSchema(): void {
		$this->silently(
			function (): void {
				$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
				$this->execute(
					$this->database->prepare(
						'CREATE TABLE IF NOT EXISTS %i (
					user_id bigint unsigned NOT NULL,
					issuer varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
					subject varbinary(255) NOT NULL,
					authorized tinyint(1) unsigned NOT NULL DEFAULT 0,
					active tinyint(1) unsigned NOT NULL DEFAULT 0,
					PRIMARY KEY (user_id),
					UNIQUE KEY issuer_subject (issuer, subject)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET ascii COLLATE ascii_bin',
						$this->links_table,
					)
				);
				$this->execute(
					$this->database->prepare(
						'CREATE TABLE IF NOT EXISTS %i (
					id bigint unsigned NOT NULL AUTO_INCREMENT,
					user_id bigint unsigned NOT NULL,
					actor_id bigint unsigned NOT NULL,
					action varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
					previous_authorized tinyint(1) unsigned NULL,
					previous_active tinyint(1) unsigned NULL,
					new_authorized tinyint(1) unsigned NOT NULL,
					new_active tinyint(1) unsigned NOT NULL,
					occurred_at datetime NOT NULL,
					PRIMARY KEY (id),
					KEY user_history (user_id, id)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET ascii COLLATE ascii_bin',
						$this->audit_table,
					)
				);
				$this->assertSchema();
			}
		);
	}

	/**
	 * A criação começa sem acesso e falha em qualquer conflito unique.
	 *
	 * @param LocalIdentityLink $link     Vínculo confirmado, ainda inactivo.
	 * @param int               $actor_id Actor previamente autorizado.
	 * @throws InvalidArgumentException Quando a criação não começa inactiva.
	 */
	public function create( LocalIdentityLink $link, int $actor_id ): void {
		if ( $actor_id < 1 || $link->authorized || $link->active ) {
			throw new InvalidArgumentException( 'Criação de vínculo inválida.' );
		}
		$this->silently(
			function () use ( $link, $actor_id ): void {
				$this->assertSchema();
				$this->transaction(
					function () use ( $link, $actor_id ): void {
						$this->execute(
							$this->database->prepare(
								'INSERT INTO %i (user_id, issuer, subject, authorized, active) VALUES (%d, %s, %s, 0, 0)',
								$this->links_table,
								$link->user_id,
								$link->issuer,
								$link->subject,
							),
							1
						);
						$this->audit( $link->user_id, $actor_id, 'create', null, false, false );
					}
				);
			}
		);
	}

	/**
	 * Bloqueia o vínculo e regista o estado anterior na mesma transacção.
	 * Uma decisão repetida com o mesmo estado é idempotente, sem nova auditoria.
	 *
	 * @param int  $user_id    Conta associada.
	 * @param bool $authorized Novo estado de autorização.
	 * @param bool $active     Novo estado activo.
	 * @param int  $actor_id   Actor previamente autorizado.
	 * @throws InvalidArgumentException Quando os IDs ou estados são inválidos.
	 */
	public function changeState( int $user_id, bool $authorized, bool $active, int $actor_id ): void {
		if ( $user_id < 1 || $actor_id < 1 || $authorized !== $active ) {
			throw new InvalidArgumentException( 'Alteração de vínculo inválida.' );
		}
		$this->silently(
			function () use ( $user_id, $authorized, $active, $actor_id ): void {
				$this->assertSchema();
				$this->transaction(
					function () use ( $user_id, $authorized, $active, $actor_id ): void {
						$rows     = $this->rows(
							$this->database->prepare(
								'SELECT user_id, issuer, subject, authorized, active FROM %i WHERE user_id = %d FOR UPDATE',
								$this->links_table,
								$user_id,
							)
						);
						$previous = 1 === count( $rows ) ? $this->hydrate( $rows[0] ) : null;
						if ( null === $previous || $previous->user_id !== $user_id ) {
							throw new RuntimeException( 'Vínculo local ausente ou inválido.' );
						}
						if ( $previous->authorized === $authorized && $previous->active === $active ) {
								return;
						}
						$this->execute(
							$this->database->prepare(
								'UPDATE %i SET authorized = %d, active = %d WHERE user_id = %d',
								$this->links_table,
								(int) $authorized,
								(int) $active,
								$user_id,
							),
							1
						);
						$this->audit( $user_id, $actor_id, $active ? 'activate' : 'revoke', $previous, $authorized, $active );
					}
				);
			}
		);
	}

	/**
	 * Resolve apenas um registo exacto, incluindo estado revogado.
	 * Schema ausente ou inválido causa excepção, nunca concessão de acesso.
	 *
	 * @param string $issuer  Emissor Google.
	 * @param string $subject Claim sub opaca.
	 */
	public function find( string $issuer, string $subject ): ?LocalIdentityLink {
		$canonical = LocalIdentityLink::canonicalIssuer( $issuer );
		if ( null === $canonical || ! LocalIdentityLink::validSubject( $subject ) ) {
			return null;
		}
		return $this->silently(
			function () use ( $canonical, $subject ): ?LocalIdentityLink {
				$this->assertSchema();
				$rows = $this->rows(
					$this->database->prepare(
						'SELECT user_id, issuer, subject, authorized, active FROM %i WHERE issuer = %s AND subject = %s LIMIT 2',
						$this->links_table,
						$canonical,
						$subject,
					)
				);
				$link = 1 === count( $rows ) ? $this->hydrate( $rows[0] ) : null;
				return null !== $link && $link->issuer === $canonical && $link->subject === $subject ? $link : null;
			}
		);
	}

	/**
	 * Resolve um único vínculo pelo ID exacto, sem cache nem criação de schema.
	 *
	 * @param int $user_id Conta local.
	 * @throws RuntimeException Quando existem dados incoerentes ou ambíguos.
	 */
	public function findForUser( int $user_id ): ?LocalIdentityLink {
		if ( $user_id < 1 ) {
			return null;
		}
		return $this->silently(
			function () use ( $user_id ): ?LocalIdentityLink {
				$this->assertSchema();
				$rows = $this->rows(
					$this->database->prepare(
						'SELECT user_id, issuer, subject, authorized, active FROM %i WHERE user_id = %d LIMIT 2',
						$this->links_table,
						$user_id,
					)
				);
				if ( array() === $rows ) {
					return null;
				}
				$link = 1 === count( $rows ) ? $this->hydrate( $rows[0] ) : null;
				if ( null === $link || $link->user_id !== $user_id ) {
					throw new RuntimeException( 'Vínculo local inválido ou ambíguo.' );
				}
				return $link;
			}
		);
	}

	/**
	 * Distingue laboratório novo de schema existente ou danificado.
	 *
	 * @throws RuntimeException Quando a leitura falha ou o schema é parcial.
	 */
	public function hasSchema(): bool {
		return $this->silently(
			function (): bool {
				$tables = $this->rows(
					$this->database->prepare(
						'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s, %s)',
						$this->links_table,
						$this->audit_table,
					)
				);
				if ( array() === $tables ) {
					return false;
				}
				$this->assertSchema();
				return true;
			}
		);
	}

	/**
	 * Verifica motor, índices completos e comparação binária sem criar tabelas.
	 *
	 * @throws RuntimeException Quando o schema não garante integridade.
	 */
	private function assertSchema(): void {
		$tables  = $this->rows(
			$this->database->prepare(
				'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s, %s)',
				$this->links_table,
				$this->audit_table,
			)
		);
		$engines = array();
		foreach ( $tables as $table ) {
			$engines[ $table->TABLE_NAME ?? '' ] = $table->ENGINE ?? '';
		}
		if ( 'InnoDB' !== ( $engines[ $this->links_table ] ?? null ) || 'InnoDB' !== ( $engines[ $this->audit_table ] ?? null ) ) {
			throw new RuntimeException( 'Schema transaccional de identidade indisponível.' );
		}
		$indexes     = $this->uniqueKeys( $this->links_table );
		$unique_user = false;
		$unique_pair = false;
		foreach ( $indexes as $columns ) {
			ksort( $columns );
			$unique_user = $unique_user || array( 'user_id' ) === array_values( $columns );
			$unique_pair = $unique_pair || array( 'issuer', 'subject' ) === array_values( $columns );
		}
		$fields = $this->fields( $this->links_table );
		if (
			! $unique_user || ! $unique_pair ||
			1 !== preg_match( '/^bigint(?:\([0-9]+\))? unsigned$/D', $fields['user_id']->Type ?? '' ) ||
			'NO' !== ( $fields['user_id']->Null ?? null ) ||
			1 !== preg_match( '/^tinyint(?:\(1\))? unsigned$/D', $fields['authorized']->Type ?? '' ) ||
			'NO' !== ( $fields['authorized']->Null ?? null ) ||
			1 !== preg_match( '/^tinyint(?:\(1\))? unsigned$/D', $fields['active']->Type ?? '' ) ||
			'NO' !== ( $fields['active']->Null ?? null ) ||
			'varchar(64)' !== ( $fields['issuer']->Type ?? null ) ||
			'ascii_bin' !== ( $fields['issuer']->Collation ?? null ) ||
			'NO' !== ( $fields['issuer']->Null ?? null ) ||
			'varbinary(255)' !== ( $fields['subject']->Type ?? null ) ||
			'NO' !== ( $fields['subject']->Null ?? null )
		) {
			throw new RuntimeException( 'Constraints de identidade inválidas.' );
		}
		$this->assertAuditSchema();
	}

	/**
	 * Índices únicos completos, sem aceitar parte de um índice composto.
	 *
	 * @param string $table Tabela própria.
	 * @return array<string, array<int, string>> Chaves ordenadas.
	 */
	private function uniqueKeys( string $table ): array {
		$indexes = array();
		$invalid = array();
		foreach ( $this->rows( $this->database->prepare( 'SHOW INDEX FROM %i', $table ) ) as $index ) {
			$name = $index->Key_name ?? '';
			if ( '0' !== (string) ( $index->Non_unique ?? '' ) || null !== ( $index->Sub_part ?? null ) ) {
				$invalid[ $name ] = true;
			}
			$indexes[ $name ][ (int) $index->Seq_in_index ] = $index->Column_name;
		}
		foreach ( $invalid as $name => $ignored ) {
			unset( $indexes[ $name ] );
		}
		foreach ( $indexes as &$columns ) {
			ksort( $columns );
		}
		return $indexes;
	}

	/**
	 * Mantém os tipos reportados pelo servidor, sem migração automática.
	 *
	 * @param string $table Tabela própria.
	 * @return array<string, object> Metadata por coluna.
	 */
	private function fields( string $table ): array {
		$fields = array();
		foreach ( $this->rows( $this->database->prepare( 'SHOW FULL COLUMNS FROM %i', $table ) ) as $field ) {
			$fields[ $field->Field ] = $field;
		}
		return $fields;
	}

	/**
	 * Exige auditoria que preserve IDs e estados sem truncagem silenciosa.
	 *
	 * @throws RuntimeException Quando a auditoria perdeu o schema esperado.
	 */
	private function assertAuditSchema(): void {
		$fields  = $this->fields( $this->audit_table );
		$indexes = $this->uniqueKeys( $this->audit_table );
		if ( array( 1 => 'id' ) !== ( $indexes['PRIMARY'] ?? null ) || 'auto_increment' !== ( $fields['id']->Extra ?? null ) ) {
			throw new RuntimeException( 'Chave de auditoria inválida.' );
		}
		foreach ( array( 'id', 'user_id', 'actor_id' ) as $name ) {
			if ( 1 !== preg_match( '/^bigint(?:\([0-9]+\))? unsigned$/D', $fields[ $name ]->Type ?? '' ) || 'NO' !== ( $fields[ $name ]->Null ?? null ) ) {
				throw new RuntimeException( 'Identificadores de auditoria inválidos.' );
			}
		}
		foreach ( array( 'previous_authorized', 'previous_active', 'new_authorized', 'new_active' ) as $name ) {
			$nullable = str_starts_with( $name, 'previous_' ) ? 'YES' : 'NO';
			if ( 1 !== preg_match( '/^tinyint(?:\(1\))? unsigned$/D', $fields[ $name ]->Type ?? '' ) || ( $fields[ $name ]->Null ?? null ) !== $nullable ) {
				throw new RuntimeException( 'Estados de auditoria inválidos.' );
			}
		}
		if (
			'varchar(16)' !== ( $fields['action']->Type ?? null ) ||
			'ascii_bin' !== ( $fields['action']->Collation ?? null ) ||
			'NO' !== ( $fields['action']->Null ?? null ) ||
			'datetime' !== ( $fields['occurred_at']->Type ?? null ) ||
			'NO' !== ( $fields['occurred_at']->Null ?? null )
		) {
			throw new RuntimeException( 'Decisão ou data de auditoria inválida.' );
		}
	}

	/**
	 * Auditoria apenas de IDs locais, decisão, estados e instante UTC.
	 *
	 * @param int                    $user_id    Conta associada.
	 * @param int                    $actor_id   Actor autorizado.
	 * @param string                 $action     Código fechado da decisão.
	 * @param LocalIdentityLink|null $previous   Estado anterior, ou criação.
	 * @param bool                   $authorized Estado novo autorizado.
	 * @param bool                   $active     Estado novo activo.
	 */
	private function audit( int $user_id, int $actor_id, string $action, ?LocalIdentityLink $previous, bool $authorized, bool $active ): void {
		$old_flags = null === $previous ? 'NULL, NULL' : '%d, %d';
		$args      = array( $this->audit_table, $user_id, $actor_id, $action );
		if ( null !== $previous ) {
			$args[] = (int) $previous->authorized;
			$args[] = (int) $previous->active;
		}
		$args[] = (int) $authorized;
		$args[] = (int) $active;
		$args[] = gmdate( 'Y-m-d H:i:s' );
		$this->execute(
			$this->database->prepare(
				"INSERT INTO %i (user_id, actor_id, action, previous_authorized, previous_active, new_authorized, new_active, occurred_at) VALUES (%d, %d, %s, {$old_flags}, %d, %d, %s)",
				...$args,
			),
			1
		);
	}

	/**
	 * Recusa iniciar sobre transacção existente e reverte toda a decisão.
	 * SET TRANSACTION sem SESSION falha se já existir uma transacção activa.
	 *
	 * @param callable():void $operation Escritas e auditoria.
	 * @throws RuntimeException Quando a operação não é confirmada.
	 */
	private function transaction( callable $operation ): void {
		$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
		$this->execute( 'START TRANSACTION' );
		try {
			$operation();
			$this->execute( 'COMMIT' );
		} catch ( Throwable ) {
			$this->database->query( 'ROLLBACK' );
			throw new RuntimeException( 'Operação de identidade não confirmada.' );
		}
	}

	/**
	 * Esconde os erros wpdb e restaura o estado em sucesso ou falha.
	 *
	 * @param callable $operation Operação SQL interna.
	 * @return mixed Resultado da operação.
	 * @throws RuntimeException Quando a persistência está indisponível.
	 */
	private function silently( callable $operation ): mixed {
		$previous = $this->database->suppress_errors( true );
		try {
			return $operation();
		} catch ( Throwable ) {
			throw new RuntimeException( 'Persistência de identidade indisponível.' );
		} finally {
			$this->database->suppress_errors( $previous );
		}
	}

	/**
	 * Um erro SQL ou resultado inesperado nunca é aceite como sucesso.
	 *
	 * @param string   $query    SQL previamente preparado ou comando fixo.
	 * @param int|null $affected Número de linhas obrigatoriamente afectadas.
	 * @throws RuntimeException Quando falha a execução ou o resultado.
	 */
	private function execute( string $query, ?int $affected = null ): void {
		$result = $this->database->query( $query );
		if ( false === $result || '' !== $this->database->last_error || ( null !== $affected && $result !== $affected ) ) {
			throw new RuntimeException( 'Falha de persistência de identidade.' );
		}
	}

	/**
	 * Leitura directa sem reutilização de autorização em cache.
	 *
	 * @param string $query SQL previamente preparado.
	 * @return object[] Resultados completos.
	 * @throws RuntimeException Quando a leitura falha.
	 */
	private function rows( string $query ): array {
		$rows = $this->database->get_results( $query );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Consulta de identidade indisponível.' );
		}
		return $rows;
	}

	/**
	 * Valida os tipos persistidos sem transformar um estado inválido em acesso.
	 *
	 * @param object $row Registo devolvido por wpdb.
	 */
	private function hydrate( object $row ): ?LocalIdentityLink {
		$user_id = $row->user_id ?? null;
		if (
			( ! is_int( $user_id ) && ! is_string( $user_id ) ) ||
			(int) $user_id < 1 || (string) (int) $user_id !== (string) $user_id ||
			! is_string( $row->issuer ?? null ) || ! is_string( $row->subject ?? null ) ||
			'https://accounts.google.com' !== $row->issuer ||
			! LocalIdentityLink::validSubject( $row->subject ) ||
			! in_array( $row->authorized ?? null, array( 0, 1, '0', '1' ), true ) ||
			! in_array( $row->active ?? null, array( 0, 1, '0', '1' ), true )
		) {
			return null;
		}
		return new LocalIdentityLink( (int) $user_id, $row->issuer, $row->subject, (bool) $row->authorized, (bool) $row->active );
	}
}
