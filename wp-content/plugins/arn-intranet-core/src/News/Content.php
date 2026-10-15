<?php
/** Immutable text and ordered assets of a news version.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\News;

use InvalidArgumentException;

/** Plain text stays unescaped until presentation. */
final readonly class Content {
	/** Ordered gallery assets.
	 *
	 * @var array<Asset>
	 */
	public array $gallery;
	/** Ordered attachment assets.
	 *
	 * @var array<Asset>
	 */
	public array $attachments;
	/** Normalized plain text body.
	 *
	 * @var string
	 */
	public string $body;

	/** Require visible body text and a featured image for submission.
	 *
	 * @return bool
	 */
	public function completeForSubmission(): bool {
		return null !== $this->featured && 1 === preg_match( '/[^\s\p{Z}]/u', $this->body );
	}

	/** Require an explicit audience for every new submission. */
	public function completeForPublication(): bool {
		return $this->completeForSubmission() && null !== $this->audience;
	}

	/** Validate and detach text and ordered lists.
	 *
	 * @param string        $title Title.
	 * @param string        $body Body with optional paragraphs.
	 * @param string        $category Closed category.
	 * @param Asset|null    $featured Optional featured image.
	 * @param array<Asset>  $gallery Ordered images.
	 * @param array<Asset>  $attachments Ordered private attachments.
	 * @param Audience|null $audience Explicit audience, when selected.
	 * @throws InvalidArgumentException For invalid content.
	 */
	public function __construct( public string $title, string $body, public string $category, public ?Asset $featured, array $gallery, array $attachments, public ?Audience $audience = null ) {
		$normal = str_replace( "\r\n", "\n", $body );
		if ( ! self::plainText( $title, 1, 200 )
			|| ! self::plainText( $normal, 0, 20000, true )
			|| ! in_array( $category, array( 'circular', 'nota-interna', 'despacho', 'comunicacao-sindical' ), true )
			|| ( null !== $featured && 'featured' !== $featured->kind )
			|| count( $gallery ) > 8 || count( $attachments ) > 5
			|| ! self::orderedAssets( $gallery, 'gallery' ) || ! self::orderedAssets( $attachments, 'attachment' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain message.
			throw new InvalidArgumentException( 'Conteúdo de notícia inválido.' );
		}
		$this->body        = $normal;
		$this->gallery     = array_map( static fn( Asset $asset ): Asset => $asset, $gallery );
		$this->attachments = array_map( static fn( Asset $asset ): Asset => $asset, $attachments );
	}

	/** Validate UTF-8 plain text and character limits.
	 *
	 * @param string $value Text to validate.
	 * @param int    $min Minimum characters.
	 * @param int    $max Maximum characters.
	 * @param bool   $paragraphs Whether LF is allowed.
	 * @return bool
	 */
	public static function plainText( string $value, int $min, int $max, bool $paragraphs = false ): bool {
		$length  = preg_match_all( '/./us', $value );
		$checked = $paragraphs ? str_replace( "\n", '', $value ) : $value;
		return false !== $length && $length >= $min && $length <= $max
			&& ( 0 === $min || 1 === preg_match( '/[^\s\p{Z}]/u', $value ) )
			&& 0 === preg_match( '/[\p{C}<>]/u', $checked );
	}

	/** Require contiguous, kind-correct elements.
	 *
	 * @param array<Asset> $assets Asset list.
	 * @param string       $kind Expected kind.
	 * @return bool
	 */
	private static function orderedAssets( array $assets, string $kind ): bool {
		if ( array_keys( $assets ) !== range( 0, count( $assets ) - 1 ) && array() !== $assets ) {
			return false;
		}
		foreach ( $assets as $asset ) {
			if ( ! $asset instanceof Asset || $asset->kind !== $kind ) {
				return false;
			}
		}
		return true;
	}
}
