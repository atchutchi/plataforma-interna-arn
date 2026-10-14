<?php
/**
 * Ensaio isolado da pré-validação sem escrita.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Organization\ImportPreview\{LocalImportPreviewPage, PreviewValidator, SyntheticScenarios};
use Arn\Intranet\Organization\Storage\{LocalUnitContext, WpdbLocalUnitRepository};

$environment = wp_get_environment_type();
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! in_array( $environment, array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
if ( 'development' === $environment && LocalImportPreviewPage::allowed() ) {
	throw new RuntimeException( 'Guarda operacional aberta em development.' );
}
$prefix = 'arn_import_' . bin2hex( random_bytes( 6 ) ) . '_';
$db     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$repo    = new WpdbLocalUnitRepository( $db );
$filter  = null;
$blocked = array();
$checks  = 0;
$assert  = static function ( bool $value, string $label ) use ( &$checks ): void {
	if ( ! $value ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Apenas CLI com label interno.
		throw new RuntimeException( 'Falhou: ' . $label );
	}
	++$checks;
};
try {
	// O setup exclusivo precede o filtro de leitura.
	$repo->installSchema();
	$repo->apply( 'initialize', 0, new LocalUnitContext( get_current_user_id(), 'Preparação exclusiva do ensaio', time() ) );
	$before       = $repo->snapshot();
	$before_audit = $repo->history();
	$filter       = static function ( string $sql ) use ( &$blocked ): string {
		if ( preg_match( '/^\s*(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE|GRANT|REVOKE)\b/i', $sql ) ) {
			$blocked[] = $sql;
			throw new RuntimeException( 'Escrita ou DDL no percurso de pré-validação.' );
		}
		return $sql;
	};
	add_filter( 'query', $filter );
	try {
		$snapshot  = $repo->snapshot();
		$valid     = PreviewValidator::validate( SyntheticScenarios::csv( 'valid' ), $snapshot );
		$duplicate = PreviewValidator::validate( SyntheticScenarios::csv( 'duplicates' ), $snapshot );
		$malformed = PreviewValidator::validate( SyntheticScenarios::csv( 'malformed' ), $snapshot );
		$assert( $valid->valid && 1 === $valid->totals['create'] && 1 === $valid->totals['update'] && 1 === $valid->totals['unchanged'], 'propostas' );
		$assert( ! $duplicate->valid && 4 === $duplicate->totals['invalid'], 'duplicados' );
		$assert( ! $malformed->valid && array() !== $malformed->global_errors, 'CSV malformado' );
		$assert( PreviewValidator::validate( SyntheticScenarios::csv( 'valid' ), $snapshot )->batch_id === $valid->batch_id, 'identidade determinística' );
	} finally {
		remove_filter( 'query', $filter );
		$filter = null;
	}
	$assert( array() === $blocked, 'nenhuma DML ou DDL' );
	$assert( $before->revision === $repo->snapshot()->revision && \Arn\Intranet\Organization\Storage\LocalUnitSnapshot::encode( $before->units ) === \Arn\Intranet\Organization\Storage\LocalUnitSnapshot::encode( $repo->snapshot()->units ), 'snapshot igual' );
	$assert( wp_json_encode( $before_audit ) === wp_json_encode( $repo->history() ), 'auditoria igual' );
} finally {
	if ( null !== $filter ) {
		remove_filter( 'query', $filter );
	}
	foreach ( array( 'audit', 'units', 'header' ) as $suffix ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_org_' . $suffix ) );
	}
	$db->close();
}
WP_CLI::success( 'Pré-validação: ' . $checks . ' verificações.' );
