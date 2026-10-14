<?php
/** Ensaio WordPress e MariaDB de sessões fictícias descartáveis.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Access\LocalFixtureAccess;
use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\LocalGrantPresets;
use Arn\Intranet\Authorization\Records\LocalRecordAccess;
use Arn\Intranet\Authorization\Records\LocalRecordSelection;
use Arn\Intranet\Authorization\Records\LocalRecordsPage;
use Arn\Intranet\Authorization\Session\LocalGrantManagementPage;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Session\LocalAccountStatePage;
use Arn\Intranet\Authorization\Session\WordPressLocalAccountSessions;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageAuthority;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageService;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio permitido apenas no laboratório por administrador.' );
}

$checks   = 0;
$assert   = static function ( bool $condition, string $label ) use ( &$checks ): void {
	if ( ! $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Rótulo estático do ensaio CLI.
		throw new RuntimeException( 'Falhou: ' . $label );
	}
	++$checks;
};
$actor    = get_current_user_id();
$prefix   = 'arn_sessions_' . bin2hex( random_bytes( 6 ) ) . '_';
$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$database->set_prefix( $prefix );
$database->suppress_errors( true );
$repository     = new WpdbAuthorizationRepository( $database );
$users          = array();
$saved_hooks    = array();
$saved_cookies  = $_COOKIE;
$cookie_capture = null;
$send_capture   = null;
$query_failure  = null;
$hook_names     = array( 'authenticate', 'determine_current_user', 'attach_session_information', 'send_auth_cookies', 'allow_password_reset', 'validate_password_reset', 'wp_is_application_passwords_available_for_user' );
try {
	// O bootstrap real tem de ligar a ferramenta, incluindo no CI com gates fechados.
	$page_registered    = false;
	$grants_registered  = false;
	$records_registered = false;
	foreach ( $GLOBALS['wp_filter']['wp_loaded']->callbacks ?? array() as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			$callback           = $entry['function'];
			$records_registered = $records_registered || ( is_array( $callback ) && $callback[0] instanceof LocalRecordsPage && 'register' === $callback[1] );
			$grants_registered  = $grants_registered || ( is_array( $callback ) && $callback[0] instanceof LocalGrantManagementPage && 'register' === $callback[1] );
			$page_registered    = $page_registered || ( is_array( $callback ) && $callback[0] instanceof LocalAccountStatePage && 'register' === $callback[1] );
		}
	}
	$assert( $page_registered, 'bootstrap wp_loaded da ferramenta' );
	$assert( $grants_registered, 'bootstrap wp_loaded das concessões' );
	$assert( $records_registered, 'bootstrap wp_loaded dos registos' );
	$assert( ! ( new LocalRecordAccess() )->allowed(), 'administrador sem fixture não recebe excepção para registos' );
	$password = wp_generate_password( 32 );
	foreach ( array( 'fixture', 'ordinary' ) as $kind ) {
		$fixture_id = wp_insert_user(
			array(
				'user_login' => $prefix . $kind,
				'user_email' => $prefix . $kind . '@example.test',
				'user_pass'  => $password,
				'role'       => 'subscriber',
			)
		);
		$assert( is_int( $fixture_id ), 'criação exclusiva ' . $kind );
		$users[ $kind ] = $fixture_id;
	}
	$fixture  = $users['fixture'];
	$ordinary = $users['ordinary'];
	update_user_meta( $fixture, LocalFixtureUser::META_KEY, '1' );
	$manager = WP_Session_Tokens::get_instance( $fixture );
	$assert( wp_authenticate( $prefix . 'fixture', $password ) instanceof WP_Error, 'bootstrap default fechado sem snapshot autorizado' );
	$assert( wp_authenticate( $prefix . 'ordinary', $password ) instanceof WP_User, 'conta comum preservada pelo bootstrap' );
	$assert( get_userdata( $actor ) instanceof WP_User && ! LocalFixtureAccess::isFixture( get_userdata( $actor ) ), 'administrador não é fixture' );

	// Composição interna do ensaio. Não altera flags, tabelas ou callbacks persistentes.
	foreach ( $hook_names as $hook ) {
		$saved_hooks[ $hook ] = isset( $GLOBALS['wp_filter'][ $hook ] ) ? clone $GLOBALS['wp_filter'][ $hook ] : null;
		foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks ?? array() as $priority => $callbacks ) {
			foreach ( $callbacks as $entry ) {
				$callback = $entry['function'];
				if ( is_array( $callback ) && ( $callback[0] instanceof WordPressLocalAccountSessions || $callback[0] instanceof LocalFixtureAccess ) ) {
					remove_filter( $hook, $callback, $priority );
				}
			}
		}
	}
	( new LocalFixtureAccess( 'local', true ) )->register();
	$enabled = true;
	$guard   = new WordPressLocalAccountSessions(
		static fn( int $fixture_id ) => $repository->find( $fixture_id ),
		static function () use ( &$enabled ): bool {
			return $enabled;
		}
	);
	$guard->register();
	$cookie         = '';
	$sent           = false;
	$cookie_capture = static function ( string $value ) use ( &$cookie ): void {
		$cookie = $value;
	};
	$send_capture   = static function ( bool $send ) use ( &$sent ): bool {
		$sent = $send;
		return $send;
	};
	add_action( 'set_logged_in_cookie', $cookie_capture );
	add_filter( 'send_auth_cookies', $send_capture, PHP_INT_MAX );
	$issue     = static function ( int $fixture_id ) use ( &$cookie, &$sent, $assert ): array {
		$cookie = '';
		$sent   = false;
		wp_set_auth_cookie( $fixture_id, false, false );
		$parsed = wp_parse_auth_cookie( $cookie, 'logged_in' );
		$assert( is_array( $parsed ), 'cookie assinado real' );
		return array( $cookie, $parsed['token'], $sent );
	};
	$resolve   = static function ( string $value ): int|false {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $value;
		unset( $_COOKIE[ AUTH_COOKIE ], $_COOKIE[ SECURE_AUTH_COOKIE ] );
		return apply_filters( 'determine_current_user', false );
	};
	$authority = new class( $actor, $fixture ) implements AuthorizationStorageAuthority {
		/** Fronteira do ensaio local.
		 *
		 * @param int $actor Actor exclusivo.
		 * @param int $fixture Alvo exclusivo.
		 */
		public function __construct( private int $actor, private int $fixture ) {}
		/** Fronteira do ensaio local.
		 *
		 * @param int $actor_id Actor.
		 */
		public function canManage( int $actor_id ): bool {
			return $actor_id === $this->actor;
		}
		/** Fronteira do ensaio local.
		 *
		 * @param int $user_id Alvo.
		 */
		public function canUseAccount( int $user_id ): bool {
			return $user_id === $this->fixture;
		}
	};
	$service   = new AuthorizationStorageService( $repository, $authority );
	$context   = new ChangeContext( $actor, 'Ensaio descartável de sessões', 'LAB-ACCOUNT-STATE', time() );
	$service->installSchema( $context );
	$assert( wp_authenticate( $prefix . 'fixture', $password ) instanceof WP_Error, 'ausência recusa login' );
	$service->createAccount( $fixture, $context );
	$assert( wp_authenticate( $prefix . 'fixture', $password ) instanceof WP_Error, 'Pending recusa login' );
	$service->changeState( $fixture, AccountState::Active, 1, $context );
	$assert( wp_authenticate( $prefix . 'fixture', $password ) instanceof WP_User, 'Active permite login real' );
	[$old_cookie, $old_token, $allowed] = $issue( $fixture );
	$assert( $allowed, 'envio Active permitido' );
	$metadata = $manager->get( $old_token );
	$assert( true === $metadata['arn_authz_local'] && $fixture === $metadata['arn_authz_user_id'] && 2 === ( $metadata['arn_authz_revision'] ), 'metadados nativos exactos' );
	$assert( wp_validate_auth_cookie( $old_cookie, 'logged_in' ) === $fixture, 'core valida assinatura/token' );
	$assert( $fixture === $resolve( $old_cookie ), 'pipeline resolve Active' );
	$assert( 2 === count( $repository->history( $fixture ) ), 'leituras não auditam' );
	[$ordinary_cookie, $ordinary_token, $allowed] = $issue( $ordinary );
	$assert( $allowed && $ordinary === $resolve( $ordinary_cookie ), 'sessão legítima emitida' );
	$assert( ! isset( WP_Session_Tokens::get_instance( $ordinary )->get( $ordinary_token )['arn_authz_local'] ), 'conta legítima sem marca interna' );

	$service->changeState( $fixture, AccountState::Suspended, 2, $context );
	$assert( 3 === $repository->find( $fixture )->revision && 'state_changed' === $repository->history( $fixture )[0]['event'], 'suspensão auditada' );
	$assert( false === $resolve( $old_cookie ) && ! $manager->verify( $old_token ), 'cookie suspenso recusado e destruído' );
	$assert( wp_authenticate( $prefix . 'fixture', $password ) instanceof WP_Error, 'login suspenso recusado' );
	$assert( false === apply_filters( 'allow_password_reset', true, $fixture ), 'recuperação suspensa recusada' );
	$reset_errors = new WP_Error();
	do_action( 'validate_password_reset', $reset_errors, get_userdata( $fixture ) );
	$assert( $reset_errors->has_errors(), 'chave já emitida recusada' );
	$assert( false === apply_filters( 'wp_is_application_passwords_available_for_user', true, get_userdata( $fixture ) ), 'application passwords fechadas' );
	[, $denied_token, $allowed] = $issue( $fixture );
	$assert( ! $allowed && ! $manager->verify( $denied_token ), 'suspenso não emite cookie utilizável' );
	$service->changeState( $fixture, AccountState::Active, 3, $context );
	$assert( false === $resolve( $old_cookie ), 'cookie destruído não ressuscita' );
	$assert( wp_authenticate( $prefix . 'fixture', $password ) instanceof WP_User, 'novo login após reactivação' );
	[$unused_cookie, $unused_token, $allowed] = $issue( $fixture );
	$assert( $allowed, 'nova geração emitida' );
	$service->changeState( $fixture, AccountState::Suspended, 4, $context );
	$service->changeState( $fixture, AccountState::Active, 5, $context );
	[$fresh_cookie, $fresh_token, $allowed] = $issue( $fixture );
	$assert( $allowed && false === $resolve( $unused_cookie ), 'cookie não usado durante suspensão não ressuscita' );
	$assert( ! $manager->verify( $unused_token ) && $manager->verify( $fresh_token ) && $fixture === $resolve( $fresh_cookie ), 'geração antiga remove só token antigo' );
	$service->createGrant( new Grant( $fixture, Role::Employee, Scope::own(), 0, null ), 6, $context );
	$assert( false === $resolve( $fresh_cookie ), 'concessão altera geração' );
	$assert( 7 === count( $repository->history( $fixture ) ), 'auditoria apenas de sete alterações' );
	$conflict = false;
	try {
		$service->changeState( $fixture, AccountState::Suspended, 6, $context );
	} catch ( RuntimeException ) {
		$conflict = true;
	}
	$assert( $conflict && 7 === $repository->find( $fixture )->revision && AccountState::Active === $repository->find( $fixture )->state && 7 === count( $repository->history( $fixture ) ), 'CAS antigo não altera estado nem auditoria' );

	// Os presets novos percorrem o serviço auditado e o adaptador reais sem activar flags.
	$grant_context           = new ChangeContext( $actor, 'Ensaio descartável de concessões', 'LAB-GRANT-MANAGEMENT', time() );
	$own_id                  = $repository->find( $fixture )->stored_grants[0]->id;
	[$before_replace_cookie] = $issue( $fixture );
	$unit_id                 = $service->replaceGrant( $fixture, $own_id, LocalGrantPresets::grant( 'unit-manager-a', $fixture, time() ), 7, $grant_context );
	$replaced                = $repository->find( $fixture );
	$assert( 8 === $replaced->revision && 2 === count( $replaced->stored_grants ) && $replaced->stored_grants[0]->grant->revoked && ! $replaced->stored_grants[1]->grant->revoked, 'substituição atómica preserva histórico revogado' );
	$assert( false === $resolve( $before_replace_cookie ), 'substituição invalida sessão anterior' );
	$assert( 'grant_replaced' === $repository->history( $fixture )[0]['event'] && 'LAB-GRANT-MANAGEMENT' === $repository->history( $fixture )[0]['decision_ref'], 'substituição auditada com referência fixa' );
	$before_replay = $repository->history( $fixture );
	$replay_denied = false;
	try {
		$service->replaceGrant( $fixture, $own_id, LocalGrantPresets::grant( 'unit-manager-a', $fixture, time() ), 7, $grant_context );
	} catch ( RuntimeException ) {
		$replay_denied = true;
	}
	$assert( $replay_denied && $before_replay === $repository->history( $fixture ) && 8 === $repository->find( $fixture )->revision && 2 === count( $repository->find( $fixture )->stored_grants ), 'replay é recusado sem mudanças parciais' );
	[$before_revoke_cookie] = $issue( $fixture );
	$service->revokeGrant( $fixture, $unit_id, 8, $grant_context );
	$assert( 9 === $repository->find( $fixture )->revision && $repository->find( $fixture )->stored_grants[1]->grant->revoked, 'revogação guarda registo e revisão' );
	$assert( false === $resolve( $before_revoke_cookie ), 'revogação invalida sessão anterior' );
	$active_own = $service->createGrant( LocalGrantPresets::grant( 'employee-own', $fixture, time() ), 9, $grant_context );
	$assert( 10 === $repository->find( $fixture )->revision, 'criação por preset auditada' );
	$service->changeState( $fixture, AccountState::Suspended, 10, $context );
	foreach ( array( 'create', 'replace' ) as $operation ) {
		$denied = false;
		try {
			$grant = LocalGrantPresets::grant( 'employee-own', $fixture, time() );
			if ( 'create' === $operation ) {
				$service->createGrant( $grant, 11, $grant_context );
			} else {
				$service->replaceGrant( $fixture, $active_own, $grant, 11, $grant_context );
			}
		} catch ( RuntimeException ) {
			$denied = true;
		}
		$assert( $denied && 11 === $repository->find( $fixture )->revision && 11 === count( $repository->history( $fixture ) ), 'Suspended recusa ' . $operation . ' sem escrita parcial' );
	}
	$service->revokeGrant( $fixture, $active_own, 11, $grant_context );
	$assert( 12 === $repository->find( $fixture )->revision && AccountState::Suspended === $repository->find( $fixture )->state, 'Suspended permite revogação' );
	$service->changeState( $fixture, AccountState::Active, 12, $context );
	$service->createGrant( LocalGrantPresets::grant( 'employee-own', $fixture, time() ), 13, $grant_context );
	$assert( wp_authenticate( $prefix . 'fixture', $password ) instanceof WP_User, 'novo login funciona após mudanças de concessão' );
	$assert( array( 'subscriber' ) === get_userdata( $fixture )->roles && array( 'subscriber' ) === get_userdata( $ordinary )->roles && null === $repository->find( $ordinary ), 'concessões não alteram papéis nem persistência da conta legítima' );

	// A composição interna só resolve esta fixture exclusiva, sem ligar flags no CI.
	$records = new LocalRecordAccess(
		static fn() => $repository,
		static fn(): int => get_current_user_id() === $fixture && '1' === get_user_meta( $fixture, LocalFixtureUser::META_KEY, true ) ? $fixture : 0
	);
	wp_set_current_user( $fixture );
	[$records_cookie] = $issue( $fixture );
	$resolve( $records_cookie );
	$history_before = count( $repository->history( $fixture ) );
	$own_rows       = $records->read();
	$assert( array( 'proprio' ) === array_keys( $own_rows ), 'snapshot real filtra lista própria antes da contagem' );
	$assert( null === LocalRecordSelection::detail( $own_rows, 'unidade-a' ) && null === LocalRecordSelection::detail( $own_rows, 'unidade-b' ) && null === LocalRecordSelection::detail( $own_rows, array( 'proprio' ) ), 'detalhes recusados e malformados não devolvem dados' );
	$records_unit = $service->createGrant( LocalGrantPresets::grant( 'unit-manager-a', $fixture, time() ), 14, $grant_context );
	$assert( null === $records->read(), 'leitura de registos recusa geração anterior à concessão' );
	[$records_cookie] = $issue( $fixture );
	$resolve( $records_cookie );
	$unit_rows = $records->read();
	$assert( array( 'proprio', 'unidade-a' ) === array_keys( $unit_rows ) && 2 === count( $unit_rows ), 'nova sessão lê próprio e A pelo motor real' );
	$assert( null !== LocalRecordSelection::detail( $unit_rows, 'unidade-a' ) && null === LocalRecordSelection::detail( $unit_rows, 'unidade-b' ), 'detalhe A permitido e B recusado' );
	$assert( 'grant_created' === $repository->history( $fixture )[0]['event'], 'concessão de leitura auditada' );
	$service->revokeGrant( $fixture, $records_unit, 15, $grant_context );
	$assert( null === $records->read(), 'revogação recusa a sessão corrente antiga' );
	[$records_cookie] = $issue( $fixture );
	$resolve( $records_cookie );
	$assert( array( 'proprio' ) === array_keys( $records->read() ) && null === LocalRecordSelection::detail( $records->read(), 'unidade-a' ), 'nova sessão após revogação conserva apenas próprio' );
	$assert( count( $repository->history( $fixture ) ) === $history_before + 2 && 'grant_revoked' === $repository->history( $fixture )[0]['event'], 'leituras sem eventos de mutação e histórico preservado' );
	$assert( AccountState::Active === $repository->find( $fixture )->state && array( 'subscriber' ) === get_userdata( $fixture )->roles, 'ensaio mantém conta activa e papel nativo' );
	wp_set_current_user( $actor );
	$assert( null === $records->read(), 'composição exclusiva também exclui administrador corrente' );

	[$failure_cookie, $failure_token] = $issue( $fixture );
	$query_failure                    = static function ( string $query ) use ( $prefix ): string {
		return str_contains( $query, $prefix . 'arn_authz_accounts' ) ? 'INVALID SESSION FIXTURE QUERY' : $query;
	};
	add_filter( 'query', $query_failure );
	wp_set_current_user( $fixture );
	$assert( null === $records->read(), 'falha SQL real não revela registos' );
	wp_set_current_user( $actor );
	$assert( false === $resolve( $failure_cookie ) && ! $manager->verify( $failure_token ), 'falha de leitura fecha e revoga' );
	$assert( wp_authenticate( $prefix . 'fixture', $password ) instanceof WP_Error, 'falha de leitura recusa login' );
	$assert( $ordinary === $resolve( $ordinary_cookie ), 'conta legítima não consulta armazenamento em falha' );
	remove_filter( 'query', $query_failure );
	$query_failure              = null;
	[$gate_cookie, $gate_token] = $issue( $fixture );
	$enabled                    = false;
	$assert( false === $resolve( $gate_cookie ) && ! $manager->verify( $gate_token ), 'gate desligado revoga sessões' );
	$enabled                    = true;
	[$mark_cookie, $mark_token] = $issue( $fixture );
	delete_user_meta( $fixture, LocalFixtureUser::META_KEY );
	$assert( false === $resolve( $mark_cookie ) && ! $manager->verify( $mark_token ), 'marca removida não contorna sessão identificada' );
	$assert( $ordinary === $resolve( $ordinary_cookie ) && WP_Session_Tokens::get_instance( $ordinary )->verify( $ordinary_token ), 'conta legítima permanece intacta' );
	$assert( true === apply_filters( 'send_auth_cookies', true, 0, 0, 0, '', '' ), 'limpeza de cookies permitida' );
	WP_CLI::success( 'Sessões locais reais: ' . $checks . ' asserções.' );
} finally {
	if ( null !== $query_failure ) {
		remove_filter( 'query', $query_failure );
	}
	if ( null !== $cookie_capture ) {
		remove_action( 'set_logged_in_cookie', $cookie_capture );
	}
	if ( null !== $send_capture ) {
		remove_filter( 'send_auth_cookies', $send_capture, PHP_INT_MAX );
	}
	foreach ( $saved_hooks as $hook => $saved ) {
		if ( null === $saved ) {
			unset( $GLOBALS['wp_filter'][ $hook ] );
		} else {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restaura exactamente os hooks capturados antes da composição interna do ensaio.
			$GLOBALS['wp_filter'][ $hook ] = $saved;
		}
	}
	$_COOKIE = $saved_cookies;
	wp_set_current_user( $actor );
	foreach ( array( 'audit', 'grants', 'accounts', 'schema' ) as $suffix ) {
		$database->query( $database->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_authz_' . $suffix ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $fixture_id ) {
		wp_delete_user( $fixture_id );
	}
	$database->close();
}
