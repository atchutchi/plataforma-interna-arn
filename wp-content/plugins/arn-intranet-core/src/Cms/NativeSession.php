<?php
/** Native session read under an InnoDB transaction lock.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
/** Caller retains the session lock until the protected operation commits. */
final class NativeSession {
	public function __construct( private readonly IdentityWpdb $database, private readonly string $table ) {
		if ( 1 !== preg_match( '/\A[A-Za-z0-9_]{1,64}\z/D', $table ) ) {
			throw new RuntimeException( 'Tabela de sessões inválida.' );
		}
	}

	public function read( int $user_id, string $token, int $now ): array {
		if ( ! $this->database->inTransaction() ) {
			throw new RuntimeException( 'A sessão exige transacção.' );
		}
		if ( $user_id < 1 || $now < 0 || 1 !== preg_match( '/\A[A-Za-z0-9]{32,256}\z/D', $token ) ) {
			return array();
		}
		$engine = $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->table ) );
		if ( 1 !== count( $engine ) || 'InnoDB' !== $engine[0]->ENGINE ) {
			throw new RuntimeException( 'Sessões exigem armazenamento transaccional.' );
		}
		$rows = $this->rows( $this->database->prepare( 'SELECT meta_value FROM %i WHERE user_id=%d AND meta_key=%s FOR UPDATE', $this->table, $user_id, 'session_tokens' ) );
		if ( 1 !== count( $rows ) || ! is_string( $rows[0]->meta_value ) ) {
			return array();
		}
		$invalid = false;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Corrupt serialized sessions must not disclose warnings.
		set_error_handler(
			static function () use ( &$invalid ): bool {
				$invalid = true;
				return true;
			}
		);
		try {
			$all = unserialize( $rows[0]->meta_value, array( 'allowed_classes' => false ) );
		} catch ( Throwable ) {
			$invalid = true;
			$all     = false;
		} finally {
			restore_error_handler();
		}
		$session = ! $invalid && is_array( $all ) ? ( $all[ hash( 'sha256', $token ) ] ?? null ) : null;
		return is_array( $session ) && is_int( $session['expiration'] ?? null ) && $session['expiration'] > $now ? $session : array();
	}

	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Sessões indisponíveis.' );
		}
		return $rows;
	}
}
