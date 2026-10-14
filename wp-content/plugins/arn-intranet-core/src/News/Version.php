<?php
/** One immutable news revision.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\News;

use InvalidArgumentException;

/** Version number and author cannot be changed after creation. */
final readonly class Version {
	/** Validate stable number, actor and UTC timestamp.
	 *
	 * @param int     $number Version number.
	 * @param Content $content Immutable content.
	 * @param int     $actor_id Author account.
	 * @param int     $created_at Unix UTC timestamp.
	 * @throws InvalidArgumentException For invalid values.
	 */
	public function __construct( public int $number, public Content $content, public int $actor_id, public int $created_at ) {
		if ( $number <= 0 || $actor_id <= 0 || $created_at < 0 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain message.
			throw new InvalidArgumentException( 'Versão de notícia inválida.' );
		}
	}
}
