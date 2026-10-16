<?php
/** Notice authority independent from native roles and authorship.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\StoredGrant;
use Arn\Intranet\Communications\Notice;
use Arn\Intranet\Communications\NoticeContent;
use Arn\Intranet\Communications\NoticePolicy;
use Arn\Intranet\F112\ActorContext;
use Arn\Intranet\F112\Audience;
use PHPUnit\Framework\TestCase;

/** Management grants never imply a recipient grant. */
final class NoticePolicyTest extends TestCase {

	/** Another manager of the same unit may act without changing the author. */
	public function test_unit_management_is_explicit_and_reading_is_separate(): void {
		$n = Notice::draft( 1, 7, new NoticeContent( 'A', 'B', Audience::unit( 'LAB-A' ), 'team-direct', null ), 10 )->publish( 7, 1, 11, 'Publicar.' );
		$a = $this->actor( 8, array( array( Role::UnitManager, Scope::units( array( 'LAB-A' ) ) ) ), array( 'LAB-A' ) );
		self::assertTrue( NoticePolicy::manage( $n, 'revise', $a, 12 ) );
		self::assertFalse( NoticePolicy::read( $n, $a, 12 ) );
		$b = $this->actor( 8, array( array( Role::UnitManager, Scope::units( array( 'LAB-B' ) ) ), array( Role::Employee, Scope::own() ) ), array( 'LAB-B' ) );
		self::assertFalse( NoticePolicy::manage( $n, 'publish', $b, 12 ) );
		self::assertFalse( NoticePolicy::read( $n, $b, 12 ) );
		$reader = $this->actor( 8, array( array( Role::Employee, Scope::own() ) ), array( 'LAB-A' ) );
		self::assertTrue( NoticePolicy::read( $n, $reader, 12 ) );
		self::assertFalse( NoticePolicy::read( $n, $this->actor( 8, array( array( Role::Employee, Scope::own() ) ), array( 'LAB-A' ), AccountState::Suspended ), 12 ) );
		self::assertFalse(
			NoticePolicy::read(
				$n,
				new ActorContext(
					$reader->snapshot,
					array(
						'arn_authz_local'    => true,
						'arn_authz_user_id'  => 8,
						'arn_authz_revision' => 1,
					),
					array( 'LAB-A' ),
					array( 'LAB-A', 'LAB-B' )
				),
				12
			)
		);
		self::assertFalse( NoticePolicy::read( $n, new ActorContext( $reader->snapshot, $reader->session, array( 'LAB-A' ), array( 'LAB-B' ) ), 12 ) );
	}

	/** Queue alone, audience alone or a news editorial grant cannot decide. */
	public function test_institutional_queue_and_audience_require_designated_second_actor(): void {
		$n     = Notice::draft( 2, 7, new NoticeContent( 'A', 'B', Audience::all(), 'institutional-review', null ), 10 )->submit( 7, 1, 11, 'Submeter.' );
		$queue = Scope::queues( array( 'communications-ficticia' ) );
		$all   = Audience::all()->scope();
		$both  = array( array( Role::NoticeReviewer, $queue ), array( Role::NoticeReviewer, $all ) );
		self::assertTrue( NoticePolicy::manage( $n, 'approve', $this->actor( 8, $both ), 12 ) );
		self::assertFalse( NoticePolicy::manage( $n, 'approve', $this->actor( 7, $both ), 12 ) );
		foreach ( array( array( array( Role::NoticeReviewer, $queue ) ), array( array( Role::NoticeReviewer, $all ) ), array( array( Role::Editorial, Scope::institutional() ) ), array( array( Role::TechnicalAdministrator, Scope::institutional() ) ) ) as $grants ) {
			self::assertFalse( NoticePolicy::manage( $n, 'approve', $this->actor( 8, $grants ), 12 ) );
		}
		$author = array( array( Role::NoticeAuthor, $queue ), array( Role::NoticeAuthor, $all ) );
		self::assertFalse( NoticePolicy::manage( $n, 'revise', $this->actor( 7, $author ), 12 ) );
		$n = $n->decide( 'return', 1, 8, 2, 12, 'Devolver.' );
		self::assertTrue( NoticePolicy::manage( $n, 'revise', $this->actor( 7, $author ), 13 ) );
		self::assertFalse( NoticePolicy::manage( $n, 'revise', $this->actor( 8, $author ), 13 ) );
		self::assertFalse( NoticePolicy::manage( $n, 'publish', $this->actor( 7, $author ), 13 ) );
	}

	/** Build actual persisted grants and a current-generation local session.
	 *
	 * @param int          $id Actor.
	 * @param array        $grants Role/scope pairs.
	 * @param array        $units Membership.
	 * @param AccountState $state State.
	 */
	private function actor( int $id, array $grants, array $units = array(), AccountState $state = AccountState::Active ): ActorContext {
		$stored = array();
		foreach ( $grants as $index => $pair ) {
			$stored[] = new StoredGrant( $index + 1, new Grant( $id, $pair[0], $pair[1], 0, null ) );
		}
		return new ActorContext(
			new AuthorizationSnapshot( $id, $state, 4, $stored ),
			array(
				'arn_authz_local'    => true,
				'arn_authz_user_id'  => $id,
				'arn_authz_revision' => 4,
			),
			$units,
			array( 'LAB-A', 'LAB-B' )
		);
	}
}
