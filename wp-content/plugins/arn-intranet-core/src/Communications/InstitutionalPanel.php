<?php
/** Institutional read model. No local fixtures, grants or writes.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Communications;

use Arn\Intranet\Authorization\{Action, AuthorizationPolicy, Request, Scope};
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Identity\Institutional\PrincipalSource;
use Closure;
use DomainException;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** Revalidates the principal after reading a consistent snapshot. */
final class InstitutionalPanel {

	public function __construct( private readonly IdentityWpdb $database, private readonly PrincipalSource $source, private readonly Closure $clock ) {}

	public static function enabled(): bool {
		return defined( 'ARN_INSTITUTIONAL_PANEL_ENABLED' ) && true === ARN_INSTITUTIONAL_PANEL_ENABLED;
	}

	public static function permitted( ?array $current, int $now ): bool {
		return null !== $current && ( new AuthorizationPolicy() )->allows(
			$current[1]->toAccount( true ),
			new Request( Action::ReadContent, Scope::own(), $current[0]->user_id, 0, true, true, true, true ),
			$current[1]->policyGrants(),
			$now
		);
	}

	/** Only an approved, currently published version for the whole institution. */
	public static function project( Notice $notice, int $now ): ?array {
		$version = $notice->published( $now );
		if ( null !== $notice->unit_id || null === $version || 'all' !== $version->content->audience->type || null !== $version->content->audience->unit_id || 'institutional-review' !== $version->content->circuit ) {
			return null;
		}
		return array(
			'id'      => $notice->id,
			'title'   => $version->content->title,
			'body'    => $version->content->body,
			'version' => $version->number,
		);
	}

	/** Cursor is an object ID, not an offset into private rows. No private counts. */
	public function read( int $before = PHP_INT_MAX ): array {
		if ( $before < 1 ) {
			throw new DomainException( 'Página inválida.' );
		}
		$current = $this->source->current();
		if ( ! self::permitted( $current, ( $this->clock )() ) ) {
			throw new DomainException( 'Acesso institucional recusado.' );
		}
		$old     = $this->database->suppress_errors( true );
		$started = false;
		try {
			$schema = new NoticeSchema( $this->database );
			$schema->assertReady();
			if ( $this->database->inTransaction() ) {
				throw new RuntimeException( 'Ligação ocupada.' );
			}
			$this->execute( 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ' );
			$this->execute( 'START TRANSACTION READ ONLY' );
			$started = true;
			$reader  = new NoticeReader( $this->database );
			$items   = array();
			$cursor  = $before;
			// Bounded work even when the store contains many drafts or other audiences.
			$ids = $this->database->get_col( $this->database->prepare( 'SELECT id FROM %i WHERE id < %d ORDER BY id DESC LIMIT 100', $schema->table( 'objects' ), $cursor ) );
			if ( ! is_array( $ids ) || '' !== $this->database->last_error ) {
				throw new RuntimeException( 'Leitura indisponível.' );
			}
			foreach ( $ids as $id ) {
				$cursor = (int) $id;
				$notice = $reader->load( $cursor );
				if ( null === $notice ) {
					throw new RuntimeException( 'Comunicado inconsistente.' );
				}
				$item = self::project( $notice, ( $this->clock )() );
				if ( null !== $item ) {
					$items[] = $item;
				}
				if ( 12 === count( $items ) ) {
					break;
				}
			}
			$this->execute( 'COMMIT' );
			$started = false;
			$after   = $this->source->current();
			if ( ! self::permitted( $after, ( $this->clock )() ) || (array) $current[0] !== (array) $after[0] ) {
				throw new DomainException( 'Sessão alterada durante a leitura.' );
			}
			return array(
				'notices' => $items,
				'next'    => ( 100 === count( $ids ) || 12 === count( $items ) ) && $cursor > 1 ? $cursor : null,
			);
		} finally {
			if ( $started ) {
				$this->database->query( 'ROLLBACK' );
			}
			$this->database->suppress_errors( $old );
		}
	}

	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Transacção indisponível.' );
		}
	}
}
