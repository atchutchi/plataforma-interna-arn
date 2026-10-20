<?php
/** Real consumer repositories with synthetic authorities and exclusive tables.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Authorization\{AccountState, Grant, Role, Scope};
use Arn\Intranet\Authorization\Storage\{AuthorizationSchema, ChangeContext, WpdbAuthorizationRepository};
use Arn\Intranet\Cms\{SettingsRepository, UploadPolicy};
use Arn\Intranet\Documents\PrivateFiles\{BlobDescriptor, FileSchema};
use Arn\Intranet\Documents\Catalog\{CatalogSchema, CurrentDocumentActor, DocumentAuthority, Metadata, Revision, WpdbDocumentAuthority, WpdbDocumentRepository};
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\News\{Asset, Content, CurrentNewsActor, NewsAuthority, NewsSchema, Version, WpdbNewsAuthority, WpdbNewsRepository};

// phpcs:disable WordPress, Squiz.Commenting -- Isolated SQL harness, no native account or transport claim.
return static function ( IdentityWpdb $db, SettingsRepository $settings, Closure $assert, Closure $reject ): void {
	if ( 1 !== preg_match( '/\Aarn_cms_test_[a-f0-9]{12}_\z/D', $db->prefix ) ) {
		throw new RuntimeException( 'Exclusive CMS prefix required.' );
	}
	$authorization = new AuthorizationSchema( $db );
	$files         = new FileSchema( $db );
	$news_schema   = new NewsSchema( $db );
	$docs_schema   = new CatalogSchema( $db );
	$tables        = array( $db->prefix . 'posts' );
	foreach ( array( 'audit', 'grants', 'accounts', 'schema' ) as $part ) {
		$tables[] = $authorization->table( $part );
	}
	foreach ( array( 'audit', 'files', 'schema' ) as $part ) {
		$tables[] = $files->table( $part );
	}
	foreach ( array( 'audit', 'notifications', 'assets', 'versions', 'news', 'schema_history', 'schema' ) as $part ) {
		$tables[] = $news_schema->table( $part );
	}
	foreach ( array( 'audit', 'versions', 'documents', 'schema' ) as $part ) {
		$tables[] = $docs_schema->table( $part );
	}
	$existing = $db->get_col( 'SHOW TABLES' );
	if ( array_intersect( $tables, $existing ) ) {
		throw new RuntimeException( 'Consumer fixtures already exist.' );
	}
	try {
		$posts = 'CREATE TABLE %i (ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, post_author BIGINT UNSIGNED NOT NULL, post_date DATETIME NOT NULL, post_date_gmt DATETIME NOT NULL, post_content LONGTEXT NOT NULL, post_title TEXT NOT NULL, post_status VARCHAR(20) NOT NULL, post_type VARCHAR(20) NOT NULL, post_name VARCHAR(200) NOT NULL, post_modified DATETIME NOT NULL, post_modified_gmt DATETIME NOT NULL, post_parent BIGINT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY(ID), KEY post_name(post_name(191)), KEY type_status_date(post_type,post_status,post_date,ID), KEY post_parent(post_parent), KEY post_author(post_author)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
		if ( false === $db->query( $db->prepare( $posts, $db->prefix . 'posts' ) ) ) {
			throw new RuntimeException( 'Synthetic posts unavailable.' );
		}
		$authorization->install();
		$files->install();
		$news_schema->install();
		$docs_schema->install();
		// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Synthetic authorities isolate admission from permission checks.
		$news_authority = new class() implements NewsAuthority {
			public function assertUploadCurrent(): void {  }
			public function lockAndAssert( int $author_id, Scope $review_scope, string $operation ): array {
				return array( 'actor_id' => 7, 'reviewer_ids' => array( 8 ) );
			}
		};
		$docs_authority = new class() implements DocumentAuthority {
			public function assertUploadCurrent(): void {  }
			public function lockAndAssert( Scope $scope, array $recipient_ids ): int {
				return 7; }
			public function assertActiveRecipients( array $recipient_ids ): void {}
		};
		// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter
		$news       = new WpdbNewsRepository( $db, $news_authority );
		$docs       = new WpdbDocumentRepository( $db, $docs_authority );
		$blob       = static fn( int $size = 32, string $mime = 'application/pdf' ): BlobDescriptor => new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'a', 64 ), $size, $mime );
		$copy       = static fn( BlobDescriptor $source ): BlobDescriptor => new BlobDescriptor( bin2hex( random_bytes( 16 ) ), $source->sha256, $source->size, $source->mime );
		$metadata   = new Metadata( 'Documento fictício CMS', 'Resumo de ensaio', 'regulamento', 'CMS-01', 'Unidade fictícia', '2026-10-20', null, null, array( 7 ) );
		$scope      = Scope::units( array( 'CMS-TESTE' ) );
		$queue      = Scope::queues( array( 'editorial-ficticia' ) );
		$content    = static fn( BlobDescriptor $image, BlobDescriptor $attachment ): Content => new Content( 'Notícia fictícia CMS', 'Texto de ensaio', 'circular', new Asset( 'featured', $image, 'Imagem fictícia' ), array(), array( new Asset( 'attachment', $attachment, 'Anexo fictício' ) ) );
		$set_policy = static function ( UploadPolicy $policy ) use ( $settings ): void {
			$settings->change( $policy, $settings->read()->revision, 'Política exclusiva dos consumidores fictícios.' );
		};
		$set_policy( UploadPolicy::defaults() );
		$document_blob = $blob();
		$document      = $docs->create( $scope, new Revision( 1, $metadata, $document_blob, 7, 100 ), 'Documento inicial' );
		$document      = $docs->publish( $document->id, 1, 1, 'Publicação fictícia' );
		$image         = $blob( 32, 'image/png' );
		$attachment    = $blob();
		$article       = $news->create( 7, $queue, new Version( 1, $content( $image, $attachment ), 7, 100 ), 'Notícia inicial' );
		$assert( 1 === $document->published_version && 1 === $article->revision, 'defaults admit both real repositories' );
		$values = UploadPolicy::defaults()->toArray();
		foreach ( $values as &$value ) {
			$value['max_bytes'] = 16; }
		unset( $value );
		$set_policy( UploadPolicy::fromArray( $values ) );
		$snapshot = static function () use ( $db, $tables ): array {
			$out = array();
			foreach ( $tables as $table ) {
				$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i', $table ) );
				if ( ! is_array( $rows ) || '' !== $db->last_error ) {
					throw new RuntimeException( 'Snapshot failed.' ); }
				$rows = array_map( static fn( object $row ): string => json_encode( $row, JSON_THROW_ON_ERROR ), $rows );
				sort( $rows );
				$out[ $table ] = hash( 'sha256', implode( "\n", $rows ) );
			}
			return $out;
		};
		$before   = $snapshot();
		$reject( static fn() => $docs->create( $scope, new Revision( 1, $metadata, $blob(), 7, 101 ), 'Recusar bytes novos' ), 'document create obeys reduced policy' );
		$reject( static fn() => $news->create( 7, $queue, new Version( 1, $content( $blob( 32, 'image/png' ), $blob( 8 ) ), 7, 101 ), 'Recusar imagem' ), 'news image obeys reduced policy' );
		$reject( static fn() => $news->create( 7, $queue, new Version( 1, $content( $blob( 8, 'image/png' ), $blob() ), 7, 101 ), 'Recusar anexo' ), 'news attachment obeys reduced policy' );
		$assert( $before === $snapshot(), 'refused creates preserve posts versions files notices and audit' );
		$assert( $document_blob->id === $docs->find( $document->id )->version( 1 )->blob->id && 1 === $docs->find( $document->id )->published_version, 'published original remains referenced after reduction' );
		$document_copy = $copy( $document_blob );
		$reject( static fn() => $docs->revise( $document->id, new Revision( 2, $metadata, $document_copy, 7, 102 ), 2, 'Sem proveniência' ), 'direct document revision cannot skip policy' );
		$reject( static fn() => $docs->revise( $document->id, new Revision( 2, $metadata, $document_copy, 7, 102 ), 2, 'Origem alheia', str_repeat( 'f', 32 ) ), 'foreign document provenance refused' );
		$document = $docs->revise( $document->id, new Revision( 2, $metadata, $document_copy, 7, 102 ), 2, 'Conservar original', $document_blob->id );
		$assert( 3 === $document->revision && 1 === $document->published_version, 'verified document duplicate preserves publication' );
		$next_image      = $copy( $image );
		$next_attachment = $copy( $attachment );
		$next            = new Version( 2, $content( $next_image, $next_attachment ), 7, 102 );
		$reject( static fn() => $news->revise( $article->id, $next, 1, 'Sem proveniência' ), 'direct news revision cannot skip policy' );
		$reject( static fn() => $news->revise( $article->id, $next, 1, 'Tipos trocados', array( $next_image->id => $attachment->id, $next_attachment->id => $image->id ) ), 'news provenance cannot cross asset kinds' );
		$article = $news->revise( $article->id, $next, 1, 'Conservar anexos', array( $next_image->id => $image->id, $next_attachment->id => $attachment->id ) );
		$assert( 2 === $article->revision && $image->id === $news->find( $article->id )->version( 1 )->content->featured->blob->id, 'verified news copies preserve old version' );
		$before = $snapshot();
		$forged = new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'b', 64 ), 32, 'application/pdf' );
		$reject( static fn() => $docs->revise( $document->id, new Revision( 3, $metadata, $forged, 7, 103 ), 3, 'Hash diferente', $document_copy->id ), 'different bytes cannot claim retained provenance' );
		$assert( $before === $snapshot(), 'forged copy leaves all consumer rows unchanged' );
		$small_document = $docs->create( $scope, new Revision( 1, $metadata, $blob( 8 ), 7, 103 ), 'Novo documento permitido' );
		$small_article  = $news->create( 7, $queue, new Version( 1, $content( $blob( 8, 'image/png' ), $blob( 8 ) ), 7, 103 ), 'Nova notícia permitida' );
		$assert( 1 === $small_document->revision && 1 === $small_article->revision, 'new compliant bytes remain admitted in both repositories' );
		foreach ( array( 'expire-news', 'expire-document', 'expire-news-revise', 'expire-document-revise' ) as $mode ) {
			$before = $snapshot();
			$owner  = arn_tx_connect();
			$owner->query( 'START TRANSACTION' );
			$owner->query( 'SELECT id FROM `' . $db->prefix . 'arn_cms_state` FOR UPDATE' );
			$process = null;
			try {
				$object  = str_starts_with( $mode, 'expire-news' ) ? $article : $document;
				$process = proc_open( array( PHP_BINARY, __DIR__ . '/mysql-cms-settings.php', $GLOBALS['argv'][1], $GLOBALS['argv'][2], $mode, $db->prefix, (string) $object->id, (string) $object->revision ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, dirname( __DIR__, 2 ), null, array( 'bypass_shell' => true ) );
				if ( ! is_resource( $process ) ) {
					throw new RuntimeException( 'Expiry worker unavailable.' ); }
				fclose( $pipes[0] );
				$deadline = microtime( true ) + 10;
				$blocked  = 0;
				while ( microtime( true ) < $deadline ) {
					$blocked = (int) $owner->get_var( $owner->prepare( 'SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB=DATABASE() AND INFO=%s', 'SELECT * FROM `' . $db->prefix . 'arn_cms_state` FOR UPDATE' ) );
					if ( 1 === $blocked ) {
						break; }
					usleep( 20000 );
				}
				$assert( 1 === $blocked, $mode . ' waits on CMS row after checking authority' );
				usleep( 450000 );
				$owner->query( 'COMMIT' );
				$output = stream_get_contents( $pipes[1] );
				$error  = stream_get_contents( $pipes[2] );
				fclose( $pipes[1] );
				fclose( $pipes[2] );
				$exit    = proc_close( $process );
				$process = null;
				$assert( 0 === $exit && '' === $error && "checked\nexpired\nrefused\n" === $output, $mode . ' rechecks expiry before commit' );
				$assert( $before === $snapshot(), $mode . ' rolls back all consumer rows' );
			} finally {
				$owner->query( 'ROLLBACK' );
				if ( is_resource( $process ) ) {
					proc_terminate( $process );
					proc_close( $process ); }
				foreach ( $pipes ?? array() as $pipe ) {
					if ( is_resource( $pipe ) ) {
						fclose( $pipe ); } }
				$owner->close();
			}
		}
		// Real authorities, separate reader connection, synthetic server principal/session.
		$auth_repository = new WpdbAuthorizationRepository( $db );
		$context         = new ChangeContext( 7, 'Autoridade SQL fictícia', 'CMS-SQL', time() );
		$auth_repository->createAccount( 7, $context );
		$auth_repository->changeState( 7, AccountState::Active, 1, $context );
		$auth_repository->createGrant( new Grant( 7, Role::Employee, Scope::own(), 0, null ), 2, $context );
		$auth_repository->createGrant( new Grant( 7, Role::HumanResources, $scope, 0, null ), 3, $context );
		$reader = arn_tx_connect();
		$reader->set_prefix( $db->prefix );
		$reader->query( 'SET SESSION innodb_lock_wait_timeout=1' );
		try {
			$reads               = 0;
			$factory             = static function () use ( $reader, &$reads ): WpdbAuthorizationRepository {
				++$reads;
				return new WpdbAuthorizationRepository( $reader );
			};
			$principal           = 7;
			$principal_provider  = static function () use ( &$principal ): int {
				return $principal;
			};
			$session             = array( 'arn_authz_local' => true, 'arn_authz_user_id' => 7, 'arn_authz_revision' => 4 );
			$session_provider    = static function () use ( &$session ): array {
				return $session;
			};
			$current_news        = new CurrentNewsActor( $factory, $principal_provider, $session_provider, static fn(): array => array(), static fn(): array => array( 7 => array( 'CMS-TESTE' ) ) );
			$current_docs        = new CurrentDocumentActor( $factory, $principal_provider, $session_provider );
			$real_news_authority = new WpdbNewsAuthority( $db, $current_news );
			$real_docs_authority = new WpdbDocumentAuthority( $db, $current_docs );
			$real_news           = new WpdbNewsRepository( $db, $real_news_authority );
			$real_docs           = new WpdbDocumentRepository( $db, $real_docs_authority );
			$real_article        = $real_news->create( 7, $queue, new Version( 1, $content( $blob( 8, 'image/png' ), $blob( 8 ) ), 7, time() ), 'Autoridade real na criação' );
			$assert( 1 === $reads, 'news creation does not open another locked account reader at commit' );
			$real_article = $real_news->revise( $real_article->id, new Version( 2, $content( $blob( 8, 'image/png' ), $blob( 8 ) ), 7, time() ), 1, 'Autoridade real na revisão' );
			$assert( 2 === $reads && 2 === $real_article->revision, 'news revision completes with real authority and one reader' );
			$real_document = $real_docs->create( $scope, new Revision( 1, $metadata, $blob( 8 ), 7, time() ), 'Autoridade real na criação' );
			$assert( 3 === $reads, 'document creation does not open another locked account reader at commit' );
			$real_document = $real_docs->revise( $real_document->id, new Revision( 2, $metadata, $blob( 8 ), 7, time() ), 1, 'Autoridade real na revisão' );
			$assert( 4 === $reads && 2 === $real_document->revision, 'document revision completes with real authority and one reader' );
			$reject( static fn() => $real_news_authority->assertUploadCurrent(), 'news context cannot be reused outside transaction' );
			$reject( static fn() => $real_docs_authority->assertUploadCurrent(), 'document context cannot be reused outside transaction' );
			foreach ( array( $real_news_authority, $real_docs_authority ) as $real_authority ) {
				$db->query( 'START TRANSACTION' );
				if ( $real_authority instanceof WpdbNewsAuthority ) {
					$real_authority->lockAndAssert( 7, $queue, 'create' ); } else {
					$real_authority->lockAndAssert( $scope, array( 7 ) ); }
					$principal = 8;
					$reject( static fn() => $real_authority->assertUploadCurrent(), 'real authority refuses changed principal without a second reader' );
					$principal                     = 7;
					$session['arn_authz_revision'] = 3;
					$reject( static fn() => $real_authority->assertUploadCurrent(), 'real authority refuses changed server session' );
					$session['arn_authz_revision'] = 4;
					$db->query( 'ROLLBACK' );
			}
		} finally {
			$db->query( 'ROLLBACK' );
			$reader->close();
		}
	} finally {
		$db->query( 'ROLLBACK' );
		// Grants precede accounts to preserve the authorization foreign key.
		foreach ( $tables as $table ) {
			$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		}
	}
};
