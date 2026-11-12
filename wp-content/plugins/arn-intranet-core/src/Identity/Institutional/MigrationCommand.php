<?php
/** Diagnóstico CLI reservado ao administrador técnico.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\Institutional;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** Aplicação exclusivamente CLI com guarda precoce obrigatória. */
final class MigrationCommand {
	public function apply( array $args, array $options ): void {
		$this->mutate( $args, $options, false );
	}
	public function release( array $args, array $options ): void {
		$this->mutate( $args, $options, true );
	}
	private function mutate( array $args, array $options, bool $release ): void {
		$database = null;
		try {
			$gate = $GLOBALS['arn_institutional_maintenance_gate'] ?? null;
			if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || 'cli' !== PHP_SAPI || is_multisite() || ! current_user_can( 'manage_options' ) || ! defined( 'ARN_ALLOW_INSTITUTIONAL_MIGRATIONS' ) || true !== ARN_ALLOW_INSTITUTIONAL_MIGRATIONS || ! $gate instanceof MaintenanceGate || $args || ( $release ? array() !== $options : array( 'backup-ref' ) !== array_keys( $options ) ) ) {
				throw new RuntimeException(); }
			if ( ! $release && ( ! is_string( $options['backup-ref'] ) || 1 !== preg_match( '/^[A-Za-z0-9._-]{1,120}$/D', $options['backup-ref'] ) ) ) {
				throw new RuntimeException(); }
			$gate->exclusive();
			global $wpdb;
			$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$database->set_prefix( $wpdb->prefix );
			$binding = hash( 'sha256', wp_json_encode( array( DB_HOST, DB_NAME, $wpdb->prefix, realpath( ABSPATH ) ), JSON_THROW_ON_ERROR ) );
			$runner  = new MigrationRunner( $database, $gate, new MigrationReceipts( $gate, $binding ) );
			if ( $release ) {
				$runner->release( get_current_user_id() );
			} else {
				$runner->apply( get_current_user_id(), $options['backup-ref'] ); }
			\WP_CLI::success( $release ? 'Manutenção libertada após validação.' : 'Migração confirmada. Manutenção permanece activa.' );
		} catch ( Throwable ) {
			\WP_CLI::error( 'Operação recusada ou interrompida. Verificar guarda, recibos e schema. Não remover a manutenção manualmente.' );
		} finally {
			if ( null !== $database ) {
				$database->close(); }
		}
	}
	public function preflight( array $args, array $options ): void {
		$database = null;
		try {
			if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || is_multisite() || ! current_user_can( 'manage_options' ) || $args || $options ) {
				throw new RuntimeException();
			}
			global $wpdb;
			$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$database->set_prefix( $wpdb->prefix );
			$result = ( new MigrationPreflight( $database ) )->inspect();
			\WP_CLI::line( wp_json_encode( $result, JSON_THROW_ON_ERROR ) );
		} catch ( Throwable ) {
			\WP_CLI::error( 'Diagnóstico recusado. Confirmar autoridade CLI e compatibilidade do schema. Nenhuma migração aplicada.' );
		} finally {
			if ( null !== $database ) {
				$database->close();
			}
		}
	}
}
