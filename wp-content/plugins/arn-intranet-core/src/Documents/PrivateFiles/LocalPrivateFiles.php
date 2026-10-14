<?php
/** Entrega local com autorização por pedido.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

use Arn\Intranet\Authorization\Records\CurrentLocalAccount;
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use Throwable;

/** Liga a sessão fictícia ao catálogo e ao armazenamento privado. */
final class LocalPrivateFiles {
	/**
	 * Leitura da conta corrente.
	 *
	 * @var Closure
	 */
	private readonly Closure $current_reader;
	/**
	 * Fábrica de registo dedicado.
	 *
	 * @var Closure
	 */
	private readonly Closure $registry_factory;
	/**
	 * Fábrica do armazém privado.
	 *
	 * @var Closure
	 */
	private readonly Closure $store_factory;
	/**
	 * Gate de configuração.
	 *
	 * @var Closure
	 */
	private readonly Closure $gate;

	/**
	 * Dependências diferidas para nunca abrir SQL antes do gate.
	 *
	 * @param Closure|null $current_reader Leitura da sessão corrente.
	 * @param Closure|null $registry_factory Registo dedicado.
	 * @param Closure|null $store_factory Armazém privado.
	 * @param Closure|null $gate Condição local efectiva.
	 */
	public function __construct( ?Closure $current_reader = null, ?Closure $registry_factory = null, ?Closure $store_factory = null, ?Closure $gate = null ) {
		$this->current_reader   = $current_reader ?? static fn(): ?array => ( new CurrentLocalAccount() )->read();
		$this->registry_factory = $registry_factory ?? static function (): WpdbFileRegistry {
			global $wpdb;
			$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$db->set_prefix( $wpdb->prefix );
			return new WpdbFileRegistry( $db );
		};
		$this->store_factory    = $store_factory ?? static fn(): PrivateBlobStore => self::store();
		$this->gate             = $gate ?? static fn(): bool => self::configured();
	}

	/**
	 * Apenas o booleano exacto activa a funcionalidade.
	 *
	 * @param string $environment Ambiente efectivo.
	 * @param mixed  $flag Flag de configuração.
	 */
	public static function enabled( string $environment, mixed $flag ): bool {
		return 'local' === $environment && true === $flag;
	}

	/** Configuração efectiva. */
	public static function configured(): bool {
		return self::enabled( wp_get_environment_type(), defined( 'ARN_ALLOW_LOCAL_PRIVATE_FILES' ) ? ARN_ALLOW_LOCAL_PRIVATE_FILES : null );
	}

	/**
	 * Monta as raízes públicas físicas conhecidas.
	 *
	 * @param string|null $root Raiz privada alternativa de integração.
	 */
	public static function store( ?string $root = null ): PrivateBlobStore {
		$public = array( realpath( ABSPATH ), realpath( WP_CONTENT_DIR ), realpath( dirname( __DIR__, 3 ) ) );
		if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) && is_dir( (string) $_SERVER['DOCUMENT_ROOT'] ) ) {
			$public[] = realpath( (string) $_SERVER['DOCUMENT_ROOT'] );
		}
		$themes = glob( WP_CONTENT_DIR . '/themes/*', GLOB_ONLYDIR );
		foreach ( false === $themes ? array() : $themes as $theme ) {
			$public[] = realpath( $theme );
		}
		return new PrivateBlobStore( $root ?? ( defined( 'ARN_LOCAL_PRIVATE_FILES_DIR' ) ? ARN_LOCAL_PRIVATE_FILES_DIR : '' ), array_values( array_unique( array_filter( $public, 'is_string' ) ) ), array( 'application/pdf', 'image/png', 'image/jpeg' ), 1048576 );
	}

	/**
	 * Resolve ID opaco e revalida o tuple do catálogo antes do binário.
	 *
	 * @param mixed $id Identificador do pedido.
	 */
	public function open( mixed $id ): ?array {
		if ( true !== ( $this->gate )() || ! is_string( $id ) || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $id ) ) {
			return null;
		}
		try {
			$current = ( $this->current_reader )();
			if ( ! is_array( $current ) || 3 !== count( $current ) || ! isset( $current[0], $current[1], $current[2] ) ) {
				return null;
			}
			[$snapshot, $user_id, $session] = $current;
			if ( ! is_int( $user_id ) || ! is_array( $session ) || ! LocalAccountSessionPolicy::valid( $snapshot, $user_id, $session, true ) ) {
				return null;
			}
			$registry = ( $this->registry_factory )();
			$delivery = new PrivateFileDelivery(
				( $this->store_factory )(),
				static function ( string $file_id ) use ( $registry, $user_id ): ?FileRecord {
					$record = $registry->find( $file_id );
					if ( null === $record || null === LocalFileCatalog::request( $record, $user_id ) ) {
						return null;
					}
					$tuple = $registry->findForObject( $record->object_type, $record->object_id, $record->version, $record->variant );
					return $tuple?->blob->id === $file_id ? $record : null;
				},
				static fn( FileRecord $record ): bool => LocalFileCatalog::allows( $record, $snapshot, $user_id, $session, time() )
			);
			return $delivery->open( $id );
		} catch ( Throwable ) {
			return null;
		}
	}

	/** Lista variantes correntes de cada objecto que a sessão pode ler. */
	public function listVisible(): ?array {
		if ( true !== ( $this->gate )() ) {
			return null;
		}
		try {
			$current = ( $this->current_reader )();
			if ( ! is_array( $current ) || 3 !== count( $current ) ) {
				return null;
			}
			[$snapshot, $user_id, $session] = $current;
			if ( ! is_int( $user_id ) || ! is_array( $session ) || ! LocalAccountSessionPolicy::valid( $snapshot, $user_id, $session, true ) ) {
				return null;
			}
			$registry = ( $this->registry_factory )();
			$rows     = array();
			foreach ( array(
				'own'    => 1,
				'unit-a' => 2,
				'unit-b' => 3,
			) as $key => $object_id ) {
				foreach ( array( 'original', 'thumbnail', 'preview' ) as $variant ) {
					$record = $registry->findForObject( 'local_document', $object_id, 2, $variant );
					if ( null !== $record && LocalFileCatalog::allows( $record, $snapshot, $user_id, $session, time() ) ) {
						$rows[ $key ][ $variant ] = $record;
					}
				}
			}
			return $rows;
		} catch ( Throwable ) {
			return null;
		}
	}

	/** Regista prioridade anterior ao portal. */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'serve' ), -1 );
	}

	/** Cabeçalhos privados e leitura do handle já verificado. */
	public function serve(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Endpoint GET de leitura, com autorização por pedido.
		if ( ! array_key_exists( 'arn_private_file', $_GET ) ) {
			return;
		}
		$id = $_GET['arn_private_file'];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$result = 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ? $this->open( $id ) : null;
		if ( null === $result ) {
			status_header( 404 );
			header( 'Cache-Control: no-store' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Content-Length: 0' );
			exit;
		}
		$record = $result['record'];
		header( 'Content-Type: ' . $record->blob->mime );
		header(
			'Content-Disposition: attachment; filename="ficheiro-' . $record->variant . match ( $record->blob->mime ) {
			'application/pdf' => '.pdf',
			'image/png' => '.png',
			'image/jpeg' => '.jpg' } . '"'
		);
		header( 'Cache-Control: no-store' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Length: ' . $record->blob->size );
		fpassthru( $result['handle'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fpassthru -- Mesmo handle verificado pelo núcleo.
		fclose( $result['handle'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Fecha o handle verificado.
		exit;
	}
}
