<?php
/** Immutable aggregate for a private document.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Documents\Catalog;

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\ScopeType;
use InvalidArgumentException;

/** Every transition returns a new aggregate with checked concurrency. */
final readonly class Document {
	/** Stored versions in ascending order.
	 *
	 * @var array<Revision>
	 */
	public array $versions;

	/** Reject incomplete or contradictory hydrated state.
	 *
	 * @param int             $id                Document ID.
	 * @param Scope           $management_scope  Persisted management scope.
	 * @param int             $revision          Concurrency revision.
	 * @param array<Revision> $versions          Contiguous versions.
	 * @param int|null        $published_version Published version.
	 * @param bool            $archived          Terminal state.
	 */
	public function __construct(
		public int $id,
		public Scope $management_scope,
		public int $revision,
		array $versions,
		public ?int $published_version,
		public bool $archived,
	) {
		$count         = count( $versions );
		$archive_steps = $archived ? 1 : 0;
		$minimum       = $count + ( null === $published_version ? 0 : 1 ) + $archive_steps;
		$maximum       = $count + ( $published_version ?? 0 ) + $archive_steps;
		if ( $id <= 0 || ! in_array( $management_scope->type, array( ScopeType::Units, ScopeType::Institutional ), true )
			|| $count < 1 || $revision < $minimum || $revision > $maximum
			|| ( null !== $published_version && ( $published_version < 1 || $published_version > $count ) ) ) {
			self::invalid();
		}
		$copy = array();
		foreach ( array_values( $versions ) as $index => $version ) {
			if ( ! $version instanceof Revision || $version->number !== $index + 1 ) {
				self::invalid();
			}
			$copy[] = $version;
		}
		$this->versions = $copy;
	}

	/** Create an unpublished document from version one.
	 *
	 * @param int      $id      Document ID.
	 * @param Scope    $scope   Management scope.
	 * @param Revision $initial First version.
	 * @return self
	 */
	public static function create( int $id, Scope $scope, Revision $initial ): self {
		if ( 1 !== $initial->number ) {
			self::invalid();
		}
		return new self( $id, $scope, 1, array( $initial ), null, false );
	}

	/** Append a version while preserving the published version.
	 *
	 * @param Revision $next              New version.
	 * @param int      $expected_revision Concurrency revision.
	 * @return self
	 */
	public function revise( Revision $next, int $expected_revision ): self {
		$this->assertWritable( $expected_revision );
		if ( count( $this->versions ) + 1 !== $next->number ) {
			self::invalid();
		}
		$versions   = $this->versions;
		$versions[] = $next;
		return new self( $this->id, $this->management_scope, $this->revision + 1, $versions, $this->published_version, false );
	}

	/** Publish only the latest version.
	 *
	 * @param int $number            Version number.
	 * @param int $expected_revision Concurrency revision.
	 * @return self
	 */
	public function publish( int $number, int $expected_revision ): self {
		$this->assertWritable( $expected_revision );
		if ( count( $this->versions ) !== $number || $this->published_version === $number ) {
			self::invalid();
		}
		return new self( $this->id, $this->management_scope, $this->revision + 1, $this->versions, $number, false );
	}

	/** Archive permanently.
	 *
	 * @param int $expected_revision Concurrency revision.
	 * @return self
	 */
	public function archive( int $expected_revision ): self {
		$this->assertWritable( $expected_revision );
		return new self( $this->id, $this->management_scope, $this->revision + 1, $this->versions, $this->published_version, true );
	}

	/** Find a version by its stable number.
	 *
	 * @param int $number Version number.
	 * @return Revision|null
	 */
	public function version( int $number ): ?Revision {
		return $number > 0 ? ( $this->versions[ $number - 1 ] ?? null ) : null;
	}

	/** Check the terminal state and the caller's expected revision.
	 *
	 * @param int $expected_revision Concurrency revision.
	 */
	private function assertWritable( int $expected_revision ): void {
		if ( $this->archived || $expected_revision !== $this->revision ) {
			self::invalid();
		}
	}

	/** Raise the constant domain error.
	 *
	 * @return never
	 * @throws InvalidArgumentException Always.
	 */
	private static function invalid(): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain message.
		throw new InvalidArgumentException( 'Documento inválido.' );
	}
}
