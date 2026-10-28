<?php
/** Closed leave transport, no native query-variable collisions.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Leave;

use InvalidArgumentException;
// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class Input {
	public static function id( mixed $value ): ?int {
		return \Arn\Intranet\F112\Input::id( $value );}
	public static function nonce( int $actor, string $view, string $op, string $key ): string {
		return 'arn-leave:' . $actor . ':' . $view . ':' . $op . ':' . $key;}
	public static function query( array $get ): ?array {
		if ( array_diff( array_keys( $get ), array( 'page_id', 'entry', 'new', 'holidays', 'notices', 'arn_leave_year', 'arn_leave_page', 'saved' ) ) ) {
			return null;
		}
		foreach ( $get as$value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 100 ) {
				return null;
			}
		}
		foreach ( array( 'entry', 'page_id', 'arn_leave_page', 'arn_leave_year' )as$key ) {
			if ( isset( $get[ $key ] ) && null === self::id( $get[ $key ] ) ) {
				return null;
			}
		}
		foreach ( array( 'new', 'holidays', 'notices', 'saved' )as$key ) {
			if ( isset( $get[ $key ] ) && '1' !== $get[ $key ] ) {
				return null;
			}
		}
		if ( count( array_intersect( array_keys( $get ), array( 'entry', 'new', 'holidays', 'notices' ) ) ) > 1 ) {
			return null;
		}
		$year = isset( $get['arn_leave_year'] ) ? (int) $get['arn_leave_year'] : (int) gmdate( 'Y' );
		$page = isset( $get['arn_leave_page'] ) ? (int) $get['arn_leave_page'] : 1;
		if ( $year < 2000 || $year > 2100 || $page > 100 ) {
			return null;
		}
		return array(
			'screen' => isset( $get['entry'] ) ? 'detail' : ( isset( $get['new'] ) ? 'new' : ( isset( $get['holidays'] ) ? 'holidays' : ( isset( $get['notices'] ) ? 'notices' : 'list' ) ) ),
			'id'     => isset( $get['entry'] ) ? (int) $get['entry'] : 0,
			'year'   => $year,
			'page'   => $page,
			'saved'  => isset( $get['saved'] ),
		);
	}
	public static function post( array $post ): array {
		foreach ( $post as$value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 12000 ) {
				throw new InvalidArgumentException( 'Formulário inválido.' );
			}
		}
		$op   = $post['operation'] ?? '';
		$keys = match ( $op ) {
			'create'=>array( 'unit', 'starts', 'ends', 'calendar_revision' ), 'calendar'=>array( 'calendar_year', 'expected', 'holidays', 'reason' ), 'edit'=>array( 'id', 'expected', 'starts', 'ends', 'calendar_revision' ), 'submit'=>array( 'id', 'expected', 'calendar_revision' ), 'approve'=>array( 'id', 'expected', 'reason', 'calendar_revision' ), 'start', 'withdraw'=>array( 'id', 'expected' ), 'return', 'reject', 'cancel'=>array( 'id', 'expected', 'reason' ), 'read'=>array( 'id' ), default=>null};
		$all = array_merge( array( 'operation', 'key', 'nonce' ), $keys ?? array() );
		if ( null === $keys || count( $post ) !== count( $all ) || array_diff( $all, array_keys( $post ) ) || 1 !== preg_match( '/\A[a-f0-9]{32}\z/D', $post['key'] ) || '' === $post['nonce'] || strlen( $post['nonce'] ) > 100 ) {
			throw new InvalidArgumentException( 'Campos inválidos.' );
		}
		foreach ( array( 'id', 'expected', 'calendar_revision', 'calendar_year' )as$key ) {
			if ( isset( $post[ $key ] ) ) {
				$value = 'calendar' === $op && 'expected' === $key && '0' === $post[ $key ] ? 0 : self::id( $post[ $key ] );
				if ( null === $value ) {
					throw new InvalidArgumentException( 'Revisão inválida.' );
				}$post[ $key ] = $value;}
		}
		foreach ( array( 'starts', 'ends' )as$key ) {
			if ( isset( $post[ $key ] ) ) {
				Calendar::date( $post[ $key ] );
			}
		}
		if ( 'calendar' === $op ) {
			$post['year'] = $post['calendar_year'];
			unset( $post['calendar_year'] );
			$raw              = str_replace( array( "\r\n", "\r" ), "\n", trim( $post['holidays'] ) );
			$post['holidays'] = '' === $raw ? array() : explode( "\n", $raw );
			sort( $post['holidays'], SORT_STRING );
			new Calendar( $post['year'], $post['expected'] + 1, $post['holidays'] );}
		return $post;
	}
	public static function fields( array $post ): array {
		$keys = match ( $post['operation'] ) {
			'edit'=>array( 'starts', 'ends', 'calendar_revision' ), 'submit'=>array( 'calendar_revision' ), 'approve'=>array( 'reason', 'calendar_revision' ), 'return', 'reject', 'cancel'=>array( 'reason' ), default=>array()};
		$result = array();
		foreach ( $keys as$key ) {
			$result[ $key ] = $post[ $key ];
		}return $result;
	}
}
