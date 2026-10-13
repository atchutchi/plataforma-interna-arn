<?php
/**
 * Fronteiras SQL, unicidade exigida e decisões auditadas atomicamente.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Identity\LocalIdentityLink;
use Arn\Intranet\Identity\WpdbIdentityLinkRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/doubles/IdentityWpdbDouble.php';

/**
 * Estes testes não substituem o ensaio em MySQL/MariaDB real.
 */
final class WpdbIdentityLinkRepositoryTest extends TestCase {

	/**

	 * Ligação simulada que nunca reconecta.
	 *
	 * @var IdentityWpdb
	 */
	private IdentityWpdb $database;

	/**
	 * Cada cenário começa sem schema instalado pelo código em teste.
	 */
	protected function setUp(): void {
		$this->database = new IdentityWpdb( '', '', '', '' );
	}

	/** Detecta o schema existente sem depender de uma opção WordPress. */
	public function testDiscoverExistingSchema(): void {
		self::assertTrue( ( new WpdbIdentityLinkRepository( $this->database ) )->hasSchema() );
	}

	/** Ausência completa é diferente de corrupção parcial. */
	public function testDiscoverAbsentSchema(): void {
		$this->database->tables_present = 0;
		self::assertFalse( ( new WpdbIdentityLinkRepository( $this->database ) )->hasSchema() );
	}

	/** Um schema incompleto não pode desactivar os controlos federados. */
	public function testDiscoverPartialSchemaFailsClosed(): void {
		$this->database->tables_present = 1;
		$this->expectException( RuntimeException::class );
		( new WpdbIdentityLinkRepository( $this->database ) )->hasSchema();
	}

	/**
	 * Construção e leitura não criam schema.
	 */
	public function testReadDoesNotInstallTablesAndBindsIdentity(): void {
		$repository = new WpdbIdentityLinkRepository( $this->database );
		self::assertSame( array(), $this->database->queries );
		self::assertNull( $repository->find( 'accounts.google.com', "opaque'identifier" ) );
		self::assertStringNotContainsString( 'CREATE TABLE', implode( '\n', $this->database->queries ) );
		self::assertContains( array( 'wp_arn_identity_links', 'https://accounts.google.com', "opaque'identifier" ), $this->database->bindings );
	}

	/**
	 * Sem motor transaccional não há leitura autorizadora.
	 */
	public function testNonTransactionalSchemaFailsClosed(): void {
		$this->database->engine = 'MyISAM';
		$repository             = new WpdbIdentityLinkRepository( $this->database );
		$this->expectException( RuntimeException::class );
		$repository->find( 'accounts.google.com', 'opaque-sub' );
	}

	/**
	 * Exige constraints do servidor antes de aceitar dados.
	 */
	public function testMissingUniqueConstraintsFailsClosed(): void {
		$this->database->unique = false;
		$repository             = new WpdbIdentityLinkRepository( $this->database );
		$this->expectException( RuntimeException::class );
		$repository->find( 'accounts.google.com', 'opaque-sub' );
	}

	/**
	 * Um índice composto com prefixo extra não garante um vínculo por conta.
	 */
	public function testPartiallyIndexedCompositeKeyCannotPretendUserUniqueness(): void {
		$this->database->indexes_override = array(
			(object) array(
				'Key_name'     => 'false_user_unique',
				'Non_unique'   => '0',
				'Seq_in_index' => '1',
				'Column_name'  => 'user_id',
				'Sub_part'     => null,
			),
			(object) array(
				'Key_name'     => 'false_user_unique',
				'Non_unique'   => '0',
				'Seq_in_index' => '2',
				'Column_name'  => 'issuer',
				'Sub_part'     => '10',
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
		);
		$repository                       = new WpdbIdentityLinkRepository( $this->database );
		$this->expectException( RuntimeException::class );
		$repository->find( 'accounts.google.com', 'opaque-sub' );
	}

	/**
	 * Um schema de auditoria alterado não pode truncar a identidade do actor.
	 */
	public function testMalformedAuditSchemaFailsClosed(): void {
		$this->database->audit_actor_type = 'tinyint unsigned';
		$repository                       = new WpdbIdentityLinkRepository( $this->database );
		$this->expectException( RuntimeException::class );
		$repository->find( 'accounts.google.com', 'opaque-sub' );
	}

	/**
	 * Uma coluna estreita não pode truncar o ID local para outra conta.
	 */
	public function testMalformedLocalUserColumnFailsClosed(): void {
		$this->database->link_user_type = 'tinyint unsigned';
		$repository                     = new WpdbIdentityLinkRepository( $this->database );
		$this->expectException( RuntimeException::class );
		$repository->find( 'accounts.google.com', 'opaque-sub' );
	}

	/**
	 * Um sub opaco não pode usar collation textual insensível a maiúsculas.
	 */
	public function testTextSubjectColumnFailsClosed(): void {
		$this->database->subject_type = 'varchar(255)';
		$repository                   = new WpdbIdentityLinkRepository( $this->database );
		$this->expectException( RuntimeException::class );
		$repository->find( 'accounts.google.com', 'opaque-sub' );
	}

	/**
	 * Auditoria e criação ficam entre BEGIN e COMMIT.
	 */
	public function testCreationCommitsOnlyAfterAuditAndOmitsIdentityFromAudit(): void {
		$repository = new WpdbIdentityLinkRepository( $this->database );
		$repository->create( new LocalIdentityLink( 12, 'accounts.google.com', 'opaque-sub', false, false ), 7 );
		$queries = $this->database->queries;
		$start   = array_search( 'START TRANSACTION', $queries, true );
		self::assertNotFalse( $start );
		self::assertStringStartsWith( 'INSERT INTO `wp_arn_identity_links`', $queries[ $start + 1 ] );
		self::assertStringStartsWith( 'INSERT INTO `wp_arn_identity_audit`', $queries[ $start + 2 ] );
		self::assertStringNotContainsString( 'opaque-sub', $queries[ $start + 2 ] );
		self::assertStringNotContainsString( 'accounts.google.com', $queries[ $start + 2 ] );
		self::assertSame( 'COMMIT', $queries[ $start + 3 ] );
		self::assertFalse( $this->database->suppressed );
	}

	/**
	 * Falha de auditoria impede confirmação e pede rollback.
	 */
	public function testAuditFailureRollsBackCreationWithSanitizedError(): void {
		$this->database->fail_prefix = 'INSERT INTO `wp_arn_identity_audit`';
		$repository                  = new WpdbIdentityLinkRepository( $this->database );
		try {
			$repository->create( new LocalIdentityLink( 12, 'accounts.google.com', 'opaque-sub', false, false ), 7 );
			self::fail( 'A auditoria falhou mas a operação foi aceite.' );
		} catch ( RuntimeException $error ) {
			self::assertStringNotContainsString( 'privado', $error->getMessage() );
			self::assertNull( $error->getPrevious() );
		}
		self::assertSame( 'ROLLBACK', end( $this->database->queries ) );
		self::assertNotContains( 'COMMIT', $this->database->queries );
		self::assertFalse( $this->database->suppressed );
	}

	/**
	 * Falha de INSERT, incluindo conflito unique, não escreve auditoria.
	 */
	public function testLinkConflictRollsBackWithoutAudit(): void {
		$this->database->fail_prefix = 'INSERT INTO `wp_arn_identity_links`';
		$repository                  = new WpdbIdentityLinkRepository( $this->database );
		try {
			$repository->create( new LocalIdentityLink( 12, 'accounts.google.com', 'opaque-sub', false, false ), 7 );
			self::fail( 'O conflito foi aceite.' );
		} catch ( RuntimeException ) {
			self::assertSame( 'ROLLBACK', end( $this->database->queries ) );
			self::assertStringNotContainsString( 'INSERT INTO `wp_arn_identity_audit`', implode( '\n', $this->database->queries ) );
		}
	}

	/**
	 * Estados anteriores são bloqueados antes da alteração.
	 */
	public function testActivationLocksAndAuditsPreviousState(): void {
		$this->database->rows = array( $this->row( '0', '0' ) );
		$repository           = new WpdbIdentityLinkRepository( $this->database );
		$repository->changeState( 12, true, true, 7 );
		$sql = implode( '\n', $this->database->queries );
		self::assertStringContainsString( 'FOR UPDATE', $sql );
		self::assertStringContainsString( "'activate', 0, 0, 1, 1", $sql );
		self::assertSame( 'COMMIT', end( $this->database->queries ) );
	}

	/**
	 * Alteração sem vínculo prévio falha e não cria uma associação.
	 */
	public function testMissingLinkCannotBeActivated(): void {
		$repository = new WpdbIdentityLinkRepository( $this->database );
		try {
			$repository->changeState( 12, true, true, 7 );
			self::fail( 'Foi activado um vínculo ausente.' );
		} catch ( RuntimeException ) {
			self::assertSame( 'ROLLBACK', end( $this->database->queries ) );
			self::assertStringNotContainsString( 'UPDATE ', implode( '\n', $this->database->queries ) );
		}
	}

	/**
	 * Falha de auditoria também reverte uma mudança de estado.
	 */
	public function testAuditFailureRollsBackStateChange(): void {
		$this->database->rows        = array( $this->row( '0', '0' ) );
		$this->database->fail_prefix = 'INSERT INTO `wp_arn_identity_audit`';
		$repository                  = new WpdbIdentityLinkRepository( $this->database );
		try {
			$repository->changeState( 12, true, true, 7 );
			self::fail( 'A alteração foi confirmada sem auditoria.' );
		} catch ( RuntimeException ) {
			self::assertSame( 'ROLLBACK', end( $this->database->queries ) );
			self::assertNotContains( 'COMMIT', $this->database->queries );
		}
	}

	/**
	 * Falha de UPDATE nunca produz uma decisão de activação confirmada.
	 */
	public function testFailedStateWriteDoesNotInsertAudit(): void {
		$this->database->rows        = array( $this->row( '0', '0' ) );
		$this->database->fail_prefix = 'UPDATE ';
		$repository                  = new WpdbIdentityLinkRepository( $this->database );
		try {
			$repository->changeState( 12, true, true, 7 );
			self::fail( 'Uma escrita falhada foi confirmada.' );
		} catch ( RuntimeException ) {
			self::assertSame( 'ROLLBACK', end( $this->database->queries ) );
			self::assertStringNotContainsString( 'INSERT INTO `wp_arn_identity_audit`', implode( '\n', $this->database->queries ) );
		}
	}

	/**
	 * COMMIT sem confirmação não é apresentado como sucesso.
	 */
	public function testFailedCommitIsNotReportedAsSuccess(): void {
		$this->database->fail_prefix = 'COMMIT';
		$repository                  = new WpdbIdentityLinkRepository( $this->database );
		try {
			$repository->create( new LocalIdentityLink( 12, 'accounts.google.com', 'opaque-sub', false, false ), 7 );
			self::fail( 'COMMIT falhado foi apresentado como sucesso.' );
		} catch ( RuntimeException ) {
			self::assertSame( 'ROLLBACK', end( $this->database->queries ) );
		}
	}

	/**
	 * A recusa de transacção aninhada não confirma ou reverte a anterior.
	 */
	public function testExistingTransactionGuardFailsBeforeStart(): void {
		$this->database->fail_prefix = 'SET TRANSACTION';
		$repository                  = new WpdbIdentityLinkRepository( $this->database );
		try {
			$repository->create( new LocalIdentityLink( 12, 'accounts.google.com', 'opaque-sub', false, false ), 7 );
			self::fail( 'Uma transacção prévia não foi respeitada.' );
		} catch ( RuntimeException ) {
			self::assertNotContains( 'START TRANSACTION', $this->database->queries );
			self::assertNotContains( 'COMMIT', $this->database->queries );
			self::assertNotContains( 'ROLLBACK', $this->database->queries );
		}
	}

	/**
	 * Sem início confirmado, não executa escritas ou rollback alheio.
	 */
	public function testFailedStartPreventsAllWrites(): void {
		$this->database->fail_prefix = 'START TRANSACTION';
		$repository                  = new WpdbIdentityLinkRepository( $this->database );
		try {
			$repository->create( new LocalIdentityLink( 12, 'accounts.google.com', 'opaque-sub', false, false ), 7 );
			self::fail( 'Foi escrita identidade sem transacção iniciada.' );
		} catch ( RuntimeException ) {
			self::assertStringNotContainsString( 'INSERT ', implode( '\n', $this->database->queries ) );
			self::assertNotContains( 'ROLLBACK', $this->database->queries );
		}
	}

	/**
	 * A revogação conserva a identidade e regista ambos os estados anteriores.
	 */
	public function testRevocationAuditsPreviousActiveState(): void {
		$this->database->rows = array( $this->row() );
		$repository           = new WpdbIdentityLinkRepository( $this->database );
		$repository->changeState( 12, false, false, 7 );
		$sql = implode( '\n', $this->database->queries );
		self::assertStringContainsString( "'revoke', 1, 1, 0, 0", $sql );
		self::assertStringNotContainsString( 'DELETE ', $sql );
		self::assertSame( 'COMMIT', end( $this->database->queries ) );
	}

	/**
	 * Repetir o mesmo estado não inventa uma nova transição auditada.
	 */
	public function testRepeatedStateChangeIsIdempotent(): void {
		$this->database->rows = array( $this->row() );
		$repository           = new WpdbIdentityLinkRepository( $this->database );
		$repository->changeState( 12, true, true, 7 );
		$sql = implode( '\n', $this->database->queries );
		self::assertStringNotContainsString( 'UPDATE ', $sql );
		self::assertStringNotContainsString( 'INSERT INTO `wp_arn_identity_audit`', $sql );
		self::assertSame( 'COMMIT', end( $this->database->queries ) );
	}

	/**
	 * Erro SQL na consulta não equivale a uma lista vazia utilizável.
	 */
	public function testFailedIdentityReadThrowsControlledException(): void {
		$this->database->fail_prefix = 'SELECT user_id';
		$repository                  = new WpdbIdentityLinkRepository( $this->database );
		$this->expectException( RuntimeException::class );
		$repository->find( 'accounts.google.com', 'opaque-sub' );
	}

	/**
	 * Uma colecção ambígua nunca resolve uma conta.
	 */
	public function testAmbiguousLookupIsRejected(): void {
		$this->database->rows = array( $this->row(), $this->row() );
		$repository           = new WpdbIdentityLinkRepository( $this->database );
		self::assertNull( $repository->find( 'accounts.google.com', 'opaque-sub' ) );
	}

	/**
	 * Flags corrompidas não são convertidas em booleano verdadeiro.
	 */
	public function testMalformedFlagsAreRejected(): void {
		$this->database->rows = array( $this->row( '2', '1' ) );
		$repository           = new WpdbIdentityLinkRepository( $this->database );
		self::assertNull( $repository->find( 'accounts.google.com', 'opaque-sub' ) );
	}

	/**
	 * Sub e emissor são comparados de novo depois do armazenamento.
	 */
	public function testReadResolvesOnlyExactCanonicalIdentity(): void {
		$this->database->rows = array( $this->row() );
		$repository           = new WpdbIdentityLinkRepository( $this->database );
		self::assertSame( 12, $repository->find( 'accounts.google.com', 'opaque-sub' )->user_id );
		self::assertNull( $repository->find( 'accounts.google.com', 'OPAQUE-SUB' ) );
	}

	/**
	 * A pesquisa pelo ID não usa email, cache ou criação de tabelas.
	 */
	public function testUserLookupBindsExactIdWithoutInstallingTables(): void {
		$this->database->rows = array( $this->row() );
		$repository           = new WpdbIdentityLinkRepository( $this->database );
		self::assertSame( 12, $repository->findForUser( 12 )->user_id );
		$sql = implode( '\n', $this->database->queries );
		self::assertStringContainsString( 'WHERE user_id = 12 LIMIT 2', $sql );
		self::assertStringNotContainsString( 'CREATE TABLE', $sql );
		self::assertContains( array( 'wp_arn_identity_links', 12 ), $this->database->bindings );
	}

	/**
	 * Só a ausência efectiva de linhas identifica uma conta sem vínculo.
	 */
	public function testUserLookupReturnsNullForNoRows(): void {
		$repository = new WpdbIdentityLinkRepository( $this->database );
		self::assertNull( $repository->findForUser( 12 ) );
	}

	/**
	 * A troca de ID na resposta não é apresentada como vínculo ausente.
	 */
	public function testUserLookupRejectsUnexpectedRows(): void {
		$this->database->rows = array( $this->row() );
		$repository           = new WpdbIdentityLinkRepository( $this->database );
		$this->expectException( RuntimeException::class );
		$repository->findForUser( 13 );
	}

	/**
	 * Vários vínculos não podem contornar a exigência de autenticação federada.
	 */
	public function testUserLookupRejectsAmbiguousRows(): void {
		$this->database->rows = array( $this->row(), $this->row() );
		$repository           = new WpdbIdentityLinkRepository( $this->database );
		$this->expectException( RuntimeException::class );
		$repository->findForUser( 12 );
	}

	/**
	 * Cada pedido reconsulta o vínculo e observa revogação corrente.
	 */
	public function testUserLookupDoesNotCacheActiveState(): void {
		$this->database->rows = array( $this->row() );
		$repository           = new WpdbIdentityLinkRepository( $this->database );
		self::assertTrue( $repository->findForUser( 12 )->active );
		$this->database->rows = array( $this->row( '0', '0' ) );
		self::assertFalse( $repository->findForUser( 12 )->active );
		self::assertFalse( $repository->findForUser( 12 )->authorized );
	}

	/**
	 * Contas inválidas não consultam SQL.
	 */
	public function testInvalidUserIdDoesNotQueryStorage(): void {
		$repository = new WpdbIdentityLinkRepository( $this->database );
		self::assertNull( $repository->findForUser( 0 ) );
		self::assertNull( $repository->findForUser( -1 ) );
		self::assertSame( array(), $this->database->queries );
	}

	/**
	 * Metadata corrompida não passa pela pesquisa por conta.
	 */
	public function testUserLookupRejectsMalformedFlags(): void {
		$this->database->rows = array( $this->row( '2', '1' ) );
		$repository           = new WpdbIdentityLinkRepository( $this->database );
		try {
			$repository->findForUser( 12 );
		} catch ( RuntimeException $error ) {
			self::assertSame( 'Persistência de identidade indisponível.', $error->getMessage() );
			self::assertNull( $error->getPrevious() );
			self::assertFalse( $this->database->suppressed );
			return;
		}
		self::fail( 'Dados corrompidos foram apresentados como vínculo ausente.' );
	}

	/**
	 * Schema inválido nega a leitura também pelo ID local.
	 */
	public function testUserLookupChecksSchemaBeforeReadingLink(): void {
		$this->database->engine = 'MyISAM';
		$repository             = new WpdbIdentityLinkRepository( $this->database );
		try {
			$repository->findForUser( 12 );
			self::fail( 'O vínculo foi lido sem schema transaccional.' );
		} catch ( RuntimeException ) {
			self::assertStringNotContainsString( 'SELECT user_id', implode( '\n', $this->database->queries ) );
		}
	}

	/**
	 * Erro de transporte não se confunde com conta sem vínculo.
	 */
	public function testUserLookupReadFailureIsControlled(): void {
		$this->database->fail_prefix = 'SELECT user_id';
		$repository                  = new WpdbIdentityLinkRepository( $this->database );
		$this->expectException( RuntimeException::class );
		$repository->findForUser( 12 );
	}

	/**
	 * A instalação é uma chamada explícita com motor fixado.
	 */
	public function testExplicitSchemaInstallationUsesInnoDb(): void {
		$repository = new WpdbIdentityLinkRepository( $this->database );
		$repository->installSchema();
		$sql = implode( '\n', $this->database->queries );
		self::assertSame( 2, substr_count( $sql, 'CREATE TABLE IF NOT EXISTS' ) );
		self::assertSame( 2, substr_count( $sql, 'ENGINE=InnoDB' ) );
		self::assertStringContainsString( 'UNIQUE KEY issuer_subject (issuer, subject)', $sql );
		self::assertStringContainsString( 'PRIMARY KEY (user_id)', $sql );
	}

	/**
	 * O transporte de identidade recusa reconexão automática.
	 */
	public function testConnectionNeverReconnectsOrBails(): void {
		self::assertFalse( $this->database->check_connection( true ) );
		self::assertFalse( $this->database->check_connection( false ) );
	}

	/**
	 * Registo literal, com tipos reais devolvidos por mysqli.
	 *
	 * @param string $authorized Flag guardada.
	 * @param string $active Flag guardada.
	 */
	private function row( string $authorized = '1', string $active = '1' ): object {
		return (object) array(
			'user_id'    => '12',
			'issuer'     => 'https://accounts.google.com',
			'subject'    => 'opaque-sub',
			'authorized' => $authorized,
			'active'     => $active,
		);
	}
}
