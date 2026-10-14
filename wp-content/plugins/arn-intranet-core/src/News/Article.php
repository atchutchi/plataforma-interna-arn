<?php
/**
 * Immutable news aggregate.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\ScopeType;
use InvalidArgumentException;

/** State and current publication are derived from the audit. */
final readonly class Article {
	/** Ordered immutable versions.
	 *
	 * @var array<Version>
	 */
	public array $versions;
	/** Ordered detached transitions.
	 *
	 * @var array<EditorialTransition>
	 */
	public array $transitions;
	/** Current editorial state.
	 *
	 * @var NewsState
	 */
	public NewsState $state;
	/** Current approved version, including during later private revisions.
	 *
	 * @var int|null
	 */
	public ?int $published_version;
	/** Timestamp of current approval.
	 *
	 * @var int|null
	 */
	public ?int $published_at;
	/** Actor who approved the current version.
	 *
	 * @var int|null
	 */
	public ?int $approved_by;

	/** Seven arguments hydrate the F1-10 created/revised/submitted shape.
	 *
	 * @param int      $id Article identifier.
	 * @param int      $author_id Original author.
	 * @param Scope    $review_scope One editorial queue.
	 * @param int      $revision CAS revision.
	 * @param array    $versions Ordered versions.
	 * @param int|null $submitted_version Last submitted version.
	 * @param int|null $submitted_at Last submission timestamp.
	 * @param array    $transitions Complete ordered audit, when available.
	 */
	public function __construct( public int $id, public int $author_id, public Scope $review_scope, public int $revision, array $versions, public ?int $submitted_version, public ?int $submitted_at, array $transitions = array() ) {
		$count = count( $versions );
		if ( $id <= 0 || $author_id <= 0 || ScopeType::Queues !== $review_scope->type || 1 !== count( $review_scope->targets ) || $count < 1 || array_keys( $versions ) !== range( 0, $count - 1 ) ) {
			self::invalid();
		}
		$version_copy = array();
		$blob_ids     = array();
		$last_time    = -1;
		foreach ( $versions as $index => $version ) {
			if ( ! $version instanceof Version || $version->number !== $index + 1 || $version->actor_id !== $author_id || $version->created_at < $last_time ) {
				self::invalid();
			}
			$last_time = $version->created_at;
			$content   = $version->content;
			$assets    = array_merge( null === $content->featured ? array() : array( $content->featured ), $content->gallery, $content->attachments );
			foreach ( $assets as $asset ) {
				if ( isset( $blob_ids[ $asset->blob->id ] ) ) {
					self::invalid();
				}
				$blob_ids[ $asset->blob->id ] = true;
			}
			$version_copy[] = $version;
		}
		if ( array() === $transitions && 8 === func_num_args() ) {
			self::invalid();
		}
		if ( array() === $transitions ) {
			if ( $revision !== $count + ( null !== $submitted_version ? 1 : 0 ) || ( null === $submitted_version ) !== ( null === $submitted_at ) || ( null !== $submitted_version && ( $submitted_version !== $count || $submitted_at < $last_time || ! $version_copy[ $count - 1 ]->content->completeForSubmission() ) ) ) {
				self::invalid();
			}
			foreach ( $version_copy as $index => $version ) {
				$transitions[] = new EditorialTransition( $index + 1, 0 === $index ? 'created' : 'revised', $version->number, $author_id, $version->created_at, '' );
			}
			if ( null !== $submitted_version ) {
				$transitions[] = new EditorialTransition( $revision, 'submitted', $submitted_version, $author_id, $submitted_at, '' );
			}
		}
		if ( count( $transitions ) !== $revision || array_keys( $transitions ) !== range( 0, $revision - 1 ) ) {
			self::invalid();
		}
		$state             = NewsState::Draft;
		$seen_versions     = 0;
		$last_submitted    = null;
		$last_submitted_at = null;
		$published_version = null;
		$published_at      = null;
		$approved_by       = null;
		$previous_at       = -1;
		$transition_copy   = array();
		foreach ( $transitions as $index => $transition ) {
			if ( ! $transition instanceof EditorialTransition || $transition->revision !== $index + 1 || $transition->occurred_at < $previous_at ) {
				self::invalid();
			}
			$event   = $transition->event;
			$number  = $transition->version;
			$version = $version_copy[ $number - 1 ] ?? null;
			if ( null === $version || $transition->occurred_at < $version->created_at ) {
				self::invalid();
			}
			switch ( $event ) {
				case 'created':
				case 'revised':
					$valid = $number === $seen_versions + 1 && $transition->actor_id === $author_id && $transition->occurred_at === $version->created_at && ( 'created' === $event ? 0 === $index : 0 < $index && in_array( $state, array( NewsState::Draft, NewsState::Rejected, NewsState::Published ), true ) );
					if ( ! $valid ) {
						self::invalid();
					}
					++$seen_versions;
					$state = NewsState::Draft;
					break;
				case 'submitted':
					if ( NewsState::Draft !== $state || $number !== $seen_versions || $number <= ( $last_submitted ?? 0 ) || $transition->actor_id !== $author_id || ! $version->content->completeForSubmission() ) {
						self::invalid();
					}
					$last_submitted    = $number;
					$last_submitted_at = $transition->occurred_at;
					$state             = NewsState::Pending;
					break;
				case 'approved':
				case 'rejected':
				case 'returned':
					if ( NewsState::Pending !== $state || $number !== $last_submitted || $transition->actor_id === $author_id ) {
						self::invalid();
					}
					if ( 'approved' === $event ) {
						if ( ! $version->content->completeForPublication() ) {
							self::invalid();
						}
						$state             = NewsState::Published;
						$published_version = $number;
						$published_at      = $transition->occurred_at;
						$approved_by       = $transition->actor_id;
					} else {
						$state = 'rejected' === $event ? NewsState::Rejected : NewsState::Draft;
					}
					break;
				case 'archived':
					if ( null === $published_version || $number !== $published_version || $transition->actor_id === $author_id || NewsState::Archived === $state ) {
						self::invalid();
					}
					$state = NewsState::Archived;
					break;
				default:
					self::invalid();
			}
			$previous_at       = $transition->occurred_at;
			$transition_copy[] = $transition;
		}
		if ( $seen_versions !== $count || $submitted_version !== $last_submitted || $submitted_at !== $last_submitted_at ) {
			self::invalid();
		}
		$this->versions          = $version_copy;
		$this->transitions       = $transition_copy;
		$this->state             = $state;
		$this->published_version = $published_version;
		$this->published_at      = $published_at;
		$this->approved_by       = $approved_by;
	}

	/** Create the first immutable version.
	 *
	 * @param int     $id Article identifier.
	 * @param int     $author_id Original author.
	 * @param Scope   $review_scope Editorial queue.
	 * @param Version $initial First version.
	 * @param string  $reason Creation reason.
	 */
	public static function create( int $id, int $author_id, Scope $review_scope, Version $initial, string $reason = '' ): self {
		return new self( $id, $author_id, $review_scope, 1, array( $initial ), null, null, array( new EditorialTransition( 1, 'created', 1, $author_id, $initial->created_at, $reason ) ) );
	}

	/** Add a private version after a successful CAS check.
	 *
	 * @param Version $next New version.
	 * @param int     $expected Expected revision.
	 * @param string  $reason Revision reason.
	 */
	public function revise( Version $next, int $expected, string $reason = '' ): self {
		$this->assertRevision( $expected );
		if ( ! in_array( $this->state, array( NewsState::Draft, NewsState::Rejected, NewsState::Published ), true ) || $next->actor_id !== $this->author_id ) {
			self::invalid();
		}
		$versions   = $this->versions;
		$versions[] = $next;
		return $this->append( 'revised', $next->number, $this->author_id, $next->created_at, $reason, $versions );
	}

	/** Submit the latest complete version.
	 *
	 * @param int    $expected Expected revision.
	 * @param int    $at Unix UTC timestamp.
	 * @param string $reason Submission reason.
	 */
	public function submit( int $expected, int $at, string $reason = '' ): self {
		$this->assertRevision( $expected );
		$number = count( $this->versions );
		if ( NewsState::Draft !== $this->state || $number <= ( $this->submitted_version ?? 0 ) || ! $this->versions[ $number - 1 ]->content->completeForPublication() ) {
			self::invalid();
		}
		return $this->append( 'submitted', $number, $this->author_id, $at, $reason, $this->versions, $number, $at );
	}

	/** Approve the submitted version and point publication to it.
	 *
	 * @param int    $expected Expected revision.
	 * @param int    $version Submitted version.
	 * @param int    $actor_id Editor account.
	 * @param int    $at Unix UTC timestamp.
	 * @param string $reason Decision reason.
	 */
	public function approve( int $expected, int $version, int $actor_id, int $at, string $reason ): self {
		$this->assertDecision( $expected, $version, $actor_id );
		if ( ! $this->version( $version )->content->completeForPublication() ) {
			self::invalid();
		}
		return $this->append( 'approved', $version, $actor_id, $at, $reason );
	}

	/** Reject the submitted version with a reason.
	 *
	 * @param int    $expected Expected revision.
	 * @param int    $version Submitted version.
	 * @param int    $actor_id Editor account.
	 * @param int    $at Unix UTC timestamp.
	 * @param string $reason Required decision reason.
	 */
	public function reject( int $expected, int $version, int $actor_id, int $at, string $reason ): self {
		$this->assertDecision( $expected, $version, $actor_id );
		return $this->append( 'rejected', $version, $actor_id, $at, $reason );
	}

	/** Return the submitted version for a new author revision.
	 *
	 * @param int    $expected Expected revision.
	 * @param int    $version Submitted version.
	 * @param int    $actor_id Editor account.
	 * @param int    $at Unix UTC timestamp.
	 * @param string $reason Required decision reason.
	 */
	public function returnForEditing( int $expected, int $version, int $actor_id, int $at, string $reason ): self {
		$this->assertDecision( $expected, $version, $actor_id );
		return $this->append( 'returned', $version, $actor_id, $at, $reason );
	}

	/** Withdraw the current publication without deleting history.
	 *
	 * @param int    $expected Expected revision.
	 * @param int    $version Published version.
	 * @param int    $actor_id Editor account.
	 * @param int    $at Unix UTC timestamp.
	 * @param string $reason Required archival reason.
	 */
	public function archive( int $expected, int $version, int $actor_id, int $at, string $reason ): self {
		$this->assertRevision( $expected );
		if ( null === $this->published_version || $version !== $this->published_version || $actor_id <= 0 || $actor_id === $this->author_id ) {
			self::invalid();
		}
		return $this->append( 'archived', $version, $actor_id, $at, $reason );
	}

	/** Read an existing immutable version.
	 *
	 * @param int $number Version number.
	 */
	public function version( int $number ): ?Version {
		return $number > 0 ? ( $this->versions[ $number - 1 ] ?? null ) : null;
	}

	/** Report whether a decision is awaiting review. */
	public function pending(): bool {
		return NewsState::Pending === $this->state;
	}

	/** Distinguish an actually submitted version from a private draft.
	 *
	 * @param int $version Version number.
	 */
	public function wasSubmitted( int $version ): bool {
		foreach ( $this->transitions as $transition ) {
			if ( 'submitted' === $transition->event && $version === $transition->version ) {
				return true;
			}
		}
		return false;
	}

	/** Read the latest decision event, when one exists. */
	public function lastDecision(): ?EditorialTransition {
		foreach ( array_reverse( $this->transitions ) as $transition ) {
			if ( in_array( $transition->event, array( 'approved', 'rejected', 'returned', 'archived' ), true ) ) {
				return $transition;
			}
		}
		return null;
	}

	/** Return a fresh aggregate with one additional audit event.
	 *
	 * @param string     $event Closed event name.
	 * @param int        $version Affected version.
	 * @param int        $actor_id Acting account.
	 * @param int        $at Unix UTC timestamp.
	 * @param string     $reason Event reason.
	 * @param array|null $versions Replacement versions, if revised.
	 * @param int|null   $submitted_version Last submitted version, if changed.
	 * @param int|null   $submitted_at Submission time, if changed.
	 */
	private function append( string $event, int $version, int $actor_id, int $at, string $reason, ?array $versions = null, ?int $submitted_version = null, ?int $submitted_at = null ): self {
		$transitions   = $this->transitions;
		$transitions[] = new EditorialTransition( $this->revision + 1, $event, $version, $actor_id, $at, $reason );
		return new self( $this->id, $this->author_id, $this->review_scope, $this->revision + 1, $versions ?? $this->versions, $submitted_version ?? $this->submitted_version, $submitted_at ?? $this->submitted_at, $transitions );
	}

	/** Reject stale or terminal mutations.
	 *
	 * @param int $expected Expected revision.
	 */
	private function assertRevision( int $expected ): void {
		if ( $expected !== $this->revision || NewsState::Archived === $this->state ) {
			self::invalid();
		}
	}

	/** Reject a stale, wrong version or author decision.
	 *
	 * @param int $expected Expected revision.
	 * @param int $version Submitted version.
	 * @param int $actor_id Editor account.
	 */
	private function assertDecision( int $expected, int $version, int $actor_id ): void {
		$this->assertRevision( $expected );
		if ( NewsState::Pending !== $this->state || $version !== $this->submitted_version || $actor_id <= 0 || $actor_id === $this->author_id ) {
			self::invalid();
		}
	}

	/** Raise a constant domain error.
	 *
	 * @throws InvalidArgumentException Always.
	 */
	private static function invalid(): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain message.
		throw new InvalidArgumentException( 'Notícia inválida.' );
	}
}
