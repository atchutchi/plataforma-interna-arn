<?php
/** Shared test-only fixture lifecycle, never a publication engine.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Exclusive test protocol is documented in the F1-12 plan.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\{AccountState, Grant, Role, Scope};
use Arn\Intranet\Authorization\Storage\{AuthorizationStorageService, ChangeContext, LocalAuthorizationStorageAuthority, WpdbAuthorizationRepository};
use Arn\Intranet\Calendar\{AgendaSelection, EventContent, EventRepository, EventSchema};
use Arn\Intranet\Communications\{DecisionToken, NoticeContent, NoticeRepository, NoticeSchema, NoticeSelection};
use Arn\Intranet\F112\{Audience, Configuration, CurrentActor};
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Organization\Storage\{LocalUnitContext, WpdbLocalUnitRepository};

require_once __DIR__ . '/f112-concurrency.php';

function arn_f112_normal_snapshot(): array {
	global $wpdb;
	$tables = $wpdb->get_col( 'SHOW TABLES' );
	if ( ! is_array( $tables ) || '' !== $wpdb->last_error ) {
		throw new RuntimeException( 'Snapshot normal indisponível.' );
	}
	$protected = array();
	sort( $tables );
	foreach ( $tables as $table ) {
		if ( ! str_starts_with( $table, $wpdb->prefix ) ) {
			continue;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table ), ARRAY_A );
		if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
			throw new RuntimeException( 'Tabela normal indisponível.' );
		}
		$hashes = array_map( static fn( array $row ): string => hash( 'sha256', json_encode( $row, JSON_THROW_ON_ERROR ) ), $rows );
		sort( $hashes );
		$protected[ $table ] = $hashes;
		if ( $table === $wpdb->options ) {
			$options = array();
			foreach ( $rows as $row ) {
				$options[ $row['option_name'] ] = hash( 'sha256', json_encode( $row, JSON_THROW_ON_ERROR ) );
			}
			ksort( $options );
			$protected['__options_by_name'] = $options;
		}
	}
	return $protected;
}

function arn_f112_storage_test( string $module ): void {
	if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! defined( 'ARN_F112_SQL_HARNESS' ) || true !== ARN_F112_SQL_HARNESS || ! current_user_can( 'manage_options' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! in_array( $module, array( 'communications', 'calendar' ), true ) ) {
		throw new RuntimeException( 'Ensaio F112 indisponível.' );
	}
	if ( 'development' === wp_get_environment_type() ) {
		if ( Configuration::current( $module ) ) {
			throw new RuntimeException( 'Gate development abriu.' );
		}
		WP_CLI::success( 'F112 development: apenas gate negativo, sem instalação.' );
		return;
	}
	foreach ( array( 'ARN_ALLOW_LOCAL_COMMUNICATIONS', 'ARN_ALLOW_LOCAL_CALENDAR', 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) as $flag ) {
		if ( ! defined( $flag ) ) {
			define( $flag, true );
		}
		if ( true !== constant( $flag ) ) {
			throw new RuntimeException( 'Flag F112 local recusada.' );
		}
	}
	$prefix = 'arn_f112_' . bin2hex( random_bytes( 5 ) ) . '_';
	$db     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$db->set_prefix( $prefix );
	$db->suppress_errors( true );
	$admin         = get_current_user_id();
	$owned         = array();
	$anchors       = array();
	$checks        = 0;
	$now           = 1894701600;
	$clock         = static function () use ( &$now ): int {
		return $now;
	};
	$assert        = static function ( bool $ok, string $label ) use ( &$checks ): void {
		if ( ! $ok ) {
			throw new RuntimeException( 'Falhou F112: ' . $label );
		}
		++$checks;
	};
	$denied        = static function ( callable $operation ) use ( $assert ): void {
		try {
			$operation();
		} catch ( DomainException | InvalidArgumentException ) {
			$assert( true, 'recusa de negócio' );
			return;
		}
		$assert( false, 'operação indevida foi aceite' );
	};
	$unready       = static function ( callable $operation ) use ( $assert ): void {
		try {
			$operation();
		} catch ( RuntimeException ) {
			$assert( true, 'schema ou SQL recusado' );
			return;
		}
		$assert( false, 'schema ou falha técnica foi aceite' );
	};
	$failure       = null;
	$schema        = null;
	$before_posts  = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->posts );
	$normal_before = arn_f112_normal_snapshot();
	try {
		$schema = 'communications' === $module ? new NoticeSchema( $db ) : new EventSchema( $db );
		$unready( static fn() => $schema->assertReady() );
		$schema->install();
		$schema->install();
		$schema->assertReady();
		$auth    = new WpdbAuthorizationRepository( $db );
		$service = new AuthorizationStorageService( $auth, new LocalAuthorizationStorageAuthority() );
		$context = new ChangeContext( $admin, 'Ensaio sintético F112', 'F112-exclusive', $now );
		$service->installSchema( $context );
		$units = new WpdbLocalUnitRepository( $db );
		$units->installSchema();
		$units->apply( 'initialize', 0, new LocalUnitContext( $admin, 'Ensaio sintético F112', $now ) );
		$roster = array();
		$ids    = array();
		$roles  = array(
			'a'        => array( array( Role::Employee, Scope::own() ) ),
			'b'        => array( array( Role::Employee, Scope::own() ) ),
			'manager'  => array( array( Role::Employee, Scope::own() ), array( Role::UnitManager, Scope::units( array( 'LAB-A' ) ) ), array( Role::UnitEventManager, Scope::units( array( 'LAB-A' ) ) ) ),
			'author'   => array( array( Role::Employee, Scope::own() ), array( Role::NoticeAuthor, Scope::queues( array( 'communications-ficticia' ) ) ), array( Role::NoticeAuthor, Scope::audiences( array( 'f112_all', 'f112_unit_LAB-A', 'f112_unit_LAB-B' ) ) ) ),
			'reviewer' => array( array( Role::Employee, Scope::own() ), array( Role::NoticeReviewer, Scope::queues( array( 'communications-ficticia' ) ) ), array( Role::NoticeReviewer, Scope::audiences( array( 'f112_all', 'f112_unit_LAB-A', 'f112_unit_LAB-B' ) ) ) ),
			'events'   => array( array( Role::Employee, Scope::own() ), array( Role::InstitutionalEventManager, Scope::institutional() ) ),
		);
		foreach ( $roles as $label => $grants ) {
			$login = $prefix . $label;
			$id    = wp_insert_user(
				array(
					'user_login'   => $login,
					'user_email'   => $login . '@example.invalid',
					'user_pass'    => bin2hex( random_bytes( 24 ) ),
					'role'         => 'subscriber',
					'display_name' => 'Ensaio F112 ' . $label,
				)
			);
			$assert( is_int( $id ), 'conta fictícia exclusiva' );
			$owned[ $id ]  = $login;
			$ids[ $label ] = $id;
			update_user_meta( $id, LocalFixtureUser::META_KEY, '1' );
			update_user_meta( $id, '_arn_f112_fixture', $prefix );
			$roster[ $id ] = array(
				'login' => $login,
				'units' => in_array( $label, array( 'a', 'manager' ), true ) ? array( 'LAB-A' ) : ( 'b' === $label ? array( 'LAB-B' ) : array() ),
			);
			$service->createAccount( $id, $context );
			$service->changeState( $id, AccountState::Active, 1, $context );
			foreach ( $grants as $pair ) {
				$service->createGrant( new Grant( $id, $pair[0], $pair[1], 0, null ), $auth->find( $id )->revision, $context );
			}
		}
		$assert( 6 === count( $roster ), 'seis contas separadas das anteriores' );
		$current_id      = $ids['manager'];
		$session         = static fn( int $id ): array => array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => $id,
			'arn_authz_revision' => $auth->find( $id )->revision,
		);
		$current_session = $session( $current_id );
		$resolver        = static function () use ( &$current_id, &$current_session ): array {
			return array( $current_id, $current_session );
		};
		$actors          = new CurrentActor( $db, $module, $roster, $clock, $resolver );
		$unready( static fn() => $actors->locked() );
		// A native session is tested separately from the explicit SQL resolver.
		$native_user     = $ids['manager'];
		$previous_cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
		$manager         = WP_Session_Tokens::get_instance( $native_user );
		$token           = $manager->create( time() + 3600 );
		$native_session  = array_merge( $manager->get( $token ), $session( $native_user ), array( 'expiration' => $now + 3600 ) );
		$manager->update( $token, $native_session );
		$original_sessions = get_user_meta( $native_user, 'session_tokens', true );
		try {
			wp_set_current_user( $native_user );
			$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $native_user, time() + 3600, 'logged_in', $token );
			$native                      = new CurrentActor( $db, $module, $roster, $clock );
			$assert( $native_user === $native->read()?->id(), 'sessão nativa relida na ligação bloqueada' );
			// update_user_meta double-serialises serialized-looking strings. Write these
			// deliberately malformed bytes only into the new exclusive fixture row.
			$assert(
				1 === $GLOBALS['wpdb']->update(
					$GLOBALS['wpdb']->usermeta,
					array( 'meta_value' => 'a:1:{s:1:"a";}' ),
					array(
						'user_id'  => $native_user,
						'meta_key' => 'session_tokens',
					)
				),
				'bytes malformados só na fixture exclusiva'
			);
			$warnings = array();
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Assert that corrupt fixture metadata cannot leak warnings, restoring the handler in finally.
			set_error_handler(
				static function ( int $severity ) use ( &$warnings ): bool {
					$warnings[] = $severity;
					return true;
				}
			);
			try {
				$assert( null === $native->read(), 'sessão persistida malformada fecha acesso' );
			} finally {
				restore_error_handler();
			}
			$assert( array() === $warnings, 'sessão inválida não emite warnings na resposta' );
			update_user_meta( $native_user, 'session_tokens', $original_sessions );
			$manager->destroy( $token );
			$assert( null === $native->read(), 'token revogado não reutiliza metadados em cache' );
		} finally {
			update_user_meta( $native_user, 'session_tokens', $original_sessions );
			$manager->destroy( $token );
			if ( null === $previous_cookie ) {
				unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
			} else {
				$_COOKIE[ LOGGED_IN_COOKIE ] = $previous_cookie;
			}
			wp_set_current_user( $admin );
		}
		$secret    = str_repeat( 'synthetic-key-', 4 );
		$repo      = 'communications' === $module ? new NoticeRepository( $db, $actors, $clock, $secret ) : new EventRepository( $db, $actors, $clock, $secret );
		$content   = static function ( string $title, Audience $audience, bool $institutional = false, ?int $expiry = null ) use ( $module ): NoticeContent|EventContent {
			return 'communications' === $module ? new NoticeContent( $title, 'Corpo fictício', $audience, $institutional ? 'institutional-review' : 'team-direct', $expiry ) : new EventContent( $title, 'Descrição fictícia', $audience, 'online', '', 'https://example.invalid/reuniao-f112', false, '2030-01-15T09:00:00Z', '2030-01-16T11:00:00Z', null, null );
		};
		$first     = $repo->create( $content( 'Comunicado de ensaio A', Audience::unit( 'LAB-A' ) ), 'Criar ensaio A.' );
		$anchors[] = $first->id;
		$first     = $repo->publish( $first->id, 1, 'Publicar ensaio A.' );
		$assert( 2 === $first->revision && 2 === count( $repo->history( $first->id ) ), 'uma revisão e um evento por escrita' );
		// A resolved request can wait while a publication becomes current in a later second.
		$portal_reflection = new ReflectionClass( \Arn\Intranet\F112\Portal::class );
		$resolved_portal   = $portal_reflection->newInstanceWithoutConstructor();
		foreach ( array(
			'module'     => $module,
			'now'        => $now - 1,
			'context'    => $actors->read(),
			'repository' => $repo,
		) as $property => $value ) {
			$portal_reflection->getProperty( $property )->setValue( $resolved_portal, $value );
		}
		$public_query = 'communications' === $module ? NoticeSelection::parse( array() ) : AgendaSelection::parse( array() );
		$projected    = $resolved_portal->listing( $public_query );
		$assert( 1 === $projected['total'] && $first->id === $projected['items'][0]['id'] && null !== $resolved_portal->detail( $first->id ), 'lista e detalhe usam o relógio transaccional da publicação' );
		$employee_grant = null;
		foreach ( $auth->find( $current_id )->stored_grants as $stored ) {
			if ( Role::Employee === $stored->grant->role && ! $stored->grant->revoked ) {
				$employee_grant = $stored;
			}
		}
		$assert( null !== $employee_grant, 'leitura Employee separada antes do controlo de detalhe' );
		$service->revokeGrant( $current_id, $employee_grant->id, $auth->find( $current_id )->revision, $context );
		$current_session = $session( $current_id );
		$assert( null === $repo->find( $first->id, 'read' ) && null !== $repo->find( $first->id, 'manage' ), 'gestão não ultrapassa a guarda transaccional de leitura' );
		$service->createGrant( new Grant( $current_id, Role::Employee, Scope::own(), 0, null ), $auth->find( $current_id )->revision, $context );
		$current_session = $session( $current_id );
		$denied( static fn() => $repo->create( $content( 'B indevido', Audience::unit( 'LAB-B' ) ), 'Recusar B.' ) );
		$denied( static fn() => $repo->create( $content( 'Todos indevido', Audience::all(), true ), 'Recusar âmbito institucional.' ) );
		$before = $repo->find( $first->id )->toArray();
		$repo->revise( $first->id, $content( 'Comunicado de ensaio A', Audience::unit( 'LAB-A' ) ), 2, 'Sem alteração.' );
		$assert( $before === $repo->find( $first->id )->toArray(), 'no-op conserva agregado e histórico' );
		$denied( static fn() => $repo->revise( $first->id, $content( 'Comunicado de ensaio A', Audience::unit( 'LAB-A' ) ), 1, 'CAS antigo mesmo em no-op.' ) );
		$current_id      = $ids['a'];
		$current_session = $session( $current_id );
		$selection       = 'communications' === $module ? NoticeSelection::parse( array() ) : AgendaSelection::parse( array() );
		$assert( 1 === $repo->visible( $selection )['total'], 'A recebe publicação A' );
		$assert( null !== $repo->find( $first->id, 'read' ) && null === $repo->find( $first->id, 'manage' ), 'leitura não expõe agregado de gestão' );
		$notices = $repo->notices();
		$assert( 1 === count( $notices ), 'um aviso por destinatário elegível' );
		$repo->markRead( $notices[0]['id'] );
		$assert( $now === $repo->notices()[0]['read_at'], 'recibo usa o relógio transaccional do servidor' );
		$repo->markRead( $notices[0]['id'], $now );
		++$now;
		$repo->markRead( $notices[0]['id'], $now );
		$assert( 1894701600 === $repo->notices()[0]['read_at'], 'recibo conserva o primeiro instante' );
		$current_id      = $ids['b'];
		$current_session = $session( $current_id );
		$assert( 0 === $repo->visible( $selection )['total'] && array() === $repo->notices() && null === $repo->find( $first->id ), 'B sem detalhe ou contagem de A' );
		$current_id      = $ids['manager'];
		$current_session = $session( $current_id );
		$before          = $repo->find( $first->id )->toArray();
		$trigger         = $prefix . 'fail_audit';
		$assert( false !== $db->query( $db->prepare( 'CREATE TRIGGER %i BEFORE INSERT ON %i FOR EACH ROW SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'synthetic-f112-failure\'', $trigger, $schema->table( 'audit' ) ) ), 'falha tardia injectada' );
		try {
			$unready( static fn() => $repo->revise( $first->id, $content( 'Não confirmar', Audience::unit( 'LAB-A' ) ), 2, 'Falhar auditoria.' ) );
		} finally {
			$db->query( $db->prepare( 'DROP TRIGGER %i', $trigger ) );
		}
		$assert( $before === $repo->find( $first->id )->toArray(), 'rollback de agregado versão e auditoria' );
		if ( 'communications' === $module ) {
			$current_id      = $ids['author'];
			$current_session = $session( $current_id );
			$institutional   = $repo->create( $content( 'Comunicado institucional de ensaio', Audience::all(), true ), 'Criar institucional.' );
			$anchors[]       = $institutional->id;
			$pending         = $repo->submit( $institutional->id, 1, 'Submeter.' );
			$denied( static fn() => $repo->decide( $pending->id, 'approve', 1, str_repeat( '0', 64 ), 'Auto aprovação.' ) );
			$current_id      = $ids['reviewer'];
			$current_session = $session( $current_id );
			$assert( null !== $repo->find( $pending->id ), 'revisor vê a submissão corrente autorizada' );
			$denied( static fn() => $repo->history( $pending->id ) );
			$published = $repo->decide( $pending->id, 'approve', 1, DecisionToken::make( $pending, $current_id, $secret ), 'Segundo actor.' );
			$assert( 1 === $published->published_version, 'aprovação exacta e grant novo roundtrip SQL' );
			$current_id      = $ids['author'];
			$current_session = $session( $current_id );
			$changed         = $repo->revise( $pending->id, $content( 'Nova audiência B', Audience::unit( 'LAB-B' ), true, $now + 100 ), 3, 'Mudar audiência.' );
			$pending         = $repo->submit( $changed->id, 4, 'Submeter versão B.' );
			$current_id      = $ids['a'];
			$current_session = $session( $current_id );
			$assert( 2 === $repo->visible( $selection )['total'], 'V1 permanece durante pending' );
			$current_id      = $ids['reviewer'];
			$current_session = $session( $current_id );
			$repo->decide( $pending->id, 'approve', 2, DecisionToken::make( $pending, $current_id, $secret ), 'Publicar B.' );
			$current_id      = $ids['a'];
			$current_session = $session( $current_id );
			$assert( 1 === $repo->visible( $selection )['total'] && null === $repo->find( $pending->id ), 'audiência retirada sem acesso residual' );
			$current_id      = $ids['b'];
			$current_session = $session( $current_id );
			$assert( 1 === count( $repo->notices() ), 'aviso antigo substituído, sem título de V1' );
			$now += 100;
			$assert( array() === $repo->notices() && 0 === $repo->visible( $selection )['total'], 'expiry não projecta aviso' );
		} else {
			$current_id      = $ids['manager'];
			$current_session = $session( $current_id );
			$updated         = $repo->revise( $first->id, $content( 'Rascunho de evento', Audience::unit( 'LAB-A' ) ), 2, 'Preparar alteração.' );
			$cancelled       = $repo->cancel( $first->id, 3, 'Cancelamento interno.' );
			$assert( 1 === $cancelled->published_version && 1 === $cancelled->history[3]['version'], 'cancelar última publicação e conservar rascunho' );
			$current_id      = $ids['a'];
			$current_session = $session( $current_id );
			$assert( 0 === $repo->visible( $selection )['total'] && 1 === $repo->visible( AgendaSelection::parse( array( 'state' => 'cancelled' ) ) )['total'], 'cancelado só no filtro explícito' );
			$assert( 1 === count( $repo->notices() ) && 'cancelled' === $repo->notices()[0]['kind'], 'aviso cancelamento substitui publicação' );
			$current_id      = $ids['manager'];
			$current_session = $session( $current_id );
			$denied( static fn() => $repo->publish( $first->id, 4, 'Reactivar cancelado.' ) );
			$repo->archive( $first->id, 4, 'Arquivar cancelado.' );
			$current_id      = $ids['a'];
			$current_session = $session( $current_id );
			$assert( null === $repo->find( $first->id ) && array() === $repo->notices(), 'arquivo retira detalhe e aviso' );
			$current_id          = $ids['events'];
			$current_session     = $session( $current_id );
			$institutional_event = $repo->create( $content( 'Evento institucional de ensaio', Audience::all(), true ), 'Gestão institucional explícita.' );
			$anchors[]           = $institutional_event->id;
			$institutional_event = $repo->publish( $institutional_event->id, 1, 'Publicar evento institucional.' );
			$assert( null === $institutional_event->unit_id && $ids['events'] === $institutional_event->author_id, 'âmbito institucional sem inferir unidade do organizador' );
			$current_id      = $ids['manager'];
			$current_session = $session( $current_id );
			$denied( static fn() => $repo->revise( $institutional_event->id, $content( 'Gestor de unidade indevido', Audience::all(), true ), 2, 'Recusar gestão institucional.' ) );
			$current_id      = $ids['a'];
			$current_session = $session( $current_id );
			$assert( 1 === $repo->visible( $selection )['total'] && 1 === count( $repo->notices() ), 'leitor recebe evento institucional autorizado' );
		}
		wp_set_current_user( $admin );
		$current_id      = $ids['manager'];
		$current_session = $session( $current_id );
		$probe           = $repo->create( $content( 'Ensaio transaccional A', Audience::unit( 'LAB-A' ) ), 'Criar sonda exclusiva.' );
		$anchors[]       = $probe->id;
		$probe           = $repo->publish( $probe->id, 1, 'Publicar sonda.' );
		$module_snapshot = static function () use ( $db, $schema ): array {
			$tables = array();
			foreach ( array( 'objects', 'versions', 'audit', 'notifications', 'schema' ) as $part ) {
				$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i', $schema->table( $part ) ), ARRAY_A );
				if ( ! is_array( $rows ) || '' !== $db->last_error ) {
					throw new RuntimeException( 'Snapshot de ensaio indisponível.' );
				}
				$hashes = array_map( static fn( array $row ): string => hash( 'sha256', json_encode( $row, JSON_THROW_ON_ERROR ) ), $rows );
				sort( $hashes );
				$tables[ $part ] = $hashes;
			}
			return $tables;
		};
		$before_probe    = $module_snapshot();
		$other           = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$other->set_prefix( $prefix );
		$other->suppress_errors( true );
		$other_auth    = new WpdbAuthorizationRepository( $other );
		$other_service = new AuthorizationStorageService( $other_auth, new LocalAuthorizationStorageAuthority() );
		try {
			$race_calls    = 0;
			$race_resolver = static function () use ( &$race_calls, &$current_id, &$current_session, $other_auth, $other_service, $context ): array {
				++$race_calls;
				if ( 3 === $race_calls ) {
					$other_service->changeState( $current_id, AccountState::Suspended, $other_auth->find( $current_id )->revision, $context );
				}
				return array( $current_id, $current_session );
			};
			$race_actors   = new CurrentActor( $db, $module, $roster, $clock, $race_resolver );
			$race_repo     = 'communications' === $module ? new NoticeRepository( $db, $race_actors, $clock, $secret ) : new EventRepository( $db, $race_actors, $clock, $secret );
			$denied( static fn() => $race_repo->revise( $probe->id, $content( 'Não confirmar suspensão', Audience::unit( 'LAB-A' ) ), 2, 'Suspensão entre preflight e locks.' ) );
			$assert( 3 === $race_calls && AccountState::Suspended === $other_auth->find( $current_id )->state && $before_probe === $module_snapshot(), 'suspensão confirmada noutra ligação impede escrita sob lock' );
			$other_service->changeState( $current_id, AccountState::Active, $other_auth->find( $current_id )->revision, $context );
			$denied( static fn() => $repo->revise( $probe->id, $content( 'Sessão antiga', Audience::unit( 'LAB-A' ) ), 2, 'Reactivação sem novo login.' ) );
			$current_session = $session( $current_id );
			$target_role     = 'communications' === $module ? Role::UnitManager : Role::UnitEventManager;
			$grant           = null;
			foreach ( $other_auth->find( $current_id )->stored_grants as $stored ) {
				if ( $stored->grant->role === $target_role && ! $stored->grant->revoked ) {
					$grant = $stored;
				}
			}
			$assert( null !== $grant, 'concessão positiva antes da revogação' );
			$race_calls      = 0;
			$revoke_resolver = static function () use ( &$race_calls, &$current_id, &$current_session, $other_auth, $other_service, $context, $grant ): array {
				++$race_calls;
				if ( 3 === $race_calls ) {
					$other_service->revokeGrant( $current_id, $grant->id, $other_auth->find( $current_id )->revision, $context );
				}
				return array( $current_id, $current_session );
			};
			$race_actors     = new CurrentActor( $db, $module, $roster, $clock, $revoke_resolver );
			$race_repo       = 'communications' === $module ? new NoticeRepository( $db, $race_actors, $clock, $secret ) : new EventRepository( $db, $race_actors, $clock, $secret );
			$denied( static fn() => $race_repo->revise( $probe->id, $content( 'Não confirmar revogação', Audience::unit( 'LAB-A' ) ), 2, 'Revogação entre preflight e locks.' ) );
			$assert( 3 === $race_calls && $before_probe === $module_snapshot(), 'revogação concorrente impede escrita' );
			$current_session = $session( $current_id );
			$denied( static fn() => $repo->revise( $probe->id, $content( 'Sem concessão', Audience::unit( 'LAB-A' ) ), 2, 'Sessão nova não repõe grant revogado.' ) );
			$other_service->createGrant( new Grant( $current_id, $target_role, $grant->grant->scope, 0, null ), $other_auth->find( $current_id )->revision, $context );
			$current_session = $session( $current_id );
		} finally {
			$other->query( 'ROLLBACK' );
			$other->close();
		}
		$prepared            = $repo->revise( $probe->id, $content( 'Versão preparada para falha tardia', Audience::unit( 'LAB-A' ) ), 2, 'Preparar publicação de ensaio.' );
		$before_notification = $module_snapshot();
		$trigger             = $prefix . 'fail_last_delivery';
		$assert( false !== $db->query( $db->prepare( 'CREATE TRIGGER %i BEFORE INSERT ON %i FOR EACH ROW BEGIN IF NEW.recipient_id=%d THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'synthetic-f112-final-delivery\'; END IF; END', $trigger, $schema->table( 'notifications' ), $ids['manager'] ) ), 'falha no último destinatário instalada' );
		try {
			$unready( static fn() => $repo->publish( $probe->id, 3, 'Falhar no último aviso.' ) );
		} finally {
			$db->query( $db->prepare( 'DROP TRIGGER %i', $trigger ) );
		}
		$assert( $before_notification === $module_snapshot(), 'último aviso reverte publicação auditoria e entregas anteriores' );
		$repo->publish( $probe->id, 3, 'Confirmar publicação após retirar falha.' );
		$concurrent = $repo->create( $content( 'Ensaio concorrente A', Audience::unit( 'LAB-A' ) ), 'Criar objecto da barreira.' );
		$anchors[]  = $concurrent->id;
		$repo->publish( $concurrent->id, 1, 'Publicar antes da barreira.' );
		arn_f112_concurrency( $db, $module, $concurrent->id, $roster, $current_id, $current_session, $now, $content( 'Revisão concorrente', Audience::unit( 'LAB-A' ) )->toArray(), $admin, $assert );
		$after_concurrent = $repo->find( $concurrent->id );
		$assert( 3 === $after_concurrent->revision && 2 === count( $after_concurrent->versions ) && 3 === count( $after_concurrent->history ), 'só uma revisão versão e transição concorrente' );
		$current_id      = 'communications' === $module ? $ids['author'] : $ids['events'];
		$current_session = $session( $current_id );
		$unit_table      = $prefix . 'arn_org_units';
		$header_table    = $prefix . 'arn_org_header';
		$unit_rows       = $db->get_results( $db->prepare( 'SELECT * FROM %i', $unit_table ), ARRAY_A );
		$unit_revision   = $units->snapshot()->revision;
		$db->query( $db->prepare( 'DELETE FROM %i', $unit_table ) );
		$db->query( $db->prepare( 'UPDATE %i SET revision=0 WHERE id=1', $header_table ) );
		try {
			$unready( static fn() => $actors->read() );
		} finally {
			foreach ( $unit_rows as $row ) {
				$assert( 1 === $db->insert( $unit_table, $row ), 'repor só linhas sintéticas da falha controlada' );
			}
			$db->query( $db->prepare( 'UPDATE %i SET revision=%d WHERE id=1', $header_table, $unit_revision ) );
		}
		$current_id      = $ids['a'];
		$current_session = $session( $current_id );
		$assert( $current_id === $actors->read()?->id(), 'controlo de sessão A positivo imediatamente antes de suspender' );
		$state = $auth->find( $ids['a'] );
		$service->changeState( $ids['a'], AccountState::Suspended, $state->revision, $context );
		$current_id = $ids['a'];
		$assert( null === $actors->read(), 'suspensão revalidada e sessão antiga inválida' );
		$db->query( $db->prepare( 'ALTER TABLE %i ADD unexpected int NULL', $schema->table( 'objects' ) ) );
		$unready( static fn() => $schema->assertReady() );
		$db->query( $db->prepare( 'ALTER TABLE %i DROP COLUMN unexpected', $schema->table( 'objects' ) ) );
		$schema->assertReady();
	} catch ( Throwable $error ) {
		$failure = $error;
	} finally {
		$db->query( 'ROLLBACK' );
		wp_set_current_user( $admin );
		foreach ( $anchors as $id ) {
			$post = get_post( $id );
			if ( $post instanceof WP_Post && in_array( $post->post_type, array( 'arn_comunicado', 'arn_evento' ), true ) && 'arn-f112:' . $prefix . ':' . $module === $post->post_excerpt ) {
				$GLOBALS['wpdb']->delete( $GLOBALS['wpdb']->posts, array( 'ID' => $id ) );
				clean_post_cache( $id );
			}
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $owned as $id => $login ) {
			$user = get_userdata( $id );
			if ( ! $user instanceof WP_User || $user->user_login !== $login || $user->user_email !== $login . '@example.invalid' || get_user_meta( $id, '_arn_f112_fixture', true ) !== $prefix ) {
				throw new RuntimeException( 'Identidade de limpeza F112 divergente.' );
			}
			wp_delete_user( $id );
		}
		$dropped_all = true;
		foreach ( array( 'arn_authz_grants', 'arn_authz_audit', 'arn_authz_accounts', 'arn_authz_schema', 'arn_org_header', 'arn_org_units', 'arn_org_audit', 'arn_comm_objects', 'arn_comm_versions', 'arn_comm_audit', 'arn_comm_notifications', 'arn_comm_schema', 'arn_calendar_objects', 'arn_calendar_versions', 'arn_calendar_audit', 'arn_calendar_notifications', 'arn_calendar_schema' ) as $suffix ) {
			$dropped     = $db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . $suffix ) );
			$dropped_all = false !== $dropped && '' === $db->last_error && $dropped_all;
		}
		$remaining = $db->get_col( $db->prepare( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s', $db->esc_like( $prefix ) . '%' ) );
		$assert( $dropped_all && array() === $remaining && '' === $db->last_error, 'nenhuma tabela do prefixo exclusivo fica por remover' );
		$assert( $before_posts === (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->posts ), 'limpeza conserva posts anteriores' );
		$normal_after = arn_f112_normal_snapshot();
		if ( $normal_before !== $normal_after ) {
			$deltas = array();
			foreach ( array_unique( array_merge( array_keys( $normal_before ), array_keys( $normal_after ) ) ) as $table ) {
				if ( ( $normal_before[ $table ] ?? null ) !== ( $normal_after[ $table ] ?? null ) ) {
					$deltas[] = $table;
				}
			}
			WP_CLI::log( 'Tabelas normais divergentes: ' . implode( ',', $deltas ) );
			$option_names = array();
			foreach ( array_unique( array_merge( array_keys( $normal_before['__options_by_name'] ?? array() ), array_keys( $normal_after['__options_by_name'] ?? array() ) ) ) as $name ) {
				if ( ( $normal_before['__options_by_name'][ $name ] ?? null ) !== ( $normal_after['__options_by_name'][ $name ] ?? null ) ) {
					$option_names[] = $name;
				}
			}
			WP_CLI::log( 'Opções divergentes, sem valores: ' . implode( ',', $option_names ) );
		}
		$assert( $normal_before === $normal_after, 'todas as linhas normais preservadas, incluindo cron e sessões' );
		$db->close();
	}
	if ( null !== $failure ) {
		throw $failure;
	}
	WP_CLI::success( 'F112 ' . $module . ': ' . $checks . ' verificações SQL e limpeza própria.' );
}
