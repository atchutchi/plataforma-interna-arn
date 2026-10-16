<?php
/**
 * Operações CLI explícitas, sem pedidos HTTP ou segredos na saída.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\PreRegistration;

use Arn\Intranet\Identity\{GoogleLoginController, IdentityWpdb};
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Comandos internos com recusa constante e contrato documentado no plano.
/** Só será registado pelo bootstrap com os restantes controlos prontos. */
final class WorkspacePreRegistrationCommand {
	public static function permitted( bool $ready = false ): bool {
		return defined( 'WP_CLI' ) && true === WP_CLI && 'local' === wp_get_environment_type() && ! is_multisite() && current_user_can( 'manage_options' ) && defined( 'ARN_ALLOW_LOCAL_WORKSPACE_PREREGISTRATION' ) && true === ARN_ALLOW_LOCAL_WORKSPACE_PREREGISTRATION && ( ! $ready || '1' === get_option( 'arn_workspace_prereg_ready' ) );
	}
	public function installSchema( array $args, array $options ): void {
		$this->run(
			function () use ( $args, $options ): void {
				if ( $args || $options ) {
					throw new RuntimeException();
				}$this->repository()->installSchema();
				\WP_CLI::success( 'Esquema de pré-cadastro preparado.' );
			}
		); }
	public function preflight( array $args, array $options ): void {
		$this->run(
			function () use ( $args, $options ): void {
				if ( $args ) {
					throw new RuntimeException();
				}$batch = $this->batch( $options );
				if ( time() >= $batch->expires_at ) {
					throw new RuntimeException();
				}\WP_CLI::success( 'Fonte íntegra: 73 contas seleccionadas e quatro excluídas. Sem escrita.' );
			}
		); }
	public function provision( array $args, array $options ): void {
		$this->run(
			function () use ( $args, $options ): void {
				if ( $args || ! self::permitted( true ) || true !== ( $options['apply'] ?? null ) ) {
					throw new RuntimeException();
				}unset( $options['apply'] );
				$batch = $this->batch( $options );
				$this->assertRuntime();
				$repository = $this->repository();
				foreach ( $batch->entries as$entry ) {
					$repository->provision( $entry, $batch, get_current_user_id(), time() );
				}\WP_CLI::success( 'Lote local preparado: 73 registos, sem funções ou permissões de negócio.' );
			}
		); }

	private function batch( array $options ): WorkspaceBatch {
		$keys = array( 'inventory', 'source', 'inventory-sha256', 'source-sha256' );
		if ( count( $options ) !== count( $keys ) || array_diff( array_keys( $options ), $keys ) ) {
			throw new RuntimeException();
		}foreach ( $keys as$key ) {
			if ( ! is_string( $options[ $key ] ?? null ) ) {
				throw new RuntimeException();}
		}
		$config = GoogleLoginController::configuration();
		if ( null === $config ) {
			throw new RuntimeException();}
		return PrivateWorkspaceSource::load( $options['inventory'], $options['source'], $options['inventory-sha256'], $options['source-sha256'], $config->workspace_domain, 73, time() );
	}
	private function repository(): WpdbPreRegistrationRepository {
		global $wpdb;
		$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$database->set_prefix( $wpdb->prefix );
		return new WpdbPreRegistrationRepository( $database );}
	private function assertRuntime(): void {
		if ( wp_using_ext_object_cache() || wp_get_mu_plugins() || get_option( 'active_plugins' ) !== array( 'arn-intranet-core/arn-intranet-core.php' ) || has_filter( 'query' ) ) {
			throw new RuntimeException();}}
	private function run( callable $operation ): void {
		try {
			if ( ! self::permitted() ) {
				throw new RuntimeException();
			}$operation();
		} catch ( Throwable ) {
			\WP_CLI::error( 'Operação de pré-cadastro indisponível. Dados e configuração não foram divulgados.' );}}
}
