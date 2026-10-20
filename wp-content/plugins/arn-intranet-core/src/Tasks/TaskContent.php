<?php
/** Canonical content for the fictional task workflow.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

use Arn\Intranet\F112\Input;
use Arn\Intranet\F112\PlainText;
use InvalidArgumentException;

/** Text is plain UTF-8. Dates are explicit UTC, never browser locale. */
final readonly class TaskContent {

	/** Canonical one-line title.
	 *
	 * @var string
	 */
	public string $title;

	/** Canonical multiline description.
	 *
	 * @var string
	 */
	public string $description;

	/** Unix UTC deadline, absent when there is no deadline.
	 *
	 * @var int|null
	 */
	public ?int $due_at;

	/** Validate before any persistence or output.
	 *
	 * @param string      $title Raw title.
	 * @param string      $description Raw description.
	 * @param string      $priority Closed priority.
	 * @param string|null $due Canonical UTC or absence.
	 */
	public function __construct( string $title, string $description, public string $priority, ?string $due ) {
		if ( ! in_array( $priority, array( 'low', 'normal', 'high' ), true ) || false !== strpbrk( $title . $description, '<>' ) ) {
			self::invalid();
		}
		$this->title       = PlainText::value( $title, 160, false );
		$this->description = PlainText::value( $description, 8000, true, false );
		$this->due_at      = null === $due ? null : Input::utc( $due );
	}

	/** Detached storage representation.
	 *
	 * @return array{title:string,description:string,priority:string,due_at:int|null}
	 */
	public function toArray(): array {
		return array(
			'title'       => $this->title,
			'description' => $this->description,
			'priority'    => $this->priority,
			'due_at'      => $this->due_at,
		);
	}

	/** Hydration refuses corruption instead of silently trimming stored data.
	 *
	 * @param array $value Persisted fields.
	 * @return self
	 */
	public static function fromArray( array $value ): self {
		if ( 4 !== count( $value ) || array_diff( array_keys( $value ), array( 'title', 'description', 'priority', 'due_at' ) ) || ! is_string( $value['title'] ) || ! is_string( $value['description'] ) || ! is_string( $value['priority'] ) || ( null !== $value['due_at'] && ( ! is_int( $value['due_at'] ) || $value['due_at'] < 0 || $value['due_at'] > 253402300799 ) ) ) {
			self::invalid();
		}
		$content   = new self( $value['title'], $value['description'], $value['priority'], null === $value['due_at'] ? null : gmdate( 'Y-m-d\TH:i:s\Z', $value['due_at'] ) );
		$canonical = $content->toArray();
		foreach ( $value as $key => $field ) {
			if ( $canonical[ $key ] !== $field ) {
				self::invalid();
			}
		}
		return $content;
	}

	/** Static domain error, with no input echoed into logs.
	 *
	 * @throws InvalidArgumentException For malformed task values.
	 */
	private static function invalid(): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain message.
		throw new InvalidArgumentException( 'Conteúdo de tarefa inválido.' );
	}
}
