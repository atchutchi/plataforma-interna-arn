<?php
/** Native notice readers, with current authority and owned connection cleanup.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\EditorialPreview;

use Arn\Intranet\Authorization\Records\CurrentLocalAccount;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\F112\Portal;
use Arn\Intranet\Tasks\{Portal as TaskPortal, View as TaskView};
use Arn\Intranet\Notifications\WpdbNotificationRepository;
use Arn\Intranet\News\NewsPublishedPortal;
use Arn\Intranet\Search\{ReadConnections, Readers};

// phpcs:disable Squiz.Commenting -- Never installs, grants, marks receipts or reads administrative originals.
final class HomeNotificationSources {
	public static function read( string $module ): ?array {
		if ( ! isset( HomeNotifications::MODULES[ $module ] ) || ! is_user_logged_in() || 'local' !== wp_get_environment_type() || is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return null;
		}
		if ( in_array( $module, array( 'requests', 'tickets', 'leave' ), true ) ) {
			return ServiceSources::notices( $module );
		}
		$connections = new ReadConnections();
		try {
			if ( 'news' === $module ) {
				$rows = Readers::news( $connections )->notifications();
				if ( null === $rows ) {
					return null; }
				$items = array();
				foreach ( array_slice( $rows, 0, 20 ) as $row ) {
					$items[] = array(
						'id'         => (int) $row->id,
						'title'      => 'Notícia #' . (int) $row->article_id,
						'event'      => match ( $row->kind ) {
						'submitted' => 'Submissão', 'approved' => 'Aprovação', 'rejected' => 'Rejeição', 'returned' => 'Devolução', 'archived' => 'Retirada', 'publication' => 'Publicação', default => throw new \UnexpectedValueException( 'Aviso incompatível.' ) },
						'created_at' => (int) $row->created_at,
						'read_at'    => null === $row->read_at ? null : (int) $row->read_at,
						'url'        => 'publication' === $row->kind ? NewsPublishedPortal::url( (int) $row->article_id ) : add_query_arg(
							array(
								'page'       => 'arn-newsroom',
								'article_id' => (int) $row->article_id,
							),
							admin_url( 'admin.php' )
						),
					);
				}
				// The publication reader may not enter the editorial CMS. Link only to the visible collection.
				$page = get_page_by_path( 'noticias' );
				return array(
					'items' => $items,
					'url'   => $page instanceof \WP_Post ? get_permalink( $page ) : home_url( '/noticias/' ),
				);
			}
			if ( 'account' === $module ) {
				$current = new CurrentLocalAccount( static fn(): WpdbAuthorizationRepository => new WpdbAuthorizationRepository( $connections->open() ) );
				$actor   = $current->read();
				if ( null === $actor ) {
					return null; }
				$rows = ( new WpdbNotificationRepository( $connections->open() ) )->page( $actor[1], $actor[0]->revision )['rows'];
				$url  = admin_url( 'tools.php?page=arn-local-notifications' );
				return array(
					'url'   => $url,
					'items' => array_map(
						static fn( array $row ): array => array(
							'id'         => $row['id'],
							'title'      => $row['title'],
							'event'      => 'Alteração de acesso',
							'created_at' => $row['occurred_at'],
							'read_at'    => $row['read_at'],
							'url'        => $url,
						),
						$rows
					),
				);
			}
			// Resolve only this source. Other modules keep independent native readers.
			$empty        = static fn(): array => array();
			$directory    = new ServiceDirectory(
				people: $empty,
				communications: 'tasks_own' === $module ? $empty : null,
				tasks: 'tasks_own' === $module ? null : $empty,
				requests: $empty,
				tickets: $empty,
				leave: $empty
			);
			$destinations = array_column( $directory->items(), 'url', 'id' );
			if ( ! isset( $destinations[ $module ] ) ) {
				return null; }
			$list = 'tasks_own' === $module ? ( new TaskPortal() )->notices( 1 ) : ( new Portal( $module ) )->notices( 1 );
			if ( null === $list ) {
				return null; }
			$base  = $destinations[ $module ];
			$items = array();
			foreach ( $list['items'] as $row ) {
				$items[] = array(
					'id'         => $row['id'],
					'title'      => $row['title'],
					'created_at' => $row['created_at'],
					'read_at'    => $row['read_at'],
					'event'      => 'tasks_own' === $module ? ( TaskView::NOTICES[ $row['kind'] ] ?? throw new \UnexpectedValueException( 'Aviso incompatível.' ) ) : ( 'communications' === $module ? 'Publicação' : match ( $row['kind'] ) {
					'cancelled' => 'Cancelamento', 'updated' => 'Actualização', 'published' => 'Publicação', default => throw new \UnexpectedValueException( 'Aviso incompatível.' ) } ),
					'url'        => add_query_arg( array( 'entry' => $row[ 'tasks_own' === $module ? 'task_id' : 'object_id' ] ), $base ),
				);
			}
			return array(
				'items' => $items,
				'url'   => add_query_arg( array( 'notices' => '1' ), $base ),
			);
		} finally {
			$connections->close();
		}
	}
}
