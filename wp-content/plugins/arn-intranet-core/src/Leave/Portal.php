<?php
/** Native leave composition, never installs resources during a read.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Leave;

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Requests\{CurrentActor, LocalRoster};
use Throwable;
// phpcs:disable Squiz.Commenting
final class Portal {
	private ?IdentityWpdb $db       = null;
	private ?Repository $repository = null;
	public function __destruct() {
		$this->db?->close();}
	private function prepare(): bool {
		if ( ! Configuration::current() ) {
			return false;
		}if ( null !== $this->repository ) {
			return true;
		}
		try {
			$roster = LocalRoster::resolve();
			if ( ! isset( $roster[ get_current_user_id() ] ) || ! InstallationState::ready() ) {
				return false;
			}
			global $wpdb;
			$this->db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$this->db->set_prefix( $wpdb->prefix );
			$this->db->suppress_errors( true );
			$clock            = static fn(): int=>time();
			$this->repository = new Repository( $this->db, new CurrentActor( $this->db, $roster, $clock ), $clock );
			return true;
		} catch ( Throwable ) {
			$this->db?->close();
			$this->db         = null;
			$this->repository = null;
			return false;}
	}
	public function access( string $view ): ?array {
		return $this->prepare() ? $this->repository->access( $view ) : null;}
	public function navigation(): array {
		return $this->prepare() ? $this->repository->navigation() : array();
	}
	public function listing( string $view, int $year, int $page = 1 ): ?array {
		return $this->prepare() ? $this->repository->listing( $view, $year, $page ) : null;}
	public function detail( int $id, string $view ): ?array {
		return $this->prepare() ? $this->repository->detail( $id, $view ) : null;}
	public function projection( string $purpose, string $query = '', int $page = 1 ): ?array {
		return $this->prepare() ? $this->repository->projection( $purpose, $query, $page ) : null;}
	public function notices(): array {
		return $this->prepare() ? $this->repository->notifications() : array();}
	public function mutate( array $post ): int {
		if ( ! $this->prepare() ) {
			throw new \DomainException( 'Férias indisponíveis.' );
		}
		return match ( $post['operation'] ) {
			'create'=>$this->repository->create( $post['unit'], $post['starts'], $post['ends'], $post['calendar_revision'], $post['key'] )->id, 'calendar'=>$this->repository->saveCalendar( $post['year'], $post['expected'], $post['holidays'], $post['reason'], $post['key'] )->year, 'read'=>$this->readNotice( $post['id'] ), default=>$this->repository->change( $post['id'], $post['expected'], $post['operation'], Input::fields( $post ), $post['key'] )->id};
	}
	private function readNotice( int $id ): int {
		$this->repository->markRead( $id );
		return $id;}
	public static function name( int $id ): string {
		return \Arn\Intranet\Requests\Portal::name( $id );}
}
