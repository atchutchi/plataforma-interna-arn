<?php
/**
 * Ensaio exclusivo do registo de ficheiros privados.
 *
 * @package ArnIntranet
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors, WordPress.Security.EscapeOutput.ExceptionNotEscaped
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\Documents\PrivateFiles\FileRecord;
use Arn\Intranet\Documents\PrivateFiles\FileSchema;
use Arn\Intranet\Documents\PrivateFiles\PrivateBlobStore;
use Arn\Intranet\Documents\PrivateFiles\WpdbFileRegistry;
use Arn\Intranet\Identity\IdentityWpdb;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Falhou: ' . $label ); }
	++$checks;
};
$prefix = 'arn_pf_' . bin2hex( random_bytes( 5 ) ) . '_';
$db     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$schema   = new FileSchema( $db );
$registry = new WpdbFileRegistry( $db );
$base     = sys_get_temp_dir() . '/arn-pf-' . bin2hex( random_bytes( 6 ) );
mkdir( $base );
mkdir( $base . '/private' );
mkdir( $base . '/public' );
$filter = null;
try {
	$rejected = false;
	try {
		$registry->find( str_repeat( 'a', 32 ) );
	} catch ( RuntimeException ) {
		$rejected = true; }
	$assert( $rejected, 'schema ausente' );
	$schema->install();
	$schema->assertReady();
	$source = $base . '/public/document.pdf';
	file_put_contents( $source, "%PDF-1.4\n%%EOF\n" );
	$store  = new PrivateBlobStore( $base . '/private', array( $base . '/public' ), array( 'application/pdf' ), 1024 );
	$blob   = $store->importFile( $source );
	$record = new FileRecord( $blob, 'local_document', 1, 2, 'original', 1, time() );
	$registry->register( $record );
	$assert( $registry->find( $blob->id )?->blob->sha256 === $blob->sha256, 'registo recuperado' );
	$assert( $registry->findForObject( 'local_document', 1, 2, 'original' )?->blob->id === $blob->id, 'consulta singular' );
	$assert( '1' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i WHERE blob_id=%s AND event=%s', $schema->table( 'audit' ), $blob->id, 'file_registered' ) ), 'auditoria' );
	$duplicate = false;
	try {
		$registry->register( $record );
	} catch ( RuntimeException ) {
		$duplicate = true; }
	$assert( $duplicate, 'duplicado recusado' );
	$assert( '1' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $schema->table( 'files' ) ) ), 'duplicado sem efeito' );
	$second_blob       = $store->importFile( $source );
	$same_version      = new FileRecord( $second_blob, 'local_document', 1, 2, 'original', 1, time() );
	$duplicate_version = false;
	try {
		$registry->register( $same_version );
	} catch ( RuntimeException ) {
		$duplicate_version = true; }
	$assert( $duplicate_version && null === $registry->find( $second_blob->id ), 'versão e variante únicas' );
	$other  = new FileRecord( new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'a', 64 ), 1, 'application/pdf' ), 'local_document', 2, 2, 'original', 1, time() );
	$filter = static fn ( string $sql ): string => str_contains( $sql, 'file_registered' ) && str_starts_with( $sql, 'INSERT INTO' ) ? 'INVALID AUDIT INSERT' : $sql;
	add_filter( 'query', $filter );
	$rolled = false;
	try {
		$registry->register( $other );
	} catch ( RuntimeException ) {
		$rolled = true; }
	remove_filter( 'query', $filter );
	$filter = null;
	$assert( $rolled && null === $registry->find( $other->blob->id ), 'falha de auditoria reverte metadados' );
	$db->query( 'START TRANSACTION' );
	$nested = false;
	try {
		$registry->find( $blob->id );
	} catch ( RuntimeException ) {
		$nested = true; }
	$db->query( 'ROLLBACK' );
	$assert( $nested, 'transacção externa recusada' );
	$db->query( $db->prepare( 'ALTER TABLE %i DROP INDEX object_version_variant', $schema->table( 'files' ) ) );
	$incompatible = false;
	try {
		$registry->find( $blob->id );
	} catch ( RuntimeException ) {
		$incompatible = true; }
	$assert( $incompatible, 'índice único adulterado recusado' );
	$db->query( $db->prepare( 'ALTER TABLE %i ADD UNIQUE KEY object_version_variant (object_type,object_id,version,variant)', $schema->table( 'files' ) ) );
	$db->query( $db->prepare( 'UPDATE %i SET version=2', $schema->table( 'schema' ) ) );
	$version_failed = false;
	try {
		$registry->find( $blob->id );
	} catch ( RuntimeException ) {
		$version_failed = true; }
	$assert( $version_failed, 'versão de schema adulterada recusada' );
	$bad         = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$bad->prefix = 'bad-prefix_';
	$invalid     = false;
	try {
		new FileSchema( $bad );
	} catch ( RuntimeException ) {
		$invalid = true; }
	$assert( $invalid, 'prefixo SQL malformado' );
	echo esc_html( 'Ficheiros privados: ' . $checks . " verificações.\n" );
} finally {
	if ( null !== $filter ) {
		remove_filter( 'query', $filter ); }
	$db->query( 'ROLLBACK' );
	foreach ( array( 'audit', 'files', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $schema->table( $name ) ) ); }
	$private_entries = glob( $base . '/private/*' );
	foreach ( false !== $private_entries ? $private_entries : array() as $entry ) {
		unlink( $entry ); }
	$public_entries = glob( $base . '/public/*' );
	foreach ( false !== $public_entries ? $public_entries : array() as $entry ) {
		unlink( $entry ); }
	rmdir( $base . '/private' );
	rmdir( $base . '/public' );
	rmdir( $base );
}
