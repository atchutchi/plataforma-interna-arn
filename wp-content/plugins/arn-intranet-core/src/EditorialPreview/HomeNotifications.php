<?php
/** Closed read-only summary of independently authorised module notices.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\EditorialPreview;

use Closure;
use Throwable;
use UnexpectedValueException;

// phpcs:disable Squiz.Commenting -- The native module readers retain all authority and receipts.
final class HomeNotifications {
	public const MODULES = array(
		'communications' => 'Comunicados',
		'calendar'       => 'Calendário',
		'tasks_own'      => 'Tarefas',
		'news'           => 'Notícias',
		'account'        => 'Acessos',
	);
	private readonly Closure $source;
	public function __construct( ?Closure $source = null ) {
		$this->source = $source ?? HomeNotificationSources::read( ... );
	}
	public function model(): array {
		$items = array();
		$links = array();
		foreach ( self::MODULES as $module => $label ) {
			try {
				$list = ( $this->source )( $module );
				if ( ! is_array( $list ) || ! is_array( $list['items'] ?? null ) || ! self::safeUrl( $list['url'] ?? null ) ) {
					continue;
				}
				$rows = array();
				foreach ( array_slice( $list['items'], 0, 20 ) as $row ) {
					if ( ! is_array( $row ) || ! is_int( $row['id'] ?? null ) || $row['id'] < 1 || ! is_string( $row['title'] ?? null ) || '' === trim( $row['title'] ) || ! is_string( $row['event'] ?? null ) || ! is_int( $row['created_at'] ?? null ) || $row['created_at'] < 0 || ! array_key_exists( 'read_at', $row ) || ( null !== $row['read_at'] && ( ! is_int( $row['read_at'] ) || $row['read_at'] < $row['created_at'] ) ) || ! self::safeUrl( $row['url'] ?? null ) ) {
						throw new UnexpectedValueException( 'Aviso indisponível.' );
					}
					$item = array_intersect_key( $row, array_flip( array( 'id', 'title', 'event', 'created_at', 'read_at', 'url' ) ) ) + array( 'module' => $module );
					if ( isset( $rows[ $row['id'] ] ) && $rows[ $row['id'] ] !== $item ) {
						throw new UnexpectedValueException( 'Aviso incompatível.' );
					}
					$rows[ $row['id'] ] = $item;
				}
				$items   = array_merge( $items, array_values( $rows ) );
				$links[] = array(
					'title' => $label,
					'url'   => $list['url'],
				);
			} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Fail closed per independent source.
				// No exception, count or private payload reaches the view.
			}
		}
		usort( $items, static fn( array $a, array $b ): int => array( -$a['created_at'], $a['module'], -$a['id'] ) <=> array( -$b['created_at'], $b['module'], -$b['id'] ) );
		return array(
			'available' => array() !== $links,
			'links'     => $links,
			'items'     => array_map(
				static fn( array $row ): array => array(
					'title'  => $row['title'],
					'url'    => $row['url'],
					'module' => self::MODULES[ $row['module'] ],
					'event'  => $row['event'],
					'date'   => gmdate( 'd/m/Y H:i', $row['created_at'] ) . ' UTC',
					'read'   => null !== $row['read_at'],
				),
				array_slice( $items, 0, 5 )
			),
		);
	}
	private static function safeUrl( mixed $url ): bool {
		if ( ! is_string( $url ) || '' === $url || preg_match( '/[\x00-\x20\x7f]/', $url ) ) {
			return false;
		}
		return str_starts_with( $url, '/' ) ? ! str_starts_with( $url, '//' ) : false !== filter_var( $url, FILTER_VALIDATE_URL ) && in_array( parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure validation.
	}
}
