<?php
/** Instala fixtures sintéticas apenas por CLI local.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use WP_User;

/** Não altera contas, concessões nem ficheiros institucionais. */
final class LocalFileInstaller {
	private const ERROR = 'Instalação de ficheiros fictícios recusada.';
	private const PNG   = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAEklEQVR4nGNUCu1gYGBgYgADAAu4AQN5vcfrAAAAAElFTkSuQmCC';
	private const JPEG  = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAACAAIDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDi6KKK948Q/9k=';

	/**
	 * Prefixo e pasta alternativos só para ensaios CLI exclusivos.
	 *
	 * @param string|null $prefix Prefixo SQL exclusivo.
	 * @param string|null $root Raiz privada exclusiva.
	 */
	public function __construct( private readonly ?string $prefix = null, private readonly ?string $root = null ) {}

	/**
	 * Verifica integralmente o contexto antes de criar ligação ou fonte.
	 *
	 * @param string $environment Ambiente efectivo.
	 * @param mixed  $files Flag de ficheiros.
	 * @param mixed  $fictional Flag de acesso fictício.
	 * @param mixed  $storage Flag de persistência.
	 * @param bool   $cli Processo WP-CLI.
	 * @param bool   $admin Actor administrativo.
	 */
	public static function eligible( string $environment, mixed $files, mixed $fictional, mixed $storage, bool $cli, bool $admin ): bool {
		return 'local' === $environment && true === $files && true === $fictional && true === $storage && $cli && $admin;
	}

	/**
	 * Instala doze tuples fixos e devolve IDs opacos para QA.
	 *
	 * @param int $owner_id ID da Ana Teste marcada.
	 * @throws RuntimeException Quando contexto ou fixtures divergem.
	 */
	public function install( int $owner_id ): array {
		if ( ! self::eligible(
			wp_get_environment_type(),
			defined( 'ARN_ALLOW_LOCAL_PRIVATE_FILES' ) ? ARN_ALLOW_LOCAL_PRIVATE_FILES : null,
			defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) ? ARN_ALLOW_FICTIONAL_LOCAL_ACCESS : null,
			defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) ? ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE : null,
			defined( 'WP_CLI' ) && true === WP_CLI,
			current_user_can( 'manage_options' )
		) ) {
			throw new RuntimeException( self::ERROR ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
		}
		$user = get_userdata( $owner_id );
		if ( $owner_id < 1 || ! $user instanceof WP_User || LocalFixtureUser::LOGIN !== $user->user_login || '1' !== get_user_meta( $owner_id, LocalFixtureUser::META_KEY, true ) ) {
			throw new RuntimeException( self::ERROR ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
		}
		$actor_id = get_current_user_id();
		if ( $actor_id < 1 ) {
			throw new RuntimeException( self::ERROR ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
		}
		global $wpdb;
		$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$db->set_prefix( $this->prefix ?? $wpdb->prefix );
		$db->suppress_errors( true );
		$schema = new FileSchema( $db );
		$schema->install();
		$registry = new WpdbFileRegistry( $db );
		$store    = LocalPrivateFiles::store( $this->root );
		$tuples   = array();
		foreach ( array(
			'own'    => 1,
			'unit-a' => 2,
			'unit-b' => 3,
		) as $key => $object_id ) {
			foreach ( array(
				'original'  => 2,
				'thumbnail' => 2,
				'preview'   => 2,
				'old'       => 1,
			) as $name => $version ) {
				$variant                 = 'old' === $name ? 'original' : $name;
				$tuples[ $key ][ $name ] = array( $object_id, $version, $variant, self::content( $object_id, $version, $variant ) );
			}
		}
		$ids = array();
		foreach ( $tuples as $key => $variants ) {
			foreach ( $variants as $name => [$object_id, $version, $variant, $content] ) {
				$existing = $registry->findForObject( 'local_document', $object_id, $version, $variant );
				if ( null !== $existing ) {
					if ( ! hash_equals( hash( 'sha256', $content ), $existing->blob->sha256 ) || strlen( $content ) !== $existing->blob->size ) {
						throw new RuntimeException( self::ERROR ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
					}
					$handle = $store->open( $existing->blob );
					fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Verificação do blob privado.
					$ids[ $key ][ $name ] = $existing->blob->id;
				}
			}
		}
		foreach ( $tuples as $key => $variants ) {
			foreach ( $variants as $name => [$object_id, $version, $variant, $content] ) {
				if ( isset( $ids[ $key ][ $name ] ) ) {
					continue;
				}
				$source = tempnam( sys_get_temp_dir(), 'arn-pf-' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_tempnam -- Fonte sintética transitória.
				if ( false === $source ) {
					throw new RuntimeException( self::ERROR ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
				}
				try {
					file_put_contents( $source, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Fixture fechada do servidor.
					$blob = $store->importFile( $source );
					$registry->register( new FileRecord( $blob, 'local_document', $object_id, $version, $variant, $actor_id, time() ) );
					$ids[ $key ][ $name ] = $blob->id;
				} finally {
					unlink( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Só a fonte transitória criada acima.
				}
			}
		}
		return $ids;
	}

	/**
	 * Bytes sintéticos determinísticos.
	 *
	 * @param int    $object_id Objecto fechado.
	 * @param int    $version Versão fechada.
	 * @param string $variant Variante fechada.
	 */
	private static function content( int $object_id, int $version, string $variant ): string {
		return match ( $variant ) {
			'original' => self::pdf( $object_id, $version ),
			'thumbnail' => base64_decode( self::PNG, true ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Imagem PNG sintética constante.
			'preview' => base64_decode( self::JPEG, true ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Imagem JPEG sintética constante.
		};
	}

	/**
	 * Cria uma página PDF sintética com referências e xref consistentes.
	 *
	 * @param int $object_id Objecto fechado.
	 * @param int $version Versão fechada.
	 */
	private static function pdf( int $object_id, int $version ): string {
		$line    = 'ARN - Documento ficticio ' . $object_id . ' versao ' . $version;
		$stream  = "BT /F1 18 Tf 72 760 Td ({$line}) Tj ET\n";
		$objects = array(
			'<< /Type /Catalog /Pages 2 0 R >>',
			'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
			'<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . 'endstream',
		);
		$pdf     = "%PDF-1.4\n";
		$offsets = array( 0 );
		foreach ( $objects as $index => $body ) {
			$offsets[] = strlen( $pdf );
			$pdf      .= ( $index + 1 ) . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= "xref\n0 6\n0000000000 65535 f \n";
		foreach ( array_slice( $offsets, 1 ) as $offset ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offset );
		}
		return $pdf . "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
	}
}
