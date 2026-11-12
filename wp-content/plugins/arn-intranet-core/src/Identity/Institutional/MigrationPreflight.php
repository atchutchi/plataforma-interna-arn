<?php
/** Diagnóstico institucional sem escrita de schema ou dados.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\Institutional;

use Arn\Intranet\Identity\{IdentityWpdb, WpdbIdentityLinkRepository};
use Arn\Intranet\Authorization\Storage\AuthorizationSchema;
use RuntimeException;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** Não instala tabelas, não actualiza versões e não concede acesso. */
final class MigrationPreflight {
	public function __construct( private readonly IdentityWpdb $database ) {}

	public function inspect(): array {
		$links  = new WpdbIdentityLinkRepository( $this->database );
		$schema = new AuthorizationSchema( $this->database );
		$old    = $this->database->suppress_errors( true );
		try {
			$identity = $links->migrationState();
			$tables   = $this->database->get_col(
				$this->database->prepare(
					'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s, %s, %s, %s)',
					$schema->table( 'accounts' ),
					$schema->table( 'grants' ),
					$schema->table( 'audit' ),
					$schema->table( 'schema' )
				)
			);
			if ( '' !== $this->database->last_error || ! is_array( $tables ) ) {
				throw new RuntimeException( 'Diagnóstico institucional indisponível.' );
			}
			if ( array() !== $tables ) {
				$schema->validate();
			}
			return array(
				'plan_version'         => 1,
				'identity'             => $identity,
				'authorization'        => array() === $tables ? 'absent' : 'current',
				'write_performed'      => false,
				'activation_permitted' => false,
			);
		} finally {
			$this->database->suppress_errors( $old );
		}
	}
}
