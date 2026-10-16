<?php
/** Immutable notice aggregate with replayed audit evidence.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Communications;

use Arn\Intranet\F112\PlainText;
use InvalidArgumentException;

/** Private workflow revision is independent of the published version. */
final readonly class Notice {

	/** Validate every derived field against the complete ordered history.
	 *
	 * @param int         $id Anchor ID.
	 * @param int         $author_id Immutable first author.
	 * @param string|null $unit_id Immutable team, null for institutional.
	 * @param int         $revision CAS revision.
	 * @param NoticeState $state Derived workflow state.
	 * @param array       $versions Consecutive NoticeVersion values.
	 * @param int|null    $published_version Current public version.
	 * @param int|null    $published_at Current publication time.
	 * @param int|null    $published_revision Publication transition.
	 * @param int|null    $submitted_version Frozen version.
	 * @param int|null    $last_submitted_version Prevent resubmitting a returned version.
	 * @param array       $history Complete transitions.
	 */
	public function __construct(
		public int $id,
		public int $author_id,
		public ?string $unit_id,
		public int $revision,
		public NoticeState $state,
		public array $versions,
		public ?int $published_version,
		public ?int $published_at,
		public ?int $published_revision,
		public ?int $submitted_version,
		public ?int $last_submitted_version,
		public array $history,
	) {
		if ( $id < 1 || $author_id < 1 || $revision < 1 || ! array_is_list( $versions ) || ! $versions || ! array_is_list( $history ) || count( $history ) !== $revision ) {
			self::invalid();
		}
		foreach ( $versions as $i => $version ) {
			if ( ! $version instanceof NoticeVersion || $version->number !== $i + 1 || ( null === $unit_id ? 'institutional-review' !== $version->content->circuit : ( 'team-direct' !== $version->content->circuit || $unit_id !== $version->content->audience->unit_id ) ) ) {
				self::invalid();
			}
		}
		$derived = NoticeState::Draft;
		$seen    = 0;
		$time    = -1;
		$pub     = null;
		$at      = null;
		$pub_rev = null;
		$pending = null;
		$last    = null;
		foreach ( $history as $i => $entry ) {
			if ( ! is_array( $entry ) || 6 !== count( $entry ) || array_diff( array_keys( $entry ), array( 'operation', 'actor_id', 'version', 'revision', 'occurred_at', 'reason' ) ) || ! is_string( $entry['operation'] ) || ! is_int( $entry['actor_id'] ) || $entry['actor_id'] < 1 || ! is_int( $entry['version'] ) || ! is_int( $entry['revision'] ) || $entry['revision'] !== $i + 1 || ! is_int( $entry['occurred_at'] ) || $entry['occurred_at'] < 0 || $entry['occurred_at'] < $time || ! is_string( $entry['reason'] ) || PlainText::value( $entry['reason'], 1000, true ) !== $entry['reason'] || NoticeState::Archived === $derived ) {
				self::invalid();
			}
			$op = $entry['operation'];
			if ( 0 === $i && 'created' !== $op ) {
				self::invalid();
			}
			$time   = $entry['occurred_at'];
			$number = $entry['version'];
			$actor  = $entry['actor_id'];
			if ( 'created' === $op || 'revised' === $op ) {
				$version = $versions[ $seen ] ?? null;
				if ( ( 'created' === $op ) !== ( 0 === $i ) || NoticeState::Pending === $derived || $number !== $seen + 1 || null === $version || $version->actor_id !== $actor || $version->created_at !== $time || ( 0 === $i && $actor !== $author_id ) || ( null === $unit_id && $actor !== $author_id ) || ( $seen > 0 && $versions[ $seen - 1 ]->content->toArray() === $version->content->toArray() ) ) {
					self::invalid();
				}
				++$seen;
				$derived = NoticeState::Draft;
			} elseif ( 'submitted' === $op ) {
				if ( null !== $unit_id || NoticeState::Draft !== $derived || $actor !== $author_id || $number !== $seen || $number === $last ) {
					self::invalid();
				}
				$pending = $number;
				$last    = $number;
				$derived = NoticeState::Pending;
			} elseif ( in_array( $op, array( 'approved', 'rejected', 'returned' ), true ) ) {
				if ( null !== $unit_id || NoticeState::Pending !== $derived || $number !== $pending || $number !== $seen || $actor === $author_id ) {
					self::invalid();
				}
				$pending = null;
				$derived = match ( $op ) {
					'approved' => NoticeState::Published,
					'rejected' => NoticeState::Rejected,
					'returned' => NoticeState::Draft,
				};
			} elseif ( 'published' === $op ) {
				if ( null === $unit_id || NoticeState::Draft !== $derived || $number !== $seen ) {
					self::invalid();
				}
				$derived = NoticeState::Published;
			} elseif ( 'archived' === $op ) {
				if ( 0 === $seen || $number !== $seen || ( null === $unit_id && $actor !== $author_id ) ) {
					self::invalid();
				}
				$pending = null;
				$derived = NoticeState::Archived;
			} else {
				self::invalid();
			}
			if ( in_array( $op, array( 'approved', 'published' ), true ) ) {
				$expiry = $versions[ $number - 1 ]->content->valid_until;
				if ( null !== $expiry && $expiry <= $time ) {
					self::invalid();
				}
				$pub     = $number;
				$at      = $time;
				$pub_rev = $i + 1;
			}
		}
		if ( count( $versions ) !== $seen || $derived !== $state || $pub !== $published_version || $at !== $published_at || $pub_rev !== $published_revision || $pending !== $submitted_version || $last !== $last_submitted_version ) {
			self::invalid();
		}
	}

	/** Create a version and its initial audit event.
	 *
	 * @param int           $id Anchor.
	 * @param int           $author Author.
	 * @param NoticeContent $content Version.
	 * @param int           $now Clock.
	 * @param string        $reason Audit reason, supplied by the repository.
	 * @return self
	 */
	public static function draft( int $id, int $author, NoticeContent $content, int $now, string $reason = 'Criação de rascunho.' ): self {
		$entry = self::entry( 'created', $author, 1, 1, $now, $reason );
		return new self( $id, $author, 'team-direct' === $content->circuit ? $content->audience->unit_id : null, 1, NoticeState::Draft, array( new NoticeVersion( 1, $content, $author, $now ) ), null, null, null, null, null, array( $entry ) );
	}

	/** New version only, never editing a pending/public version.
	 *
	 * @param NoticeContent $content New content.
	 * @param int           $actor Writer.
	 * @param int           $expected CAS.
	 * @param int           $now Clock.
	 * @param string        $reason Private audit reason.
	 * @return self
	 */
	public function revise( NoticeContent $content, int $actor, int $expected, int $now, string $reason ): self {
		$this->guard( $actor, $expected, $now, $reason );
		if ( NoticeState::Pending === $this->state || ( null === $this->unit_id && $actor !== $this->author_id ) || $this->latest()->content->circuit !== $content->circuit || ( null !== $this->unit_id && $content->audience->unit_id !== $this->unit_id ) ) {
			self::invalid();
		}
		if ( $content->toArray() === $this->latest()->content->toArray() ) {
			return $this;
		}
		$versions   = $this->versions;
		$versions[] = new NoticeVersion( count( $versions ) + 1, $content, $actor, $now );
		return $this->transition( 'revised', $actor, $now, $reason, $versions, NoticeState::Draft, null, $this->last_submitted_version );
	}

	/** Submit a new frozen version.
	 *
	 * @param int    $actor Author.
	 * @param int    $expected CAS.
	 * @param int    $now Clock.
	 * @param string $reason Audit reason.
	 * @return self
	 */
	public function submit( int $actor, int $expected, int $now, string $reason ): self {
		$this->guard( $actor, $expected, $now, $reason );
		if ( null !== $this->unit_id || $actor !== $this->author_id || NoticeState::Draft !== $this->state || $this->last_submitted_version === $this->latest()->number ) {
			self::invalid();
		}
		return $this->transition( 'submitted', $actor, $now, $reason, $this->versions, NoticeState::Pending, $this->latest()->number, $this->latest()->number );
	}

	/** Second actor decides the exact current submission.
	 *
	 * @param string $operation approve/reject/return.
	 * @param int    $version Submitted version.
	 * @param int    $actor Reviewer.
	 * @param int    $expected CAS resolved from the opaque token.
	 * @param int    $now Clock.
	 * @param string $reason Private reason.
	 * @return self
	 */
	public function decide( string $operation, int $version, int $actor, int $expected, int $now, string $reason ): self {
		$this->guard( $actor, $expected, $now, $reason );
		if ( null !== $this->unit_id || NoticeState::Pending !== $this->state || $actor === $this->author_id || $version !== $this->submitted_version || ! in_array( $operation, array( 'approve', 'reject', 'return' ), true ) ) {
			self::invalid();
		}
		$state = match ( $operation ) {
			'approve' => NoticeState::Published,
			'reject' => NoticeState::Rejected,
			'return' => NoticeState::Draft,
		};
		return $this->transition(
			array(
				'approve' => 'approved',
				'reject'  => 'rejected',
				'return'  => 'returned',
			)[ $operation ],
			$actor,
			$now,
			$reason,
			$this->versions,
			$state,
			null,
			$this->last_submitted_version,
			'approve' === $operation
		);
	}

	/** Publish the current team draft.
	 *
	 * @param int    $actor Manager.
	 * @param int    $expected CAS.
	 * @param int    $now Clock.
	 * @param string $reason Audit reason.
	 * @return self
	 */
	public function publish( int $actor, int $expected, int $now, string $reason ): self {
		$this->guard( $actor, $expected, $now, $reason );
		if ( null === $this->unit_id || NoticeState::Draft !== $this->state ) {
			self::invalid();
		}
		return $this->transition( 'published', $actor, $now, $reason, $this->versions, NoticeState::Published, null, $this->last_submitted_version, true );
	}

	/** Archive is terminal.
	 *
	 * @param int    $actor Manager or institutional author.
	 * @param int    $expected CAS.
	 * @param int    $now Clock.
	 * @param string $reason Audit reason.
	 * @return self
	 */
	public function archive( int $actor, int $expected, int $now, string $reason ): self {
		$this->guard( $actor, $expected, $now, $reason );
		if ( null === $this->unit_id && $actor !== $this->author_id ) {
			self::invalid();
		}
		return $this->transition( 'archived', $actor, $now, $reason, $this->versions, NoticeState::Archived, null, $this->last_submitted_version );
	}

	/** Current public content only, with no private workflow metadata.
	 *
	 * @param int $now Clock.
	 * @return NoticeVersion|null
	 */
	public function published( int $now ): ?NoticeVersion {
		if ( null === $this->published_version || NoticeState::Archived === $this->state || $now < $this->published_at ) {
			return null;
		}
		$version = $this->versions[ $this->published_version - 1 ];
		return null === $version->content->valid_until || $now < $version->content->valid_until ? $version : null;
	}

	/** Latest private version.
	 *
	 * @return NoticeVersion
	 */
	public function latest(): NoticeVersion {
		return $this->versions[ count( $this->versions ) - 1 ];
	}

	/** Complete private persistence value, never a public DTO.
	 *
	 * @return array
	 */
	public function toArray(): array {
		return array(
			'id'                     => $this->id,
			'author_id'              => $this->author_id,
			'unit_id'                => $this->unit_id,
			'revision'               => $this->revision,
			'state'                  => $this->state->value,
			'versions'               => array_map( static fn( NoticeVersion $v ): array => $v->toArray(), $this->versions ),
			'published_version'      => $this->published_version,
			'published_at'           => $this->published_at,
			'published_revision'     => $this->published_revision,
			'submitted_version'      => $this->submitted_version,
			'last_submitted_version' => $this->last_submitted_version,
			'history'                => $this->history,
		);
	}

	/** Strict hydration, then full audit replay.
	 *
	 * @param array $data Private stored data.
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		$keys = array( 'id', 'author_id', 'unit_id', 'revision', 'state', 'versions', 'published_version', 'published_at', 'published_revision', 'submitted_version', 'last_submitted_version', 'history' );
		if ( count( $data ) !== count( $keys ) || array_diff( array_keys( $data ), $keys ) || ! is_int( $data['id'] ) || ! is_int( $data['author_id'] ) || ! is_int( $data['revision'] ) || ! is_string( $data['state'] ) || null === NoticeState::tryFrom( $data['state'] ) || ! is_array( $data['versions'] ) || ! array_is_list( $data['versions'] ) || ! is_array( $data['history'] ) || ( null !== $data['unit_id'] && ! is_string( $data['unit_id'] ) ) ) {
			self::invalid();
		}
		foreach ( array( 'published_version', 'published_at', 'published_revision', 'submitted_version', 'last_submitted_version' ) as $key ) {
			if ( null !== $data[ $key ] && ! is_int( $data[ $key ] ) ) {
				self::invalid();
			}
		}
		$versions = array();
		foreach ( $data['versions'] as $version ) {
			if ( ! is_array( $version ) ) {
				self::invalid();
			}
			$versions[] = NoticeVersion::fromArray( $version );
		}
		return new self( $data['id'], $data['author_id'], $data['unit_id'], $data['revision'], NoticeState::from( $data['state'] ), $versions, $data['published_version'], $data['published_at'], $data['published_revision'], $data['submitted_version'], $data['last_submitted_version'], $data['history'] );
	}

	/** Reject stale CAS even for no-op operations.
	 *
	 * @param int    $actor Actor.
	 * @param int    $expected Expected revision.
	 * @param int    $now Clock.
	 * @param string $reason Reason.
	 */
	private function guard( int $actor, int $expected, int $now, string $reason ): void {
		if ( $actor < 1 || $expected !== $this->revision || PHP_INT_MAX === $this->revision || NoticeState::Archived === $this->state || $now < $this->history[ $this->revision - 1 ]['occurred_at'] ) {
			self::invalid();
		}
		PlainText::value( $reason, 1000, true );
	}

	/** Reconstruct through the same validation used for persisted values.
	 *
	 * @param string      $op Operation.
	 * @param int         $actor Actor.
	 * @param int         $now Clock.
	 * @param string      $reason Reason.
	 * @param array       $versions Immutable versions.
	 * @param NoticeState $state Resulting state.
	 * @param int|null    $submitted Frozen version.
	 * @param int|null    $last Last submitted version.
	 * @param bool        $publish Replace public reference.
	 * @return self
	 */
	private function transition( string $op, int $actor, int $now, string $reason, array $versions, NoticeState $state, ?int $submitted, ?int $last, bool $publish = false ): self {
		$revision  = $this->revision + 1;
		$number    = count( $versions );
		$history   = $this->history;
		$history[] = self::entry( $op, $actor, $number, $revision, $now, $reason );
		return new self( $this->id, $this->author_id, $this->unit_id, $revision, $state, $versions, $publish ? $number : $this->published_version, $publish ? $now : $this->published_at, $publish ? $revision : $this->published_revision, $submitted, $last, $history );
	}

	/** One private transition.
	 *
	 * @param string $op Operation.
	 * @param int    $actor Actor.
	 * @param int    $version Version.
	 * @param int    $revision Revision.
	 * @param int    $now Clock.
	 * @param string $reason Reason.
	 * @return array
	 */
	private static function entry( string $op, int $actor, int $version, int $revision, int $now, string $reason ): array {
		return array(
			'operation'   => $op,
			'actor_id'    => $actor,
			'version'     => $version,
			'revision'    => $revision,
			'occurred_at' => $now,
			'reason'      => PlainText::value( $reason, 1000, true ),
		);
	}

	/** Fail before any side effect.
	 *
	 * @throws InvalidArgumentException For invalid stored data.
	 * @return never
	 */
	private static function invalid(): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain error.
		throw new InvalidArgumentException( 'Transição de comunicado inválida.' );
	}
}
