<?php
/** Local task composition, connection ownership and recipient-safe models.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

use Arn\Intranet\Identity\IdentityWpdb;
use Throwable;

// phpcs:disable Squiz.Commenting -- No factory installs, creates accounts or grants access.
final class Portal {
	private ?IdentityWpdb $database     = null;
	private ?TaskRepository $repository = null;
	private array $roster               = array();

	public function __destruct() {
		$this->database?->close();
	}

	private function prepare(): bool {
		if ( ! Configuration::current() ) {
			return false;
		}
		if ( null !== $this->repository ) {
			return true;
		}
		try {
			$this->roster = LocalRoster::resolve();
			if ( ! isset( $this->roster[ get_current_user_id() ] ) || ! InstallationState::ready() ) {
				return false;
			}
			global $wpdb;
			$this->database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$this->database->set_prefix( $wpdb->prefix );
			$this->database->suppress_errors( true );
			$clock            = static fn(): int => time();
			$this->repository = new TaskRepository( $this->database, new CurrentActor( $this->database, $this->roster, $clock ), $clock );
			return true;
		} catch ( Throwable ) {
			$this->database?->close();
			$this->database   = null;
			$this->repository = null;
			return false;
		}
	}

	public function access( string $view ): ?array {
		if ( ! $this->prepare() ) {
			return null;
		}
		$access = $this->repository->access( $view );
		if ( null === $access ) {
			return null;
		}
		foreach ( array( 'assignees', 'members' ) as $key ) {
			$access[ $key ] = array_map(
				fn( int $id ): array => array(
					'id'    => $id,
					'label' => $this->name( $id ),
				),
				$access[ $key ]
			);
		}
		return $access;
	}

	public function listing( TaskSelection $selection, string $view ): ?array {
		if ( ! $this->prepare() ) {
			return null;
		}
		$result = $this->repository->listing( $selection, $view );
		if ( null !== $result ) {
			$result['items'] = array_map( $this->decorate( ... ), $result['items'] );
		}
		return $result;
	}

	public function detail( int $id, string $view ): ?array {
		if ( ! $this->prepare() ) {
			return null;
		}
		$result = $this->repository->detail( $id, $view );
		if ( null === $result ) {
			return null;
		}
		$result = $this->decorate( $result );
		foreach ( array( 'history', 'comments' ) as $key ) {
			foreach ( $result[ $key ] as &$row ) {
				$row['actor'] = $this->name( $row['actor_id'] );
				if ( 'history' === $key ) {
					$row['assignee'] = $this->name( $row['assignee_id'] );
				}
			}
			unset( $row );
		}
		if ( 'own' === $view ) {
			$result['operations'] = array_values( array_intersect( $result['operations'], array( 'start', 'complete', 'comment' ) ) );
		}
		return $result;
	}

	public function notices( int $page ): ?array {
		if ( $page < 1 || ! $this->prepare() || null === $this->repository->access( 'own' ) ) {
			return null;
		}
		$items = $this->repository->notifications();
		$total = count( $items );
		$pages = intdiv( $total, 20 ) + ( 0 !== $total % 20 ? 1 : 0 );
		return array(
			'items' => $page > $pages ? array() : array_slice( $items, ( $page - 1 ) * 20, 20 ),
			'page'  => $page,
			'pages' => $pages,
			'total' => $total,
		);
	}

	public function create( array $post ): int {
		if ( ! $this->prepare() ) {
			throw new \DomainException( 'Tarefas indisponíveis.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Static error.
		}
		return $this->repository->create( Input::content( $post ), Input::id( $post['assignee_id'] ), $post['create_request_id'] )->id;
	}

	public function change( array $post ): int {
		if ( ! $this->prepare() ) {
			throw new \DomainException( 'Tarefas indisponíveis.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Static error.
		}
		return $this->repository->change( Input::id( $post['id'] ), $post['operation'], Input::id( $post['expected_revision'] ), Input::fields( $post ) )->id;
	}

	public function markRead( int $id ): void {
		if ( ! $this->prepare() ) {
			throw new \DomainException( 'Tarefas indisponíveis.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Static error.
		}
		$this->repository->markRead( $id );
	}

	private function name( int $id ): string {
		$user = isset( $this->roster[ $id ] ) ? get_userdata( $id ) : null;
		return $user instanceof \WP_User ? (string) $user->display_name : 'Conta de teste';
	}

	private function decorate( array $row ): array {
		$row['assignee'] = $this->name( $row['assignee_id'] );
		$row['author']   = $this->name( $row['author_id'] );
		return $row;
	}
}
