<?php
/** Atomic communications persistence and recipient notices.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Communications;

use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\F112\{ActorContext, Configuration, CurrentActor};
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting -- Internal repository contract is documented in the F1-12 plan and SQL acceptance.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
/** Every write revalidates current authority inside the caller-owned transaction. */
final class NoticeRepository {
	private readonly NoticeSchema $schema;

	public function __construct( private readonly IdentityWpdb $database, private readonly CurrentActor $actors, private readonly Closure $clock, private readonly string $token_secret ) {
		if ( $actors->connection() !== $database || 'communications' !== $actors->module || strlen( $token_secret ) < 32 ) {
			throw new RuntimeException( 'Composição de comunicados inválida.' );
		}
		$this->schema = new NoticeSchema( $database );
	}

	public function create( NoticeContent $content, string $reason ): Notice {
		return $this->transaction(
			function ( ActorContext $actor, array $recipients, int $now ) use ( $content, $reason ): Notice {
				$candidate = Notice::draft( 1, $actor->id(), $content, $now, $reason );
				if ( ! NoticePolicy::manage( $candidate, 'revise', $actor, $now ) ) {
					self::denied();
				}
				$id     = $this->anchor( $actor->id(), $now );
				$notice = Notice::draft( $id, $actor->id(), $content, $now, $reason );
				$this->persist( null, $notice, $recipients, $now );
				return $notice;
			}
		);
	}

	/** Internal model for an eligible recipient or competent CMS actor, never a public DTO. */
	public function find( int $id ): ?Notice {
		if ( $id < 1 ) {
			return null;
		}
		try {
			return $this->transaction(
				function ( ActorContext $actor, array $recipients, int $now ) use ( $id ): ?Notice {
					if ( ! $this->canUse( $actor, $now ) ) {
						return null;
					}
					$notice = $this->load( $id );
					return null !== $notice && ( NoticePolicy::read( $notice, $actor, $now ) || NoticePolicy::manage( $notice, 'view', $actor, $now ) ) ? $notice : null;
				}
			);
		} catch ( DomainException ) {
			return null;
		}
	}

	public function revise( int $id, NoticeContent $content, int $expected, string $reason ): Notice {
		return $this->change( $id, 'revise', $expected, $reason, $content );
	}

	public function publish( int $id, int $expected, string $reason ): Notice {
		return $this->change( $id, 'publish', $expected, $reason );
	}

	public function archive( int $id, int $expected, string $reason ): Notice {
		return $this->change( $id, 'archive', $expected, $reason );
	}

	public function submit( int $id, int $expected, string $reason ): Notice {
		return $this->change( $id, 'submit', $expected, $reason );
	}

	public function decide( int $id, string $operation, int $version, string $opaque_token, string $reason ): Notice {
		return $this->transaction(
			function ( ActorContext $actor, array $recipients, int $now ) use ( $id, $operation, $version, $opaque_token, $reason ): Notice {
				if ( ! in_array( $operation, array( 'approve', 'reject', 'return' ), true ) || ! $this->canManage( $actor, $now ) ) {
					self::denied();
				}
				$before = $this->load( $id, true );
				if ( null === $before || ! NoticePolicy::manage( $before, $operation, $actor, $now ) || ! DecisionToken::valid( $before, $actor->id(), $this->token_secret, $opaque_token ) || $before->submitted_version !== $version ) {
					self::denied();
				}
				$after = $before->decide( $operation, $version, $actor->id(), $before->revision, $now, $reason );
				$this->persist( $before, $after, $recipients, $now );
				return $after;
			}
		);
	}

	private function change( int $id, string $operation, int $expected, string $reason, ?NoticeContent $content = null ): Notice {
		return $this->transaction(
			function ( ActorContext $actor, array $recipients, int $now ) use ( $id, $operation, $expected, $reason, $content ): Notice {
				if ( ! $this->canManage( $actor, $now ) ) {
					self::denied();
				}
				$before = $this->load( $id, true );
				if ( null === $before || ! NoticePolicy::manage( $before, $operation, $actor, $now ) ) {
					self::denied();
				}
				if ( $before->revision !== $expected ) {
					throw new DomainException( 'Conflito F112.' );
				}
				$after = match ( $operation ) {
					'revise' => $before->revise( $content, $actor->id(), $expected, $now, $reason ),
					'publish' => $before->publish( $actor->id(), $expected, $now, $reason ),
					'submit' => $before->submit( $actor->id(), $expected, $now, $reason ),
					'archive' => $before->archive( $actor->id(), $expected, $now, $reason ),
				};
				// A new audience requires current authority, not only authority over the old draft.
				if ( ! NoticePolicy::manage( $after, 'view', $actor, $now ) ) {
					self::denied();
				}
				$this->persist( $before, $after, $recipients, $now );
				return $after;
			}
		);
	}

	public function visible( NoticeSelection $selection ): array {
		return $this->listing( $selection, false );
	}

	public function manageable( NoticeSelection $selection ): array {
		return $this->listing( $selection, true );
	}

	private function listing( NoticeSelection $selection, bool $management ): array {
		return $this->transaction(
			function ( ActorContext $actor, array $recipients, int $now ) use ( $selection, $management ): array {
				$authorised = array();
				$permitted  = $management ? $this->canManage( $actor, $now ) : $actor->allows( Action::ReadContent, Scope::own(), $actor->id(), $now );
				if ( $permitted ) {
					foreach ( $this->rows( $this->database->prepare( 'SELECT id FROM %i ORDER BY id', $this->schema->table( 'objects' ) ) ) as $row ) {
						$notice = $this->load( self::integer( $row->id ) );
						if ( null !== $notice && ( $management ? NoticePolicy::manage( $notice, 'view', $actor, $now ) : NoticePolicy::read( $notice, $actor, $now ) ) ) {
							$authorised[] = $notice;
						}
					}
				}
				return $selection->apply( $authorised, $now, $management );
			}
		);
	}

	public function history( int $id ): array {
		return $this->transaction(
			function ( ActorContext $actor, array $recipients, int $now ) use ( $id ): array {
				if ( ! $this->canManage( $actor, $now ) ) {
					self::denied();
				}
				$notice = $this->load( $id );
				if ( null === $notice || ! NoticePolicy::manage( $notice, 'view', $actor, $now ) || ( null === $notice->unit_id && $notice->author_id !== $actor->id() ) ) {
					self::denied();
				}
				return $notice->history;
			}
		);
	}

	public function notices(): array {
		return $this->transaction(
			function ( ActorContext $actor, array $recipients, int $now ): array {
				if ( ! $actor->allows( Action::ReadContent, Scope::own(), $actor->id(), $now ) ) {
					return array();
				}
				$visible = array();
				foreach ( $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE recipient_id=%d ORDER BY id DESC', $this->schema->table( 'notifications' ), $actor->id() ) ) as $row ) {
					$notice = $this->load( self::integer( $row->object_id ) );
					if ( null !== $notice && $this->noticeEligible( $row, $notice, $actor, $now ) ) {
						$visible[] = $this->noticeProjection( $row, $notice, $now );
					}
				}
				return $visible;
			}
		);
	}

	public function markRead( int $notice_id, int $now ): void {
		$this->transaction(
			function ( ActorContext $actor, array $recipients, int $clock ) use ( $notice_id, $now ): void {
				if ( $notice_id < 1 || $now !== $clock || ! $actor->allows( Action::ReadContent, Scope::own(), $actor->id(), $clock ) ) {
					self::denied();
				}
				$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE id=%d AND recipient_id=%d', $this->schema->table( 'notifications' ), $notice_id, $actor->id() ) );
				if ( 1 !== count( $rows ) ) {
					self::denied();
				}
				$notice = $this->load( self::integer( $rows[0]->object_id ), true );
				$locked = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE id=%d AND recipient_id=%d FOR UPDATE', $this->schema->table( 'notifications' ), $notice_id, $actor->id() ) );
				if ( null === $notice || 1 !== count( $locked ) || ! $this->noticeEligible( $locked[0], $notice, $actor, $clock ) ) {
					self::denied();
				}
				if ( null === $locked[0]->read_at ) {
					$this->execute( $this->database->prepare( 'UPDATE %i SET read_at=%d WHERE id=%d AND recipient_id=%d AND read_at IS NULL', $this->schema->table( 'notifications' ), $clock, $notice_id, $actor->id() ), 1 );
				}
			}
		);
	}

	private function noticeEligible( object $row, Notice $notice, ActorContext $actor, int $now ): bool {
		return 'published' === $row->kind && self::integer( $row->recipient_id ) === $actor->id() && self::integer( $row->version ) === $notice->published_version && self::integer( $row->revision ) === $notice->published_revision && NoticePolicy::read( $notice, $actor, $now );
	}

	private function noticeProjection( object $row, Notice $notice, int $now ): array {
		return array(
			'id'         => self::integer( $row->id ),
			'object_id'  => $notice->id,
			'kind'       => $row->kind,
			'title'      => $notice->published( $now )->content->title,
			'created_at' => self::integer( $row->created_at, 0 ),
			'read_at'    => null === $row->read_at ? null : self::integer( $row->read_at, 0 ),
		);
	}

	private function canManage( ActorContext $actor, int $now ): bool {
		foreach ( $actor->active_units as $unit ) {
			if ( $actor->allows( Action::PublishTeamNotice, Scope::units( array( $unit ) ), $actor->id(), $now ) ) {
				return true;
			}
		}
		foreach ( array( Action::ManageInstitutionalNotice, Action::ReviewInstitutionalNotice ) as $action ) {
			if ( $actor->allows( $action, Scope::queues( array( 'communications-ficticia' ) ), $actor->id(), $now ) ) {
				return true;
			}
		}
		return false;
	}

	private function canUse( ActorContext $actor, int $now ): bool {
		return $this->canManage( $actor, $now ) || $actor->allows( Action::ReadContent, Scope::own(), $actor->id(), $now );
	}

	private function anchor( int $actor, int $now ): int {
		$table  = $GLOBALS['wpdb']->posts;
		$engine = $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
		if ( 1 !== count( $engine ) || 'InnoDB' !== $engine[0]->ENGINE ) {
			throw new RuntimeException( 'Âncora de comunicados não transaccional.' );
		}
		$date = gmdate( 'Y-m-d H:i:s', $now );
		$data = array(
			'post_author'           => $actor,
			'post_date'             => $date,
			'post_date_gmt'         => $date,
			'post_content'          => '',
			'post_title'            => 'Comunicado privado',
			'post_excerpt'          => 'arn-f112:' . $this->database->prefix . ':communications',
			'post_status'           => 'private',
			'comment_status'        => 'closed',
			'ping_status'           => 'closed',
			'post_password'         => '',
			'post_name'             => '',
			'to_ping'               => '',
			'pinged'                => '',
			'post_modified'         => $date,
			'post_modified_gmt'     => $date,
			'post_content_filtered' => '',
			'post_parent'           => 0,
			'guid'                  => '',
			'menu_order'            => 0,
			'post_type'             => 'arn_comunicado',
			'post_mime_type'        => '',
			'comment_count'         => 0,
		);
		if ( 1 !== $this->database->insert( $table, $data ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Criação de âncora de comunicado indisponível.' );
		}
		return self::integer( $this->database->insert_id );
	}

	private function load( int $id, bool $lock = false ): ?Notice {
		if ( $id < 1 ) {
			return null;
		}
		$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE id=%d', $this->schema->table( 'objects' ), $id ) . ( $lock ? ' FOR UPDATE' : '' ) );
		if ( ! $rows ) {
			return null;
		}
		$row  = $rows[0];
		$data = json_decode( $row->payload, true, 64, JSON_THROW_ON_ERROR );
		if ( 1 !== count( $rows ) || ! is_array( $data ) ) {
			throw new RuntimeException( 'Agregado de comunicados inválido.' );
		}
		$notice = Notice::fromArray( $data );
		$digest = hash( 'sha256', self::encode( $notice->toArray() ) );
		if ( $notice->id !== $id || self::integer( $row->author_id ) !== $notice->author_id || $row->unit_id !== $notice->unit_id || self::integer( $row->revision ) !== $notice->revision || $row->digest !== $digest || self::encode( $notice->toArray() ) !== $row->payload ) {
			throw new RuntimeException( 'Digest de comunicados inválido.' );
		}
		$anchor = $this->rows( $this->database->prepare( 'SELECT post_author,post_type,post_status,post_content,post_title,post_excerpt FROM %i WHERE ID=%d', $GLOBALS['wpdb']->posts, $id ) );
		if ( 1 !== count( $anchor ) || self::integer( $anchor[0]->post_author ) !== $notice->author_id || 'arn_comunicado' !== $anchor[0]->post_type || 'private' !== $anchor[0]->post_status || '' !== $anchor[0]->post_content || 'Comunicado privado' !== $anchor[0]->post_title || 'arn-f112:' . $this->database->prefix . ':communications' !== $anchor[0]->post_excerpt ) {
			throw new RuntimeException( 'Âncora de comunicado incompatível.' );
		}
		$versions = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE object_id=%d ORDER BY `number`', $this->schema->table( 'versions' ), $id ) );
		if ( count( $versions ) !== count( $notice->versions ) ) {
			throw new RuntimeException( 'Versões de comunicado incompletas.' );
		}
		foreach ( $versions as $i => $version ) {
			if ( self::integer( $version->object_id ) !== $id || self::integer( $version->number ) !== $i + 1 || self::encode( $notice->versions[ $i ]->toArray() ) !== $version->payload ) {
				throw new RuntimeException( 'Versões de comunicado incompatíveis.' );
			}
		}
		$audit = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE object_id=%d ORDER BY revision', $this->schema->table( 'audit' ), $id ) );
		if ( count( $audit ) !== $notice->revision ) {
			throw new RuntimeException( 'Auditoria de comunicado incompleta.' );
		}
		$previous = str_repeat( '0', 64 );
		foreach ( $audit as $i => $event ) {
			$entry = $notice->history[ $i ];
			if ( self::integer( $event->object_id ) !== $id || self::integer( $event->revision ) !== $i + 1 || $event->event !== $entry['operation'] || self::integer( $event->actor_id ) !== $entry['actor_id'] || self::integer( $event->occurred_at, 0 ) !== $entry['occurred_at'] || $event->reason !== $entry['reason'] || self::encode( $entry ) !== $event->payload || $event->before_hash !== $previous || 1 !== preg_match( '/\A[a-f0-9]{64}\z/', $event->after_hash ) ) {
				throw new RuntimeException( 'Cadeia de auditoria de comunicado inválida.' );
			}
			$previous = $event->after_hash;
		}
		if ( $previous !== $digest ) {
			throw new RuntimeException( 'Última auditoria de comunicado inválida.' );
		}
		return $notice;
	}

	private function persist( ?Notice $before, Notice $after, array $recipients, int $now ): void {
		if ( $before === $after ) {
			return;
		}
		$payload = self::encode( $after->toArray() );
		$digest  = hash( 'sha256', $payload );
		$data    = array(
			'id'        => $after->id,
			'author_id' => $after->author_id,
			'unit_id'   => $after->unit_id,
			'revision'  => $after->revision,
			'payload'   => $payload,
			'digest'    => $digest,
		);
		$written = null === $before ? $this->database->insert( $this->schema->table( 'objects' ), $data ) : $this->database->update(
			$this->schema->table( 'objects' ),
			$data,
			array(
				'id'       => $after->id,
				'revision' => $before->revision,
			)
		);
		$this->assertWrite( $written );
		if ( null === $before || count( $after->versions ) > count( $before->versions ) ) {
			$this->assertWrite(
				$this->database->insert(
					$this->schema->table( 'versions' ),
					array(
						'object_id' => $after->id,
						'number'    => $after->latest()->number,
						'payload'   => self::encode( $after->latest()->toArray() ),
					)
				)
			);
		}
		$entry = $after->history[ $after->revision - 1 ];
		$this->assertWrite(
			$this->database->insert(
				$this->schema->table( 'audit' ),
				array(
					'object_id'   => $after->id,
					'revision'    => $after->revision,
					'event'       => $entry['operation'],
					'actor_id'    => $entry['actor_id'],
					'occurred_at' => $entry['occurred_at'],
					'reason'      => $entry['reason'],
					'before_hash' => null === $before ? str_repeat( '0', 64 ) : hash( 'sha256', self::encode( $before->toArray() ) ),
					'after_hash'  => $digest,
					'payload'     => self::encode( $entry ),
				)
			)
		);
		if ( in_array( $entry['operation'], array( 'published', 'approved' ), true ) ) {
			$public = $after->published( $now );
			foreach ( $recipients as $recipient ) {
				if ( $recipient->canReceive( $public->content->audience, $now ) ) {
					$this->assertWrite(
						$this->database->insert(
							$this->schema->table( 'notifications' ),
							array(
								'object_id'    => $after->id,
								'version'      => $public->number,
								'revision'     => $after->revision,
								'recipient_id' => $recipient->id(),
								'kind'         => 'published',
								'created_at'   => $now,
								'read_at'      => null,
							)
						)
					);
				}
			}
		}
	}

	private function transaction( Closure $operation ): mixed {
		if ( ! Configuration::current( 'communications' ) ) {
			self::denied();
		}
		$previous = $this->database->suppress_errors( true );
		try {
			$this->schema->assertReady();
			if ( '0' !== (string) $this->database->get_var( 'SELECT @@session.in_transaction' ) || '' !== $this->database->last_error ) {
				throw new RuntimeException( 'Ligação de comunicado ocupada.' );
			}
			if ( null === $this->actors->read() ) {
				self::denied();
			}
			$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
			$this->execute( 'START TRANSACTION' );
			try {
				$recipients = $this->actors->locked();
				$actor      = $recipients[ $this->actors->currentId() ] ?? null;
				$now        = ( $this->clock )();
				if ( ! $actor instanceof ActorContext || ! is_int( $now ) || ! $actor->valid( $now ) ) {
					self::denied();
				}
				$result = $operation( $actor, $recipients, $now );
				$this->execute( 'COMMIT' );
				return $result;
			} catch ( DomainException | InvalidArgumentException $error ) {
				$this->database->query( 'ROLLBACK' );
				throw $error;
			} catch ( Throwable ) {
				$this->database->query( 'ROLLBACK' );
				throw new RuntimeException( 'Persistência de comunicados indisponível.' );
			}
		} finally {
			$this->database->suppress_errors( $previous );
		}
	}

	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Leitura de comunicados indisponível.' );
		}
		return $rows;
	}

	private function execute( string $sql, ?int $expected = null ): void {
		$result = $this->database->query( $sql );
		if ( false === $result || '' !== $this->database->last_error || ( null !== $expected && $result !== $expected ) ) {
			throw new RuntimeException( 'Escrita de comunicados indisponível.' );
		}
	}

	private function assertWrite( mixed $result ): void {
		if ( 1 !== $result || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Escrita de comunicado incompleta.' );
		}
	}

	private static function integer( mixed $value, int $minimum = 1 ): int {
		if ( is_int( $value ) ) {
			if ( $value >= $minimum ) {
				return $value;
			}
		} elseif ( is_string( $value ) && 1 === preg_match( '/\A(?:0|[1-9][0-9]*)\z/', $value ) && strlen( $value ) <= strlen( (string) PHP_INT_MAX ) && ( strlen( $value ) < strlen( (string) PHP_INT_MAX ) || strcmp( $value, (string) PHP_INT_MAX ) <= 0 ) && (int) $value >= $minimum ) {
			return (int) $value;
		}
		throw new RuntimeException( 'Inteiro de comunicado inválido.' );
	}

	private static function encode( array $value ): string {
		return json_encode( $value, JSON_THROW_ON_ERROR );
	}

	private static function denied(): never {
		throw new DomainException( 'Operação F112 recusada.' );
	}
}
