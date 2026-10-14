<?php
/** Exclusive integration checks for the local document catalog.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Documents\PrivateFiles\FileSchema;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\Documents\PrivateFiles\FileRecord;
use Arn\Intranet\Documents\PrivateFiles\WpdbFileRegistry;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Documents\Catalog\CatalogSchema;
use Arn\Intranet\Documents\Catalog\DocumentCatalogPage;
use Arn\Intranet\Documents\Catalog\CurrentDocumentActor;
use Arn\Intranet\Documents\Catalog\LocalDocumentCatalog;
use Arn\Intranet\Documents\Catalog\WpdbDocumentAuthority;
use Arn\Intranet\Documents\Catalog\DocumentAuthority;
use Arn\Intranet\Documents\Catalog\WpdbDocumentRepository;
use Arn\Intranet\Documents\Catalog\Metadata;
use Arn\Intranet\Documents\Catalog\Revision;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Storage\ChangeContext;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Falhou: ' . $label );
	}
	++$checks;
};
$prefix = 'arn_dc_' . bin2hex( random_bytes( 5 ) ) . '_';
$db     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$db->query( $db->prepare( 'CREATE TABLE %i LIKE %i', $prefix . 'posts', $GLOBALS['wpdb']->posts ) );
$files = new FileSchema( $db );
try {
	$files->install();
	$catalog_schema = new CatalogSchema( $db );
	$catalog_schema->install();
	$catalog_schema->assertReady();
	$assert( null === ( new CurrentDocumentActor() )->read(), 'administrador técnico não é actor documental' );
	$assert( ! LocalDocumentCatalog::configured() || 'local' === wp_get_environment_type(), 'runtime só em local' );
	$closed = new LocalDocumentCatalog(
		new CurrentDocumentActor(),
		static function (): never {
			throw new RuntimeException( 'Leitura inesperada.' );
		},
		null,
		static fn(): bool => false
	);
	$assert( null === $closed->visible(), 'gate fechado impede consulta' );
	$registry      = new WpdbFileRegistry( $db );
	$authorization = new WpdbAuthorizationRepository( $db );
	$rejected      = false;
	try {
		$authorization->findInTransaction( 1 );
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected, 'leitura externa recusa ausência de transacção' );
	$record   = new FileRecord( new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'a', 64 ), 12, 'application/pdf' ), 'document', 1, 1, 'original', 1, time() );
	$rejected = false;
	try {
		$registry->registerInTransaction( $record );
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected, 'registo exige transacção exterior' );
	$authorization->installSchema();
	$db->query( 'START TRANSACTION' );
	$assert( null === $authorization->findInTransaction( 987654321 ), 'leitura de conta ausente na transacção exterior' );
	$denied = false;
	try {
		( new WpdbDocumentAuthority( $db, new CurrentDocumentActor() ) )->lockAndAssert( Scope::units( array( 'LAB-A' ) ), array() );
	} catch ( RuntimeException ) {
		$denied = true;
	}
	$assert( $denied, 'administrador técnico sem competência documental' );
	$registry->registerInTransaction( $record );
	$assert( '1' === (string) $db->get_var( 'SELECT @@session.in_transaction' ), 'registo preserva transacção exterior' );
	$db->query( 'ROLLBACK' );
	$assert( '0' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $files->table( 'files' ) ) ), 'rollback exterior reverte registo' );
	$authority  = new class() implements DocumentAuthority {
		// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
		public function lockAndAssert( Scope $scope, array $recipient_ids ): int {
			return 1; }
		public function assertActiveRecipients( array $recipient_ids ): void {}
	};
	$repository = new WpdbDocumentRepository( $db, $authority );
	$metadata   = new Metadata( 'Circular fictícia', 'Resumo', 'circular', 'LAB-01', 'RH teste', '2026-10-14', null, null, array( 1 ) );
	$blob       = new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'b', 64 ), 12, 'application/pdf' );
	$document   = $repository->create( Scope::units( array( 'LAB-A' ) ), new Revision( 1, $metadata, $blob, 1, time() ), 'Ensaio de criação' );
	$assert( 1 === $document->revision && null === $document->published_version && 1 === count( $document->versions ), 'criação documental' );
	$assert( $repository->find( $document->id )?->version( 1 )?->metadata->title === 'Circular fictícia', 'hidratação da versão' );
	$assert( 'Documento privado' === (string) $db->get_var( $db->prepare( 'SELECT post_title FROM %i WHERE ID=%d', $prefix . 'posts', $document->id ) ), 'âncora genérica' );
	$assert( $registry->find( $blob->id )?->object_type === 'document', 'ficheiro atómico associado' );
	$assert( 1 === count( $repository->history( $document->id ) ), 'auditoria da criação' );
	$first_event = $repository->history( $document->id )[0];
	$first_line  = $first_event instanceof stdClass ? DocumentCatalogPage::auditLine( $first_event ) : '';
	$assert( str_contains( $first_line, 'Rascunho criado' ) && str_contains( $first_line, 'Conta #1' ) && str_contains( $first_line, 'Ensaio de criação' ) && str_contains( $first_line, 'UTC' ), 'linha real de auditoria apresentada' );
	$next_blob = new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'c', 64 ), 12, 'application/pdf' );
	$next      = new Revision( 2, $metadata, $next_blob, 1, time() );
	$document  = $repository->revise( $document->id, $next, 1, 'Revisão fictícia' );
	$assert( 2 === $document->revision && 2 === count( $document->versions ), 'nova revisão' );
	$conflict = false;
	try {
		$repository->revise( $document->id, new Revision( 3, $metadata, new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'd', 64 ), 12, 'application/pdf' ), 1, time() ), 1, 'Revisão antiga' );
	} catch ( RuntimeException ) {
		$conflict = true;
	}
	$assert( $conflict && 2 === $repository->find( $document->id )?->revision, 'CAS recusa revisão antiga' );
	$document = $repository->publish( $document->id, 2, 2, 'Publicação fictícia' );
	$assert( 2 === $document->published_version && 3 === $document->revision, 'publicação' );
	$document = $repository->archive( $document->id, 3, 'Arquivo fictício' );
	$assert( $document->archived && 4 === count( $repository->history( $document->id ) ), 'arquivo terminal auditado' );
	$count_rows     = static function () use ( $db, $prefix, $files, $catalog_schema ): array {
		$tables = array(
			$catalog_schema->table( 'documents' ),
			$catalog_schema->table( 'versions' ),
			$catalog_schema->table( 'audit' ),
			$prefix . 'posts',
			$files->table( 'files' ),
			$files->table( 'audit' ),
		);
		return array_map( static fn( string $table ): int => (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $table ) ), $tables );
	};
	$before_failure = $count_rows();
	$filter         = static fn ( string $sql ): string => str_contains( $sql, 'arn_document_audit' ) && str_starts_with( $sql, 'INSERT INTO' ) ? 'INVALID AUDIT INSERT' : $sql;
	add_filter( 'query', $filter );
	$rollback_blob  = new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'e', 64 ), 12, 'application/pdf' );
	$rolled         = false;
	try {
		$repository->create( Scope::units( array( 'LAB-A' ) ), new Revision( 1, $metadata, $rollback_blob, 1, time() ), 'Falha de auditoria' );
	} catch ( RuntimeException ) {
		$rolled = true;
	}
	remove_filter( 'query', $filter );
	$assert( $rolled && $before_failure === $count_rows() && null === $registry->find( $rollback_blob->id ), 'auditoria falhada reverte post, documento, versão, ficheiro e ambas auditorias' );
	$actor_id     = 800000 + random_int( 1, 100000 );
	$recipient_id = $actor_id + 1;
	$context      = static fn ( string $reason ): ChangeContext => new ChangeContext( $actor_id, $reason, 'LAB-DOC', time() );
	$authorization->createAccount( $actor_id, $context( 'Criação fictícia' ) );
	$authorization->changeState( $actor_id, AccountState::Active, 1, $context( 'Activação fictícia' ) );
	$grant_id = $authorization->createGrant( new Grant( $actor_id, Role::HumanResources, Scope::units( array( 'LAB-A' ) ), 0, null ), 2, $context( 'Concessão fictícia' ) );
	$authorization->createAccount( $recipient_id, $context( 'Criação destinatário' ) );
	$authorization->changeState( $recipient_id, AccountState::Active, 1, $context( 'Activação destinatário' ) );
	$reader_db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$reader_db->set_prefix( $prefix );
	$reader_db->suppress_errors( true );
	$db->query( 'START TRANSACTION' );
	$db->get_results( $db->prepare( 'SELECT user_id FROM %i WHERE user_id IN (%d,%d) ORDER BY user_id FOR UPDATE', $prefix . 'arn_authz_accounts', $actor_id, $recipient_id ) );
	$reader_db->query( 'SET SESSION innodb_lock_wait_timeout=1' );
	$reader_db->query( 'START TRANSACTION' );
	$wait_result = $reader_db->get_results( $reader_db->prepare( 'SELECT user_id FROM %i WHERE user_id=%d FOR UPDATE', $prefix . 'arn_authz_accounts', $recipient_id ) );
	$wait_error  = $reader_db->last_error;
	$reader_db->query( 'ROLLBACK' );
	$db->query( 'ROLLBACK' );
	$assert( array() === $wait_result && str_contains( $wait_error, 'Lock wait timeout exceeded' ), 'duas ligações: bloqueio de conta causa timeout local e rollback' );
	$session        = array(
		'arn_authz_local'    => true,
		'arn_authz_user_id'  => $actor_id,
		'arn_authz_revision' => 3,
	);
	$current        = new CurrentDocumentActor( static fn() => new WpdbAuthorizationRepository( $reader_db ), static fn(): int => $actor_id, static fn( int $id ): array => $id === $actor_id ? $session : array() );
	$real_authority = new WpdbDocumentAuthority( $db, $current );
	$db->query( 'START TRANSACTION' );
	$assert( $actor_id === $real_authority->lockAndAssert( Scope::units( array( 'LAB-A' ) ), array( $recipient_id ) ), 'autoridade RH válida na mesma transacção' );
	$real_authority->assertActiveRecipients( array( $recipient_id ) );
	$db->query( 'ROLLBACK' );
	$real_repository = new WpdbDocumentRepository( $db, $real_authority );
	$real_metadata   = new Metadata( 'Nota com audiência', 'Resumo', 'nota-interna', 'LAB-02', 'RH teste', '2026-10-14', null, null, array( $recipient_id ) );
	$real_doc        = $real_repository->create( Scope::units( array( 'LAB-A' ) ), new Revision( 1, $real_metadata, new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'f', 64 ), 12, 'application/pdf' ), $actor_id, time() ), 'Criação autorizada' );
	$real_doc        = $real_repository->publish( $real_doc->id, 1, 1, 'Publicação autorizada' );
	$assert( 1 === $real_doc->published_version, 'publicação com destinatário activo' );
	$pending_doc = $real_repository->create( Scope::units( array( 'LAB-A' ) ), new Revision( 1, $real_metadata, new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'f', 64 ), 12, 'application/pdf' ), $actor_id, time() ), 'Outro rascunho' );
	$authorization->changeState( $recipient_id, AccountState::Suspended, 2, $context( 'Suspensão fictícia' ) );
	$blocked = false;
	try {
		$real_repository->publish( $pending_doc->id, 1, 1, 'Tentativa de publicação' );
	} catch ( RuntimeException ) {
		$blocked = true;
	}
	$assert( $blocked && null === $real_repository->find( $pending_doc->id )?->published_version, 'publicação recusa destinatário suspenso' );
	$real_doc = $real_repository->archive( $real_doc->id, 2, 'Arquivo após suspensão' );
	$assert( $real_doc->archived, 'arquivo não exige destinatário activo' );
	$authorization->revokeGrant( $actor_id, $grant_id, 3, $context( 'Revogação fictícia' ) );
	$db->query( 'START TRANSACTION' );
	$denied = false;
	try {
		$real_authority->lockAndAssert( Scope::units( array( 'LAB-A' ) ), array( $recipient_id ) );
	} catch ( RuntimeException ) {
		$denied = true;
	}
	$db->query( 'ROLLBACK' );
	$assert( $denied, 'geração antiga e grant revogado recusados' );
	$db->query( $db->prepare( 'ALTER TABLE %i DROP INDEX state_revision', $catalog_schema->table( 'documents' ) ) );
	$incompatible = false;
	try {
		$catalog_schema->assertReady();
	} catch ( RuntimeException ) {
		$incompatible = true;
	}
	$assert( $incompatible, 'índice documental adulterado recusado' );
	echo esc_html( 'Catálogo documental: ' . $checks . " verificações.\n" );
} finally {
	$db->query( 'ROLLBACK' );
	foreach ( array( 'audit', 'files', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $files->table( $name ) ) );
	}
	foreach ( array( 'audit', 'grants', 'accounts', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_authz_' . $name ) );
	}
	foreach ( array( 'audit', 'versions', 'documents', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_document' . ( 'documents' === $name ? 's' : '_' . $name ) ) );
	}
	$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'posts' ) );
}
