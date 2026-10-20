<?php
/** Strict browser input for private task pages.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

use Arn\Intranet\F112\{Input as SharedInput, PlainText};
use InvalidArgumentException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Pure closed transport contracts.
final class Input {
	public static function id( mixed $value ): ?int {
		return SharedInput::id( $value );
	}

	public static function query( string $view, array $get ): ?array {
		if ( ! in_array( $view, array( 'own', 'team' ), true ) || array_diff( array_keys( $get ), array( 'entry', 'new', 'notices', 'q', 'state', 'assignee', 'arn_tasks_page', 'page_id', 'saved' ) ) ) {
			return null;
		}
		foreach ( $get as $value ) {
			if ( ! is_string( $value ) ) {
				return null;
			}
		}
		foreach ( array( 'entry', 'page_id' ) as $key ) {
			if ( isset( $get[ $key ] ) && null === self::id( $get[ $key ] ) ) {
				return null;
			}
		}
		foreach ( array( 'new', 'notices', 'saved' ) as $key ) {
			if ( isset( $get[ $key ] ) && '1' !== $get[ $key ] ) {
				return null;
			}
		}
		if ( count( array_intersect( array_keys( $get ), array( 'entry', 'new', 'notices' ) ) ) > 1 || ( 'own' === $view && ( isset( $get['assignee'] ) || isset( $get['new'] ) ) ) || ( 'team' === $view && isset( $get['notices'] ) ) || ( isset( $get['new'] ) && array_diff( array_keys( $get ), array( 'new', 'page_id' ) ) ) || ( isset( $get['notices'] ) && array_intersect( array_keys( $get ), array( 'q', 'state', 'assignee' ) ) ) ) {
			return null;
		}
		$filters = array_intersect_key( $get, array_flip( array( 'q', 'state', 'assignee' ) ) );
		if ( isset( $get['arn_tasks_page'] ) ) {
			$filters['page'] = $get['arn_tasks_page'];
		}
		$selection = TaskSelection::parse( $filters );
		return null === $selection ? null : array(
			'view'      => $view,
			'screen'    => isset( $get['entry'] ) ? 'detail' : ( isset( $get['new'] ) ? 'create' : ( isset( $get['notices'] ) ? 'notices' : 'list' ) ),
			'id'        => isset( $get['entry'] ) ? self::id( $get['entry'] ) : null,
			'selection' => $selection,
			'get'       => $get,
		);
	}

	public static function shape( array $post ): bool {
		foreach ( $post as $value ) {
			if ( ! is_string( $value ) ) {
				return false;
			}
		}
		$operation = $post['operation'] ?? '';
		$fields    = match ( $operation ) {
			'create' => array( 'create_request_id', 'title', 'description', 'priority', 'due', 'assignee_id' ),
			'start', 'complete', 'cancel' => array( 'id', 'expected_revision', 'reason' ),
			'reassign' => array( 'id', 'expected_revision', 'assignee_id', 'reason' ),
			'change_due' => array( 'id', 'expected_revision', 'due', 'reason' ),
			'comment' => array( 'id', 'expected_revision', 'body' ),
			'mark-read' => array( 'id' ),
			default => null,
		};
		if ( null === $fields ) {
			return false;
		}
		$expected = array_merge( array( 'operation', 'nonce' ), $fields );
		if ( count( $post ) !== count( $expected ) || array_diff( $expected, array_keys( $post ) ) || '' === ( $post['nonce'] ?? '' ) || strlen( $post['nonce'] ) > 200 ) {
			return false;
		}
		foreach ( array( 'id', 'expected_revision', 'assignee_id' ) as $key ) {
			if ( isset( $post[ $key ] ) && null === self::id( $post[ $key ] ) ) {
				return false;
			}
		}
		return ! isset( $post['create_request_id'] ) || 1 === preg_match( '/\A[a-f0-9]{32}\z/D', $post['create_request_id'] );
	}

	public static function content( array $post ): TaskContent {
		return new TaskContent( $post['title'], $post['description'], $post['priority'], '' === $post['due'] ? null : $post['due'] );
	}

	public static function fields( array $post ): array {
		return match ( $post['operation'] ) {
			'comment' => array( 'body' => $post['body'] ),
			'reassign' => array(
				'assignee_id' => self::id( $post['assignee_id'] ),
				'reason'      => $post['reason'],
			),
			'change_due' => array(
				'due'    => '' === $post['due'] ? null : $post['due'],
				'reason' => $post['reason'],
			),
			'start', 'complete', 'cancel' => array( 'reason' => $post['reason'] ),
			default => throw new InvalidArgumentException( 'Operação de formulário inválida.' ),
		};
	}

	public static function validation( array $post ): array {
		$errors = array();
		foreach ( array(
			'title'       => array( 160, false, true ),
			'description' => array( 8000, true, false ),
			'reason'      => array( 1000, true, 'cancel' === $post['operation'] ),
			'body'        => array( 2000, true, true ),
		) as $key => [$limit, $multiline, $required] ) {
			if ( ! isset( $post[ $key ] ) ) {
				continue;
			}
			try {
				if ( false !== strpbrk( $post[ $key ], '<>' ) ) {
					throw new InvalidArgumentException( 'Usa texto simples.' );
				}
				PlainText::value( $post[ $key ], $limit, $multiline, $required );
			} catch ( InvalidArgumentException ) {
				$errors[ $key ] = 'Preenche este campo com texto simples válido, até ' . $limit . ' caracteres.';
			}
		}
		if ( isset( $post['priority'] ) && ! in_array( $post['priority'], array( 'low', 'normal', 'high' ), true ) ) {
			$errors['priority'] = 'Escolhe uma prioridade da lista.';
		}
		if ( isset( $post['due'] ) && '' !== $post['due'] ) {
			try {
				SharedInput::utc( $post['due'] );
			} catch ( InvalidArgumentException ) {
				$errors['due'] = 'Indica uma data real em UTC no formato AAAA-MM-DDTHH:MM:SSZ ou deixa em branco.';
			}
		}
		return $errors;
	}

	public static function nonce( int $actor, string $view, string $operation, int $id, string $request = '' ): string {
		return 'arn_tasks_' . $actor . '_' . $view . '_' . $operation . '_' . $id . '_' . $request;
	}
}
