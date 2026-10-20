<?php
/** Strict HTTP adapter for news drafts.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Throwable;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment
/** Validates browser fields without accepting actor or scope claims. */
final class NewsFormInput {
	private const POST_KEYS = array( 'action', 'operation', '_wpnonce', '_wp_http_referer', 'article_id', 'expected_revision', 'title', 'body', 'category', 'audience_preset', 'reason', 'featured', 'gallery', 'attachments' );

	/** Return the draft and field errors. No upload path is accepted from POST.
	 *
	 * @throws \InvalidArgumentException If a temporary upload is invalid.
	 */
	public static function validate( array $post, array $files, ?callable $resolve_preset = null ): array {
		$errors = array();
		foreach ( array_keys( $post ) as $key ) {
			if ( ! is_string( $key ) || ! in_array( $key, self::POST_KEYS, true ) ) {
				$errors['form'] = 'O pedido contém campos não permitidos.';
				break;
			}
		}
		foreach ( array_keys( $files ) as $key ) {
			if ( ! in_array( $key, array( 'featured_file', 'gallery_file', 'attachment_file' ), true ) ) {
				$errors['form'] = 'O pedido contém ficheiros não permitidos.';
				break;
			}
		}
		$title          = $post['title'] ?? null;
		$body           = $post['body'] ?? null;
		$category       = $post['category'] ?? null;
		$reason         = $post['reason'] ?? null;
		$preset         = $post['audience_preset'] ?? '';
		$resolve_preset = $resolve_preset ?? array( NewsAudiencePresets::class, 'resolve' );
		$audience       = null;
		if ( is_string( $preset ) && '' !== $preset ) {
			$audience = $resolve_preset( $preset );
		}
		if ( ! is_string( $preset ) || ( '' !== $preset && null === $audience ) || ( null !== $audience && ! $audience instanceof Audience ) ) {
			$errors['audience_preset'] = 'Selecciona uma audiência válida.';
		}
		if ( ! is_string( $title ) || ! Content::plainText( $title, 1, 200 ) ) {
			$errors['title'] = 'Indica um título de texto simples até 200 caracteres.';
		}
		if ( ! is_string( $body ) || ! Content::plainText( str_replace( "\r\n", "\n", $body ), 0, 20000, true ) ) {
			$errors['body'] = 'Usa texto simples até 20 000 caracteres, sem controlos.';
		}
		if ( ! is_string( $category ) || ! isset( NewsPortal::categories()[ $category ] ) ) {
			$errors['category'] = 'Selecciona uma categoria válida.';
		}
		if ( ! is_string( $reason ) || '' === trim( $reason ) || strlen( $reason ) > 500 || 1 !== preg_match( '/\A[^\p{C}<>]+\z/u', $reason ) || trim( $reason ) !== $reason ) {
			$errors['reason'] = 'Escreve uma justificação breve numa linha, até 500 bytes.';
		}
		$featured = null;
		if ( isset( $post['featured'] ) || isset( $files['featured_file'] ) ) {
			try {
				if ( ! is_array( $post['featured'] ?? null ) ) {
					throw new \InvalidArgumentException();
				}
				$featured = self::media( $post['featured'], self::file( $files['featured_file'] ?? null, 'featured' ), 'featured' );
			} catch ( Throwable $error ) {
				$errors['featured'] = '' !== $error->getMessage() ? $error->getMessage() : 'Indica uma imagem válida e o texto alternativo ou retira o destaque.';
			}
		}
		$gallery     = self::list( $post['gallery'] ?? array(), $files['gallery_file'] ?? null, 'gallery', Content::GALLERY_LIMIT, $errors );
		$attachments = self::list( $post['attachments'] ?? array(), $files['attachment_file'] ?? null, 'attachments', 5, $errors );
		if ( array() !== $errors ) {
			return array(
				'draft'  => null,
				'errors' => $errors,
			);
		}
		try {
			return array(
				'draft'  => new DraftInput( $title, $body, $category, $featured, $gallery, $attachments, $audience ),
				'errors' => array(),
			);
		} catch ( Throwable ) {
			return array(
				'draft'  => null,
				'errors' => array( 'form' => 'Confirma o conteúdo e os ficheiros.' ),
			);
		}
	}

	private static function list( mixed $rows, mixed $files, string $key, int $limit, array &$errors ): array {
		if ( ! is_array( $rows ) || count( $rows ) > $limit || array_keys( $rows ) !== ( array() === $rows ? array() : range( 0, count( $rows ) - 1 ) ) ) {
			$errors[ $key ] = 'Confirma a lista e o limite de ficheiros.';
			return array();
		}
		if ( null !== $files && ( ! is_array( $files ) || ! isset( $files['error'] ) || ! is_array( $files['error'] ) || array_keys( $files['error'] ) !== array_keys( $rows ) ) ) {
			$errors[ $key ] = 'A lista de ficheiros não corresponde à ordem apresentada.';
			return array();
		}
		$result = array();
		foreach ( $rows as $index => $row ) {
			try {
				if ( ! is_array( $row ) ) {
					throw new \InvalidArgumentException();
				}
				$file     = null === $files ? null : array(
					'error'    => $files['error'][ $index ] ?? null,
					'size'     => $files['size'][ $index ] ?? null,
					'tmp_name' => $files['tmp_name'][ $index ] ?? null,
				);
				$kind     = 'gallery' === $key ? 'gallery' : 'attachment';
				$result[] = self::media( $row, self::file( $file, $kind ), $kind );
			} catch ( Throwable $error ) {
				$errors[ $key . '_' . $index ] = '' !== $error->getMessage() ? $error->getMessage() : 'Confirma o ficheiro e a descrição desta posição. Selecciona de novo um ficheiro novo após erro.';
			}
		}
		return $result;
	}

	private static function media( array $row, ?string $source, string $kind ): MediaInput {
		if ( array_diff( array_keys( $row ), array( 'id', 'description' ) ) ) {
			throw new \InvalidArgumentException();
		}
		$id          = $row['id'] ?? '';
		$description = $row['description'] ?? null;
		if ( ! is_string( $id ) || ! is_string( $description ) || ! Content::plainText( $description, 1, 'attachment' === $kind ? 160 : 300 ) ) {
			throw new \InvalidArgumentException();
		}
		return new MediaInput( '' === $id ? null : $id, $source, $description );
	}

	private static function file( mixed $file, string $kind ): ?string {
		if ( null === $file ) {
			return null;
		}
		if ( ! is_array( $file ) || ! isset( $file['error'] ) || ! is_int( $file['error'] ) ) {
			throw new \InvalidArgumentException();
		}
		if ( UPLOAD_ERR_NO_FILE === $file['error'] ) {
			return null;
		}
		if ( in_array( $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) || ( is_int( $file['size'] ?? null ) && $file['size'] > 1048576 ) ) {
			throw new \InvalidArgumentException( 'Cada ficheiro pode ter no máximo 1 MiB.' );
		}
		if ( UPLOAD_ERR_OK !== $file['error'] || ! is_int( $file['size'] ) || $file['size'] < 1 || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			throw new \InvalidArgumentException();
		}
		$problem = NewsUploadInspector::error( $file['tmp_name'], $kind );
		if ( null !== $problem ) {
			throw new \InvalidArgumentException( $problem ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Fixed server-authored field message, escaped at rendering.
		}
		return $file['tmp_name'];
	}
}
