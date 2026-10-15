<?php
/**
 * Published news and editorial decision policy tests.
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
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\News\Article;
use Arn\Intranet\News\Asset;
use Arn\Intranet\News\Audience;
use Arn\Intranet\News\Content;
use Arn\Intranet\News\NewsPolicy;
use Arn\Intranet\News\Version;
use PHPUnit\Framework\TestCase;

/** Check current-account and audience boundaries for decisions and reads. */
final class NewsEditorialPolicyTest extends TestCase {
	/** Build account grants for one fixture user.
	 *
	 * @param int          $user Account ID.
	 * @param array        $grants Role and scope pairs.
	 * @param AccountState $state Account state.
	 */
	private function snapshot( int $user, array $grants, AccountState $state = AccountState::Active ): AuthorizationSnapshot {
		$stored = array();
		foreach ( $grants as $index => $grant ) {
			$stored[] = new StoredGrant( $index + 1, new Grant( $user, $grant[0], $grant[1], 0, null, false ) );
		}
		return new AuthorizationSnapshot( $user, $state, 4, $stored );
	}

	/** Build the server session fixture.
	 *
	 * @param int $user Account ID.
	 * @param int $revision Session generation.
	 */
	private function session( int $user, int $revision = 4 ): array {
		return array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => $user,
			'arn_authz_revision' => $revision,
		);
	}

	/** Build an article published to LAB-A. */
	private function article(): Article {
		$queue   = Scope::queues( array( 'editorial-ficticia' ) );
		$asset   = new Asset( 'featured', new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'b', 64 ), 1, 'image/png' ), 'Imagem' );
		$version = new Version( 1, new Content( 'Título', 'Corpo A', 'circular', $asset, array(), array(), Audience::units( array( 'LAB-A' ) ) ), 2, 10 );
		return Article::create( 7, 2, $queue, $version )->submit( 1, 11 )->approve( 2, 1, 3, 12, 'Aprovado' );
	}

	/** Reader must pass account, grant, session and membership gates. */
	public function test_reader_needs_current_session_active_account_grant_and_membership(): void {
		$article = $this->article();
		$reader  = $this->snapshot( 8, array( array( Role::Employee, Scope::own() ) ) );
		self::assertTrue( NewsPolicy::canReadPublished( $article, $reader, $this->session( 8 ), 20, array( 'LAB-A' ) ) );
		self::assertFalse( NewsPolicy::canReadPublished( $article, $reader, $this->session( 8 ), 20, array( 'LAB-B' ) ) );
		self::assertFalse( NewsPolicy::canReadPublished( $article, $reader, $this->session( 8, 3 ), 20, array( 'LAB-A' ) ) );
		self::assertFalse( NewsPolicy::canReadPublished( $article, $this->snapshot( 8, array( array( Role::Employee, Scope::own() ) ), AccountState::Suspended ), $this->session( 8 ), 20, array( 'LAB-A' ) ) );
		self::assertFalse( NewsPolicy::canReadPublished( $article, $this->snapshot( 8, array() ), $this->session( 8 ), 20, array( 'LAB-A' ) ) );
		self::assertFalse( NewsPolicy::canReadPublished( $article, $this->snapshot( 8, array( array( Role::TechnicalAdministrator, Scope::institutional() ) ) ), $this->session( 8 ), 20, array( 'LAB-A' ) ) );
	}

	/** Private version B does not change published audience A. */
	public function test_pending_revision_does_not_replace_published_audience_or_expose_new_version(): void {
		$published = $this->article();
		$asset     = new Asset( 'featured', new BlobDescriptor( str_repeat( 'c', 32 ), str_repeat( 'd', 64 ), 1, 'image/png' ), 'Nova imagem' );
		$version   = new Version( 2, new Content( 'Novo título', 'Corpo B', 'circular', $asset, array(), array(), Audience::units( array( 'LAB-B' ) ) ), 2, 13 );
		$draft     = $published->revise( $version, 3 );
		$pending   = $draft->submit( 4, 14 );
		$rejected  = $pending->reject( 5, 2, 3, 15, 'Corrigir' );
		$reader    = $this->snapshot( 8, array( array( Role::Employee, Scope::own() ) ) );
		$editor    = $this->snapshot( 3, array( array( Role::Editorial, Scope::queues( array( 'editorial-ficticia' ) ) ) ) );
		foreach ( array( $draft, $pending, $rejected ) as $article ) {
			self::assertTrue( NewsPolicy::canReadPublished( $article, $reader, $this->session( 8 ), 20, array( 'LAB-A' ) ) );
			self::assertFalse( NewsPolicy::canReadPublished( $article, $reader, $this->session( 8 ), 20, array( 'LAB-B' ) ) );
		}
		self::assertFalse( NewsPolicy::canRead( $draft, 2, $editor, $this->session( 3 ), 20 ) );
		self::assertTrue( NewsPolicy::canRead( $pending, 2, $editor, $this->session( 3 ), 20 ) );
		self::assertFalse( NewsPolicy::canRead( $draft, 2, $reader, $this->session( 8 ), 20, array( 'LAB-B' ) ) );
		self::assertTrue( NewsPolicy::canRead( $pending, 1, $reader, $this->session( 8 ), 20, array( 'LAB-A' ) ) );
	}

	/** Approval needs queue rights and one complete audience grant. */
	public function test_decision_requires_queue_actions_full_audience_scope_and_other_actor(): void {
		$pending = $this->article()->revise( new Version( 2, new Content( 'Título', 'Corpo', 'circular', new Asset( 'featured', new BlobDescriptor( str_repeat( 'c', 32 ), str_repeat( 'd', 64 ), 1, 'image/png' ), 'Imagem' ), array(), array(), Audience::units( array( 'LAB-A', 'LAB-B' ) ) ), 2, 13 ), 3 )->submit( 4, 14 );
		$queue   = array( Role::Editorial, Scope::queues( array( 'editorial-ficticia' ) ) );
		$both    = array( Role::Editorial, Scope::audiences( array( 'news_unit_LAB-A', 'news_unit_LAB-B' ) ) );
		$full    = $this->snapshot( 3, array( $queue, $both ) );
		self::assertTrue( NewsPolicy::canDecide( $pending, 'approve', 2, $full, $this->session( 3 ), 20 ) );
		self::assertTrue( NewsPolicy::canDecide( $pending, 'reject', 2, $this->snapshot( 3, array( $queue ) ), $this->session( 3 ), 20 ) );
		self::assertTrue( NewsPolicy::canDecide( $pending, 'return', 2, $this->snapshot( 3, array( $queue ) ), $this->session( 3 ), 20 ) );
		self::assertFalse( NewsPolicy::canDecide( $pending, 'approve', 2, $this->snapshot( 3, array( $queue, array( Role::Editorial, Scope::audiences( array( 'news_unit_LAB-A' ) ) ) ) ), $this->session( 3 ), 20 ) );
		self::assertFalse( NewsPolicy::canDecide( $pending, 'approve', 2, $this->snapshot( 3, array( $queue, array( Role::Editorial, Scope::audiences( array( 'news_unit_LAB-B' ) ) ) ) ), $this->session( 3 ), 20 ) );
		self::assertFalse( NewsPolicy::canDecide( $pending, 'approve', 2, $this->snapshot( 3, array( $queue, array( Role::Editorial, Scope::audiences( array( 'news_unit_LAB-A' ) ) ), array( Role::Editorial, Scope::audiences( array( 'news_unit_LAB-B' ) ) ) ) ), $this->session( 3 ), 20 ) );
		self::assertFalse( NewsPolicy::canDecide( $pending, 'approve', 1, $full, $this->session( 3 ), 20 ) );
		self::assertFalse( NewsPolicy::canDecide( $pending, 'approve', 2, $full, $this->session( 3, 3 ), 20 ) );
		self::assertFalse( NewsPolicy::canDecide( $pending, 'approve', 2, $this->snapshot( 2, array( $queue, $both ) ), $this->session( 2 ), 20 ) );
		self::assertFalse( NewsPolicy::canDecide( $pending, 'reject', 2, $this->snapshot( 2, array( $queue, $both ) ), $this->session( 2 ), 20 ) );
		self::assertFalse( NewsPolicy::canDecide( $pending, 'return', 2, $this->snapshot( 2, array( $queue, $both ) ), $this->session( 2 ), 20 ) );
	}

	/** Approval swaps the reader audience only at the decision revision. */
	public function test_new_approval_switches_published_audience(): void {
		$published = $this->article();
		$asset     = new Asset( 'featured', new BlobDescriptor( str_repeat( 'c', 32 ), str_repeat( 'd', 64 ), 1, 'image/png' ), 'Imagem' );
		$version   = new Version( 2, new Content( 'Título', 'Corpo B', 'circular', $asset, array(), array(), Audience::units( array( 'LAB-B' ) ) ), 2, 13 );
		$approved  = $published->revise( $version, 3 )->submit( 4, 14 )->approve( 5, 2, 3, 15, 'Aprovado' );
		$reader    = $this->snapshot( 8, array( array( Role::Employee, Scope::own() ) ) );
		self::assertFalse( NewsPolicy::canReadPublished( $approved, $reader, $this->session( 8 ), 20, array( 'LAB-A' ) ) );
		self::assertTrue( NewsPolicy::canReadPublished( $approved, $reader, $this->session( 8 ), 20, array( 'LAB-B' ) ) );
		self::assertFalse( NewsPolicy::canRead( $approved, 1, $reader, $this->session( 8 ), 20, array( 'LAB-A' ) ) );
	}

	/** Archiving needs the current publication and its full audience grant. */
	public function test_archive_requires_published_audience_grant(): void {
		$published = $this->article();
		$queue     = array( Role::Editorial, Scope::queues( array( 'editorial-ficticia' ) ) );
		$audience  = array( Role::Editorial, Scope::audiences( array( 'news_unit_LAB-A' ) ) );
		$editor    = $this->snapshot( 3, array( $queue, $audience ) );
		self::assertTrue( NewsPolicy::canDecide( $published, 'archive', 1, $editor, $this->session( 3 ), 20 ) );
		self::assertFalse( NewsPolicy::canDecide( $published, 'archive', 1, $this->snapshot( 3, array( $queue ) ), $this->session( 3 ), 20 ) );
		self::assertFalse( NewsPolicy::canDecide( $published, 'archive', 1, $this->snapshot( 2, array( $queue, $audience ) ), $this->session( 2 ), 20 ) );
		self::assertFalse( NewsPolicy::canDecide( $published->archive( 3, 1, 3, 13, 'Retirar' ), 'archive', 1, $editor, $this->session( 3 ), 20 ) );
	}
}
