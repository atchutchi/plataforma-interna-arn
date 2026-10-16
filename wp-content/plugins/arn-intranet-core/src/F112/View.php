<?php
/**
 * Minimal publication projections.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\F112;

use Arn\Intranet\Communications\Notice;
use Arn\Intranet\Calendar\{Event, EventState};

// phpcs:disable Squiz.Commenting -- Projection contains only the current published reference.
final class View {
	public static function management( array $model ): void {
		if ( ! isset( $model['module'] ) ) {
			return;
		}
		require dirname( __DIR__, 2 ) . '/views/f112-management.php';
	}
	public static function published( Notice|Event $aggregate, int $now ): ?array {
		$version = $aggregate instanceof Notice ? $aggregate->published( $now ) : $aggregate->published();
		if ( null === $version ) {
			return null;
		}
		$content = $version->content;
		$common  = array(
			'id'           => $aggregate->id,
			'title'        => $content->title,
			'version'      => $version->number,
			'author_id'    => $aggregate->author_id,
			'audience'     => $content->audience->unit_id ?? 'Toda a comunidade',
			'published_at' => $aggregate->published_at,
		);
		return $aggregate instanceof Notice ? $common + array(
			'body'        => $content->body,
			'valid_until' => $content->valid_until,
		) : $common + array(
			'body'      => $content->description,
			'mode'      => $content->mode,
			'location'  => $content->location,
			'url'       => $content->url,
			'all_day'   => $content->all_day,
			'start'     => $content->start(),
			'end'       => $content->end(),
			'cancelled' => EventState::Cancelled === $aggregate->state,
		);
	}

	public static function stateLabel( string $value ): string {
		return array(
			'Draft'     => 'Rascunho',
			'Pending'   => 'Em revisão',
			'Rejected'  => 'Devolvido ou rejeitado',
			'Published' => 'Publicado',
			'Cancelled' => 'Cancelado',
			'Archived'  => 'Arquivado',
		)[ $value ] ?? $value;
	}
}
