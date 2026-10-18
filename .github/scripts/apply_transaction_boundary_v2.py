from __future__ import annotations

from pathlib import Path


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: esperado 1 contexto, encontrado {count}")
    return text.replace(old, new, 1)


def update_transaction_boundary() -> None:
    path = Path("wp-content/plugins/arn-intranet-core/src/Identity/TransactionBoundary.php")
    text = path.read_text(encoding="utf-8")

    text = replace_once(
        text,
        "\t/** Consulta de leitura. */\n\tprivate const READ_STATEMENT = 'read_statement';",
        "\t/** Limite controlado de espera por bloqueio da sessão. */\n"
        "\tprivate const SET_LOCK_TIMEOUT = 'set_lock_timeout';\n\n"
        "\t/** Consulta de leitura. */\n"
        "\tprivate const READ_STATEMENT = 'read_statement';",
        "constante do timeout",
    )

    text = replace_once(
        text,
        "\t\t\tcase self::BEGIN:\n"
        "\t\t\t\t$this->requireState( self::IDLE, 'Transacção aninhada recusada.' );\n"
        "\t\t\t\t$prepared = 'START TRANSACTION';\n"
        "\t\t\t\tbreak;",
        "\t\t\tcase self::BEGIN:\n"
        "\t\t\t\t$this->requireState( self::IDLE, 'Transacção aninhada recusada.' );\n"
        "\t\t\t\t$prepared = $statement['sql'];\n"
        "\t\t\t\tbreak;",
        "START preparado",
    )

    text = replace_once(
        text,
        "\t\t\tcase self::SET_ISOLATION:\n"
        "\t\t\t\t$this->requireState( self::IDLE, 'Isolamento durante transacção recusado.' );\n"
        "\t\t\t\t$prepared = 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED';\n"
        "\t\t\t\tbreak;",
        "\t\t\tcase self::SET_ISOLATION:\n"
        "\t\t\tcase self::SET_LOCK_TIMEOUT:\n"
        "\t\t\t\t$this->requireState( self::IDLE, 'Configuração durante transacção recusada.' );\n"
        "\t\t\t\t$prepared = $statement['sql'];\n"
        "\t\t\t\tbreak;",
        "configurações de sessão",
    )

    text = replace_once(
        text,
        "\t\t$cleaned = self::sanitize( $sql );",
        "\t\t$cleaned = self::isTriggerStatement( $sql ) ? self::sanitizeTrigger( $sql ) : self::sanitize( $sql );",
        "selecção do sanitizador",
    )

    text = replace_once(
        text,
        "\t\tif ( 1 === preg_match( '/^(START TRANSACTION|BEGIN(?: WORK)?)$/D', $normalized ) ) {\n"
        "\t\t\treturn array(\n"
        "\t\t\t\t'kind' => self::BEGIN,\n"
        "\t\t\t\t'sql'  => 'START TRANSACTION',\n"
        "\t\t\t);\n"
        "\t\t}",
        "\t\tif ( 'START TRANSACTION WITH CONSISTENT SNAPSHOT' === $normalized ) {\n"
        "\t\t\treturn array(\n"
        "\t\t\t\t'kind' => self::BEGIN,\n"
        "\t\t\t\t'sql'  => 'START TRANSACTION WITH CONSISTENT SNAPSHOT',\n"
        "\t\t\t);\n"
        "\t\t}\n"
        "\t\tif ( 1 === preg_match( '/^(START TRANSACTION|BEGIN(?: WORK)?)$/D', $normalized ) ) {\n"
        "\t\t\treturn array(\n"
        "\t\t\t\t'kind' => self::BEGIN,\n"
        "\t\t\t\t'sql'  => 'START TRANSACTION',\n"
        "\t\t\t);\n"
        "\t\t}",
        "snapshot consistente",
    )

    text = replace_once(
        text,
        "\t\tif ( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' === $normalized ) {\n"
        "\t\t\treturn array(\n"
        "\t\t\t\t'kind' => self::SET_ISOLATION,\n"
        "\t\t\t\t'sql'  => $cleaned,\n"
        "\t\t\t);\n"
        "\t\t}",
        "\t\tif ( in_array( $normalized, array( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED', 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ' ), true ) ) {\n"
        "\t\t\treturn array(\n"
        "\t\t\t\t'kind' => self::SET_ISOLATION,\n"
        "\t\t\t\t'sql'  => $normalized,\n"
        "\t\t\t);\n"
        "\t\t}\n"
        "\t\tif ( 1 === preg_match( '/^SET SESSION INNODB_LOCK_WAIT_TIMEOUT\\s*=\\s*([1-9][0-9]{0,2})$/D', $normalized, $matches ) ) {\n"
        "\t\t\treturn array(\n"
        "\t\t\t\t'kind' => self::SET_LOCK_TIMEOUT,\n"
        "\t\t\t\t'sql'  => 'SET SESSION innodb_lock_wait_timeout = ' . (int) $matches[1],\n"
        "\t\t\t);\n"
        "\t\t}",
        "isolamento e timeout",
    )

    marker = (
        "\t/**\n"
        "\t * Remove comentários ordinários, rejeita comentários executáveis e impede\n"
        "\t * múltiplas instruções. O conteúdo entre aspas é preservado integralmente."
    )
    helpers = """\t/** Confirma se a instrução pertence ao contrato fechado de triggers de ensaio. */
\tprivate static function isTriggerStatement( string $sql ): bool {
\t\treturn 1 === preg_match( '/^\\s*CREATE\\s+TRIGGER\\b/i', $sql );
\t}

\t/**
\t * Aceita apenas os dois formatos de trigger usados para injectar falhas nos
\t * ensaios. Os pontos e vírgulas internos pertencem ao corpo BEGIN/END e não
\t * abrem uma segunda instrução arbitrária.
\t *
\t * @param string $sql Definição do trigger.
\t * @return string Definição validada sem terminador final.
\t * @throws RuntimeException Quando o trigger sai do contrato fechado.
\t */
\tprivate static function sanitizeTrigger( string $sql ): string {
\t\tif ( 1 === preg_match( '/\\/\\*|#|--[\\x00-\\x20]/', $sql ) ) {
\t\t\tthrow new RuntimeException( 'Comentário SQL em trigger recusado.' );
\t\t}

\t\t$cleaned = trim( $sql );
\t\tif ( str_ends_with( $cleaned, ';' ) ) {
\t\t\t$cleaned = rtrim( substr( $cleaned, 0, -1 ) );
\t\t}

\t\t$identifier = '(?:`(?:``|[^`])+`|[A-Za-z0-9_]+)';
\t\t$literal    = \"'(?:''|\\\\\\\\.|[^'])*'\";
\t\t$signal     = \"SIGNAL\\\\s+SQLSTATE\\\\s+{$literal}\\\\s+SET\\\\s+MESSAGE_TEXT\\\\s*=\\\\s*{$literal}\";
\t\t$prefix     = \"^CREATE\\\\s+TRIGGER\\\\s+{$identifier}\\\\s+(?:BEFORE|AFTER)\\\\s+(?:INSERT|UPDATE|DELETE)\\\\s+ON\\\\s+{$identifier}\\\\s+FOR\\\\s+EACH\\\\s+ROW\\\\s+\";
\t\t$simple     = \"/{$prefix}{$signal}$/isD\";
\t\t$compound   = \"/{$prefix}BEGIN\\\\s+IF\\\\s+NEW\\\\.[A-Za-z0-9_]+\\\\s*=\\\\s*(?:-?[0-9]+|{$literal})\\\\s+THEN\\\\s+{$signal}\\\\s*;\\\\s*END\\\\s+IF\\\\s*;\\\\s*END$/isD\";

\t\tif ( 1 !== preg_match( $simple, $cleaned ) && 1 !== preg_match( $compound, $cleaned ) ) {
\t\t\tthrow new RuntimeException( 'Trigger SQL fora do contrato recusado.' );
\t\t}

\t\treturn $cleaned;
\t}

"""
    text = replace_once(text, marker, helpers + marker, "helpers de trigger")
    path.write_text(text, encoding="utf-8")


def update_identity_wpdb() -> None:
    path = Path("wp-content/plugins/arn-intranet-core/src/Identity/IdentityWpdb.php")
    text = path.read_text(encoding="utf-8")

    text = replace_once(
        text,
        "\t\t\t\t'START TRANSACTION',\n"
        "\t\t\t\t'COMMIT AND NO CHAIN NO RELEASE',\n"
        "\t\t\t\t'ROLLBACK AND NO CHAIN NO RELEASE',\n"
        "\t\t\t\t'SET TRANSACTION ISOLATION LEVEL READ COMMITTED',",
        "\t\t\t\t'START TRANSACTION',\n"
        "\t\t\t\t'START TRANSACTION WITH CONSISTENT SNAPSHOT',\n"
        "\t\t\t\t'COMMIT AND NO CHAIN NO RELEASE',\n"
        "\t\t\t\t'ROLLBACK AND NO CHAIN NO RELEASE',\n"
        "\t\t\t\t'SET TRANSACTION ISOLATION LEVEL READ COMMITTED',\n"
        "\t\t\t\t'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',",
        "lista de fronteiras",
    )

    text = replace_once(
        text,
        "\t\t) || 1 === preg_match( '/^(SAVEPOINT|ROLLBACK(?: WORK)? TO|RELEASE SAVEPOINT)\\b/i', $sql );",
        "\t\t) || 1 === preg_match( '/^(SAVEPOINT|ROLLBACK(?: WORK)? TO|RELEASE SAVEPOINT)\\b/i', $sql )\n"
        "\t\t\t|| 1 === preg_match( '/^SET SESSION innodb_lock_wait_timeout = [1-9][0-9]{0,2}$/D', $sql );",
        "retorno de isBoundaryCommand",
    )
    path.write_text(text, encoding="utf-8")


def update_boundary_tests() -> None:
    path = Path("tests/phpunit/TransactionBoundaryTest.php")
    text = path.read_text(encoding="utf-8")
    marker = "\t/**\n\t * Cria uma fronteira com transacção activa."
    addition = """\t/** Snapshot consistente, isolamento e timeout preservam o contrato existente. */
\tpublic function testExistingSnapshotAndLockTimeoutCommandsRemainSupported(): void {
\t\t$boundary = new TransactionBoundary();
\t\t$boundary->markReady();

\t\tself::assertSame( 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ', $boundary->prepare( 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ' ) );
\t\t$boundary->confirm( true );
\t\tself::assertSame( 'SET SESSION innodb_lock_wait_timeout = 1', $boundary->prepare( 'SET SESSION innodb_lock_wait_timeout=1' ) );
\t\t$boundary->confirm( true );
\t\tself::assertSame( 'START TRANSACTION WITH CONSISTENT SNAPSHOT', $boundary->prepare( 'START TRANSACTION WITH CONSISTENT SNAPSHOT' ) );
\t\t$boundary->confirm( true );
\t\tself::assertTrue( $boundary->inTransaction() );
\t\t$this->expectDenied( static fn() => $boundary->prepare( 'SET SESSION innodb_lock_wait_timeout=2' ) );
\t\tself::assertSame( 'ROLLBACK AND NO CHAIN NO RELEASE', $boundary->prepare( 'ROLLBACK' ) );
\t\t$boundary->confirm( true );
\t\tself::assertFalse( $boundary->inTransaction() );
\t}

\t/** Triggers fechados de falha são uma única DDL, sem aceitar SQL adicional. */
\tpublic function testClosedFailureTriggersAreAcceptedOnlyWhileIdle(): void {
\t\t$boundary = new TransactionBoundary();
\t\t$boundary->markReady();

\t\t$simple = \"CREATE TRIGGER arn_simple BEFORE INSERT ON arn_table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='simple'\";
\t\tself::assertSame( $simple, $boundary->prepare( $simple ) );
\t\t$boundary->confirm( true );

\t\t$compound = \"CREATE TRIGGER arn_compound BEFORE INSERT ON arn_table FOR EACH ROW BEGIN IF NEW.id=1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='compound'; END IF; END\";
\t\tself::assertSame( $compound, $boundary->prepare( $compound ) );
\t\t$boundary->confirm( true );

\t\t$this->expectDenied( static fn() => $boundary->prepare( \"CREATE TRIGGER arn_bad BEFORE INSERT ON arn_table FOR EACH ROW BEGIN SELECT 1; DROP TABLE arn_table; END\" ) );

\t\t$boundary->prepare( 'START TRANSACTION' );
\t\t$boundary->confirm( true );
\t\t$this->expectDenied( static fn() => $boundary->prepare( $simple ) );
\t\tself::assertSame( 'ROLLBACK AND NO CHAIN NO RELEASE', $boundary->prepare( 'ROLLBACK' ) );
\t\t$boundary->confirm( true );
\t}

"""
    text = replace_once(text, marker, addition + marker, "testes de regressão")
    path.write_text(text, encoding="utf-8")


def update_mysql_integration() -> None:
    path = Path("tests/integration/mysql-transaction-state.php")
    text = path.read_text(encoding="utf-8")
    marker = "\t$assert( ! $db->inTransaction(), 'sessão inicial fora de transacção' );"
    addition = """

\t$assert( 0 === $db->query( 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ' ), 'isolamento repeatable read aceite' );
\t$assert( 0 === $db->query( 'SET SESSION innodb_lock_wait_timeout=1' ), 'timeout de lock controlado aceite' );
\t$assert( 0 === $db->query( 'START TRANSACTION WITH CONSISTENT SNAPSHOT' ) && $db->inTransaction(), 'snapshot consistente inicia transacção' );
\t$assert( 0 === $db->query( 'ROLLBACK' ) && ! $db->inTransaction(), 'snapshot consistente termina por rollback' );"""
    text = replace_once(text, marker, marker + addition, "integração MySQL")
    path.write_text(text, encoding="utf-8")


def update_news_integration() -> None:
    path = Path("tests/integration/news-editorial.php")
    text = path.read_text(encoding="utf-8")
    text = replace_once(
        text,
        "if ( 'COMMIT' === $sql && ! $intercepted )",
        "if ( 'COMMIT AND NO CHAIN NO RELEASE' === $sql && ! $intercepted )",
        "intercepção editorial do commit",
    )
    path.write_text(text, encoding="utf-8")


def remove_temporary_files() -> None:
    for path in (
        Path(".github/workflows/inventariar-sql-transaccional-v2.yml"),
        Path(".github/workflows/aplicar-correcao-fronteira-v2.yml"),
        Path(".github/scripts/apply_transaction_boundary_v2.py"),
    ):
        if path.exists():
            path.unlink()


def main() -> None:
    update_transaction_boundary()
    update_identity_wpdb()
    update_boundary_tests()
    update_mysql_integration()
    update_news_integration()
    remove_temporary_files()


if __name__ == "__main__":
    main()
