<?php
/** Local task composition, connection ownership and recipient-safe models.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Requests;

use Arn\Intranet\Identity\IdentityWpdb;
use Throwable;
use Arn\Intranet\Documents\PrivateFiles\{ImportBatch, LocalPrivateFiles, WpdbFileRegistry};
use Arn\Intranet\Media\LocalMediaProcessing;

// phpcs:disable Squiz.Commenting -- No factory installs, creates accounts or grants access.
final class Portal {
	private ?IdentityWpdb $database = null;
	private ?Repository $repository = null;
	private array $roster           = array();

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
			$this->repository = new Repository( $this->database, new CurrentActor( $this->database, $this->roster, $clock ), $clock );
			return true;
		} catch ( Throwable ) {
			$this->database?->close();
			$this->database   = null;
			$this->repository = null;
			return false;
		}
	}

	public function access( string $view ): ?array {
		return $this->prepare() ? $this->repository->access( $view ) : null;
	}
	public function listing( string $view, string $query = '', string $state = '', int $page = 1 ): ?array {
		return $this->prepare() ? $this->repository->listing( $view, $query, $state, $page ) : null;
	}
	public function projection( string $purpose, string $query = '', int $page = 1 ): ?array {
		return $this->prepare() ? $this->repository->projection( $purpose, $query, $page ) : null;
	}
	public function detail( int $id, string $view ): ?array {
		$data = $this->prepare() ? $this->repository->detail( $id, $view ) : null;
		if ( null !== $data && ! AttachmentConfiguration::enabled() ) {
			$data['operations'] = array_values( array_diff( $data['operations'], array( 'attach' ) ) );
		}
		return $data;
	}
	public function notices(): array {
		return $this->prepare() ? $this->repository->notifications() : array();
	}
	public function mutate( array $post ): int {
		if ( ! $this->prepare() ) {
			throw new \DomainException( 'Pedidos indisponíveis.' );
		}
		return match ( $post['operation'] ) {
			'create' => $this->repository->create( $post['type_id'], $post['type_revision'], $post['title'], $post['body'], $post['key'] )->id,
			'type' => $this->repository->saveType( $post['id'], $post['expected'], Input::fields( $post ), $post['key'] )->id,
			'read' => $this->readNotice( $post['id'] ),
			default => $this->repository->change( $post['id'], $post['expected'], $post['operation'], Input::fields( $post ), $post['key'] )->id,
		};
	}
	private function readNotice( int $id ): int {
		$this->repository->markRead( $id );
		return $id;
	}
	public static function name( int $id ): string {
		$user = get_userdata( $id );
		return $user instanceof \WP_User ? (string) $user->display_name : 'Conta fictícia indisponível';
	}
	public function attach( array $post, string $source, string $view, ?\Arn\Intranet\Media\PreparedBatch $prepared = null ): int {
		if ( ! AttachmentConfiguration::enabled() || ! $this->prepare() ) {
			throw new \DomainException( 'Anexos indisponíveis.' ); }
		$before = $this->detail( Input::id( (string) $post['id'] ), $view );
		$actor  = get_current_user_id();
		if ( null === $before || ! in_array( 'attach', $before['operations'], true ) || Input::id( (string) $post['expected'] ) !== $before['revision'] ) {
			throw new \DomainException( 'Pedido alterado ou operação indisponível.' ); }
		$pipeline = $prepared ?? LocalMediaProcessing::pipeline( $actor );
		if ( null === $pipeline ) {
			throw new \DomainException( 'Análise de anexos indisponível.' ); }
		return ( new ImportBatch( LocalPrivateFiles::store(), $pipeline ) )->run(
			function ( ImportBatch $batch ) use ( $post, $source, $actor ): int {
				$blob = $batch->importFile( $source );
				return $this->repository->attach( Input::id( (string) $post['id'] ), $blob, $post['label'], Input::id( (string) $post['expected'] ), $actor )->id;
			},
			static function (): WpdbFileRegistry {
				global $wpdb;
				$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
				$db->set_prefix( $wpdb->prefix );
				return new WpdbFileRegistry( $db );
			}
		);
	}

	public function file( int $id, string $blob, string $variant ): ?array {
		try {
			if ( ! AttachmentConfiguration::readable() || ! $this->prepare() ) {
				return null; }
			return $this->repository->file( $id, $blob, $variant, LocalPrivateFiles::store() );
		} catch ( Throwable ) {
			return null; }
	}
}
