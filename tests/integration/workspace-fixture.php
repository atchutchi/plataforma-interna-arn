<?php
/**
 * Tabelas exclusivas para ensaiar utilizadores nativos sem tocar nos anteriores.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\IdentityWpdb;

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Harness fechado, identificadores preparados e dados fictícios.
/** Preparação e limpeza sem prefixos recebidos de pedidos HTTP. */
final class ArnWorkspaceSqlFixture {
	public readonly IdentityWpdb $database;
	public readonly string $prefix;

	public function __construct() {
		if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || 'local' !== wp_get_environment_type() || ! current_user_can( 'manage_options' ) ) {
			throw new RuntimeException( 'Harness Workspace local recusado.' ); }
		global $wpdb;
		$this->prefix   = 'arn_ws_' . bin2hex( random_bytes( 6 ) ) . '_';
		$this->database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$this->database->set_prefix( $this->prefix );
		$this->database->suppress_errors( true );
		foreach ( array( 'users', 'usermeta', 'options' ) as $part ) {
			if ( false === $this->database->query( $this->database->prepare( 'CREATE TABLE %i LIKE %i', $this->prefix . $part, $wpdb->$part ) ) ) {
				$this->close();
				throw new RuntimeException( 'Harness Workspace incompleto.' ); }
		}
		$this->database->query( $this->database->prepare( 'ALTER TABLE %i AUTO_INCREMENT=1000000000', $this->prefix . 'users' ) );
		$this->database->query( $this->database->prepare( "INSERT INTO %i (option_name,option_value,autoload) VALUES ('default_role','administrator','off')", $this->prefix . 'options' ) );
	}

	public function count( string $suffix ): int {
		if ( ! in_array( $suffix, array( 'users', 'usermeta', 'arn_workspace_accounts', 'arn_workspace_audit' ), true ) ) {
			throw new RuntimeException( 'Tabela de ensaio recusada.' ); }
		return (int) $this->database->get_var( $this->database->prepare( 'SELECT COUNT(*) FROM %i', $this->prefix . $suffix ) );
	}

	public static function normalWriteGuard(): Closure {
		global $wpdb;
		$normal_tables = $wpdb->tables( 'all', true );
		return static function ( string $sql ) use ( $normal_tables ): string {
			if ( preg_match( '/\A\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|CREATE|TRUNCATE)\b/i', $sql ) ) {
				foreach ( $normal_tables as$table ) {
					if ( preg_match( '/(?<![A-Za-z0-9_])`?' . preg_quote( $table, '/' ) . '`?(?![A-Za-z0-9_])/', $sql ) ) {
						throw new RuntimeException( 'Escrita normal recusada pelo ensaio exclusivo.' );}
				}
			}
			return $sql;
		};
	}

	public function close(): void {
		if ( 1 !== preg_match( '/\Aarn_ws_[a-f0-9]{12}_\z/D', $this->prefix ) ) {
			throw new RuntimeException( 'Limpeza de ensaio recusada.' ); }
		foreach ( array( 'arn_workspace_audit', 'arn_workspace_accounts', 'arn_workspace_schema', 'arn_identity_audit', 'arn_identity_links', 'options', 'usermeta', 'users' ) as $part ) {
			$this->database->query( $this->database->prepare( 'DROP TABLE IF EXISTS %i', $this->prefix . $part ) ); }
		$this->database->close();
	}
}
