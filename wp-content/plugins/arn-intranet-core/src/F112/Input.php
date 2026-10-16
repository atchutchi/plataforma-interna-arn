<?php
/**
 * Closed F1-12 transport input.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\F112;

use Arn\Intranet\Communications\{NoticeContent, NoticeSelection};
use Arn\Intranet\Calendar\{EventContent, AgendaSelection};
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Pure closed input contracts.
final class Input {
	public const NOTICE_FIELDS = array( 'title', 'body', 'audience', 'circuit', 'valid_until' );
	public const EVENT_FIELDS  = array( 'title', 'description', 'audience', 'mode', 'location', 'url', 'all_day', 'start_at', 'end_at', 'start_date', 'end_date_exclusive' );

	public static function id( mixed $value ): ?int {
		return is_string( $value ) && 1 === preg_match( '/\A[1-9][0-9]*\z/D', $value ) && (string) (int) $value === $value ? (int) $value : null;
	}

	public static function query( string $module, array $get, bool $management = false ): ?array {
		if ( ! in_array( $module, array( 'communications', 'calendar' ), true ) || array_diff( array_keys( $get ), array( 'entry', 'notices', 'from', 'q', 'unit', 'state', 'month', 'arn_f112_page', 'page_id', 'saved' ) ) ) {
			return null;
		}
		foreach ( $get as $value ) {
			if ( ! is_string( $value ) ) {
				return null;
			}
		}
		if ( ( isset( $get['entry'] ) && null === self::id( $get['entry'] ) ) || ( isset( $get['page_id'] ) && null === self::id( $get['page_id'] ) ) || ( isset( $get['notices'] ) && ( $management || '1' !== $get['notices'] || isset( $get['entry'] ) ) ) || ( isset( $get['saved'] ) && '1' !== $get['saved'] ) || ( ! $management && 'communications' === $module && '' !== ( $get['state'] ?? '' ) ) || ( 'communications' === $module && isset( $get['month'] ) ) ) {
			return null;
		}
		if ( isset( $get['from'] ) && ( $management || 'notices' !== $get['from'] || ! isset( $get['entry'] ) || array_diff( array_keys( $get ), array( 'entry', 'from', 'arn_f112_page', 'page_id' ) ) ) ) {
			return null;
		}
		$filters = array_intersect_key( $get, array_flip( array( 'q', 'unit', 'state', 'month' ) ) );
		if ( isset( $get['notices'] ) && array() !== $filters ) {
			return null;
		}
		if ( isset( $get['arn_f112_page'] ) ) {
			$filters['page'] = $get['arn_f112_page'];
		}
		$selection = 'communications' === $module ? NoticeSelection::parse( $filters ) : AgendaSelection::parse( $filters, $management );
		return null === $selection ? null : array(
			'view'      => isset( $get['entry'] ) ? 'detail' : ( isset( $get['notices'] ) ? 'notices' : 'list' ),
			'id'        => isset( $get['entry'] ) ? self::id( $get['entry'] ) : null,
			'selection' => $selection,
			'get'       => $get,
		);
	}

	public static function shape( string $module, array $post, bool $management ): bool {
		if ( ! in_array( $module, array( 'communications', 'calendar' ), true ) ) {
			return false;
		}
		foreach ( $post as $value ) {
			if ( ! is_string( $value ) ) {
				return false;
			}
		}
		$operation = $post['operation'] ?? '';
		if ( ! $management ) {
			$expected = array( 'operation', 'id', 'nonce' );
			if ( 'mark-read' !== $operation ) {
				return false;
			}
		} else {
			$operations = 'communications' === $module ? array( 'create', 'revise', 'submit', 'publish', 'approve', 'reject', 'return', 'archive' ) : array( 'create', 'revise', 'publish', 'cancel', 'archive' );
			if ( ! in_array( $operation, $operations, true ) ) {
				return false;
			}
			$expected = array( 'operation', 'nonce', 'reason' );
			if ( in_array( $operation, array( 'create', 'revise' ), true ) ) {
				$expected = array_merge( $expected, 'communications' === $module ? self::NOTICE_FIELDS : self::EVENT_FIELDS );
			}
			if ( 'create' !== $operation ) {
				$expected[] = 'id';
				$expected   = array_merge( $expected, in_array( $operation, array( 'approve', 'reject', 'return' ), true ) ? array( 'version', 'token' ) : array( 'revision' ) );
			}
		}
		if ( count( $expected ) !== count( $post ) || array_diff( $expected, array_keys( $post ) ) || '' === ( $post['nonce'] ?? '' ) || strlen( $post['nonce'] ) > 200 ) {
			return false;
		}
		foreach ( array( 'id', 'revision', 'version' ) as $key ) {
			if ( isset( $post[ $key ] ) && null === self::id( $post[ $key ] ) ) {
				return false;
			}
		}
		return ! isset( $post['token'] ) || ( strlen( $post['token'] ) <= 512 && '' !== $post['token'] );
	}

	public static function content( string $module, array $data ): NoticeContent|EventContent {
		$audience = match ( $data['audience'] ?? null ) {
			'all' => Audience::all(), 'LAB-A' => Audience::unit( 'LAB-A' ), 'LAB-B' => Audience::unit( 'LAB-B' ), default => throw new InvalidArgumentException( 'Escolhe uma audiência válida.' ) };
		if ( 'communications' === $module ) {
			$expiry = '' === $data['valid_until'] ? null : self::utc( $data['valid_until'] );
			return new NoticeContent( $data['title'], $data['body'], $audience, $data['circuit'], $expiry );
		}
		if ( 'calendar' !== $module || ! in_array( $data['all_day'], array( '0', '1' ), true ) ) {
			throw new InvalidArgumentException( 'Verifica o tipo de intervalo.' );
		}
		$nullable = static fn( string $value ): ?string => '' === $value ? null : $value;
		return new EventContent( $data['title'], $data['description'], $audience, $data['mode'], $data['location'], $data['url'], '1' === $data['all_day'], $nullable( $data['start_at'] ), $nullable( $data['end_at'] ), $nullable( $data['start_date'] ), $nullable( $data['end_date_exclusive'] ) );
	}

	public static function validation( string $module, array $data ): array {
		$errors = array();
		$limits = array(
			'title'       => 160,
			'body'        => 10000,
			'description' => 10000,
			'location'    => 240,
			'url'         => 2048,
			'reason'      => 1000,
		);
		foreach ( $limits as $key => $limit ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			try {
				PlainText::value( $data[ $key ], $limit, in_array( $key, array( 'body', 'description', 'reason' ), true ), ! in_array( $key, array( 'location', 'url' ), true ) );
			} catch ( InvalidArgumentException ) {
				$errors[ $key ] = 'Preenche este campo com texto válido, até ' . $limit . ' caracteres.';
			}
		}
		if ( isset( $data['audience'] ) && ! in_array( $data['audience'], array( 'all', 'LAB-A', 'LAB-B' ), true ) ) {
			$errors['audience'] = 'Escolhe uma audiência da lista.';
		}
		if ( 'communications' === $module && '' !== ( $data['valid_until'] ?? '' ) ) {
			try {
				self::utc( $data['valid_until'] );
			} catch ( InvalidArgumentException ) {
				$errors['valid_until'] = 'Indica uma data real no formato AAAA-MM-DDTHH:MM:SSZ ou deixa em branco.';
			}
		}
		if ( array() === $errors && in_array( $data['operation'] ?? '', array( 'create', 'revise' ), true ) ) {
			try {
				self::content( $module, $data );
			} catch ( InvalidArgumentException ) {
				$errors[ 'calendar' === $module ? 'end_at' : 'circuit' ] = 'Verifica a audiência, a modalidade e os formatos. Nos eventos, preenche apenas horários UTC ou dias inteiros, com fim posterior ao início.';
			}
		}
		return $errors;
	}

	public static function utc( string $value ): int {
		if ( 1 !== preg_match( '/\A(?!0000)[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z\z/D', $value ) ) {
			throw new InvalidArgumentException( 'Usa o formato UTC indicado.' );
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s\Z', $value, new DateTimeZone( 'UTC' ) );
		if ( false === $date || $date->format( 'Y-m-d\TH:i:s\Z' ) !== $value || $date->getTimestamp() < 0 ) {
			throw new InvalidArgumentException( 'Indica uma data UTC válida.' );
		}
		return $date->getTimestamp();
	}

	public static function nonce( int $actor, string $module, string $operation, int $id ): string {
		return 'arn_f112_' . $actor . '_' . $module . '_' . $operation . '_' . $id;
	}
}
