<?php
/** Local authenticated consumer orchestration. Workers never borrow this authority.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Session\WordPressLocalAccountSessions;
use Arn\Intranet\Documents\PrivateFiles\{BlobDescriptor, LocalPrivateFiles};
use Arn\Intranet\Documents\Catalog\{CurrentDocumentActor, DocumentPolicy, LocalDocumentCatalog, Metadata};
use Arn\Intranet\News\{CurrentNewsActor, DraftInput, LocalNewsroom, NewsPolicy};
use Arn\Intranet\Tasks\{AttachmentConfiguration, Portal};
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions
final class ConsumerJobs {
	public static function enabled(): bool {
		return defined( 'ARN_ALLOW_LOCAL_MEDIA_CONSUMERS' ) && true === ARN_ALLOW_LOCAL_MEDIA_CONSUMERS;
	}

	public static function allowed( int $id ): bool {
		if ( ! self::enabled() || is_multisite() || 'local' !== wp_get_environment_type() || ! defined( 'ARN_ALLOW_LOCAL_MEDIA_QUEUE' ) || true !== ARN_ALLOW_LOCAL_MEDIA_QUEUE || ! defined( 'ARN_ALLOW_LOCAL_MEDIA_PROCESSING' ) || true !== ARN_ALLOW_LOCAL_MEDIA_PROCESSING || $id < 1 || get_current_user_id() !== $id ) {
			return false; }
		clean_user_cache( $id );
		$user  = new \WP_User( $id );
		$token = wp_get_session_token();
		return 1 === preg_match( '/@example\.(invalid|test)$/D', (string) $user->user_email ) && '1' === get_user_meta( $id, LocalFixtureUser::META_KEY, true ) && '' !== $token && \WP_Session_Tokens::get_instance( $id )->verify( $token ) && ( new WordPressLocalAccountSessions() )->filterCurrentUser( $id ) === $id;
	}

	public static function queue(): MediaQueue {
		if ( ! self::allowed( get_current_user_id() ) || ! defined( 'ARN_MEDIA_QUEUE_DIR' ) || ! defined( 'ARN_MEDIA_QUARANTINE_DIR' ) || ! defined( 'ARN_LOCAL_PRIVATE_FILES_DIR' ) ) {
			throw new RuntimeException( 'Envios indisponíveis.' ); }
		return new MediaQueue( ARN_MEDIA_QUEUE_DIR, array_merge( LocalPrivateFiles::publicRoots(), array( ARN_MEDIA_QUARANTINE_DIR, ARN_LOCAL_PRIVATE_FILES_DIR ) ), self::allowed( ... ), static fn(): int => time(), true );
	}

	public static function intent( string $module, int $id, int $expected, string $reason, array $payload ): array {
		return array(
			'module'    => $module,
			'operation' => 'tasks' === $module ? 'attach' : ( 0 === $id ? 'create' : 'revise' ),
			'id'        => $id,
			'expected'  => $expected,
			'reason'    => $reason,
			'payload'   => $payload,
		);
	}

	/** No revision check for private status reading; mutations always require the original CAS. */
	public static function preflight( array $context, bool $mutation = true, ?int $receipt = null ): mixed {
		if ( ! self::allowed( get_current_user_id() ) ) {
			throw new RuntimeException( 'Envio indisponível.' ); }
		$id     = $receipt ?? $context['id'];
		$before = null;
		if ( 'news' === $context['module'] ) {
			$actor    = LocalNewsroom::configured() ? ( new CurrentNewsActor() )->read() : null;
			$before   = $id > 0 ? ( new LocalNewsroom() )->findVisible( $id ) : null;
			$ok       = null !== $actor && NewsPolicy::canCreate( $actor[0], $actor[2], time() ) && ( 0 === $id || ( null !== $before && $before->author_id === $actor[1] && ( ! $mutation || NewsPolicy::canEdit( $before, $actor[0], $actor[2], time() ) ) ) );
			$revision = $before?->revision;
		} elseif ( 'documents' === $context['module'] ) {
			$actor    = LocalDocumentCatalog::configured() ? ( new CurrentDocumentActor() )->read() : null;
			$before   = $id > 0 ? ( new LocalDocumentCatalog() )->findVisible( $id ) : null;
			$ok       = null !== $actor && ( 0 === $id ? DocumentPolicy::canManageScope( Scope::units( array( 'unidade-ficticia-a' ) ), $actor[0], $actor[2], time() ) : null !== $before && DocumentPolicy::canManage( $before, $actor[0], $actor[2], time() ) );
			$revision = $before?->revision;
		} else {
			$before   = AttachmentConfiguration::enabled() ? ( new Portal() )->detail( $id, $context['payload']['view'] ) : null;
			$ok       = null !== $before && ( ! $mutation || in_array( 'attach', $before['operations'], true ) );
			$revision = $before['revision'] ?? null;
		}
		if ( ! $ok || ( $mutation && $id > 0 && $context['expected'] !== $revision ) ) {
			throw new RuntimeException( 'Permissão ou revisão alterada. Reabre o formulário.' ); }
		return $before;
	}

	private static function original( BlobDescriptor $blob ): string {
		$handle = LocalPrivateFiles::store()->open( $blob );
		fclose( $handle );
		return ARN_LOCAL_PRIVATE_FILES_DIR . '/' . $blob->id;
	}

	private static function enqueue( array $context, array $sources, string $nonce ): string {
		ConsumerIntent::validate( $context, count( $sources ) );
		self::preflight( $context );
		// Same native form, actor, intent and bytes cannot create a second batch on retransmission.
		$digest   = json_encode( array( get_current_user_id(), $nonce, $context, array_map( static fn( string $path ): string => hash_file( 'sha256', $path ), $sources ) ), JSON_THROW_ON_ERROR );
		$id       = substr( hash_hmac( 'sha256', $digest, wp_salt( 'nonce' ) ), 0, 32 );
		$queue    = self::queue();
		$existing = $queue->listing( get_current_user_id() )[ $id ] ?? null;
		if ( null !== $existing ) {
			return $id; }
		return $queue->enqueue( $sources, get_current_user_id(), $id, $context );
	}

	public static function news( DraftInput $input, int $id, int $expected, string $reason, string $nonce ): ?string {
		$media = array_merge( null === $input->featured ? array() : array( array( 'featured', $input->featured ) ), array_map( static fn( $m ): array => array( 'gallery', $m ), $input->gallery ), array_map( static fn( $m ): array => array( 'attachment', $m ), $input->attachments ) );
		if ( array() === $media ) {
			return null; }
		$context = self::intent(
			'news',
			$id,
			$expected,
			$reason,
			array(
				'title'    => $input->title,
				'body'     => $input->body,
				'category' => $input->category,
				'audience' => null === $input->audience ? null : array(
					'kind'    => $input->audience->kind,
					'targets' => $input->audience->targets,
				),
				'media'    => array(),
			)
		);
		$before  = self::preflight( $context );
		$allowed = array();
		if ( null !== $before ) {
			$c = $before->version( count( $before->versions ) )->content;
			foreach ( array_merge( null === $c->featured ? array() : array( $c->featured ), $c->gallery, $c->attachments ) as $asset ) {
				$allowed[ $asset->blob->id ] = $asset; }
		}
		$sources = array();
		foreach ( $media as [$kind, $m] ) {
			if ( null !== $m->existing_id && ( ! isset( $allowed[ $m->existing_id ] ) || $allowed[ $m->existing_id ]->kind !== $kind ) ) {
				throw new RuntimeException( 'Original anterior indisponível.' ); }
			$sources[]                     = null === $m->existing_id ? $m->source : self::original( $allowed[ $m->existing_id ]->blob );
			$context['payload']['media'][] = array(
				'kind'        => $kind,
				'existing'    => $m->existing_id,
				'description' => $m->description,
			);
		}
		return self::enqueue( $context, $sources, $nonce );
	}

	public static function document( Metadata $metadata, ?string $source, int $id, int $expected, string $reason, string $nonce ): string {
		$context = self::intent(
			'documents',
			$id,
			$expected,
			$reason,
			array(
				'metadata' => get_object_vars( $metadata ),
				'retained' => null === $source,
			)
		);
		$before  = self::preflight( $context );
		if ( null === $source ) {
			if ( null === $before ) {
				throw new RuntimeException( 'Original em falta.' ); }
			$source = self::original( $before->version( count( $before->versions ) )->blob );
		}
		return self::enqueue( $context, array( $source ), $nonce );
	}

	public static function task( array $post, string $source, string $view ): string {
		$context = self::intent(
			'tasks',
			(int) $post['id'],
			(int) $post['expected_revision'],
			'Inclusão de anexo pelo formulário de tarefas',
			array(
				'label' => $post['label'],
				'view'  => $view,
			)
		);
		return self::enqueue( $context, array( $source ), $post['nonce'] );
	}

	public static function listing(): array {
		$rows = array();
		foreach ( self::queue()->listing( get_current_user_id() ) as $id => $state ) {
			try {
				if ( null === $state['context'] || $state['archived'] ) {
					continue;
				} self::preflight( $state['context'], false, $state['receipt'] );
				$rows[ $id ] = $state;
			} catch ( Throwable ) {
				continue; }
		}
		return $rows;
	}

	public static function complete( string $id, int $revision ): int {
		$prepared = null;
		return self::queue()->complete(
			$id,
			$revision,
			get_current_user_id(),
			static function ( array $state ) use ( &$prepared ): void {
				self::preflight( $state['context'], 'applied' !== $state['consumption'], $state['receipt'] );
				if ( 'applied' !== $state['consumption'] ) {
					$prepared = new PreparedBatch( $state, ARN_MEDIA_QUARANTINE_DIR, array_merge( LocalPrivateFiles::publicRoots(), array( ARN_MEDIA_QUEUE_DIR, ARN_LOCAL_PRIVATE_FILES_DIR ) ), time() ); }
			},
			static function ( array $state, string $directory ) use ( &$prepared ): int {
				$c     = $state['context'];
				$p     = $c['payload'];
				$paths = array_map( static fn( array $item ): string => $directory . '/' . $item['blob']['id'], $state['items'] );
				if ( 'news' === $c['module'] ) {
					$draft   = ConsumerIntent::draft( $p, $paths );
					$service = new LocalNewsroom();
					return ( 0 === $c['id'] ? $service->create( $draft, $c['reason'], $prepared ) : $service->revise( $c['id'], $draft, $c['expected'], $c['reason'], $prepared ) )->id;
				}
				if ( 'documents' === $c['module'] ) {
					$metadata = new Metadata( ...$p['metadata'] );
					$service  = new LocalDocumentCatalog();
					return ( 0 === $c['id'] ? $service->create( Scope::units( array( 'unidade-ficticia-a' ) ), $metadata, $paths[0], $c['reason'], $prepared ) : $service->revise( $c['id'], $metadata, $p['retained'] ? null : $paths[0], $c['expected'], $c['reason'], $prepared ) )->id;
				}
				return ( new Portal() )->attach(
					array(
						'id'                => (string) $c['id'],
						'expected_revision' => (string) $c['expected'],
						'label'             => $p['label'],
					),
					$paths[0],
					$p['view'],
					$prepared
				);
			}
		);
	}
}
