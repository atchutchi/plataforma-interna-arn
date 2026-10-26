<?php
/** Isolated SQL registration and rollback for processed fictitious media.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Documents\PrivateFiles\{BlobDescriptor, FileRecord, FileSchema, ImportBatch, PrivateBlobStore, VariantSelection, WpdbFileRegistry};
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Media\MediaPipeline;

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
$checks = 0;
$assert = static function ( bool $condition, string $label ) use ( &$checks ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Falhou: ' . $label );
	}
	++$checks;
};
$prefix = 'arn_mp_' . bin2hex( random_bytes( 6 ) ) . '_';
$db     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$schema = new FileSchema( $db );
$base   = sys_get_temp_dir() . '/arn-media-sql-' . bin2hex( random_bytes( 8 ) );
foreach ( array( '', '/public', '/private', '/quarantine' ) as $folder ) {
	mkdir( $base . $folder, 0700 );
}
$filter = null;
try {
	$schema->install();
	$registry = new WpdbFileRegistry( $db );
	$png      = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC', true );
	file_put_contents( $base . '/public/input.png', $png );
	$store    = new PrivateBlobStore( $base . '/private', array( $base . '/public' ), array( 'image/png' ), 1048576 );
	$pipeline = static fn(): MediaPipeline => new MediaPipeline(
		$base . '/quarantine',
		array( $base . '/public', $base . '/private' ),
		static fn(): string => 'clean',
		static function ( string $source, string $job ) use ( $png ): void {
		file_put_contents( $job . '/thumbnail.png', $png );
		file_put_contents( $job . '/preview.png', $png );
		},
		1
	);
	$make     = static function ( string $object_type, int $version = 1 ) use ( $store, $pipeline, $registry, $base ): BlobDescriptor {
		return ( new ImportBatch( $store, $pipeline() ) )->run(
			static function ( ImportBatch $batch ) use ( $object_type, $version, $base, $registry ): BlobDescriptor {
				$blob = $batch->importFile( $base . '/public/input.png' );
				$registry->register( new FileRecord( $blob, $object_type, 10, $version, 'original', 1, 100 ) );
				return $blob;
			},
			static fn(): WpdbFileRegistry => $registry
		);
	};
	foreach ( array( 'document', 'news_asset' ) as $object_type ) {
		$blob   = $make( $object_type );
		$record = $registry->find( $blob->id );
		$assert( $record instanceof FileRecord, 'original registado ' . $object_type );
		foreach ( array( 'thumbnail', 'preview' ) as $variant ) {
			$derived = $registry->findForObject( $object_type, 10, 1, $variant );
			$assert( $derived?->blob->id === $blob->derivatives[ $variant ]->id, 'variante ligada ' . $object_type . $variant );
			$assert( 1 === $derived->actor_id && 100 === $derived->created_at, 'actor e instante herdados' );
			$assert( VariantSelection::select( $registry, $record, $variant )?->id === $derived->blob->id, 'selecção exacta' );
		}
		$assert( null === VariantSelection::select( $registry, $record, 'unknown' ), 'variante desconhecida recusada' );
	}
	$count     = static fn( string $table ): int => (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $schema->table( $table ) ) );
	$assert( 6 === $count( 'files' ) && 6 === $count( 'audit' ), 'seis referências e seis eventos' );
	$inventory = $store->inventory();
	try {
		$make( 'document' );
		throw new LogicException( 'Duplicação aceite.' );
	} catch ( RuntimeException ) {
		$assert( 6 === $count( 'files' ) && 6 === $count( 'audit' ), 'rollback por tuple duplicado' );
		$assert( array_keys( $inventory ) === array_keys( $store->inventory() ), 'remove apenas cópias novas não registadas' );
	}
	$filter = static fn( string $sql ): string => str_starts_with( $sql, 'INSERT INTO `' . $schema->table( 'files' ) . '`' ) && str_contains( $sql, "'preview'" ) ? 'INVALID DERIVATIVE WRITE' : $sql;
	add_filter( 'query', $filter );
	try {
		$make( 'document', 2 );
		throw new LogicException( 'Falha de derivado aceite.' );
	} catch ( RuntimeException ) {
		$assert( 6 === $count( 'files' ) && 6 === $count( 'audit' ), 'falha no segundo derivado reverte original, thumbnail e eventos' );
	}
	remove_filter( 'query', $filter );
	$filter = null;
	$assert( array_keys( $inventory ) === array_keys( $store->inventory() ), 'falha parcial conserva todos os originais anteriores' );
	$assert( 4 === count( glob( $base . '/quarantine/*/source' ) ), 'quarentena conserva as quatro tentativas' );
	$assert( hash( 'sha256', $png ) === hash_file( 'sha256', $base . '/public/input.png' ), 'fonte conservada' );
	// Corruption never makes a derivative selectable by an unrelated version.
	$db->query( $db->prepare( 'UPDATE %i SET actor_id=2 WHERE variant=%s', $schema->table( 'files' ), 'thumbnail' ) );
	$record = $registry->findForObject( 'document', 10, 1, 'original' );
	$assert( null === VariantSelection::select( $registry, $record, 'thumbnail' ), 'actor divergente recusado' );

	// Queue completion uses the same SQL lifecycle and cleanup, with scanned copies.
	mkdir( $base . '/queue', 0700 );
	$queue    = new \Arn\Intranet\Media\MediaQueue( $base . '/queue', array( $base . '/public', $base . '/quarantine', $base . '/private' ), static fn( int $actor ): bool => in_array( $actor, array( 1, 42 ), true ), static fn(): int => time(), false );
	$intent   = \Arn\Intranet\Media\ConsumerJobs::intent(
		'tasks',
		10,
		1,
		'Anexo fictício da fila SQL',
		array(
			'label' => 'Imagem fictícia',
			'view'  => 'own',
		)
	);
	$queued   = static function () use ( $queue, $base, $intent, $pipeline, $assert, $count ): string {
		$before   = $count( 'files' );
		$batch_id = $queue->enqueue( array( $base . '/public/input.png' ), 42, null, $intent );
		$queue->work( 1, 1, $pipeline );
		$assert( 'ready' === $queue->read( $batch_id, 42 )['items'][0]['status'], 'resultado técnico pronto' );
		$assert( $before === $count( 'files' ), 'worker não regista ficheiros de negócio' );
		return $batch_id;
	};
	$complete = static function ( string $batch_id, int $version, bool $lost = false ) use ( $queue, $base, $store, $registry ): int {
		$state = $queue->read( $batch_id, 42 );
		return $queue->complete(
			$batch_id,
			$state['revision'],
			42,
			static function (): void {},
			static function ( array $state, string $directory ) use ( $base, $store, $registry, $version, $lost ): int {
				$prepared = new \Arn\Intranet\Media\PreparedBatch( $state, $base . '/quarantine', array( $base . '/public', $base . '/queue', $base . '/private' ), time() );
				return ( new ImportBatch( $store, $prepared ) )->run(
					static function ( ImportBatch $batch ) use ( $state, $directory, $registry, $version, $lost ): int {
						$blob = $batch->importFile( $directory . '/' . $state['items'][0]['blob']['id'] );
						$registry->register( new FileRecord( $blob, 'task_attachment', 10, $version, 'original', 42, 101 ) );
						if ( $lost ) {
							throw new RuntimeException( 'Resposta perdida após commit confirmado.' ); }
						return 10;
					},
					static fn(): WpdbFileRegistry => $registry
				);
			}
		);
	};
	$batch_id = $queued();
	$assert( 10 === $complete( $batch_id, 1 ), 'conclusão da fila com registo SQL' );
	$assert( 9 === $count( 'files' ) && 9 === $count( 'audit' ), 'original e derivados auditados' );
	$assert( 42 === $registry->findForObject( 'task_attachment', 10, 1, 'original' )->actor_id, 'registo pertence ao autor, não ao worker' );
	$assert( 10 === $complete( $batch_id, 1 ) && 9 === $count( 'files' ), 'recibo repetido sem inserções' );
	$inventory = $store->inventory();
	$batch_id  = $queued();
	try {
		$complete( $batch_id, 1 );
		throw new LogicException( 'Tuple duplicado aceite.' );
	} catch ( RuntimeException ) {
		$assert( 'applying' === $queue->read( $batch_id, 42 )['consumption'], 'excepção conserva intenção durável' );
	}
		$assert( 'applying' === $queue->read( $batch_id, 42 )['consumption'], 'falha SQL conserva intenção sem repetição' );
		$assert( array_keys( $inventory ) === array_keys( $store->inventory() ) && 9 === $count( 'files' ), 'rollback elimina apenas cópias próprias sem registo' );
		$batch_id = $queued();
	try {
		$complete( $batch_id, 2, true );
		throw new LogicException( 'Resposta perdida não simulada.' );
	} catch ( RuntimeException ) {
		$assert( 'applying' === $queue->read( $batch_id, 42 )['consumption'], 'excepção conserva intenção durável' );
	}
			$assert( 12 === $count( 'files' ) && 12 === $count( 'audit' ), 'resposta perdida conserva referências confirmadas' );
			$assert( count( $inventory ) + 3 === count( $store->inventory() ), 'resposta perdida conserva originais e derivados registados' );
	try {
		$complete( $batch_id, 2 );
		throw new LogicException( 'Conclusão incerta repetida.' );
	} catch ( RuntimeException ) {
		$assert( 'applying' === $queue->read( $batch_id, 42 )['consumption'], 'excepção conserva intenção durável' );
	}
				$assert( 'applying' === $queue->read( $batch_id, 42 )['consumption'] && 12 === $count( 'files' ), 'resultado incerto permanece bloqueado' );
	// PDF variants use the same atomic registry, never an independent audience.
	file_put_contents( $base . '/public/input.pdf', "%PDF-1.4\n%%EOF\n" );
	$pdf_store    = new PrivateBlobStore( $base . '/private', array( $base . '/public' ), array( 'application/pdf', 'image/png' ), 1048576 );
	$pdf_pipeline = new MediaPipeline(
		$base . '/quarantine',
		array( $base . '/public', $base . '/private' ),
		static fn(): string => 'clean',
		static function (): void {},
		42,
		static function ( string $source, string $job ) use ( $png ): void {
			file_put_contents( $job . '/thumbnail.png', $png );
			file_put_contents( $job . '/preview.png', $png );
		}
	);
	foreach ( array( 'document', 'news_asset', 'task_attachment' ) as $pdf_type ) {
		$pdf    = ( new ImportBatch( $pdf_store, $pdf_pipeline ) )->run(
			static function ( ImportBatch $batch ) use ( $base, $registry, $pdf_type ): BlobDescriptor {
				$blob = $batch->importFile( $base . '/public/input.pdf' );
				$registry->register( new FileRecord( $blob, $pdf_type, 90, 1, 'original', 42, 200 ) );
				return $blob;
			},
			static fn(): WpdbFileRegistry => $registry
		);
		$record = $registry->find( $pdf->id );
		$assert( 'application/pdf' === $record->blob->mime, 'original PDF ' . $pdf_type );
		foreach ( array( 'thumbnail', 'preview' ) as $variant ) {
			$derived = VariantSelection::select( $registry, $record, $variant );
			$assert( $pdf->derivatives[ $variant ]->id === $derived?->id, 'derivado PDF exacto ' . $pdf_type . $variant );
			$assert( 'image/png' === $derived->mime, 'derivado PDF estático' );
			$handle = $pdf_store->open( $derived );
			$assert( stream_get_contents( $handle ) === $png, 'bytes PDF derivados verificados' );
			fclose( $handle );
		}
	}
	$assert( 21 === $count( 'files' ) && 21 === $count( 'audit' ), 'três PDFs e seis derivados registados e auditados' );
	$legacy = $pdf_store->importFile( $base . '/public/input.pdf' );
	$registry->register( new FileRecord( $legacy, 'document', 91, 1, 'original', 42, 201 ) );
	$legacy_record = $registry->find( $legacy->id );
	$assert( null === VariantSelection::select( $registry, $legacy_record, 'preview' ), 'PDF legado não devolve original como imagem' );
	$selected_legacy = VariantSelection::select( $registry, $legacy_record, 'original' );
	$assert( $selected_legacy?->id === $legacy->id, 'download legado conservado' );
	echo esc_html( 'Media privada SQL: ' . $checks . " verificações.\n" );
} finally {
	if ( null !== $filter ) {
		remove_filter( 'query', $filter );
	}
	$db->query( 'ROLLBACK' );
	foreach ( array( 'audit', 'files', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $schema->table( $name ) ) );
	}
	$db->close();
	// Exclusive randomly named test root only. No application originals are removed.
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $entry ) {
		$entry->isDir() && ! $entry->isLink() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
	}
	rmdir( $base );
}
