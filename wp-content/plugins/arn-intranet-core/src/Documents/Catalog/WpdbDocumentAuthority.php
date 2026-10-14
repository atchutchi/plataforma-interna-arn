<?php
/**
 * Transactional authorization for the document repository.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\Catalog;

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Authorization\Storage\AuthorizationSchema;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment

/** Locks actor and recipients by ID, then validates scope and token generation. */
final class WpdbDocumentAuthority implements DocumentAuthority {
	private readonly WpdbAuthorizationRepository $repository;
	private readonly AuthorizationSchema $schema;
	private array $locked = array();

	public function __construct( private readonly IdentityWpdb $database, private readonly CurrentDocumentActor $current ) {
		$this->schema     = new AuthorizationSchema( $database );
		$this->repository = new WpdbAuthorizationRepository( $database );
	}

	public function lockAndAssert( Scope $scope, array $recipient_ids ): int {
		$this->locked = array();
		$row          = $this->database->get_row( 'SELECT @@session.in_transaction AS active' );
		if ( ! is_object( $row ) || '' !== $this->database->last_error || '1' !== (string) $row->active ) {
			throw new RuntimeException( 'Autoridade documental indisponível.' );
		}
		$current = $this->current->read();
		if ( null === $current ) {
			throw new RuntimeException( 'Operação documental não autorizada.' );
		}
		[, $actor_id, $session] = $current;
		$ids                    = array_values( array_unique( array_merge( array( $actor_id ), $recipient_ids ) ) );
		foreach ( $ids as $id ) {
			if ( ! is_int( $id ) || $id < 1 ) {
				throw new RuntimeException( 'Destinatário documental inválido.' );
			}
		}
		sort( $ids, SORT_NUMERIC );
		foreach ( $ids as $id ) {
			$this->locked[ $id ] = $this->repository->findInTransaction( $id );
			if ( null === $this->locked[ $id ] ) {
				throw new RuntimeException( 'Conta documental indisponível.' );
			}
		}
		$fresh_session = $this->current->sessionFor( $actor_id );
		if ( $session !== $fresh_session || ! LocalAccountSessionPolicy::valid( $this->locked[ $actor_id ], $actor_id, $fresh_session, true ) || ! DocumentPolicy::canManageScope( $scope, $this->locked[ $actor_id ], $fresh_session, time() ) ) {
			throw new RuntimeException( 'Operação documental não autorizada.' );
		}
		return $actor_id;
	}

	public function assertActiveRecipients( array $recipient_ids ): void {
		foreach ( $recipient_ids as $id ) {
			if ( ! isset( $this->locked[ $id ] ) || AccountState::Active !== $this->locked[ $id ]->state ) {
				throw new RuntimeException( 'Destinatário documental inactivo.' );
			}
		}
	}
}
