<?php
/** Read-only reconciliation against exclusive disposable SQL and physical fixtures.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Authorization\Storage\{AuthorizationSchema, WpdbAuthorizationRepository};
use Arn\Intranet\Documents\Catalog\CatalogSchema;
use Arn\Intranet\Documents\PrivateFiles\{FileRecord, FileSchema, PrivateBlobStore, WpdbFileRegistry, FileReconciliation, ReconciliationSnapshot, ReconciliationPage};
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\News\NewsSchema;

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
$checks = 0;
$assert = static function ( bool $condition, string $label ) use ( &$checks ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Falhou: ' . $label );
	}
	++$checks;
};
$prefix = 'arn_rc_' . bin2hex( random_bytes( 6 ) ) . '_';
$db     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$files   = new FileSchema( $db );
$catalog = new CatalogSchema( $db );
$news    = new NewsSchema( $db );
$auth    = new AuthorizationSchema( $db );
$base    = sys_get_temp_dir() . '/arn-reconcile-' . bin2hex( random_bytes( 8 ) );
mkdir( $base );
mkdir( $base . '/private' );
mkdir( $base . '/public' );
$execute = static function ( string $sql ) use ( $db ): void {
	if ( false === $db->query( $sql ) || '' !== $db->last_error ) {
		throw new RuntimeException( 'Preparação fictícia falhou.' );
	}
};
$filter  = null;
try {
	$assert( ! ReconciliationPage::allowed(), 'CLI sem sessão nativa não abre a página' );
	$files->install();
	$reader = new ReconciliationSnapshot( $db );
	$assert( null === $reader->read()['documents'] && null === $reader->read()['news'] && null === $reader->read()['tasks'] && null === $reader->read()['requests'], 'módulos ausentes identificados' );
	$execute( $db->prepare( 'CREATE TABLE %i LIKE %i', $prefix . 'posts', $GLOBALS['wpdb']->posts ) );
	( new WpdbAuthorizationRepository( $db ) )->installSchema();
	$catalog->install();
	$news->install();
	file_put_contents( $base . '/public/source.pdf', "%PDF-1.4\n%%EOF\n" );
	$store    = new PrivateBlobStore( $base . '/private', array( $base . '/public' ), array( 'application/pdf' ), 1048576 );
	$registry = new WpdbFileRegistry( $db );
	$blob     = $store->importFile( $base . '/public/source.pdf' );
	$registry->register( new FileRecord( $blob, 'document', 10, 1, 'original', 1, 100 ) );
	$execute( $db->prepare( "INSERT INTO %i (id,scope_type,scope_targets_json,revision,published_version,archived,created_at) VALUES (10,'institutional','[]',1,NULL,0,100)", $catalog->table( 'documents' ) ) );
	$execute( $db->prepare( "INSERT INTO %i (document_id,number,metadata_json,blob_id,sha256,size_bytes,mime,actor_id,created_at) VALUES (10,1,'{}',%s,%s,%d,%s,1,100)", $catalog->table( 'versions' ), $blob->id, $blob->sha256, $blob->size, $blob->mime ) );
	$news_blob = $store->importFile( $base . '/public/source.pdf' );
	$registry->register( new FileRecord( $news_blob, 'news_asset', 20, 1, 'original', 1, 100 ) );
	$execute( $db->prepare( "INSERT INTO %i (id,author_id,review_queue,revision,created_at) VALUES (30,1,'fixture',1,100)", $news->table( 'news' ) ) );
	$execute( $db->prepare( "INSERT INTO %i (article_id,number,title,body,category,actor_id,created_at) VALUES (30,1,'Fictício','Fictício','circular',1,100)", $news->table( 'versions' ) ) );
	$execute( $db->prepare( "INSERT INTO %i (id,article_id,version,kind,position,blob_id,sha256,size_bytes,mime,description) VALUES (20,30,1,'attachment',0,%s,%s,%d,%s,'Fictício')", $news->table( 'assets' ), $news_blob->id, $news_blob->sha256, $news_blob->size, $news_blob->mime ) );
	$service   = new FileReconciliation( $reader->read( ... ), $store );
	$before    = $reader->read();
	$inventory = $store->inventory();
	$queries   = array();
	$filter    = static function ( string $sql ) use ( &$queries ): string {
		$queries[] = $sql;
		return $sql;
	};
	add_filter( 'query', $filter );
	$report = $service->run();
	remove_filter( 'query', $filter );
	$filter = null;
	$assert( $report['complete'] && 0 === $report['total_findings'] && 2 === $report['checked'], 'documento e notícia íntegros' );
	$assert(
		array(
			'documents' => true,
			'news'      => true,
			'tasks'     => false,
			'requests'  => false,
		) === $report['coverage'],
		'documentos e notícias incluídos, tarefas e pedidos ausentes sem cobertura inventada'
	);
	$assert( $reader->read() === $before && $store->inventory() === $inventory, 'diagnóstico conserva linhas e bytes' );
	$assert( array() === array_filter( $queries, static fn( string $sql ): bool => 1 === preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE)\b/i', $sql ) ), 'nenhum DML ou DDL no diagnóstico' );
	$assert( 0 === $service->run()['total_findings'], 'repetição sem escrita' );
	$codes = static fn(): array => array_column( $service->run()['findings'], 'code' );
	$execute( $db->prepare( 'DELETE FROM %i WHERE blob_id=%s', $files->table( 'audit' ), $blob->id ) );
	$assert( in_array( 'audit_missing', $codes(), true ), 'auditoria ausente' );
	$execute( $db->prepare( "INSERT INTO %i (blob_id,event,actor_id,occurred_at) VALUES (%s,'file_registered',2,100)", $files->table( 'audit' ), $blob->id ) );
	$assert( in_array( 'audit_mismatch', $codes(), true ), 'actor divergente' );
	$execute( $db->prepare( 'UPDATE %i SET actor_id=1 WHERE blob_id=%s', $files->table( 'audit' ), $blob->id ) );
	$execute( $db->prepare( "INSERT INTO %i (blob_id,event,actor_id,occurred_at) VALUES (%s,'file_registered',1,100)", $files->table( 'audit' ), $blob->id ) );
	$assert( in_array( 'audit_mismatch', $codes(), true ), 'auditoria duplicada' );
	$execute( $db->prepare( 'UPDATE %i SET sha256=%s WHERE document_id=10', $catalog->table( 'versions' ), str_repeat( 'a', 64 ) ) );
	$assert( in_array( 'consumer_mismatch', $codes(), true ), 'hash da versão divergente' );
	$execute( $db->prepare( 'UPDATE %i SET document_id=11 WHERE document_id=10', $catalog->table( 'versions' ) ) );
	$assert( in_array( 'consumer_parent_missing', $codes(), true ), 'documento pai ausente' );
	$execute( $db->prepare( 'UPDATE %i SET version=2 WHERE id=20', $news->table( 'assets' ) ) );
	$assert( in_array( 'consumer_parent_missing', $codes(), true ), 'versão da notícia ausente' );
	$execute( $db->prepare( 'DELETE FROM %i WHERE blob_id=%s', $files->table( 'files' ), $news_blob->id ) );
	$assert( in_array( 'consumer_unregistered', $codes(), true ), 'referência sem registo' );
	$assert( in_array( 'audit_unregistered', $codes(), true ), 'auditoria sem registo' );
	$assert( in_array( 'orphan_candidate', $codes(), true ), 'órfão possível conservado' );
	file_put_contents( $base . '/private/' . $blob->id, "%PDF-1.5\n%%EOF\n" );
	$assert( in_array( 'file_mismatch', $codes(), true ), 'bytes divergentes' );
	$hash = hash_file( 'sha256', $base . '/private/' . $blob->id );
	$service->run();
	$assert( hash_file( 'sha256', $base . '/private/' . $blob->id ) === $hash, 'bytes divergentes não reparados' );
	$other = str_repeat( 'e', 32 );
	rename( $base . '/private/' . $blob->id, $base . '/private/' . $other );
	$assert( in_array( 'file_missing', $codes(), true ), 'ficheiro ausente' );
	$execute( 'START TRANSACTION' );
	$refused = false;
	try {
		$reader->read();
	} catch ( RuntimeException ) {
		$refused = true;
	}
	$assert( $refused && $db->inTransaction(), 'transacção alheia recusada e conservada' );
	$execute( 'ROLLBACK' );
	$filter = static fn( string $sql ): string => str_contains( $sql, 'SELECT * FROM `' . $files->table( 'files' ) . '`' ) ? 'INVALID RECONCILIATION QUERY' : $sql;
	add_filter( 'query', $filter );
	$failed = $service->run();
	remove_filter( 'query', $filter );
	$filter = null;
	$assert( ! $failed['complete'] && ! $db->inTransaction(), 'falha SQL termina leitura sem resultado limpo' );
	$execute( $db->prepare( 'DROP TABLE %i', $news->table( 'assets' ) ) );
	$assert( ! $service->run()['complete'], 'schema parcial não vira origem vazia' );
	$assert( is_file( $base . '/private/' . $other ) && is_file( $base . '/private/' . $news_blob->id ), 'originais fictícios presentes no fecho' );
	echo esc_html( 'Reconciliação: ' . $checks . " verificações.\n" );
} finally {
	if ( null !== $filter ) {
		remove_filter( 'query', $filter );
	}
	$db->query( 'ROLLBACK' );
	// Drop referencing test tables first. Never leave an empty accounts table behind.
	foreach ( array( 'audit', 'grants', 'accounts', 'schema' ) as $name ) {
		$execute( $db->prepare( 'DROP TABLE IF EXISTS %i', $auth->table( $name ) ) );
	}
	// Only the random prefix owned by this test, never the active WordPress prefix.
	foreach ( $db->get_col( 'SHOW TABLES' ) as $table ) {
		if ( str_starts_with( $table, $prefix ) ) {
			$execute( $db->prepare( 'DROP TABLE %i', $table ) );
		}
	}
	$db->close();
	foreach ( array( 'private', 'public' ) as $folder ) {
		foreach ( glob( $base . '/' . $folder . '/*' ) as $fixture_path ) {
			unlink( $fixture_path );
		}
		rmdir( $base . '/' . $folder );
	}
	rmdir( $base );
}
