<?php
/** Physical copies and real SQL registry, only inside the exclusive CMS fixture.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Documents\PrivateFiles\{FileRecord, FileSchema, ImportBatch, PrivateBlobStore, WpdbFileRegistry};
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Cms\{SettingsRepository, UploadPolicy};
use Arn\Intranet\Documents\Catalog\{CurrentDocumentActor, LocalDocumentCatalog, Metadata, WpdbDocumentAuthority, WpdbDocumentRepository};
use Arn\Intranet\News\{CurrentNewsActor, DraftInput, LocalNewsroom, MediaInput, WpdbNewsAuthority, WpdbNewsRepository};

// phpcs:disable WordPress, Squiz.Commenting -- Synthetic CLI fixture, no HTTP or native-session claim.
return static function ( IdentityWpdb $db, Closure $assert, SettingsRepository $settings, CurrentNewsActor $current_news, CurrentDocumentActor $current_docs, Metadata $metadata, Scope $scope ): void {
	if ( 1 !== preg_match( '/\Aarn_cms_test_[a-f0-9]{12}_\z/D', $db->prefix ) ) {
		throw new RuntimeException( 'Exclusive CMS prefix required.' );
	}
	$base = sys_get_temp_dir() . '/arn-cms-blobs-' . bin2hex( random_bytes( 12 ) );
	mkdir( $base, 0700 );
	mkdir( $base . '/private', 0700 );
	mkdir( $base . '/public', 0700 );
	$source = $base . '/public/source.pdf';
	file_put_contents( $source, "%PDF-1.4\n%%EOF\n" );
	$store       = new PrivateBlobStore( $base . '/private', array( $base . '/public' ), array( 'application/pdf' ), 1048576 );
	$registry    = new WpdbFileRegistry( $db );
	$schema      = new FileSchema( $db );
	$connections = array();
	$fresh       = static function () use ( $db, &$connections ): WpdbFileRegistry {
		$connection = arn_tx_connect();
		$connection->set_prefix( $db->prefix );
		$connections[] = $connection;
		return new WpdbFileRegistry( $connection );
	};
	$exists      = static fn( $blob ): bool => is_file( $base . '/private/' . $blob->id );
	$original    = new RuntimeException( 'Synthetic operation failed.' );
	$run_failed  = static function ( Closure $operation, ?Closure $factory = null ) use ( $store, $fresh, $original, $assert ): void {
		try {
			( new ImportBatch( $store ) )->run( $operation, $factory ?? $fresh );
			throw new LogicException( 'Failure expected.' );
		} catch ( RuntimeException $caught ) {
			$assert( $caught === $original, 'cleanup preserves the original exception instance' );
		}
	};
	try {
		$created = null;
		$run_failed(
			static function ( ImportBatch $batch ) use ( $source, $original, &$created ): void {
				$created = $batch->importFile( $source );
				throw $original;
			}
		);
		$assert( ! $exists( $created ) && null === $registry->find( $created->id ), 'refused mutation removes only the unregistered physical copy' );

		$run_failed(
			static function ( ImportBatch $batch ) use ( $source, $original, $db, $registry, &$created ): void {
				$created = $batch->importFile( $source );
				$db->query( 'START TRANSACTION' );
				$registry->registerInTransaction( new FileRecord( $created, 'cms_lifecycle', 9001, 1, 'original', 7, 100 ) );
				$db->query( 'ROLLBACK' );
				throw $original;
			}
		);
		$assert( ! $exists( $created ) && null === $registry->find( $created->id ), 'rollback of file and audit permits physical cleanup' );

		$run_failed(
			static function ( ImportBatch $batch ) use ( $source, $original, $registry, &$created ): void {
				$created = $batch->importFile( $source );
				$registry->register( new FileRecord( $created, 'cms_lifecycle', 9002, 1, 'original', 7, 101 ) );
				throw $original; // Commit completed, caller did not receive success.
			}
		);
		$assert( $exists( $created ) && null !== $registry->find( $created->id ), 'lost success response retains committed bytes and metadata' );
		$committed = $created;
		$run_failed(
			static function ( ImportBatch $batch ) use ( $committed, $original, &$created ): void {
				$created = $batch->duplicate( $committed );
				throw $original;
			}
		);
		$assert( ! $exists( $created ) && $exists( $committed ), 'failed revision removes the new duplicate and preserves the registered original' );

		$run_failed(
			static function ( ImportBatch $batch ) use ( $source, $original, $db, $schema, &$created ): void {
				$created = $batch->importFile( $source );
				$db->query( $db->prepare( 'INSERT INTO %i (blob_id,event,actor_id,occurred_at) VALUES (%s,%s,7,102)', $schema->table( 'audit' ), $created->id, 'file_registered' ) );
				throw $original;
			}
		);
		$assert( $exists( $created ) && null === $registry->find( $created->id ), 'audit-only inconsistent reference retains bytes conservatively' );

		$copies = array();
		$run_failed(
			static function ( ImportBatch $batch ) use ( $source, $base, $original, &$copies ): void {
				$copies[] = $batch->importFile( $source );
				$copies[] = $batch->importFile( $source );
				file_put_contents( $base . '/private/' . $copies[0]->id, "%PDF-1.5\n%%EOF\n" );
				throw $original;
			}
		);
		$assert( $exists( $copies[0] ) && ! $exists( $copies[1] ), 'one invalid copy does not stop cleanup of remaining owned copies' );

		$run_failed(
			static function ( ImportBatch $batch ) use ( $source, $original, &$created ): void {
				$created = $batch->importFile( $source );
				throw $original;
			},
			static fn() => throw new RuntimeException( 'Database unavailable.' )
		);
		$assert( $exists( $created ), 'unavailable database never permits physical deletion' );

		$run_failed(
			static function ( ImportBatch $batch ) use ( $source, $original, &$created ): void {
				$created = $batch->importFile( $source );
				throw $original;
			},
			static function () use ( $db, &$connections ): WpdbFileRegistry {
				$connection = arn_tx_connect();
				$connection->set_prefix( $db->prefix . 'absent_' );
				$connections[] = $connection;
				return new WpdbFileRegistry( $connection );
			}
		);
		$assert( $exists( $created ), 'missing registry schema never permits physical deletion' );
		$news_factory     = static function () use ( $db, $current_news, &$connections ): array {
			$connection = arn_tx_connect();
			$connection->set_prefix( $db->prefix );
			$connections[] = $connection;
			return array( new WpdbNewsRepository( $connection, new WpdbNewsAuthority( $connection, $current_news ) ), new WpdbFileRegistry( $connection ) );
		};
		$docs_factory     = static function () use ( $db, $current_docs, &$connections ): array {
			$connection = arn_tx_connect();
			$connection->set_prefix( $db->prefix );
			$connections[] = $connection;
			return array( new WpdbDocumentRepository( $connection, new WpdbDocumentAuthority( $connection, $current_docs ) ), new WpdbFileRegistry( $connection ) );
		};
		$news             = new LocalNewsroom( $current_news, $news_factory, static fn(): PrivateBlobStore => $store, static fn(): bool => true );
		$docs             = new LocalDocumentCatalog( $current_docs, $docs_factory, static fn(): PrivateBlobStore => $store, static fn(): bool => true );
		$input            = new DraftInput( 'Notícia de ensaio', 'Conteúdo fictício', 'circular', null, array(), array( new MediaInput( null, $source, 'Anexo fictício' ) ) );
		$physical         = static function () use ( $base ): array {
			$paths = glob( $base . '/private/*' );
			return false === $paths ? array() : $paths;
		};
		$expect_unchanged = static function ( Closure $operation ) use ( $physical, $assert ): void {
			$before = $physical();
			try {
				$operation();
				throw new LogicException( 'Runtime refusal expected.' );
			} catch ( RuntimeException ) {
				$assert( $before === $physical(), 'runtime refusal preserves the exact set of prior private files' );
			}
		};
		$values           = UploadPolicy::defaults()->toArray();
		foreach ( $values as &$value ) {
			$value['max_bytes'] = 1;
		}
		unset( $value );
		$settings->change( UploadPolicy::fromArray( $values ), $settings->read()->revision, 'Recusa de bytes na composição local' );
		$expect_unchanged( static fn() => $news->create( $input, 'Limite reduzido' ) );
		$expect_unchanged( static fn() => $docs->create( $scope, $metadata, $source, 'Limite reduzido' ) );
		$settings->change( UploadPolicy::defaults(), $settings->read()->revision, 'Reposição do ensaio local' );
		$article  = $news->create( $input, 'Criação local aceite' );
		$document = $docs->create( $scope, $metadata, $source, 'Criação local aceite' );
		$assert( $exists( $article->version( 1 )->content->attachments[0]->blob ) && $exists( $document->version( 1 )->blob ), 'successful real runtimes retain registered physical files' );
		$retained = new DraftInput( 'Revisão fictícia', 'Texto fictício', 'circular', null, array(), array( new MediaInput( $article->version( 1 )->content->attachments[0]->blob->id, null, 'Anexo conservado' ) ) );
		$expect_unchanged( static fn() => $news->revise( $article->id, $retained, $article->revision, '' ) );
		$expect_unchanged( static fn() => $docs->revise( $document->id, $metadata, null, $document->revision + 1, 'Revisão desactualizada' ) );
		$invalid_image = new DraftInput( 'Imagem inválida', 'Texto fictício', 'circular', new MediaInput( null, $source, 'PDF recusado como imagem' ), array(), array() );
		$expect_unchanged( static fn() => $news->create( $invalid_image, 'Validação posterior à cópia' ) );
		$assert( null !== $registry->find( $article->version( 1 )->content->attachments[0]->blob->id ) && null !== $registry->find( $document->version( 1 )->blob->id ), 'failed revisions preserve original file references' );
	} finally {
		$db->query( 'ROLLBACK' );
		foreach ( $connections as $connection ) {
			$connection->close();
		}
		// Test-owned directory only. No recursion or application storage roots.
		foreach ( array( 'private', 'public' ) as $directory ) {
			$paths = glob( $base . '/' . $directory . '/*' );
			foreach ( false === $paths ? array() : $paths as $path ) {
				if ( dirname( $path ) !== $base . '/' . $directory ) {
					throw new RuntimeException( 'Unexpected fixture path.' );
				}
				unlink( $path );
			}
			rmdir( $base . '/' . $directory );
		}
		rmdir( $base );
	}
};
