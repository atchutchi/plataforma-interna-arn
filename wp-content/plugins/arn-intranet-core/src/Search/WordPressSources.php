<?php
/** Read-only adapters to each published module's existing authority.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Search;

use Arn\Intranet\EditorialPreview\ServiceDirectory;
use Arn\Intranet\People\LocalPeopleDirectory;
use Arn\Intranet\People\PeopleQuery;
use Arn\Intranet\People\PeopleNavigation;
use Arn\Intranet\News\NewsPublishedPortal;
use Arn\Intranet\Documents\Catalog\DocumentPortal;
use Arn\Intranet\Communications\NoticeSelection;
use Arn\Intranet\Calendar\AgendaSelection;
use Arn\Intranet\F112\Portal;
use Arn\Intranet\Tasks\Portal as TaskPortal;
use Arn\Intranet\Tasks\TaskSelection;
use WP_Post;

// phpcs:disable Squiz.Commenting -- No preview, management, filesystem or raw SQL source is used.
final class WordPressSources {
	private ?array $destinations = null;

	public static function model( array $input ): array {
		$allowed = 'local' === wp_get_environment_type() && get_current_user_id() > 0 && ! is_admin()
			&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true );
		$source  = new self();
		return ( new FederatedSearch( $allowed ? $source->fetch( ... ) : static fn(): ?array => null ) )->model( $input );
	}

	public function fetch( string $key, Query $query, int $page ): ?array {
		if ( ! isset( FederatedSearch::SOURCES[ $key ] ) ) {
			return null; }
		$connections = new ReadConnections();
		try {
			return $this->read( $key, $query, $page, $connections ); } finally {
			$connections->close(); }
	}

	private function read( string $key, Query $query, int $page, ReadConnections $connections ): ?array {
		if ( 'news' === $key || 'documents' === $key ) {
			$url = self::collectionUrl( 'news' === $key ? 'noticias' : 'documentos', $key );
			if ( null === $url ) {
				return null; }
			$input = array(
				'q'        => $query->q,
				'arn_page' => (string) $page,
			);
			$list  = 'news' === $key ? NewsPublishedPortal::model( $input, Readers::news( $connections ) ) : DocumentPortal::model( 'documents', $input, Readers::documents( $connections ) );
			if ( ! $list['available'] ) {
				return null; }
			$parameter = 'news' === $key ? 'arn_news' : 'arn_doc';
		} else {
			$this->destinations ??= array_column( ( new ServiceDirectory( people: static fn(): array => PeopleNavigation::destinations( Readers::people( $connections ) ) ) )->items(), 'url', 'id' );
			// In the closed local roster, team scope includes the manager's own unit.
			// Choose one authorised surface so overlapping own/team rows are not duplicated.
			$task_view = isset( $this->destinations['tasks_team'] ) ? 'team' : 'own';
			$module    = 'tasks' === $key ? 'tasks_' . $task_view : ( str_starts_with( $key, 'calendar' ) ? 'calendar' : $key );
			$url       = $this->destinations[ $module ] ?? null;
			if ( null === $url ) {
				return null; }
			if ( 'directory' === $key ) {
				$selection = PeopleQuery::parse(
					array(
						'q'               => $query->q,
						'arn_people_page' => (string) $page,
					)
				);
				$list      = null === $selection ? null : Readers::people( $connections )->contactList( $selection );
				$parameter = 'person';
			} elseif ( 'tasks' === $key ) {
				$selection = TaskSelection::parse(
					array(
						'q'    => $query->q,
						'page' => (string) $page,
					)
				);
				$list      = null === $selection ? null : ( new TaskPortal() )->listing( $selection, $task_view );
				$parameter = 'entry';
			} else {
				$state = match ( $key ) {
					'calendar_history' => 'history', 'calendar_cancelled' => 'cancelled', default => 'future' };
				$input     = array(
					'q'    => $query->q,
					'page' => (string) $page,
				);
				$selection = 'communications' === $module ? NoticeSelection::parse( $input ) : AgendaSelection::parse( array_merge( $input, array( 'state' => $state ) ) );
				$list      = null === $selection ? null : ( new Portal( $module ) )->listing( $selection );
				$parameter = 'entry';
			}
		}
		if ( null === $list ) {
			return null; }
		$items = array();
		foreach ( $list['items'] as $row ) {
			$id = $row['id'] ?? null;
			if ( ( ! is_int( $id ) && ! is_string( $id ) ) || 1 !== preg_match( '/\A[1-9][0-9]*\z/D', (string) $id ) ) {
				return null; }
			$items[] = array(
				'title' => 'directory' === $key ? $row['name'] : $row['title'],
				'url'   => add_query_arg( $parameter, $id, $url ),
			);
		}
		return array(
			'total' => $list['total'],
			'items' => $items,
		);
	}

	/** Refuse unpublished, replaced or unrelated collection pages. Never use the administrative manifest. */
	public static function collectionUrl( string $slug, string $view ): ?string {
		$post = get_page_by_path( $slug, OBJECT, 'page' );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || 0 !== (int) $post->post_parent ) {
			return null; }
		$blocks = array_values( array_filter( parse_blocks( $post->post_content ), static fn( array $block ): bool => null !== $block['blockName'] || '' !== trim( $block['innerHTML'] ?? '' ) ) );
		if ( 1 !== count( $blocks ) || 'arn/portal-view' !== $blocks[0]['blockName'] || ( $blocks[0]['attrs']['view'] ?? null ) !== $view ) {
			return null; }
		return get_permalink( $post );
	}
}
