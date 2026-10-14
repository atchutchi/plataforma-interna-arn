<?php
/** Exclusive SQL integration of private news.
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
use Arn\Intranet\Documents\PrivateFiles\FileSchema;
use Arn\Intranet\Documents\PrivateFiles\WpdbFileRegistry;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\News\Asset;
use Arn\Intranet\News\Content;
use Arn\Intranet\News\CurrentNewsActor;
use Arn\Intranet\News\NewsConfiguration;
use Arn\Intranet\News\NewsSchema;
use Arn\Intranet\News\Version;
use Arn\Intranet\News\WpdbNewsAuthority;
use Arn\Intranet\News\WpdbNewsRepository;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Falhou: ' . $label );
	}
	++$checks;
};
$prefix = 'arn_ns_' . bin2hex( random_bytes( 5 ) ) . '_';
$db     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$reader_db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$reader_db->set_prefix( $prefix );
$reader_db->suppress_errors( true );
$files         = new FileSchema( $db );
$schema        = new NewsSchema( $db );
$created_users = array();
$db->query( $db->prepare( 'CREATE TABLE %i LIKE %i', $schema->table( 'posts' ), $GLOBALS['wpdb']->posts ) );
try {
	$files->install();
	$authorization = new WpdbAuthorizationRepository( $db );
	$authorization->installSchema();
	$schema->install();
	$schema->assertReady();
	$rejected = false;
	try {
		$schema->table( 'unexpected' );
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected, 'catálogo de identificadores SQL fechado' );
	$assert( ! NewsConfiguration::configured() || 'local' === wp_get_environment_type(), 'gate runtime não se abre em development' );
	$assert( null === ( new CurrentNewsActor() )->read(), 'administrador não é actor editorial' );
	$assert( NewsConfiguration::enabled( 'local', true, true, true, true ) && ! NewsConfiguration::enabled( 'local', 1, true, true, true ), 'gates booleanos exactos' );
	$login_seed = bin2hex( random_bytes( 5 ) );
	foreach ( array( 'author', 'reviewer' ) as $kind ) {
		$user = wp_insert_user(
			array(
				'user_login' => 'arn_news_' . $kind . '_' . $login_seed,
				'user_pass'  => wp_generate_password( 32, true, true ),
				'user_email' => 'arn-news-' . $kind . '-' . $login_seed . '@example.invalid',
				'role'       => 'subscriber',
			)
		);
		if ( is_wp_error( $user ) ) {
			throw new RuntimeException( 'Fixture WordPress indisponível.' );
		}
		$created_users[ $kind ] = (int) $user;
	}
	$author_id   = $created_users['author'];
	$reviewer_id = $created_users['reviewer'];
	$scope       = Scope::queues( array( 'editorial-ficticia' ) );
	$context     = static fn( string $reason ): ChangeContext => new ChangeContext( $author_id, $reason, 'LAB-NEWS', time() );
	foreach ( array( $author_id, $reviewer_id ) as $fixture_id ) {
		$authorization->createAccount( $fixture_id, $context( 'Conta exclusiva de notícias' ) );
		$authorization->changeState( $fixture_id, AccountState::Active, 1, $context( 'Activação exclusiva' ) );
	}
	$author_grant     = $authorization->createGrant( new Grant( $author_id, Role::Employee, Scope::own(), 0, null ), 2, $context( 'Autoria exclusiva' ) );
	$reviewer_grant   = $authorization->createGrant( new Grant( $reviewer_id, Role::Editorial, $scope, 0, null ), 2, $context( 'Revisão exclusiva' ) );
	$active_id        = $author_id;
	$session_revision = array(
		$author_id   => 3,
		$reviewer_id => 3,
	);
	$session          = static function ( int $id ) use ( &$session_revision ): array {
		return array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => $id,
			'arn_authz_revision' => $session_revision[ $id ] ?? 0,
		);
	};
	$current          = new CurrentNewsActor(
		static fn() => new WpdbAuthorizationRepository( $reader_db ),
		static function () use ( &$active_id ): int {
			return $active_id;
		},
		static fn( int $id ): array => $session( $id ),
		static fn(): array => array( $reviewer_id )
	);
	$authority        = new WpdbNewsAuthority( $db, $current );
	$repo             = new WpdbNewsRepository( $db, $authority );
	$registry         = new WpdbFileRegistry( $db );
	$asset            = static function ( string $kind, string $description ): Asset {
		$mime = 'attachment' === $kind ? 'application/pdf' : 'image/png';
		return new Asset( $kind, new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'a', 64 ), 12, $mime ), $description );
	};
	$content          = new Content( 'Notícia de ensaio', '', 'nota-interna', null, array(), array() );
	$article          = $repo->create( $author_id, $scope, new Version( 1, $content, $author_id, time() ), 'Criar rascunho' );
	$assert( 1 === $article->revision && ! $article->pending() && 1 === count( $repo->all() ), 'rascunho durável' );
	$assert( 'Notícia privada' === (string) $db->get_var( $db->prepare( 'SELECT post_title FROM %i WHERE ID=%d', $schema->table( 'posts' ), $article->id ) ), 'âncora genérica' );
	$featured   = $asset( 'featured', 'Alternativa do destaque' );
	$gallery    = array( $asset( 'gallery', 'Primeira imagem' ), $asset( 'gallery', 'Segunda imagem' ) );
	$attachment = $asset( 'attachment', 'Documento anexo' );
	$next       = new Version( 2, new Content( 'Notícia de ensaio', "Corpo de notícia\nParágrafo novo", 'nota-interna', $featured, $gallery, array( $attachment ) ), $author_id, time() );
	$article    = $repo->revise( $article->id, $next, 1, 'Revisão com assets' );
	$hydrated   = $repo->find( $article->id );
	$assert( 2 === $hydrated->revision && 2 === count( $hydrated->version( 2 )->content->gallery ) && 1 === count( $hydrated->version( 2 )->content->attachments ), 'hidratação completa' );
	$asset_row = $repo->asset( $featured->blob->id );
	$assert( $asset_row['article_id'] === $article->id && 2 === $asset_row['version'] && $registry->find( $featured->blob->id )->object_id === $asset_row['asset_id'] && 'news_asset' === $registry->find( $featured->blob->id )->object_type, 'vínculo ao ID próprio do asset' );
	$rejected = false;
	try {
		$repo->revise( $article->id, new Version( 3, $next->content, $author_id, time() ), 1, 'CAS antigo' );
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && 2 === $repo->find( $article->id )->revision, 'CAS protege revisão' );
	$count_rows     = static function () use ( $db, $schema, $files ): array {
		$tables = array( $schema->table( 'posts' ), $schema->table( 'news' ), $schema->table( 'versions' ), $schema->table( 'assets' ), $schema->table( 'audit' ), $schema->table( 'notifications' ), $files->table( 'files' ), $files->table( 'audit' ) );
		return array_map( static fn( string $table ): int => (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $table ) ), $tables );
	};
	$before         = $count_rows();
	$before_article = $repo->find( $article->id );
	$before_notices = $repo->notificationRows( $reviewer_id );
	$fingerprint    = static function ( mixed $value ): string {
		$encoded = wp_json_encode( $value );
		if ( ! is_string( $encoded ) ) {
			throw new RuntimeException( 'Estado de ensaio indisponível.' );
		}
		return $encoded;
	};
	$filter         = static fn( string $sql ): string => str_contains( $sql, 'arn_news_audit' ) && str_starts_with( $sql, 'INSERT INTO' ) ? 'INVALID NEWS AUDIT INSERT' : $sql;
	add_filter( 'query', $filter );
	$failed         = false;
	try {
		$repo->submit( $article->id, 2, 'Auditoria forçada' );
	} catch ( RuntimeException ) {
		$failed = true;
	} finally {
		remove_filter( 'query', $filter );
	}
	$assert( $failed && $before === $count_rows() && $fingerprint( $before_article ) === $fingerprint( $repo->find( $article->id ) ) && $fingerprint( $before_notices ) === $fingerprint( $repo->notificationRows( $reviewer_id ) ), 'falha de auditoria reverte oito tabelas e agregado' );
	$filter = static fn( string $sql ): string => str_contains( $sql, 'arn_news_notifications' ) && str_starts_with( $sql, 'INSERT INTO' ) ? 'INVALID NEWS NOTICE INSERT' : $sql;
	add_filter( 'query', $filter );
	$failed = false;
	try {
		$repo->submit( $article->id, 2, 'Notificação forçada' );
	} catch ( RuntimeException ) {
		$failed = true;
	} finally {
		remove_filter( 'query', $filter );
	}
	$assert( $failed && $before === $count_rows() && $fingerprint( $before_article ) === $fingerprint( $repo->find( $article->id ) ) && $fingerprint( $before_notices ) === $fingerprint( $repo->notificationRows( $reviewer_id ) ), 'falha de notificação reverte oito tabelas e agregado' );
	$article = $repo->submit( $article->id, 2, 'Enviar para revisão' );
	$notices = $repo->notificationRows( $reviewer_id );
	$assert( $article->pending() && 3 === $article->revision && 1 === count( $notices ) && 2 === (int) $notices[0]->version, 'submissão e aviso único' );
	$rejected = false;
	try {
		$repo->submit( $article->id, 3, 'Duplicação' );
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && 1 === count( $repo->notificationRows( $reviewer_id ) ), 'Pendente bloqueia duplicação' );
	$active_id                        = $reviewer_id;
	$notice_id                        = (int) $notices[0]->id;
	$session_revision[ $reviewer_id ] = 2;
	$rejected                         = false;
	try {
		$repo->markRead( $notice_id, time() );
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && null === $repo->notificationRows( $reviewer_id )[0]->read_at, 'sessão editorial antiga recusada' );
	$session_revision[ $reviewer_id ] = 3;
	$repo->markRead( $notice_id, time() );
	$read_at = $repo->notificationRows( $reviewer_id )[0]->read_at;
	$repo->markRead( $notice_id, time() + 10 );
	$assert( $read_at === $repo->notificationRows( $reviewer_id )[0]->read_at && 3 === $repo->find( $article->id )->revision, 'recibo idempotente sem revisão editorial' );
	$active_id = $author_id;
	$rejected  = false;
	try {
		$repo->markRead( $notice_id, time() );
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected, 'autor não marca aviso do editor' );
	$reader_db->query( 'SET SESSION innodb_lock_wait_timeout=1' );
	$db->query( 'START TRANSACTION' );
	$db->get_results( $db->prepare( 'SELECT user_id FROM %i WHERE user_id=%d FOR UPDATE', $prefix . 'arn_authz_accounts', $author_id ) );
	$reader_db->query( 'START TRANSACTION' );
	$wait       = $reader_db->get_results( $reader_db->prepare( 'SELECT user_id FROM %i WHERE user_id=%d FOR UPDATE', $prefix . 'arn_authz_accounts', $author_id ) );
	$wait_error = $reader_db->last_error;
	$reader_db->query( 'ROLLBACK' );
	$db->query( 'ROLLBACK' );
	$assert( array() === $wait && str_contains( $wait_error, 'Lock wait timeout exceeded' ), 'duas ligações confirmam contenção de conta' );
	$authorization->revokeGrant( $reviewer_id, $reviewer_grant, 3, $context( 'Revogação exclusiva' ) );
	$session_revision[ $reviewer_id ] = 4;
	$active_id                        = $reviewer_id;
	$rejected                         = false;
	try {
		$repo->markRead( $notice_id, time() );
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected, 'recibo recusa concessão revogada' );
	$active_id     = $author_id;
	$draft_content = new Content( 'Outro rascunho', 'Texto', 'nota-interna', $asset( 'featured', 'Novo destaque' ), array(), array() );
	$draft         = $repo->create( $author_id, $scope, new Version( 1, $draft_content, $author_id, time() ), 'Outro rascunho' );
	$rejected      = false;
	try {
		$repo->submit( $draft->id, 1, 'Sem editor elegível' );
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && ! $repo->find( $draft->id )->pending(), 'editor revogado impede transição' );
	$authorization->changeState( $author_id, AccountState::Suspended, 3, $context( 'Suspensão de autor exclusivo' ) );
	$session_revision[ $author_id ] = 4;
	$rejected                       = false;
	try {
		$repo->revise( $draft->id, new Version( 2, new Content( 'Nova versão', 'Texto', 'nota-interna', null, array(), array() ), $author_id, time() ), 1, 'Autor suspenso' );
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && 1 === $repo->find( $draft->id )->revision, 'autor suspenso não altera rascunho' );
	$orphan_blob = bin2hex( random_bytes( 16 ) );
	$db->query( $db->prepare( 'INSERT INTO %i (article_id,version,kind,position,blob_id,sha256,size_bytes,mime,description) VALUES (%d,99,%s,0,%s,%s,12,%s,%s)', $schema->table( 'assets' ), $draft->id, 'featured', $orphan_blob, str_repeat( 'a', 64 ), 'image/png', 'Órfão' ) );
	$rejected = false;
	try {
		$repo->find( $draft->id );
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$db->query( $db->prepare( 'DELETE FROM %i WHERE blob_id=%s', $schema->table( 'assets' ), $orphan_blob ) );
	$assert( $rejected && null !== $repo->find( $draft->id ), 'hidratação recusa asset órfão' );
	$db->query( $db->prepare( 'ALTER TABLE %i DROP INDEX queue_state', $schema->table( 'news' ) ) );
	$rejected = false;
	try {
		$schema->assertReady();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected, 'schema incompatível recusado' );
	echo esc_html( 'Notícias locais: ' . $checks . " verificações.\n" );
} finally {
	$db->query( 'ROLLBACK' );
	$reader_db->query( 'ROLLBACK' );
	foreach ( array( 'audit', 'grants', 'accounts', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_authz_' . $name ) );
	}
	foreach ( array( 'audit', 'files', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $files->table( $name ) ) );
	}
	foreach ( array( 'notifications', 'audit', 'assets', 'versions', 'news', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $schema->table( $name ) ) );
	}
	$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $schema->table( 'posts' ) ) );
	foreach ( $created_users as $fixture_id ) {
		wp_delete_user( $fixture_id );
	}
}
