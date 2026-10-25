<?php
/** Closed, path-free intent persisted alongside queued originals.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\News\{Audience, Content, DraftInput, MediaInput};
use Arn\Intranet\Documents\Catalog\Metadata;
use RuntimeException;

// phpcs:disable WordPress.WP.AlternativeFunctions, Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class ConsumerIntent {

	public static function validate( array $value, int $count ): void {
		if ( array_keys( $value ) !== array( 'module', 'operation', 'id', 'expected', 'reason', 'payload' ) || ! is_int( $value['id'] ) || ! is_int( $value['expected'] ) || ! is_string( $value['reason'] ) || ! is_array( $value['payload'] ) || strlen( json_encode( $value, JSON_THROW_ON_ERROR ) ) > 131072 || $count < 1 || $count > 8 ) {
			throw new RuntimeException( 'Contexto de envio inválido.' );
		}
		$create = 'create' === $value['operation'];
		if ( ! in_array( $value['operation'], array( 'create', 'revise', 'attach' ), true ) || ( $create ? 0 !== $value['id'] || 0 !== $value['expected'] : $value['id'] < 1 || $value['expected'] < 1 ) || ! Content::plainText( $value['reason'], 1, 500 ) ) {
			throw new RuntimeException( 'Operação de envio inválida.' );
		}
		$p = $value['payload'];
		if ( 'news' === $value['module'] && in_array( $value['operation'], array( 'create', 'revise' ), true ) ) {
			if ( array_keys( $p ) !== array( 'title', 'body', 'category', 'audience', 'media' ) || ! is_array( $p['media'] ) || ! array_is_list( $p['media'] ) || count( $p['media'] ) !== $count ) {
				throw new RuntimeException( 'Notícia inválida.' ); }
			$seen     = array();
			$rank     = -1;
			$featured = 0;
			foreach ( $p['media'] as $row ) {
				if ( ! is_array( $row ) || array_keys( $row ) !== array( 'kind', 'existing', 'description' ) || ! in_array( $row['kind'], array( 'featured', 'gallery', 'attachment' ), true ) || ! is_string( $row['description'] ) || ! Content::plainText( $row['description'], 1, 'attachment' === $row['kind'] ? 160 : 300 ) ) {
					throw new RuntimeException( 'Media inválida.' ); }
				$next = array_search( $row['kind'], array( 'featured', 'gallery', 'attachment' ), true );
				if ( $next < $rank || ( 'featured' === $row['kind'] && ++$featured > 1 ) || ( null !== $row['existing'] && ( $create || ! is_string( $row['existing'] ) || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $row['existing'] ) || isset( $seen[ $row['existing'] ] ) ) ) ) {
					throw new RuntimeException( 'Ordem de media inválida.' ); }
				$rank = $next;
				if ( null !== $row['existing'] ) {
					$seen[ $row['existing'] ] = true; }
			}
			$draft = self::draft( $p, array_fill( 0, $count, 'queued-original' ) );
			new Content( $draft->title, $draft->body, $draft->category, null, array(), array(), $draft->audience );
		} elseif ( 'documents' === $value['module'] && in_array( $value['operation'], array( 'create', 'revise' ), true ) ) {
			if ( 1 !== $count || array_keys( $p ) !== array( 'metadata', 'retained' ) || ! is_array( $p['metadata'] ) || ! is_bool( $p['retained'] ) || ( $create && $p['retained'] ) ) {
				throw new RuntimeException( 'Documento inválido.' ); }
			$keys = array_keys( $p['metadata'] );
			sort( $keys );
			$expected = array( 'title', 'summary', 'category', 'source_reference', 'owner_label', 'review_on', 'valid_from', 'valid_until', 'recipient_ids' );
			sort( $expected );
			if ( $keys !== $expected ) {
				throw new RuntimeException( 'Metadados inválidos.' ); }
			new Metadata( ...$p['metadata'] );
		} elseif ( 'tasks' === $value['module'] && 'attach' === $value['operation'] ) {
			if ( 1 !== $count || array_keys( $p ) !== array( 'label', 'view' ) || ! is_string( $p['label'] ) || ! Content::plainText( $p['label'], 1, 160 ) || ! in_array( $p['view'], array( 'own', 'team' ), true ) ) {
				throw new RuntimeException( 'Anexo inválido.' ); }
		} else {
			throw new RuntimeException( 'Módulo inválido.' ); }
	}

	public static function draft( array $payload, array $paths ): DraftInput {
		$audience = $payload['audience'];
		if ( null !== $audience ) {
			if ( ! is_array( $audience ) || array_keys( $audience ) !== array( 'kind', 'targets' ) || ! is_array( $audience['targets'] ) ) {
				throw new RuntimeException( 'Audiência inválida.' ); }
			$audience = match ( $audience['kind'] ) {
				'all' => array() === $audience['targets'] ? Audience::all() : throw new RuntimeException( 'Audiência inválida.' ),
				'units' => Audience::units( $audience['targets'] ),
				'people' => Audience::people( $audience['targets'] ),
				default => throw new RuntimeException( 'Audiência inválida.' ),
			};
		}
		$groups = array(
			'featured'   => array(),
			'gallery'    => array(),
			'attachment' => array(),
		);
		foreach ( $payload['media'] as $i => $row ) {
			$groups[ $row['kind'] ][] = new MediaInput( $row['existing'], null === $row['existing'] ? $paths[ $i ] : null, $row['description'] ); }
		return new DraftInput( $payload['title'], $payload['body'], $payload['category'], $groups['featured'][0] ?? null, $groups['gallery'], $groups['attachment'], $audience );
	}
}
