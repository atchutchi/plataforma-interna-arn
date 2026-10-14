<?php
/**
 * Ensaio do portal com uma fonte exclusiva e sintética.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\EditorialPreview\Manifest;
use Arn\Intranet\EditorialPreview\Portal;
use Arn\Intranet\EditorialPreview\Selection;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Fixtures temporárias, exclusivas do CLI.

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}

$base = sys_get_temp_dir() . '/arn-editorial-integration-' . bin2hex( random_bytes( 8 ) );
mkdir( $base . '/files', 0700, true );
$checks = 0;
$check  = static function ( bool $condition, string $label ) use ( &$checks ): void {
	if ( ! $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem exclusiva do CLI.
		throw new RuntimeException( 'Falhou: ' . $label );
	}
	++$checks;
};
try {
	$file = $base . '/files/fixture.pdf';
	file_put_contents( $file, '%PDF-1.7 synthetic fixture' );
	$item = array(
		'id'           => 'fixture',
		'title'        => 'Circular sintética',
		'category'     => 'circular',
		'date'         => '2026-10-14',
		'reference'    => 'LAB-1',
		'summary'      => 'Texto fictício',
		'body'         => array( 'Parágrafo fictício.' ),
		'source_label' => 'Original sintético',
		'file'         => 'fixture.pdf',
		'sha256'       => hash_file( 'sha256', $file ),
		'format'       => 'PDF',
		'note'         => '',
	);
	file_put_contents(
		$base . '/manifest.json',
		wp_json_encode(
			array(
				'version' => 1,
				'items'   => array( $item ),
			)
		)
	);
	$manifest = new Manifest( $base );
	$check( 1 === count( $manifest->items() ), 'fonte sintética' );
	$check( 'application/pdf' === $manifest->verifiedFile( $item )['mime'], 'MIME e hash' );
	$check( 1 === count( Selection::filter( $manifest->items(), 'news', array() ) ), 'seleção' );
	$services = Portal::model( 'services', array( 'item' => 'fixture' ) );
	$check( null === $services['selected'] && 0 === array_sum( $services['counts'] ), 'serviços sem metadados' );
	$old_user = get_current_user_id();
	wp_set_current_user( 0 );
	$empty = Portal::model( 'news', array() );
	$check( false === $empty['available'] && 0 === $empty['total'] && null === $empty['selected'], 'guarda antes da fonte' );
	wp_set_current_user( $old_user );
	if ( 'development' === wp_get_environment_type() ) {
		$check( ! Portal::allowed(), 'ambiente development fechado' );
	}
} finally {
	wp_set_current_user( $old_user ?? 0 );
	if ( isset( $file ) && is_file( $file ) ) {
		unlink( $file );
	}
	if ( is_file( $base . '/manifest.json' ) ) {
		unlink( $base . '/manifest.json' );
	}
	rmdir( $base . '/files' );
	rmdir( $base );
}
WP_CLI::success( $checks . ' verificações editoriais sintéticas.' );
