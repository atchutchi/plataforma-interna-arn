<?php
/** Closed request form grammar, no permissive field coercion.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Requests;

use InvalidArgumentException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Strict form parsing precedes storage.
final class Input {
	public static function id( mixed $value ): ?int {
		return \Arn\Intranet\F112\Input::id( $value );
	}
	public static function nonce( int $actor, string $view, string $operation, string $key ): string {
		return 'arn-requests:' . $actor . ':' . $view . ':' . $operation . ':' . $key;
	}
	public static function query( array $get ): ?array {
		if ( array_diff( array_keys( $get ), array( 'page_id', 'entry', 'new', 'types', 'notices', 'q', 'state', 'arn_requests_page', 'saved' ) ) ) {
			return null;
		}
		foreach ( $get as $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 400 ) {
				return null;
			}
		}
		foreach ( array( 'entry', 'page_id', 'arn_requests_page' ) as $key ) {
			if ( isset( $get[ $key ] ) && null === self::id( $get[ $key ] ) ) {
				return null;
			}
		}
		foreach ( array( 'new', 'types', 'notices', 'saved' ) as $key ) {
			if ( isset( $get[ $key ] ) && '1' !== $get[ $key ] ) {
				return null;
			}
		}
		if ( count( array_intersect( array_keys( $get ), array( 'entry', 'new', 'types', 'notices' ) ) ) > 1 || ( isset( $get['state'] ) && '' !== $get['state'] && null === RequestState::tryFrom( $get['state'] ) ) ) {
			return null;
		}
		return array(
			'screen' => isset( $get['entry'] ) ? 'detail' : ( isset( $get['new'] ) ? 'new' : ( isset( $get['types'] ) ? 'types' : ( isset( $get['notices'] ) ? 'notices' : 'list' ) ) ),
			'id'     => isset( $get['entry'] ) ? self::id( $get['entry'] ) : 0,
			'q'      => $get['q'] ?? '',
			'state'  => $get['state'] ?? '',
			'page'   => isset( $get['arn_requests_page'] ) ? self::id( $get['arn_requests_page'] ) : 1,
			'saved'  => isset( $get['saved'] ),
		);
	}
	public static function post( array $post ): array {
		foreach ( $post as $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 25000 ) {
				throw new InvalidArgumentException( 'Formulário inválido.' );
			}
		}
		$op   = $post['operation'] ?? '';
		$keys = match ( $op ) {
			'create' => array( 'type_id', 'type_revision', 'title', 'body' ), 'type' => array( 'id', 'expected', 'type_name', 'unit', 'reviewer', 'active', 'title_label', 'body_label', 'instructions', 'attachments' ), 'edit' => array( 'id', 'expected', 'title', 'body' ), 'submit' => array( 'id', 'expected', 'type_revision' ), 'start', 'withdraw', 'revoke_substitute' => array( 'id', 'expected' ), 'return', 'approve', 'reject', 'cancel' => array( 'id', 'expected', 'reason' ), 'substitute' => array( 'id', 'expected', 'user', 'starts', 'ends', 'reason' ), 'attach' => array( 'id', 'expected', 'label' ), 'read' => array( 'id' ), default => null,
		};
		$all = array_merge( array( 'operation', 'nonce', 'key' ), $keys ?? array() );
		if ( null === $keys || count( $all ) !== count( $post ) || array_diff( $all, array_keys( $post ) ) || 1 !== preg_match( '/\A[a-f0-9]{32}\z/D', $post['key'] ) || '' === $post['nonce'] || strlen( $post['nonce'] ) > 100 ) {
			throw new InvalidArgumentException( 'Campos de formulário inválidos.' );
		}
		foreach ( array( 'starts', 'ends' ) as $key ) {
			if ( isset( $post[ $key ] ) ) {
				$date = \DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $post[ $key ], new \DateTimeZone( 'UTC' ) );
				if ( false === $date || $date->format( 'Y-m-d\TH:i' ) !== $post[ $key ] ) {
					throw new InvalidArgumentException( 'Data de substituição inválida.' );
				}
				$post[ $key ] = $date->getTimestamp();
			}
		}
		foreach ( array( 'id', 'expected', 'type_id', 'type_revision', 'reviewer', 'user' ) as $key ) {
			if ( isset( $post[ $key ] ) ) {
				$number = 'type' === $op && in_array( $key, array( 'id', 'expected' ), true ) && '0' === $post[ $key ] ? 0 : self::id( $post[ $key ] );
				if ( null === $number ) {
					throw new InvalidArgumentException( 'Identificador inválido.' );
				}
				$post[ $key ] = $number;
			}
		}
		foreach ( array( 'active', 'attachments' ) as $key ) {
			if ( isset( $post[ $key ] ) ) {
				if ( ! in_array( $post[ $key ], array( '0', '1' ), true ) ) {
					throw new InvalidArgumentException( 'Opção inválida.' );
				}
				$post[ $key ] = '1' === $post[ $key ];
			}
		}
		if ( 'type' === $op ) {
			$post['name'] = $post['type_name'];
			unset( $post['type_name'] );
		}
		foreach ( array( 'body', 'instructions' ) as $key ) {
			if ( isset( $post[ $key ] ) ) {
				$post[ $key ] = str_replace( array( "\r\n", "\r" ), "\n", $post[ $key ] );
			}
		}
		return $post;
	}
	public static function fields( array $post ): array {
		$keys = match ( $post['operation'] ) {
			'type' => array( 'name', 'unit', 'reviewer', 'active', 'title_label', 'body_label', 'instructions', 'attachments' ), 'edit' => array( 'title', 'body' ), 'submit' => array( 'type_revision' ), 'return', 'approve', 'reject', 'cancel' => array( 'reason' ), 'substitute' => array( 'user', 'starts', 'ends', 'reason' ), default => array(),
		};
		$fields = array();
		foreach ( $keys as $key ) {
			$fields[ $key ] = $post[ $key ];
		}
		return $fields;
	}
}
