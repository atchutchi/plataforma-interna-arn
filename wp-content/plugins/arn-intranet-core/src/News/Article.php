<?php
/** Immutable aggregate for one private news article.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\News;

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\ScopeType;
use InvalidArgumentException;

/** Draft to pending is the only status transition in F1-10. */
final readonly class Article {
	/** Ordered immutable history.
	 *
	 * @var array<Version>
	 */
	public array $versions;

	/** Reject contradictory hydrated state and reused private IDs.
	 *
	 * @param int            $id Article ID.
	 * @param int            $author_id Fixed author account.
	 * @param Scope          $review_scope One editorial queue.
	 * @param int            $revision Concurrency revision.
	 * @param array<Version> $versions Contiguous ordered history.
	 * @param int|null       $submitted_version Submitted version.
	 * @param int|null       $submitted_at Submission timestamp.
	 * @throws InvalidArgumentException For invalid state.
	 */
	public function __construct( public int $id, public int $author_id, public Scope $review_scope, public int $revision, array $versions, public ?int $submitted_version, public ?int $submitted_at ) {
		$count   = count( $versions );
		$pending = null !== $submitted_version;
		if ( $id <= 0 || $author_id <= 0 || ScopeType::Queues !== $review_scope->type || 1 !== count( $review_scope->targets )
			|| $count < 1 || array_keys( $versions ) !== range( 0, $count - 1 )
			|| $count + ( $pending ? 1 : 0 ) !== $revision
			|| ( null !== $submitted_at ) !== $pending
			|| ( $pending && ( $count !== $submitted_version || $submitted_at < 0 ) ) ) {
			self::invalid();
		}
		$copy = array();
		$ids  = array();
		foreach ( $versions as $index => $version ) {
			if ( ! $version instanceof Version || $version->number !== $index + 1 || $version->actor_id !== $author_id ) {
				self::invalid();
			}
			$content = $version->content;
			$assets  = array_merge( null === $content->featured ? array() : array( $content->featured ), $content->gallery, $content->attachments );
			foreach ( $assets as $asset ) {
				if ( isset( $ids[ $asset->blob->id ] ) ) {
					self::invalid();
				}
				$ids[ $asset->blob->id ] = true;
			}
			$copy[] = $version;
		}
		if ( $pending && ( $submitted_at < $versions[ $count - 1 ]->created_at
			|| ! $versions[ $count - 1 ]->content->completeForSubmission() ) ) {
			self::invalid();
		}
		$this->versions = $copy;
	}

	/** Create a draft with version one.
	 *
	 * @param int     $id Article ID.
	 * @param int     $author_id Author account.
	 * @param Scope   $review_scope One editorial queue.
	 * @param Version $initial First version.
	 * @return self
	 */
	public static function create( int $id, int $author_id, Scope $review_scope, Version $initial ): self {
		return new self( $id, $author_id, $review_scope, 1, array( $initial ), null, null );
	}

	/** Append a new draft version under CAS.
	 *
	 * @param Version $next Next version.
	 * @param int     $expected Expected revision.
	 * @return self
	 */
	public function revise( Version $next, int $expected ): self {
		$this->assertDraft( $expected );
		$versions   = $this->versions;
		$versions[] = $next;
		return new self( $this->id, $this->author_id, $this->review_scope, $this->revision + 1, $versions, null, null );
	}

	/** Submit the latest complete draft under CAS.
	 *
	 * @param int $expected Expected revision.
	 * @param int $at Unix UTC timestamp.
	 * @return self
	 */
	public function submit( int $expected, int $at ): self {
		$this->assertDraft( $expected );
		$content = $this->versions[ count( $this->versions ) - 1 ]->content;
		if ( ! $content->completeForSubmission() ) {
			self::invalid();
		}
		return new self( $this->id, $this->author_id, $this->review_scope, $this->revision + 1, $this->versions, count( $this->versions ), $at );
	}

	/** Read one historic version by number.
	 *
	 * @param int $number Version number.
	 * @return Version|null
	 */
	public function version( int $number ): ?Version {
		return $number > 0 ? ( $this->versions[ $number - 1 ] ?? null ) : null;
	}

	/** Whether this article awaits editorial review.
	 *
	 * @return bool
	 */
	public function pending(): bool {
		return null !== $this->submitted_version;
	}

	/** Reject stale or pending changes.
	 *
	 * @param int $expected Expected revision.
	 * @return void
	 */
	private function assertDraft( int $expected ): void {
		if ( $this->pending() || $expected !== $this->revision ) {
			self::invalid();
		}
	}

	/** Raise a constant domain error.
	 *
	 * @return never
	 * @throws InvalidArgumentException Always.
	 */
	private static function invalid(): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain message.
		throw new InvalidArgumentException( 'Notícia inválida.' );
	}
}
