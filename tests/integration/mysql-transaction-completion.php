<?php
/** Real engine regressions for the reviewed transaction boundary.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals, Squiz.Commenting
require __DIR__ . '/mysql-transaction-bootstrap.php';
if ( 1 !== preg_match( '/\A(?:127\.0\.0\.1|localhost)(?::[0-9]{1,5})?\z/D', (string) getenv( 'ARN_TX_TEST_HOST' ) ) ) {
	throw new RuntimeException( 'Apenas servidor de ensaio loopback.' );
}
$owner       = arn_tx_connect();
$table       = 'arn_completion_' . bin2hex( random_bytes( 6 ) );
$checks      = 0;
$assert      = static function ( bool $value, string $label ) use ( &$checks ): void {
	if ( ! $value ) {
		throw new RuntimeException( 'Falhou fronteira: ' . $label );
	}
	++$checks;
};
$connections = array();
try {
	$assert( false !== $owner->query( 'CREATE TABLE `' . $table . '` (id INT PRIMARY KEY,value INT) ENGINE=InnoDB' ), 'preparação exclusiva' );
	$db            = arn_tx_connect();
	$connections[] = $db;
	// Recusar antes do motor: a falta de privilégios não prova a fronteira.
	$scoped_sets = array(
		'SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED',
		'SET LOCAL TRANSACTION READ ONLY',
		'SET GLOBAL TRANSACTION ISOLATION LEVEL SERIALIZABLE',
		'SET GLOBAL transaction_read_only=ON',
		'SET PERSIST transaction_read_only=ON',
		'SET PERSIST_ONLY transaction_read_only=ON',
		'SET @@global.max_connections=100',
		'SET @@/*comment*/GLOBAL.max_connections=100',
		'SET @@ GLOBAL.`max_connections`=100',
		"SET @@-- comment\nGLOBAL.max_connections=100",
		'SET @marker=1, @@/*comment*/GLOBAL.max_connections=100',
		'SET @@/*comment*/PERSIST.max_connections=100',
		'SET @@/*comment*/PERSIST_ONLY.max_connections=100',
		'SET @marker=1, GLOBAL max_connections=100',
		'SET @marker=1, @@global.`max_connections`=100',
		'SET /*!80000 SESSION */ TRANSACTION ISOLATION LEVEL READ UNCOMMITTED',
		'SET /*!80000 GLOBAL */ max_connections=100',
		'SET NAMES utf8mb4, GLOBAL max_connections=100',
		'SET NAMES utf8mb4, @@global.max_connections=100',
		"SET NAMES utf8mb4, SESSION tx_isolation='READ-UNCOMMITTED'",
		'SET NAMES utf8mb4, transaction_read_only=ON',
		'SET NAMES utf8mb4, PERSIST max_connections=100',
		'SET NAMES utf8mb4, PERSIST_ONLY max_connections=100',
		'SET NAMES utf8mb4, @marker=1',
		"SET NAMES 'utf8mb4' COLLATE 'utf8mb4_bin', /*!80000 SESSION */ transaction_isolation='READ-UNCOMMITTED'",
	);

	$characteristic_values = array(
		'transaction_read_only' => 'ON',
		'tx_read_only'          => 'ON',
		'transaction_isolation' => "'READ-UNCOMMITTED'",
		'tx_isolation'          => "'READ-UNCOMMITTED'",
	);
	foreach ( $characteristic_values as $name => $value ) {
		foreach ( array( '', 'SESSION ', 'LOCAL ', '@@session.', '@@local.', '@@', '@marker=1, ', '@marker=1, @@session.' ) as $prefix ) {
			$scoped_sets[] = 'SET ' . $prefix . '`' . $name . '`=' . $value;
		}
	}
	$session_characteristics = $db->get_var( "SELECT CONCAT(@@session.transaction_isolation,':',@@session.transaction_read_only)" );
	foreach ( array( false, true ) as $inside_transaction ) {
		if ( $inside_transaction ) {
			$assert( false !== $db->query( 'START TRANSACTION' ) && false !== $db->query( 'INSERT INTO `' . $table . '` VALUES (5,5)' ), 'sentinela de âmbitos SET' );
		}
		foreach ( $scoped_sets as $sql ) {
			// A pré-condição permite RED sem enviar alterações globais ao servidor.
			$assert( ! \Arn\Intranet\Identity\SqlBoundary::classify( $sql, $db->dbh->server_info )['trusted'], 'classificador recusa âmbito SET' );
			$queries = $db->num_queries;
			$assert( false === $db->query( $sql ) && $queries === $db->num_queries, 'âmbito SET recusado antes do motor' );
			$assert( $inside_transaction === $db->inTransaction(), 'recusa SET preserva a guarda' );
			$assert( $session_characteristics === $db->get_var( "SELECT CONCAT(@@session.transaction_isolation,':',@@session.transaction_read_only)" ), 'recusa SET preserva características de sessão' );
		}
		if ( $inside_transaction ) {
			$assert( false !== $db->query( 'ROLLBACK' ) && '0' === (string) $owner->get_var( 'SELECT COUNT(*) FROM `' . $table . '`' ), 'âmbitos SET não confirmaram sentinela' );
		}
	}
	$assert( false !== $db->query( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' ), 'isolamento apenas da próxima transacção preservado' );
	$assert( false !== $db->query( "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_bin'" ), 'SET NAMES isolado conserva a forma WordPress' );
	$assert( false !== $db->query( 'SET @scope_marker=1' ) && false !== $db->query( 'SET SESSION wait_timeout=600' ), 'atribuições de utilizador e sessão preservadas' );
	$assert( false !== $owner->query( 'START TRANSACTION' ) && false !== $owner->query( 'INSERT INTO `' . $table . '` VALUES (6,6)' ), 'sentinela não confirmada do observador' );
	for ( $iteration = 0; $iteration < 2; ++$iteration ) {
		$assert( false !== $db->query( 'START TRANSACTION' ) && $db->inTransaction(), 'transacção seguinte conserva guarda' );
		$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $table . '`' ), 'transacção seguinte não permite leitura suja' );
		$assert( false !== $db->query( 'INSERT INTO `' . $table . '` VALUES (7,7)' ), 'transacção seguinte mantém escrita' );
		$assert( false !== $db->query( 'ROLLBACK' ), 'transacção seguinte reverte escrita' );
	}
	$assert( false !== $owner->query( 'ROLLBACK' ) && '0' === (string) $owner->get_var( 'SELECT COUNT(*) FROM `' . $table . '`' ), 'sentinelas seguintes totalmente revertidas' );
	$assert( $session_characteristics === $db->get_var( "SELECT CONCAT(@@session.transaction_isolation,':',@@session.transaction_read_only)" ), 'isolamento da próxima transacção não altera default de sessão' );
	$assert( false !== $db->query( 'START TRANSACTION' ), 'início CASE' );
	$assert( false !== $db->query( 'INSERT INTO `' . $table . '` VALUES (1,CASE WHEN 1=1 THEN 7 ELSE 0 END)' ), 'escrita CASE' );
	$assert( 'THEN' === $db->get_var( "SELECT 'THEN'" ), 'literal THEN' );
	$assert( false !== $db->query( 'ROLLBACK' ) && '0' === (string) $owner->get_var( 'SELECT COUNT(*) FROM `' . $table . '`' ), 'CASE revertido externamente' );
	foreach ( array( 'COMMIT /*!99999 AND CHAIN */', 'ROLLBACK /*!99999 AND CHAIN */', 'COMMIT AND /*!40101 NO */ CHAIN' ) as $sql ) {
		$assert( false !== $db->query( 'START TRANSACTION' ) && false !== $db->query( 'INSERT INTO `' . $table . '` VALUES (2,2)' ), 'sentinela CHAIN' );
		$assert( false === $db->query( $sql ) && $db->inTransaction(), 'CHAIN ambíguo recusado com guarda preservada' );
		$assert( false !== $db->query( 'ROLLBACK' ) && '0' === (string) $owner->get_var( 'SELECT COUNT(*) FROM `' . $table . '`' ), 'CHAIN não confirmou sentinela' );
	}
	$assert( false !== $db->query( 'START TRANSACTION' ), 'início filtro CHAIN' );
	add_filter( 'query', static fn( $sql ) => 'COMMIT' === $sql ? 'COMMIT AND CHAIN' : $sql );
	$assert( false === $db->query( 'COMMIT' ), 'filtro não acrescenta nova transacção' );
	$GLOBALS['arn_tx_filters']['query'] = array();
	$assert( $db->inTransaction() && false !== $db->query( 'ROLLBACK' ), 'filtro preserva saída de limpeza' );
	$assert( false !== $db->query( 'START TRANSACTION' ) && false !== $db->query( 'INSERT INTO `' . $table . '` VALUES (3,3)' ), 'sentinela de COMMIT inválido' );
	$queries   = $db->num_queries;
	$reporting = ( new mysqli_driver() )->report_mode;
	$assert( false === $db->query( 'COMMIT AND CHAIN NO CHAIN' ) && $db->num_queries === $queries + 1 && '' !== $db->last_error, 'COMMIT falha efectivamente na análise do motor' );
	$assert( ( new mysqli_driver() )->report_mode === $reporting, 'modo global do driver preservado após falha SQL' );
	$assert( $db->inTransaction() && false !== $db->query( 'ROLLBACK' ), 'COMMIT falhado conserva rollback' );
	$assert( '0' === (string) $owner->get_var( 'SELECT COUNT(*) FROM `' . $table . '`' ), 'COMMIT falhado não confirmou sentinela' );
	$db->get_var( 'SELECT 917' );
	$assert( null === $db->get_var( 'SAVEPOINT forbidden' ), 'get_var não reutiliza resultado anterior' );
	$db->get_row( 'SELECT 918 AS previous' );
	$assert( null === $db->get_row( 'SAVEPOINT forbidden' ), 'get_row não reutiliza resultado anterior' );
	foreach ( array( false, null, 19, array() ) as $blocked ) {
		$db->get_var( 'SELECT 919' );
		add_filter( 'query', static fn( $sql ) => 'SELECT 920' === $sql ? $blocked : $sql );
		try {
			$assert( null === $db->get_var( 'SELECT 920' ), 'filtro sem SQL não executa a consulta original ou resultado anterior' );
		} finally {
			$GLOBALS['arn_tx_filters']['query'] = array();
		}
	}
	$assert( false === $db->query( "SET SESSION sql_mode='NO_BACKSLASH_ESCAPES'" ), 'modo lexical não editável pela API' );
	$assert( false !== $db->query( 'START TRANSACTION' ), 'início charset indirecto' );
	foreach ( array( "SET SESSION collation_connection='latin1_swedish_ci'", 'SET CHARACTER SET utf8mb4', 'SET CHARSET utf8mb4' ) as $sql ) {
		$assert( false === $db->query( $sql ) && $db->inTransaction(), 'charset indirecto recusado sem perder guarda' );
	}
	$assert( false !== $db->query( 'ROLLBACK' ), 'rollback possível após charset indirecto recusado' );
	$assert( false !== $db->query( 'START TRANSACTION' ) && false !== $db->query( 'INSERT INTO `' . $table . '` VALUES (4,4)' ), 'sentinela de estado lexical' );
	$assert( true === $db->dbh->query( "SET SESSION sql_mode='NO_BACKSLASH_ESCAPES'" ), 'alteração directa de sessão injectada no ensaio' );
	$assert( false === $db->query( 'SELECT 1' ), 'modo lexical alterado directamente é recusado' );
	echo 'Fronteira complementar: ' . $checks . ' verificações.' . PHP_EOL;
} finally {
	$GLOBALS['arn_tx_filters']['query'] = array();
	foreach ( $connections as $db ) {
		try {
			$db->dbh->query( 'ROLLBACK' );
		} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Ligação terminada já reverteu a transacção no servidor.
		}
		$db->close();
	}
	$owner->dbh->query( 'DROP TABLE IF EXISTS `' . $table . '`' );
	$owner->close();
}
