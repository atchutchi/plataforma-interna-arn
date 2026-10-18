<?php
/**
 * Fronteira wpdb simulada para verificar SQL e falhas de transporte.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// Este double só é carregado pelos testes puros, sem WordPress.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
// O nome corresponde ao core WordPress.
// phpcs:disable PEAR.NamingConventions.ValidClassName.StartWithCapital
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
// phpcs:disable WordPress.DB.RestrictedFunctions.mysql_mysqli_report
// phpcs:disable WordPress.DB.RestrictedFunctions.mysql_mysqli_init

/**
 * Não representa uma base de dados nem prova as constraints do servidor.
 */
class wpdb {

	/**

	 * Prefixo do site fictício.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';
	/**
	 * Erro de transporte.
	 *
	 * @var string
	 */
	public string $last_error = '';
	/**
	 * Estado da supressão de erros.
	 *
	 * @var bool
	 */
	public bool $suppressed = false;
	/**
	 * Motor reportado pelas tabelas.
	 *
	 * @var string
	 */
	public string $engine = 'InnoDB';

	/**
	 * Número de tabelas próprias presentes no cenário.
	 *
	 * @var int
	 */
	public int $tables_present = 2;
	/**
	 * Constraints únicas presentes.
	 *
	 * @var bool
	 */
	public bool $unique = true;

	/**
	 * Índices alternativos para corrupção de schema.
	 *
	 * @var array<object>|null
	 */
	public ?array $indexes_override = null;

	/**
	 * Tipo da coluna do actor na auditoria.
	 *
	 * @var string
	 */
	public string $audit_actor_type = 'bigint unsigned';

	/**
	 * Tipo do identificador local no vínculo.
	 *
	 * @var string
	 */
	public string $link_user_type = 'bigint unsigned';
	/**
	 * Tipo binário do identificador opaco.
	 *
	 * @var string
	 */
	public string $subject_type = 'varbinary(255)';
	/**
	 * Prefixo SQL cuja execução falha.
	 *
	 * @var string
	 */
	public string $fail_prefix = '';
	/**
	 * Resultados fictícios da consulta ao vínculo.
	 *
	 * @var array<object>
	 */
	public array $rows = array();
	/**
	 * SQL executado para verificar ordem de transacção.
	 *
	 * @var string[]
	 */
	public array $queries = array();
	/**
	 * Argumentos passados à preparação.
	 *
	 * @var array<array<mixed>>
	 */
	public array $bindings = array();
	/**
	 * A ligação simulada nunca fica pronta.
	 *
	 * @var bool
	 */
	public bool $ready = false;
	/**
	 * Sem handle mysqli.
	 *
	 * @var object|null
	 */
	protected $dbh = null;
	/**
	 * Último SQL observado pelo core.
	 *
	 * @var string
	 */
	public string $last_query = '';

	/**
	 * Aceita os argumentos habituais sem abrir ligação real.
	 *
	 * @param string $user Utilizador fictício.
	 * @param string $password Segredo fictício.
	 * @param string $database Nome fictício.
	 * @param string $host Host fictício.
	 */
	public function __construct( string $user = '', string $password = '', string $database = '', string $host = '' ) {
		$this->ready = true;
		if ( function_exists( 'mysqli_init' ) ) {
			$reporting = ( new mysqli_driver() )->report_mode;
			mysqli_report( MYSQLI_REPORT_OFF );
			try {
				$this->dbh = mysqli_init();
			} finally {
				mysqli_report( $reporting );
			}
		}
		$boundary = new \Arn\Intranet\Identity\TransactionBoundary();
		$boundary->markReady();
		$property = new ReflectionProperty( \Arn\Intranet\Identity\IdentityWpdb::class, 'transaction_boundary' );
		$property->setValue( $this, $boundary );
	}

	/** Fecha a ligação simulada e permite testar a invalidação da fronteira. */
	public function close() {
		$this->ready = false;
		$this->dbh   = null;
		return true;
	}

	/**
	 * Preparação mínima para verificar os bindings emitidos pelo adaptador.
	 *
	 * @param string $query SQL com placeholders.
	 * @param mixed  ...$args Valores.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		$this->bindings[] = $args;
		return preg_replace_callback(
			'/%[isd]/',
			static function ( array $placeholder ) use ( &$args ): string {
				$value = array_shift( $args );
				return match ( $placeholder[0] ) {
					'%i' => '`' . str_replace( '`', '``', (string) $value ) . '`',
					'%d' => (string) (int) $value,
					'%s' => "'" . str_replace( "'", "''", (string) $value ) . "'",
				};
			},
			$query
		);
	}

	/**
	 * Devolve estado anterior, como wpdb.
	 *
	 * @param bool $suppress Novo estado.
	 */
	public function suppress_errors( bool $suppress = true ): bool {
		$previous         = $this->suppressed;
		$this->suppressed = $suppress;
		return $previous;
	}

	/**
	 * Regista SQL e simula uma falha restrita ao comando pretendido.
	 *
	 * @param string $query SQL preparado.
	 * @return int|false
	 */
	public function query( $query ) {
		$this->queries[]  = $query;
		$this->last_query = $query;
		$this->last_error = '';
		if ( '' !== $this->fail_prefix && str_starts_with( $query, $this->fail_prefix ) ) {
			$this->last_error = 'erro privado fictício';
			return false;
		}
		return 1;
	}

	/**
	 * Fronteira de leitura, com metadata literal de duas tabelas próprias.
	 *
	 * @param string $query SQL preparado.
	 * @return array<object>|null
	 */
	public function get_results( string $query ): ?array {
		if ( false === $this->query( $query ) ) {
			return null;
		}
		if ( str_contains( $query, 'information_schema.TABLES' ) ) {
			return array_slice(
				array(
					(object) array(
						'TABLE_NAME' => 'wp_arn_identity_links',
						'ENGINE'     => $this->engine,
					),
					(object) array(
						'TABLE_NAME' => 'wp_arn_identity_audit',
						'ENGINE'     => $this->engine,
					),
				),
				0,
				$this->tables_present
			);
		}
		if ( str_starts_with( $query, 'SHOW INDEX' ) ) {
			if ( null !== $this->indexes_override && ! str_contains( $query, 'arn_identity_audit' ) ) {
				return $this->indexes_override;
			}
			if ( str_contains( $query, 'arn_identity_audit' ) ) {
				return array(
					(object) array(
						'Key_name'     => 'PRIMARY',
						'Non_unique'   => '0',
						'Seq_in_index' => '1',
						'Column_name'  => 'id',
						'Sub_part'     => null,
					),
				);
			}
			return $this->unique ? array(
				(object) array(
					'Key_name'     => 'PRIMARY',
					'Non_unique'   => '0',
					'Seq_in_index' => '1',
					'Column_name'  => 'user_id',
					'Sub_part'     => null,
				),
				(object) array(
					'Key_name'     => 'issuer_subject',
					'Non_unique'   => '0',
					'Seq_in_index' => '1',
					'Column_name'  => 'issuer',
					'Sub_part'     => null,
				),
				(object) array(
					'Key_name'     => 'issuer_subject',
					'Non_unique'   => '0',
					'Seq_in_index' => '2',
					'Column_name'  => 'subject',
					'Sub_part'     => null,
				),
			) : array();
		}
		if ( str_starts_with( $query, 'SHOW FULL COLUMNS' ) ) {
			if ( str_contains( $query, 'arn_identity_audit' ) ) {
				return array(
					(object) array(
						'Field' => 'id',
						'Type'  => 'bigint unsigned',
						'Null'  => 'NO',
						'Extra' => 'auto_increment',
					),
					(object) array(
						'Field' => 'user_id',
						'Type'  => 'bigint unsigned',
						'Null'  => 'NO',
					),
					(object) array(
						'Field' => 'actor_id',
						'Type'  => $this->audit_actor_type,
						'Null'  => 'NO',
					),
					(object) array(
						'Field'     => 'action',
						'Type'      => 'varchar(16)',
						'Collation' => 'ascii_bin',
						'Null'      => 'NO',
					),
					(object) array(
						'Field' => 'previous_authorized',
						'Type'  => 'tinyint(1) unsigned',
						'Null'  => 'YES',
					),
					(object) array(
						'Field' => 'previous_active',
						'Type'  => 'tinyint(1) unsigned',
						'Null'  => 'YES',
					),
					(object) array(
						'Field' => 'new_authorized',
						'Type'  => 'tinyint(1) unsigned',
						'Null'  => 'NO',
					),
					(object) array(
						'Field' => 'new_active',
						'Type'  => 'tinyint(1) unsigned',
						'Null'  => 'NO',
					),
					(object) array(
						'Field' => 'occurred_at',
						'Type'  => 'datetime',
						'Null'  => 'NO',
					),
				);
			}
			return array(
				(object) array(
					'Field' => 'user_id',
					'Type'  => $this->link_user_type,
					'Null'  => 'NO',
				),
				(object) array(
					'Field' => 'authorized',
					'Type'  => 'tinyint(1) unsigned',
					'Null'  => 'NO',
				),
				(object) array(
					'Field' => 'active',
					'Type'  => 'tinyint(1) unsigned',
					'Null'  => 'NO',
				),
				(object) array(
					'Field'     => 'issuer',
					'Type'      => 'varchar(64)',
					'Collation' => 'ascii_bin',
					'Null'      => 'NO',
				),
				(object) array(
					'Field'     => 'subject',
					'Type'      => $this->subject_type,
					'Collation' => null,
					'Null'      => 'NO',
				),
			);
		}
		return $this->rows;
	}
}
