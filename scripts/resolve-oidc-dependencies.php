<?php
/**
 * Preparação explícita de dependências no contentor descartável da CI.
 * Não executada pelo plugin ou pelo arranque WordPress.
 */
if ( '1' !== getenv( 'ARN_RESOLVE_OIDC_DEPENDENCIES' ) || 'Linux' !== PHP_OS_FAMILY ) {
	throw new RuntimeException( 'Resolução permitida apenas no contentor explicitamente seleccionado.' );
}
$configuration = json_decode( file_get_contents( 'composer.json' ), true, 512, JSON_THROW_ON_ERROR );
$configuration['require']['facile-it/php-openid-client'] = '1.0.0';
$configuration['require']['guzzlehttp/guzzle']           = '7.15.5';
$configuration['description'] = 'Dependências PHP da plataforma interna da ARN.';
ksort( $configuration['require'] );
file_put_contents( 'composer.json', json_encode( $configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n" );
if ( file_exists( 'composer.lock' ) ) {
	if ( ! rename( 'composer.lock', 'composer.lock.before-oidc' ) ) {
		throw new RuntimeException( 'Não foi possível preservar o lockfile anterior.' );
	}
}
