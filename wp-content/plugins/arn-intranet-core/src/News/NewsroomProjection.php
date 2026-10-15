<?php
/** Reduced authorised CMS rows.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

// phpcs:disable Squiz.Commenting.FunctionComment
/** Never serialise an Article into HTML or filters. */
final class NewsroomProjection {
	/** Project exactly the actor-visible version. */
	public static function article( Article $article, bool $own ): ?array {
		$number  = $own ? count( $article->versions ) : $article->submitted_version;
		$version = null === $number ? null : $article->version( $number );
		if ( null === $version || ( ! $own && ! $article->wasSubmitted( $number ) ) ) {
			return null;
		}
		$state = $own ? strtolower( $article->state->name ) : 'pending';
		if ( ! $own ) {
			foreach ( $article->transitions as $event ) {
				if ( 'archived' === $event->event && $article->wasSubmitted( $event->version ) ) {
					$state = 'archived';
					continue;
				}
				if ( $event->version === $number && in_array( $event->event, array( 'approved', 'rejected', 'returned', 'archived' ), true ) ) {
					$state = match ( $event->event ) {
						'approved' => 'published', 'rejected' => 'rejected', 'returned' => 'draft', 'archived' => 'archived',
						default => 'pending',
					};
				}
			}
		}
		return array(
			'id'        => $article->id,
			'author_id' => $article->author_id,
			'version'   => $number,
			'state'     => $state,
			'title'     => $version->content->title,
			'audience'  => $version->content->audience,
			'at'        => $own ? $version->created_at : $article->submitted_at,
		);
	}

	/** Localised label for the visible editorial state. */
	public static function stateLabel( string $state ): string {
		return match ( $state ) {
			'draft' => 'Rascunho', 'pending' => 'Pendente', 'rejected' => 'Rejeitada',
			'published' => 'Publicada', 'archived' => 'Arquivada', default => 'Indisponível',
		};
	}

	/** Remove private events and global revision counters. */
	public static function history( array $rows, Article $article, bool $own ): array {
		$visible = array();
		foreach ( $rows as $row ) {
			if ( ! $row instanceof \stdClass || ! isset( $row->version, $row->event, $row->actor_id, $row->occurred_at ) ) {
				continue;
			}
			$number = (int) $row->version;
			if ( ! $own && ! $article->wasSubmitted( $number ) ) {
				continue;
			}
			if ( ! $own && 'created' === $row->event ) {
				continue;
			}
			$visible[] = (object) array(
				'version'     => $number,
				'event'       => (string) $row->event,
				'actor_id'    => (int) $row->actor_id,
				'occurred_at' => (int) $row->occurred_at,
				'reason'      => (string) ( $row->reason ?? '' ),
			);
		}
		return $visible;
	}
}
