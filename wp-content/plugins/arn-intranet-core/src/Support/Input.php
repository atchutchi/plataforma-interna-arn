<?php
/** Strict native support transport, distinct from WordPress routing keys.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Support;

use InvalidArgumentException;
// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class Input {
	public static function id( mixed $value ): ?int {
		return \Arn\Intranet\F112\Input::id( $value ); }
	public static function nonce( int $actor, string $view, string $op, string $key ): string {
		return 'arn-tickets:' . $actor . ':' . $view . ':' . $op . ':' . $key; }
	public static function query( array $get ): ?array {
		if ( array_diff( array_keys( $get ), array( 'page_id', 'entry', 'new', 'notices', 'q', 'state', 'priority', 'arn_tickets_page', 'saved' ) ) ) {
			return null; }
		foreach ( $get as $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 400 ) {
				return null; }
		}
		foreach ( array( 'entry', 'page_id', 'arn_tickets_page' ) as $key ) {
			if ( isset( $get[ $key ] ) && null === self::id( $get[ $key ] ) ) {
				return null; }
		}
		foreach ( array( 'new', 'notices', 'saved' ) as $key ) {
			if ( isset( $get[ $key ] ) && '1' !== $get[ $key ] ) {
				return null; }
		}
		if ( count( array_intersect( array_keys( $get ), array( 'entry', 'new', 'notices' ) ) ) > 1 || ( isset( $get['state'] ) && '' !== $get['state'] && null === TicketState::tryFrom( $get['state'] ) ) || ! in_array( $get['priority'] ?? '', array( '', 'low', 'normal', 'high' ), true ) ) {
			return null; }
		return array(
			'screen'   => isset( $get['entry'] ) ? 'detail' : ( isset( $get['new'] ) ? 'new' : ( isset( $get['notices'] ) ? 'notices' : 'list' ) ),
			'id'       => isset( $get['entry'] ) ? self::id( $get['entry'] ) : 0,
			'q'        => $get['q'] ?? '',
			'state'    => $get['state'] ?? '',
			'priority' => $get['priority'] ?? '',
			'page'     => isset( $get['arn_tickets_page'] ) ? self::id( $get['arn_tickets_page'] ) : 1,
			'saved'    => isset( $get['saved'] ),
		);
	}
	public static function post( array $post ): array {
		foreach ( $post as $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 25000 ) {
				throw new InvalidArgumentException( 'Formulário inválido.' ); }
		}
		$keys = match ( $post['operation'] ?? '' ) {
			'create' => array( 'unit', 'title', 'body', 'priority' ), 'claim' => array( 'id', 'expected' ), 'reply', 'resolve' => array( 'id', 'expected', 'message' ), 'attach' => array( 'id', 'expected', 'label' ), 'read' => array( 'id' ), default => null };
		$all = array_merge( array( 'operation', 'nonce', 'key' ), $keys ?? array() );
		if ( null === $keys || count( $all ) !== count( $post ) || array_diff( $all, array_keys( $post ) ) || 1 !== preg_match( '/\A[a-f0-9]{32}\z/D', $post['key'] ) || '' === $post['nonce'] || strlen( $post['nonce'] ) > 100 ) {
			throw new InvalidArgumentException( 'Campos de formulário inválidos.' ); }
		foreach ( array( 'id', 'expected' ) as $key ) {
			if ( isset( $post[ $key ] ) ) {
				$post[ $key ] = self::id( $post[ $key ] );
				if ( null === $post[ $key ] ) {
					throw new InvalidArgumentException( 'Identificador inválido.' ); }
			}
		}
		foreach ( array( 'body', 'message' ) as $key ) {
			if ( isset( $post[ $key ] ) ) {
				$post[ $key ] = str_replace( array( "\r\n", "\r" ), "\n", $post[ $key ] ); }
		}
		return $post;
	}
	public static function fields( array $post ): array {
		return in_array( $post['operation'], array( 'reply', 'resolve' ), true ) ? array( 'message' => $post['message'] ) : array(); }
}
