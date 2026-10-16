<?php
/** Audited immutable event lifecycle.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Calendar;

use Arn\Intranet\F112\PlainText;
use InvalidArgumentException;

/** Publication and organiser remain stable while the current draft changes. */
final readonly class Event {

	/** Detached immutable versions.
	 *
	 * @var array<EventVersion>
	 */
	public array $versions;

	/** Detached scalar audit records.
	 *
	 * @var array<array>
	 */
	public array $history;

	/** Hydrate only if the entire history proves the derived state.
	 *
	 * @param int         $id Native private anchor.
	 * @param int         $author_id Original organiser.
	 * @param string|null $unit_id Immutable owner unit, null for institutional.
	 * @param int         $revision CAS.
	 * @param EventState  $state Derived state.
	 * @param array       $versions Ordered immutable versions.
	 * @param int|null    $published_version Current published version.
	 * @param int|null    $published_at Publication clock.
	 * @param int|null    $published_revision Publication revision.
	 * @param int|null    $cancelled_at Cancellation clock.
	 * @param array       $history Complete audit transitions.
	 */
	public function __construct( public int $id, public int $author_id, public ?string $unit_id, public int $revision, public EventState $state, array $versions, public ?int $published_version, public ?int $published_at, public ?int $published_revision, public ?int $cancelled_at, array $history ) {
		if ( $id < 1 || $author_id < 1 || $revision < 1 || ! $versions || ! array_is_list( $versions ) || ! array_is_list( $history ) || count( $history ) !== $revision || ( null !== $unit_id && ! in_array( $unit_id, array( 'LAB-A', 'LAB-B' ), true ) ) ) {
			self::invalid();
		}
		foreach ( $versions as $i => $version ) {
			if ( ! $version instanceof EventVersion || $version->number !== $i + 1 || ( null !== $unit_id && $version->content->audience->unit_id !== $unit_id ) ) {
				self::invalid();
			}
		}
		$derived   = EventState::Draft;
		$seen      = 0;
		$time      = -1;
		$pub       = null;
		$at        = null;
		$pub_rev   = null;
		$cancelled = null;
		foreach ( $history as $i => $entry ) {
			if ( ! is_array( $entry ) || 6 !== count( $entry ) || array_diff( array_keys( $entry ), array( 'operation', 'actor_id', 'version', 'revision', 'occurred_at', 'reason' ) ) || ! is_string( $entry['operation'] ) || ! is_int( $entry['actor_id'] ) || $entry['actor_id'] < 1 || ! is_int( $entry['version'] ) || ! is_int( $entry['revision'] ) || $entry['revision'] !== $i + 1 || ! is_int( $entry['occurred_at'] ) || $entry['occurred_at'] < 0 || $entry['occurred_at'] < $time || ! is_string( $entry['reason'] ) || PlainText::value( $entry['reason'], 1000, true ) !== $entry['reason'] || EventState::Archived === $derived ) {
				self::invalid();
			}
			$op     = $entry['operation'];
			$actor  = $entry['actor_id'];
			$number = $entry['version'];
			$time   = $entry['occurred_at'];
			if ( ( 0 === $i && 'created' !== $op ) || ( EventState::Cancelled === $derived && 'archived' !== $op ) ) {
				self::invalid();
			}
			if ( in_array( $op, array( 'created', 'revised' ), true ) ) {
				$version = $versions[ $seen ] ?? null;
				if ( ( 'created' === $op ) !== ( 0 === $i ) || $number !== $seen + 1 || null === $version || $version->actor_id !== $actor || $version->created_at !== $time || ( 0 === $i && $actor !== $author_id ) || ( $seen > 0 && $versions[ $seen - 1 ]->content->toArray() === $version->content->toArray() ) ) {
					self::invalid();
				}
				++$seen;
				$derived = EventState::Draft;
			} elseif ( 'published' === $op ) {
				if ( EventState::Draft !== $derived || $number !== $seen ) {
					self::invalid();
				}
				$pub     = $number;
				$at      = $time;
				$pub_rev = $i + 1;
				$derived = EventState::Published;
			} elseif ( 'cancelled' === $op ) {
				if ( null === $pub || $number !== $pub ) {
					self::invalid();
				}
				$cancelled = $time;
				$derived   = EventState::Cancelled;
			} elseif ( 'archived' === $op ) {
				if ( $number !== $seen ) {
					self::invalid();
				}
				$derived = EventState::Archived;
			} else {
				self::invalid();
			}
		}
		if ( count( $versions ) !== $seen || $state !== $derived || $published_version !== $pub || $published_at !== $at || $published_revision !== $pub_rev || $cancelled_at !== $cancelled ) {
			self::invalid();
		}
		$version_copy = array();
		foreach ( $versions as $version ) {
			$version_copy[] = $version;
		}
		$history_copy = array();
		foreach ( $history as $entry ) {
			$history_copy[] = self::entry( $entry['operation'], $entry['actor_id'], $entry['version'], $entry['revision'], $entry['occurred_at'], $entry['reason'] );
		}
		$this->versions = $version_copy;
		$this->history  = $history_copy;
	}

	/** Create in a server-resolved owner scope.
	 *
	 * @param int          $id Anchor.
	 * @param int          $author Organiser.
	 * @param string|null  $unit_id Owner.
	 * @param EventContent $content Version.
	 * @param int          $now Clock.
	 * @param string       $reason Audit reason.
	 * @return self
	 */
	public static function draft( int $id, int $author, ?string $unit_id, EventContent $content, int $now, string $reason = 'Criação de rascunho.' ): self {
		return new self( $id, $author, $unit_id, 1, EventState::Draft, array( new EventVersion( 1, $content, $author, $now ) ), null, null, null, null, array( self::entry( 'created', $author, 1, 1, $now, $reason ) ) );
	}

	/** New draft keeps the current published version.
	 *
	 * @param EventContent $content New content.
	 * @param int          $actor Writer.
	 * @param int          $expected CAS.
	 * @param int          $now Clock.
	 * @param string       $reason Private reason.
	 * @return self
	 */
	public function revise( EventContent $content, int $actor, int $expected, int $now, string $reason ): self {
		$this->guard( 'revise', $actor, $expected, $now, $reason );
		if ( null !== $this->unit_id && $content->audience->unit_id !== $this->unit_id ) {
			self::invalid();
		}
		if ( $content->toArray() === $this->latest()->content->toArray() ) {
			return $this;
		}
		$versions   = $this->versions;
		$versions[] = new EventVersion( count( $versions ) + 1, $content, $actor, $now );
		return $this->transition( 'revised', $actor, $now, $reason, $versions, EventState::Draft );
	}

	/** Publish latest version atomically.
	 *
	 * @param int    $actor Manager.
	 * @param int    $expected CAS.
	 * @param int    $now Clock.
	 * @param string $reason Reason.
	 * @return self
	 */
	public function publish( int $actor, int $expected, int $now, string $reason ): self {
		$this->guard( 'publish', $actor, $expected, $now, $reason );
		if ( EventState::Draft !== $this->state ) {
			self::invalid();
		}
		return $this->transition( 'published', $actor, $now, $reason, $this->versions, EventState::Published );
	}

	/** Cancellation retains the last public content, even during an unpublished revision.
	 *
	 * @param int    $actor Manager.
	 * @param int    $expected CAS.
	 * @param int    $now Clock.
	 * @param string $reason Reason.
	 * @return self
	 */
	public function cancel( int $actor, int $expected, int $now, string $reason ): self {
		$this->guard( 'cancel', $actor, $expected, $now, $reason );
		if ( null === $this->published_version ) {
			self::invalid();
		}
		return $this->transition( 'cancelled', $actor, $now, $reason, $this->versions, EventState::Cancelled );
	}

	/** Terminal archive preserves every private version and reason.
	 *
	 * @param int    $actor Manager.
	 * @param int    $expected CAS.
	 * @param int    $now Clock.
	 * @param string $reason Reason.
	 * @return self
	 */
	public function archive( int $actor, int $expected, int $now, string $reason ): self {
		$this->guard( 'archive', $actor, $expected, $now, $reason );
		return $this->transition( 'archived', $actor, $now, $reason, $this->versions, EventState::Archived );
	}

	/** Cancelled detail uses the original public version, never its private reason.
	 *
	 * @return EventVersion|null
	 */
	public function published(): ?EventVersion {
		return null === $this->published_version || EventState::Archived === $this->state ? null : $this->versions[ $this->published_version - 1 ];
	}

	/** Latest private version.
	 *
	 * @return EventVersion
	 */
	public function latest(): EventVersion {
		return $this->versions[ count( $this->versions ) - 1 ];
	}

	/** Complete private data, not a public projection.
	 *
	 * @return array
	 */
	public function toArray(): array {
		return array(
			'id'                 => $this->id,
			'author_id'          => $this->author_id,
			'unit_id'            => $this->unit_id,
			'revision'           => $this->revision,
			'state'              => $this->state->value,
			'versions'           => array_map( static fn( EventVersion $v ): array => $v->toArray(), $this->versions ),
			'published_version'  => $this->published_version,
			'published_at'       => $this->published_at,
			'published_revision' => $this->published_revision,
			'cancelled_at'       => $this->cancelled_at,
			'history'            => $this->history,
		);
	}

	/** Strict stored shape before audit replay.
	 *
	 * @param array $data Private persistent data.
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		$keys = array( 'id', 'author_id', 'unit_id', 'revision', 'state', 'versions', 'published_version', 'published_at', 'published_revision', 'cancelled_at', 'history' );
		if ( count( $data ) !== count( $keys ) || array_diff( array_keys( $data ), $keys ) || ! is_int( $data['id'] ) || ! is_int( $data['author_id'] ) || ! is_int( $data['revision'] ) || ! is_string( $data['state'] ) || null === EventState::tryFrom( $data['state'] ) || ! is_array( $data['versions'] ) || ! array_is_list( $data['versions'] ) || ! is_array( $data['history'] ) || ( null !== $data['unit_id'] && ! is_string( $data['unit_id'] ) ) ) {
			self::invalid();
		}
		foreach ( array( 'published_version', 'published_at', 'published_revision', 'cancelled_at' ) as $key ) {
			if ( null !== $data[ $key ] && ! is_int( $data[ $key ] ) ) {
				self::invalid();
			}
		}
		$versions = array();
		foreach ( $data['versions'] as $version ) {
			if ( ! is_array( $version ) ) {
				self::invalid();
			}
			$versions[] = EventVersion::fromArray( $version );
		}
		return new self( $data['id'], $data['author_id'], $data['unit_id'], $data['revision'], EventState::from( $data['state'] ), $versions, $data['published_version'], $data['published_at'], $data['published_revision'], $data['cancelled_at'], $data['history'] );
	}

	/** Check CAS before no-op and disallow all cancellation reactivation paths.
	 *
	 * @param string $op Operation.
	 * @param int    $actor Actor.
	 * @param int    $expected Expected revision.
	 * @param int    $now Clock.
	 * @param string $reason Private reason.
	 */
	private function guard( string $op, int $actor, int $expected, int $now, string $reason ): void {
		if ( $actor < 1 || $expected !== $this->revision || PHP_INT_MAX === $this->revision || $now < $this->history[ $this->revision - 1 ]['occurred_at'] || EventState::Archived === $this->state || ( EventState::Cancelled === $this->state && 'archive' !== $op ) ) {
			self::invalid();
		}
		PlainText::value( $reason, 1000, true );
	}

	/** Apply and validate one audited transition.
	 *
	 * @param string     $op Operation.
	 * @param int        $actor Actor.
	 * @param int        $now Clock.
	 * @param string     $reason Reason.
	 * @param array      $versions Immutable versions.
	 * @param EventState $state New state.
	 * @return self
	 */
	private function transition( string $op, int $actor, int $now, string $reason, array $versions, EventState $state ): self {
		$revision  = $this->revision + 1;
		$history   = $this->history;
		$history[] = self::entry( $op, $actor, 'cancelled' === $op ? $this->published_version : count( $versions ), $revision, $now, $reason );
		return new self( $this->id, $this->author_id, $this->unit_id, $revision, $state, $versions, 'published' === $op ? count( $versions ) : $this->published_version, 'published' === $op ? $now : $this->published_at, 'published' === $op ? $revision : $this->published_revision, 'cancelled' === $op ? $now : $this->cancelled_at, $history );
	}

	/** One private audit record.
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

	/** Fail closed.
	 *
	 * @return never
	 * @throws InvalidArgumentException For invalid state or history.
	 */
	private static function invalid(): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain error.
		throw new InvalidArgumentException( 'Transição de evento inválida.' );
	}
}
