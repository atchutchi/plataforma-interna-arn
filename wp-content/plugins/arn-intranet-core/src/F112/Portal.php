<?php
/**
 * Local F1-12 composition and authorised projections.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\F112;

use Arn\Intranet\Authorization\{Action, Scope};
use Arn\Intranet\Calendar\{Event, EventContent, EventPolicy, EventRepository, EventSchema, AgendaSelection};
use Arn\Intranet\Communications\{Notice, NoticeContent, NoticePolicy, NoticeRepository, NoticeSchema, NoticeSelection, DecisionToken};
use Arn\Intranet\Identity\IdentityWpdb;
use DomainException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- No factory installs or repairs resources.
final class Portal {
	public static function available( string $module, ?string $prefix = null ): bool {
		return Configuration::current( $module ) && InstallationState::ready( $module, $prefix );
	}
	public static function register(): void {
		Controller::register();
		ManagementPage::register();
		Navigation::register();
		add_action(
			'wp_enqueue_scripts',
			static function (): void {
				if ( null === Controller::model( 'communications' ) && null === Controller::model( 'calendar' ) ) {
					return;
				}
				$file = get_stylesheet_directory() . '/assets/css/f112.css';
				if ( is_file( $file ) ) {
					wp_enqueue_style( 'arn-f112', get_stylesheet_directory_uri() . '/assets/css/f112.css', array( 'arn-intranet' ), (string) filemtime( $file ) );
				}
			}
		);
	}

	public static function render( array $attributes ): string {
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_admin() || ! is_singular( 'page' ) || ! is_string( $attributes['module'] ?? null ) ) {
			return '';
		}
		$module = $attributes['module'];
		$model  = Controller::model( $module );
		if ( ( new Pages() )->viewFor( get_post() ) !== $module || ! Configuration::current( $module ) || null === $model ) {
			return '';
		}
		ob_start();
		get_template_part( 'portal/' . $module, null, array( 'f112' => $model ) );
		return (string) ob_get_clean();
	}
	private ?ActorContext $context                            = null;
	private NoticeRepository|EventRepository|null $repository = null;
	private string $secret                                    = '';
	private readonly int $now;
	private ?IdentityWpdb $connection = null;
	public function __construct( public readonly string $module ) {
		$this->now = time();
	}

	/** Release only this portal's dedicated connection, including failed preparation. */
	public function __destruct() {
		$this->connection?->close();
	}

	private function prepare(): bool {
		if ( ! Configuration::current( $this->module ) ) {
			return false;
		}
		if ( null !== $this->context ) {
			return true;
		}
		if ( ! self::available( $this->module ) ) {
			return false;
		}
		try {
			$roster = LocalRoster::resolve();
			if ( ! isset( $roster[ get_current_user_id() ] ) ) {
				return false;
			}
			global $wpdb;
			$db               = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$this->connection = $db;
			$db->set_prefix( $wpdb->prefix );
			$db->suppress_errors( true );
			$clock   = static fn(): int => time();
			$actors  = new CurrentActor( $db, $this->module, $roster, $clock );
			$context = $actors->read();
			if ( null === $context ) {
				$this->connection->close();
				$this->connection = null;
				return false;
			}
			$schema = 'communications' === $this->module ? new NoticeSchema( $db ) : new EventSchema( $db );
			$schema->assertReady();
			$this->secret     = hash( 'sha256', wp_salt( 'nonce' ) . ':f112:' . $this->module );
			$this->repository = 'communications' === $this->module ? new NoticeRepository( $db, $actors, $clock, $this->secret ) : new EventRepository( $db, $actors, $clock, $this->secret );
			$this->context    = $context;
			return true;
		} catch ( Throwable ) {
			$this->connection?->close();
			$this->connection = null;
			return false;
		}
	}

	public function readable(): bool {
		return $this->prepare() && $this->context->allows( Action::ReadContent, Scope::own(), $this->context->id(), time() );
	}

	public function listing( NoticeSelection|AgendaSelection $selection ): ?array {
		if ( ! $this->readable() ) {
			return null;
		}
		return $this->repository->publishedList( $selection );
	}

	public function detail( int $id ): ?array {
		if ( ! $this->readable() ) {
			return null;
		}
		return $this->repository->publishedDetail( $id );
	}

	public function notices( int $page ): ?array {
		if ( ! $this->readable() ) {
			return null;
		}
		$notices = $this->repository->notices();
		$total   = count( $notices );
		$pages   = intdiv( $total, 20 ) + ( 0 !== $total % 20 ? 1 : 0 );
		return array(
			'items' => $page > $pages ? array() : array_slice( $notices, ( $page - 1 ) * 20, 20 ),
			'total' => $total,
			'page'  => $page,
			'pages' => $pages,
		);
	}

	public function markRead( int $id ): void {
		if ( ! $this->readable() ) {
			throw new DomainException( 'Operação F112 recusada.' );
		}
		$this->repository->markRead( $id );
	}

	public function creationOptions(): array {
		if ( ! $this->prepare() ) {
			return array();
		}
		$options = array();
		foreach ( array( 'LAB-A', 'LAB-B', 'all' ) as $audience ) {
			$target = 'all' === $audience ? Audience::all() : Audience::unit( $audience );
			if ( 'communications' === $this->module ) {
				$circuits = 'all' === $audience ? array( 'institutional-review' ) : array( 'team-direct', 'institutional-review' );
				foreach ( $circuits as $circuit ) {
					$content   = new NoticeContent( 'Preparação fictícia', 'Conteúdo fictício', $target, $circuit, null );
					$candidate = Notice::draft( 1, $this->context->id(), $content, $this->now );
					if ( $this->policy( $candidate, 'revise' ) ) {
						$options[ $circuit . '-' . $audience ] = array(
							'label'    => ( 'team-direct' === $circuit ? 'Comunicado de equipa' : 'Comunicado institucional' ) . ' · ' . ( 'all' === $audience ? 'Toda a comunidade' : $audience ),
							'audience' => $audience,
							'circuit'  => $circuit,
						);
					}
				}
			} else {
				$institutional = $this->context->allows( Action::ManageInstitutionalEvent, Scope::institutional(), $this->context->id(), $this->now );
				$content       = new EventContent( 'Preparação fictícia', 'Conteúdo fictício', $target, 'presencial', 'Sala de teste', '', true, null, null, '2030-01-15', '2030-01-16' );
				$candidate     = Event::draft( 1, $this->context->id(), $institutional ? null : $target->unit_id, $content, $this->now );
				if ( $this->policy( $candidate, 'revise' ) ) {
					$options[ 'event-' . $audience ] = array(
						'label'    => 'Evento · ' . ( 'all' === $audience ? 'Toda a comunidade' : $audience ),
						'audience' => $audience,
					);
				}
			}
		}
		return $options;
	}

	public function manageable(): bool {
		if ( ! $this->prepare() ) {
			return false;
		}
		if ( array() !== $this->creationOptions() ) {
			return true;
		}
		if ( 'communications' !== $this->module ) {
			return false;
		}
		foreach ( array( Audience::all(), Audience::unit( 'LAB-A' ), Audience::unit( 'LAB-B' ) ) as $audience ) {
			if ( $this->context->allows( Action::ReviewInstitutionalNotice, Scope::queues( array( 'communications-ficticia' ) ), $this->context->id(), $this->now ) && $this->context->allows( Action::ReviewInstitutionalNotice, $audience->scope(), $this->context->id(), $this->now ) ) {
				return true;
			}
		}
		return false;
	}

	public function managementList( NoticeSelection|AgendaSelection $selection ): ?array {
		if ( ! $this->manageable() ) {
			return null;
		}
		$list          = $this->repository->manageable( $selection );
		$list['items'] = array_map( fn( Notice|Event $aggregate ): array => $this->managementProjection( $aggregate, false ), $list['items'] );
		return $list;
	}

	public function managementDetail( int $id ): ?array {
		if ( ! $this->manageable() ) {
			return null;
		}
		$aggregate = $this->repository->find( $id, 'manage' );
		return null !== $aggregate && $this->policy( $aggregate, 'view' ) ? $this->managementProjection( $aggregate, true ) : null;
	}

	private function managementProjection( Notice|Event $aggregate, bool $history ): array {
		$review             = $aggregate instanceof Notice && null === $aggregate->unit_id && $aggregate->author_id !== $this->context->id();
		$version            = $review ? $aggregate->versions[ $aggregate->submitted_version - 1 ] : $aggregate->latest();
		$fields             = $version->content->toArray();
		$fields['audience'] = $version->content->audience->unit_id ?? 'all';
		if ( $aggregate instanceof Notice ) {
			$fields['valid_until'] = null === $fields['valid_until'] ? '' : gmdate( 'Y-m-d\TH:i:s\Z', $fields['valid_until'] );
		} else {
			$fields['all_day'] = $fields['all_day'] ? '1' : '0';
			foreach ( array( 'start_at', 'end_at', 'start_date', 'end_date_exclusive' ) as $key ) {
				$fields[ $key ] ??= '';
			}
		}
		$actions = array();
		foreach ( $aggregate instanceof Notice ? array( 'revise', 'submit', 'publish', 'approve', 'reject', 'return', 'archive' ) : array( 'revise', 'publish', 'cancel', 'archive' ) as $operation ) {
			if ( $this->policy( $aggregate, $operation ) ) {
				$actions[] = $operation;
			}
		}
		$row = array(
			'id'      => $aggregate->id,
			'title'   => $version->content->title,
			'fields'  => $fields,
			'version' => $version->number,
			'state'   => $aggregate->state->value,
			'actions' => $actions,
			'history' => $history && ! $review ? $this->repository->history( $aggregate->id ) : array(),
		);
		if ( $review ) {
			$row['token'] = DecisionToken::make( $aggregate, $this->context->id(), $this->secret );
		} else {
			$row['revision'] = $aggregate->revision;
		}
		return $row;
	}

	public function mutate( array $input ): int {
		if ( ! $this->manageable() ) {
			throw new DomainException( 'Operação F112 recusada.' );
		}
		$reason    = PlainText::value( $input['reason'], 1000, true );
		$operation = $input['operation'];
		if ( 'create' === $operation ) {
			return $this->repository->create( Input::content( $this->module, $input ), $reason )->id;
		}
		$id = (int) $input['id'];
		if ( 'revise' === $operation ) {
			return $this->repository->revise( $id, Input::content( $this->module, $input ), (int) $input['revision'], $reason )->id;
		}
		if ( in_array( $operation, array( 'approve', 'reject', 'return' ), true ) && $this->repository instanceof NoticeRepository ) {
			return $this->repository->decide( $id, $operation, (int) $input['version'], $input['token'], $reason )->id;
		}
		return match ( $operation ) {
			'publish' => $this->repository->publish( $id, (int) $input['revision'], $reason )->id,
			'archive' => $this->repository->archive( $id, (int) $input['revision'], $reason )->id,
			'submit' => $this->repository instanceof NoticeRepository ? $this->repository->submit( $id, (int) $input['revision'], $reason )->id : throw new DomainException( 'Operação recusada.' ),
			'cancel' => $this->repository instanceof EventRepository ? $this->repository->cancel( $id, (int) $input['revision'], $reason )->id : throw new DomainException( 'Operação recusada.' ),
			default => throw new DomainException( 'Operação recusada.' ),
		};
	}

	private function policy( Notice|Event $aggregate, string $operation ): bool {
		if ( $aggregate instanceof Notice ) {
			return 'read' === $operation ? NoticePolicy::read( $aggregate, $this->context, $this->now ) : NoticePolicy::manage( $aggregate, $operation, $this->context, $this->now );
		}
		return 'read' === $operation ? EventPolicy::read( $aggregate, $this->context, $this->now ) : EventPolicy::manage( $aggregate, $operation, $this->context, $this->now );
	}
}
