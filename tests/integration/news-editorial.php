<?php
/** Exclusive real SQL editorial and historical migration checks.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\Documents\PrivateFiles\FileRecord;
use Arn\Intranet\Documents\PrivateFiles\FileSchema;
use Arn\Intranet\Documents\PrivateFiles\WpdbFileRegistry;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\News\Asset;
use Arn\Intranet\News\Audience;
use Arn\Intranet\News\Content;
use Arn\Intranet\News\CurrentNewsActor;
use Arn\Intranet\News\LocalNewsroom;
use Arn\Intranet\News\NewsPublishedPortal;
use Arn\Intranet\News\NewsSchema;
use Arn\Intranet\News\NewsState;
use Arn\Intranet\News\Version;
use Arn\Intranet\News\WpdbNewsAuthority;
use Arn\Intranet\News\WpdbNewsRepository;
use Arn\Intranet\Tests\Fixtures\LegacyNewsSchema;
use Arn\Intranet\Tests\Fixtures\EditorialWorker;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions
// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
// phpcs:disable WordPress.PHP.YodaConditions.NotYoda

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
require_once __DIR__ . '/fixtures/LegacyNewsSchema.php';
require_once __DIR__ . '/fixtures/EditorialWorker.php';
$diagnostic_fault = getenv( 'ARN_EDITORIAL_DIAGNOSTIC_FAULT' );
$diagnostic_fault = is_string( $diagnostic_fault ) && '' !== $diagnostic_fault ? $diagnostic_fault : 'none';
if ( ! in_array( $diagnostic_fault, array( 'none', 'early-worker', 'observer-query' ), true ) ) {
	throw new RuntimeException( 'Cenário de diagnóstico desconhecido.' );
}
$checks  = 0;
$assert  = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Falhou: ' . $label );
	}
	++$checks;
};
$refuses = static function ( callable $call ) use ( $assert ): void {
	$failed = false;
	try {
		$call();
	} catch ( RuntimeException | InvalidArgumentException ) {
		$failed = true;
	}
	$assert( $failed, 'operação inválida recusada' );
};
$prefix  = 'arn_ed_' . bin2hex( random_bytes( 5 ) ) . '_';
$db      = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$reader = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$reader->set_prefix( $prefix );
$reader->suppress_errors( true );
$lock_observer = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$lock_observer->set_prefix( $prefix );
$lock_observer->suppress_errors( true );
$schema = new NewsSchema( $db );
$files  = new FileSchema( $db );
$users  = array();
$now    = time();
try {
	$db->query( $db->prepare( 'CREATE TABLE %i LIKE %i', $schema->table( 'posts' ), $GLOBALS['wpdb']->posts ) );
	$files->install();
	$authz = new WpdbAuthorizationRepository( $db );
	$authz->installSchema();
	( new LegacyNewsSchema( $db ) )->install();
	foreach ( array( 'author', 'editor', 'reader', 'outside' ) as $name ) {
		$id = wp_insert_user(
			array(
				'user_login' => $prefix . $name,
				'user_pass'  => wp_generate_password( 48 ),
				'role'       => 'subscriber',
			)
		);
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( 'Fixture exclusiva indisponível.' );
		}
		$users[ $name ] = (int) $id;
	}
	$author  = $users['author'];
	$editor  = $users['editor'];
	$scope   = Scope::queues( array( 'editorial-ficticia' ) );
	$context = static fn(): ChangeContext => new ChangeContext( $author, 'Ensaio exclusivo', 'LAB-EDITORIAL', time() );
	foreach ( $users as $name => $id ) {
		$authz->createAccount( $id, $context() );
		$authz->changeState( $id, AccountState::Active, 1, $context() );
		$grant_ids[ $name ] = $authz->createGrant( new Grant( $id, 'editor' === $name ? Role::Editorial : Role::Employee, 'editor' === $name ? $scope : Scope::own(), 0, null ), 2, $context() );
	}
	$authz->createGrant( new Grant( $editor, Role::Editorial, Scope::audiences( array( 'news_all', 'news_unit_LAB-A' ) ), 0, null ), 3, $context() );
	$active                 = $author;
	$generations            = array_fill_keys( array_values( $users ), 3 );
	$generations[ $editor ] = 4;
	$participants           = array(
		$author           => array( 'LAB-A' ),
		$editor           => array(),
		$users['reader']  => array( 'LAB-A' ),
		$users['outside'] => array( 'LAB-B' ),
	);
	$current                = new CurrentNewsActor(
		static fn() => new WpdbAuthorizationRepository( $reader ),
		static function () use ( &$active ): int {
			return $active;
		},
		static function ( int $id ) use ( &$generations ): array {
			return array(
				'arn_authz_local'    => true,
				'arn_authz_user_id'  => $id,
				'arn_authz_revision' => $generations[ $id ] ?? 0,
			);
		},
		static fn(): array => array( $editor ),
		static function () use ( &$participants ): array {
			return $participants; }
	);
	$authority              = new WpdbNewsAuthority(
		$db,
		$current,
		null,
		static function () use ( &$participants ): array {
			return $participants;
		}
	);
	$repo                   = new WpdbNewsRepository( $db, $authority );
	$room                   = new LocalNewsroom( $current, static fn(): array => array( $repo, new WpdbFileRegistry( $db ) ), null, static fn(): bool => true );
	// Historical DDL and rows never depend on today's installer or aggregate writes.
	foreach ( array(
		1 => false,
		2 => true,
	) as $id => $pending ) {
		$db->query( $db->prepare( "INSERT INTO %i (ID,post_author,post_date,post_date_gmt,post_content,post_title,post_status,post_type,post_name,post_modified,post_modified_gmt) VALUES (%d,%d,'2026-10-14','2026-10-14','','Notícia privada','private','arn_noticia','','2026-10-14','2026-10-14')", $schema->table( 'posts' ), $id, $author ) );
		$db->query( $db->prepare( 'INSERT INTO %i (id,author_id,review_queue,revision,created_at) VALUES (%d,%d,%s,%d,%d)', $schema->table( 'news' ), $id, $author, 'editorial-ficticia', $pending ? 2 : 1, $now ) );
		$db->query( $db->prepare( 'INSERT INTO %i (article_id,number,title,body,category,actor_id,created_at) VALUES (%d,1,%s,%s,%s,%d,%d)', $schema->table( 'versions' ), $id, 'Histórico', 'Texto histórico', 'nota-interna', $author, $now ) );
		$blob = new BlobDescriptor( bin2hex( random_bytes( 16 ) ), hash( 'sha256', 'historical-binary' ), 17, 'image/png' );
		$db->query( 'START TRANSACTION' );
		$db->query( $db->prepare( "INSERT INTO %i (article_id,version,kind,position,blob_id,sha256,size_bytes,mime,description) VALUES (%d,1,'featured',0,%s,%s,17,'image/png','Histórico')", $schema->table( 'assets' ), $id, $blob->id, $blob->sha256 ) );
		( new WpdbFileRegistry( $db ) )->registerInTransaction( new FileRecord( $blob, 'news_asset', (int) $db->insert_id, 1, 'original', $author, $now ) );
		$db->query( 'COMMIT' );
		$db->query( $db->prepare( "INSERT INTO %i (article_id,revision,event,actor_id,reason,occurred_at) VALUES (%d,1,'created',%d,'Criação histórica',%d)", $schema->table( 'audit' ), $id, $author, $now ) );
		if ( $pending ) {
			$db->query( $db->prepare( 'UPDATE %i SET submitted_version=1,submitted_at=%d WHERE id=%d', $schema->table( 'news' ), $now, $id ) );
			$db->query( $db->prepare( "INSERT INTO %i (article_id,revision,event,actor_id,reason,occurred_at) VALUES (%d,2,'submitted',%d,'Submissão histórica',%d)", $schema->table( 'audit' ), $id, $author, $now ) );
			$db->query( $db->prepare( 'INSERT INTO %i (article_id,version,recipient_id,created_at) VALUES (%d,1,%d,%d)', $schema->table( 'notifications' ), $id, $editor, $now ) );
		}
	}
	$columns = array();
	foreach ( array( 'news', 'versions', 'assets', 'audit', 'notifications' ) as $name ) {
		$columns[ $schema->table( $name ) ] = array_map( static fn( object $row ): string => $row->Field, $db->get_results( $db->prepare( 'SHOW COLUMNS FROM %i', $schema->table( $name ) ) ) );
	}
	$original = static function () use ( $db, $columns, $files, $schema ): string {
		$rows = array();
		foreach ( $columns as $table => $names ) {
			$rows[] = $db->get_results( 'SELECT `' . implode( '`,`', $names ) . '` FROM `' . $table . '` ORDER BY 1,2' );
		}
		foreach ( array( $files->table( 'files' ), $files->table( 'audit' ), $schema->table( 'posts' ) ) as $table ) {
			$rows[] = $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY 1', $table ) );
		}
		return hash( 'sha256', wp_json_encode( $rows ) );
	};
	$before   = $original();
	$refuses( static fn() => $schema->install() );
	$refuses( static fn() => $repo->find( 1 ) );
	$refuses( static fn() => $repo->notificationRows( $editor ) );
	$schema->assertVersion( 1 );
	$db->query( $db->prepare( 'ALTER TABLE %i ADD unexpected INT NULL', $schema->table( 'versions' ) ) );
	$refuses( static fn() => $schema->upgrade( $author, $now ) );
	$db->query( $db->prepare( 'ALTER TABLE %i DROP COLUMN unexpected', $schema->table( 'versions' ) ) );
	$db->query( $db->prepare( "UPDATE %i SET event='approved' WHERE article_id=2 AND revision=2", $schema->table( 'audit' ) ) );
	$refuses( static fn() => $schema->upgrade( $author, $now ) );
	$db->query( $db->prepare( "UPDATE %i SET event='submitted' WHERE article_id=2 AND revision=2", $schema->table( 'audit' ) ) );
	// Each historical inconsistency must stop the upgrade before its first DDL step.
	foreach ( array(
		array( 'news', 'submitted_at', 2, $now + 1, $now ),
		array( 'versions', 'actor_id', 1, $editor, $author ),
		array( 'notifications', 'version', 1, 99, 1 ),
		array( 'audit', 'revision', 1, 3, 1 ),
		array( 'posts', 'post_author', 1, $editor, $author ),
		array( 'assets', 'version', 1, 99, 1 ),
	) as $case ) {
		[ $table, $column, $id, $invalid, $valid ] = $case;
		$key                                       = 'versions' === $table ? 'article_id' : ( 'posts' === $table ? 'ID' : 'id' );
		$db->query( $db->prepare( 'UPDATE %i SET ' . $column . '=%d WHERE ' . $key . '=%d', $schema->table( $table ), $invalid, $id ) );
		try {
			$refuses( static fn() => $schema->upgrade( $author, $now ) );
		} finally {
			$db->query( $db->prepare( 'UPDATE %i SET ' . $column . '=%d WHERE ' . $key . '=%d', $schema->table( $table ), $valid, $id ) );
		}
		$schema->assertLayout( 0 );
	}
	$assert( $before === $original(), 'recusa de histórico corrupto não altera os originais' );
	$filter = static fn( string $sql ): string => str_starts_with( $sql, 'ALTER TABLE `' . $schema->table( 'audit' ) . '` ADD version' ) ? 'INVALID EXCLUSIVE MIGRATION' : $sql;
	add_filter( 'query', $filter );
	try {
		$refuses( static fn() => $schema->upgrade( $author, $now ) );
	} finally {
		remove_filter( 'query', $filter );
	}
	$schema->assertVersion( 1 );
	$schema->assertLayout( 1 );
	$assert( $before === $original(), 'DDL parcial conserva dados originais' );
	$refuses( static fn() => $schema->assertReady() );
	$filter = static fn( string $sql ): string => str_starts_with( $sql, 'INSERT INTO `' . $schema->table( 'schema_history' ) . '`' ) ? 'INVALID EXCLUSIVE HISTORY' : $sql;
	add_filter( 'query', $filter );
	try {
		$refuses( static fn() => $schema->upgrade( $author, $now ) );
	} finally {
		remove_filter( 'query', $filter );
	}
	$schema->assertVersion( 1 );
	$schema->assertLayout( 6 );
	$assert( $before === $original() && '0' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $schema->table( 'schema_history' ) ) ), 'falha tardia mantém marcador e histórico técnico vazio' );
	$result = $schema->upgrade( $author, $now );
	$assert( 1 === $result['from'] && 2 === $result['to'] && 2 === $result['articles'] && 3 === $result['audit_events'] && 1 === $result['notifications'], 'migração conta histórico exacto' );
	$assert( $before === $original(), 'migração conserva todas as colunas originais e registos de ficheiros' );
	$history_before = wp_json_encode( $db->get_results( $db->prepare( 'SELECT * FROM %i', $schema->table( 'schema_history' ) ) ) );
	$repeat         = $schema->upgrade( $editor, $now + 5 );
	$assert( 2 === $repeat['from'] && $history_before === wp_json_encode( $db->get_results( $db->prepare( 'SELECT * FROM %i', $schema->table( 'schema_history' ) ) ) ) && $original() === $before, 'repetição não altera dados nem auditoria técnica' );
	$assert( null === $repo->find( 2 )->version( 1 )->content->audience && NewsState::Pending === $repo->find( 2 )->state, 'Pending legado com audiência NULL' );
	$active = $editor;
	$refuses( static fn() => $repo->decide( 2, 'approve', 1, 2, 'Sem audiência' ) );
	$assert( NewsState::Draft === $repo->decide( 2, 'return', 1, 2, 'Escolher audiência' )->state, 'devolver legado sem audiência' );
	$legacy = $repo->find( 2 );
	$assert( 'Escolher audiência' === $legacy->lastDecision()->reason && 1 === $legacy->submitted_version, 'hidratação conserva motivo e última submissão' );
	$asset   = static fn(): Asset => new Asset( 'featured', new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'a', 64 ), 12, 'image/png' ), 'Destaque exclusivo' );
	$content = static fn(): Content => new Content( 'Editorial exclusivo', 'Texto editorial', 'nota-interna', $asset(), array(), array(), Audience::units( array( 'LAB-A' ) ) );
	$active  = $author;
	$article = $repo->create( $author, $scope, new Version( 1, $content(), $author, time() ), 'Criar com audiência' );
	$assert( wp_json_encode( $article ) === wp_json_encode( $repo->find( $article->id ) ), 'criação hidrata história e audiência sem divergência' );
	$article = $repo->submit( $article->id, 1, 'Submissão explícita' );
	$assert( wp_json_encode( $article ) === wp_json_encode( $repo->find( $article->id ) ), 'submissão hidrata história exacta' );
	$active = $editor;
	$closed = new WpdbNewsRepository( $db, new WpdbNewsAuthority( $db, $current ) );
	$refuses( static fn() => $closed->decide( $article->id, 'approve', 1, 2, 'Sem roster' ) );
	$refuses( static fn() => $repo->decide( $article->id, 'approve', 2, 2, 'Versão incorrecta' ) );
	$all_rows = static function () use ( $db, $schema, $files ): string {
		$state = array();
		foreach ( array( $schema->table( 'posts' ), $schema->table( 'news' ), $schema->table( 'versions' ), $schema->table( 'assets' ), $schema->table( 'audit' ), $schema->table( 'notifications' ), $files->table( 'files' ), $files->table( 'audit' ) ) as $table ) {
			$state[] = $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY 1,2', $table ) );
		}
		return wp_json_encode( $state );
	};
	foreach ( array( 'audit', 'notifications' ) as $failure ) {
		$before_rows    = $all_rows();
		$before_article = wp_json_encode( $repo->find( $article->id ) );
		$filter         = static fn( string $sql ): string => str_starts_with( $sql, 'INSERT INTO `' . $schema->table( $failure ) . '`' ) ? 'INVALID EXCLUSIVE DECISION' : $sql;
		add_filter( 'query', $filter );
		try {
			$refuses( static fn() => $repo->decide( $article->id, 'approve', 1, 2, 'Falha tardia' ) );
		} finally {
			remove_filter( 'query', $filter );
		}
		$assert( $before_rows === $all_rows() && $before_article === wp_json_encode( $repo->find( $article->id ) ), 'decisão reverte oito tabelas e agregado após falha tardia' );
	}
	$article = $repo->decide( $article->id, 'approve', 1, 2, 'Aprovar versão um' );
	$assert( NewsState::Published === $article->state && 1 === $article->published_version && $editor === $article->approved_by && wp_json_encode( $article ) === wp_json_encode( $repo->find( $article->id ) ), 'aprovação atómica e hidratação exacta' );
	$author_notices = array_values( array_filter( $repo->notificationRows( $author ), static fn( object $notice ): bool => (int) $notice->article_id === $article->id ) );
	$assert( 1 === count( $author_notices ) && 'approved' === $author_notices[0]->kind && 3 === (int) $author_notices[0]->revision, 'autor recebe um único approved' );
	$reader_notice = $repo->notificationRows( $users['reader'] )[0];
	$assert( 'publication' === $reader_notice->kind && array() === $repo->notificationRows( $users['outside'] ), 'audiência filtra entrega actual' );
	$active    = $users['reader'];
	$published = NewsPublishedPortal::model( array(), $room );
	$assert( 1 === $published['total'] && 'Editorial exclusivo' === $published['items'][0]['title'] && 1 === $published['counts']['nota-interna'], 'portal conta apenas publicações aprovadas autorizadas' );
	$assert( 'Editorial exclusivo' === NewsPublishedPortal::detail( $article->id, $room )['title'], 'detalhe publicado usa runtime autorizado' );
	$active = $users['outside'];
	$assert( 0 === NewsPublishedPortal::model( array(), $room )['total'] && null === NewsPublishedPortal::detail( $article->id, $room ), 'fora da audiência não recebe lista nem detalhe' );
	$active = $editor;
	$assert( 0 === NewsPublishedPortal::model( array(), $room )['total'] && null === NewsPublishedPortal::detail( $article->id, $room ), 'editor não tem excepção na leitura publicada' );
	$active = $users['reader'];
	$repo->markRead( (int) $reader_notice->id, time() );
	$read_at = $repo->notificationRows( $active )[0]->read_at;
	$repo->markRead( (int) $reader_notice->id, time() + 20 );
	$assert( $read_at === $repo->notificationRows( $active )[0]->read_at && 3 === $repo->find( $article->id )->revision, 'recibo idempotente não altera notícia' );
	$participants[ $active ] = array( 'LAB-B' );
	$refuses( static fn() => $repo->markRead( (int) $reader_notice->id, time() ) );
	$participants[ $active ] = array( 'LAB-A' );
	$active                  = $author;
	$private_content         = new Content( 'Versão reservada', 'Corpo reservado', 'circular', $asset(), array(), array(), Audience::units( array( 'LAB-B' ) ) );
	$article                 = $repo->revise( $article->id, new Version( 2, $private_content, $author, time() ), 3, 'Nova versão privada' );
	$assert( 1 === $article->published_version && 1 === $article->submitted_version && wp_json_encode( $article ) === wp_json_encode( $repo->find( $article->id ) ), 'revisão conserva publicação e submissão anteriores' );
	$active = $users['reader'];
	$assert( 'Editorial exclusivo' === NewsPublishedPortal::detail( $article->id, $room )['title'] && 0 === NewsPublishedPortal::model( array( 'q' => 'reservada' ), $room )['total'], 'rascunho e audiência posterior não alteram publicação nem pesquisa' );
	$active = $users['outside'];
	$assert( null === NewsPublishedPortal::detail( $article->id, $room ), 'audiência privada posterior não concede leitura antecipada' );
	$active  = $author;
	$article = $repo->submit( $article->id, 4, 'Submeter versão dois' );
	$active  = $editor;
	$article = $repo->decide( $article->id, 'reject', 2, 5, 'Corrigir texto' );
	$assert( NewsState::Rejected === $article->state && 1 === $article->published_version && 'Corrigir texto' === $repo->find( $article->id )->lastDecision()->reason, 'rejeição conserva publicação anterior e motivo' );
	$active  = $author;
	$article = $repo->revise( $article->id, new Version( 3, $content(), $author, time() ), 6, 'Texto corrigido' );
	$article = $repo->submit( $article->id, 7, 'Reenviar' );
	$active  = $editor;
	$article = $repo->decide( $article->id, 'approve', 3, 8, 'Versão corrigida aprovada' );
	$active  = $users['reader'];
	$refuses( static fn() => $repo->markRead( (int) $reader_notice->id, time() ) );
	$active = $editor;
	$authz->changeState( $author, AccountState::Suspended, 3, $context() );
	$article        = $repo->decide( $article->id, 'archive', 3, 9, 'Retirar publicação' );
	$author_notices = $repo->notificationRows( $author );
	$assert( NewsState::Archived === $article->state && 'archived' === $author_notices[0]->kind && 3 === count( $repo->find( $article->id )->versions ), 'arquivo conserva versões e entrega aviso ao autor suspenso' );
	$active = $users['reader'];
	$assert( null === NewsPublishedPortal::detail( $article->id, $room ) && 0 === NewsPublishedPortal::model( array(), $room )['total'], 'arquivo retira lista e detalhe sem apagar versões' );
	$active = $author;
	$refuses( static fn() => $repo->markRead( (int) $author_notices[0]->id, time() ) );
	$authz->changeState( $author, AccountState::Active, 4, $context() );
	$refuses( static fn() => $repo->markRead( (int) $author_notices[0]->id, time() ) );
	$generations[ $author ] = 5;
	$repo->markRead( (int) $author_notices[0]->id, time() );
	$refuses( static fn() => $repo->revise( $article->id, new Version( 4, $content(), $author, time() ), 10, 'Arquivo terminal' ) );
	$waiting = static function ( int $connection, EditorialWorker $worker, string $scenario ) use ( $reader, $lock_observer, $diagnostic_fault, $assert ): void {
		$sample          = static function ( IdentityWpdb $database, string $sql ): array {
			$value = $database->get_var( $sql );
			$known = '' === $database->last_error && ( is_int( $value ) || is_string( $value ) ) && 1 === preg_match( '/\A[0-9]+\z/D', (string) $value );
			$errno = null;
			try {
				if ( $database->dbh instanceof mysqli ) {
					$errno = $database->dbh->errno;
				}
			} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Uma falha de introspecção permanece desconhecida.
				// Ligação perdida: o errno permanece desconhecido.
			}
			return array(
				'known'     => $known,
				'count'     => $known ? (int) $value : null,
				'sql_error' => '' !== $database->last_error,
				'errno'     => $errno,
			);
		};
		$found           = false;
		$secondary_found = false;
		$polls           = 0;
		$unknown_polls   = 0;
		$primary         = array(
			'known'     => false,
			'count'     => null,
			'sql_error' => null,
			'errno'     => null,
		);
		$secondary       = $primary;
		$started         = hrtime( true );
		$deadline        = microtime( true ) + 15;
		$sql             = $reader->prepare( 'SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id=w.requesting_trx_id WHERE t.trx_mysql_thread_id=%d', $connection );
		$primary_sql     = 'observer-query' === $diagnostic_fault ? 'SELECT arn_editorial_missing_column FROM information_schema.INNODB_LOCK_WAITS' : $sql;
		while ( $connection > 0 && ! $found && microtime( true ) < $deadline ) {
			$primary   = $sample( $reader, $primary_sql );
			$secondary = $sample( $lock_observer, $sql );
			++$polls;
			$unknown_polls  += $primary['known'] ? 0 : 1;
			$found           = $primary['known'] && $primary['count'] > 0;
			$secondary_found = $secondary_found || ( $secondary['known'] && $secondary['count'] > 0 );
			usleep( 50000 );
		}
		$state = 'unknown';
		if ( $connection > 0 ) {
			$value = $lock_observer->get_var( $lock_observer->prepare( 'SELECT trx_state FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id=%d', $connection ) );
			if ( '' === $lock_observer->last_error && in_array( $value, array( 'RUNNING', 'LOCK WAIT', 'ROLLING BACK', 'COMMITTING' ), true ) ) {
				$state = $value;
			}
		}
		$diagnostic     = $worker->diagnostic();
		$classification = match ( true ) {
			$found => 'wait-observed',
			$connection < 1 => 'worker-before-marker',
			! $primary['known'] => 'observer-query-failed',
			$secondary_found => 'observers-disagree',
			false === $diagnostic['running'] => 'worker-exited-before-lock',
			default => 'wait-not-observed',
		};
		WP_CLI::log(
			'Editorial contention: ' . wp_json_encode(
				array(
					'scenario'            => $scenario,
					'classification'      => $classification,
					'connection'          => $connection,
					'polls'               => $polls,
					'unknown_polls'       => $unknown_polls,
					'elapsed_ms'          => (int) ( ( hrtime( true ) - $started ) / 1000000 ),
					'primary'             => $primary,
					'secondary'           => $secondary,
					'secondary_wait_seen' => $secondary_found,
					'transaction_state'   => $state,
					'worker'              => $diagnostic,
				)
			)
		);
		$assert( $connection > 0 && $found && $primary['known'] && ! $primary['sql_error'], 'processo concorrente espera num lock real' );
	};
	$race    = $repo->create( $author, $scope, new Version( 1, $content(), $author, time() ), 'Concorrência exclusiva' );
	$race    = $repo->submit( $race->id, 1, 'Concorrência na fila' );
	$workers = array();
	try {
		$workers[] = new EditorialWorker( $prefix, $editor, $race->id, $participants );
		$workers[] = new EditorialWorker( $prefix, $editor, $race->id, $participants );
		$assert( $workers[0]->ready() && $workers[1]->ready(), 'dois processos editoriais preparados' );
		$db->query( 'START TRANSACTION' );
		$db->get_results( $db->prepare( 'SELECT id FROM %i WHERE id=%d FOR UPDATE', $schema->table( 'news' ), $race->id ) );
		$waiting( $workers[0]->start(), $workers[0], 'decision-first' );
		$waiting( $workers[1]->start(), $workers[1], 'decision-second' );
		$db->query( 'ROLLBACK' );
		$outcomes = array( $workers[0]->finish(), $workers[1]->finish() );
		foreach ( $workers as $index => $worker ) {
			WP_CLI::log(
				'Editorial completion: ' . wp_json_encode(
					array(
						'worker'     => $index,
						'outcome'    => $outcomes[ $index ],
						'diagnostic' => $worker->diagnostic(),
					)
				)
			);
		}
		sort( $outcomes );
		$assert( array( 'EDITORIAL_CONFIRMED', 'EDITORIAL_REFUSED' ) === $outcomes && 3 === $repo->find( $race->id )->revision && 3 === count( $repo->history( $race->id ) ), 'duas decisões concorrentes confirmam exactamente uma transição' );
	} finally {
		$db->query( 'ROLLBACK' );
		foreach ( $workers as $worker ) {
			$worker->close();
		}
	}
	$race        = $repo->create( $author, $scope, new Version( 1, $content(), $author, time() ), 'Revogação exclusiva' );
	$race        = $repo->submit( $race->id, 1, 'Revogação na fila' );
	$before_rows = $all_rows();
	$worker      = new EditorialWorker( $prefix, $editor, $race->id, $participants );
	try {
		$assert( $worker->ready(), 'editor observa concessão anterior' );
		$intercepted = false;
		$filter      = static function ( string $sql ) use ( &$intercepted, $worker, $waiting, $reader, $schema, $race, $assert ): string {
			if ( 'COMMIT' === $sql && ! $intercepted ) {
				$intercepted = true;
				$waiting( $worker->start(), $worker, 'revocation' );
				$reader->query( 'START TRANSACTION' );
				$probe = $reader->get_results( $reader->prepare( 'SELECT id FROM %i WHERE id=%d FOR UPDATE NOWAIT', $schema->table( 'news' ), $race->id ) );
				$error = $reader->last_error;
				$reader->query( 'ROLLBACK' );
				$assert( is_array( $probe ) && 1 === count( $probe ) && '' === $error, 'notícia livre enquanto decisão espera pela conta' );
			}
			return $sql;
		};
		add_filter( 'query', $filter );
		try {
			$authz->revokeGrant( $editor, $grant_ids['editor'], 4, $context() );
		} finally {
			remove_filter( 'query', $filter );
		}
		$assert( $intercepted && 'EDITORIAL_REFUSED' === $worker->finish() && $before_rows === $all_rows() && NewsState::Pending === $repo->find( $race->id )->state, 'revogação confirmada sob lock impede decisão sem efeitos operacionais' );
		WP_CLI::log(
			'Editorial completion: ' . wp_json_encode(
				array(
					'scenario'   => 'revocation',
					'diagnostic' => $worker->diagnostic(),
				)
			)
		);
	} finally {
		$db->query( 'ROLLBACK' );
		$reader->query( 'ROLLBACK' );
		$worker->close();
	}
	echo esc_html( 'Editorial exclusivo: ' . $checks . " verificações.\n" );
} finally {
	$db->query( 'ROLLBACK' );
	foreach ( array( 'audit', 'grants', 'accounts', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_authz_' . $name ) );
	}
	foreach ( array( 'audit', 'files', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $files->table( $name ) ) );
	}
	foreach ( array( 'notifications', 'audit', 'assets', 'versions', 'news', 'schema', 'schema_history' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_news' . ( 'news' === $name ? '' : '_' . $name ) ) );
	}
	$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $schema->table( 'posts' ) ) );
	foreach ( $users as $id ) {
		wp_delete_user( $id );
	}
	$lock_observer->close();
}
