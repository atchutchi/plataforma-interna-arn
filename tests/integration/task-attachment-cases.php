<?php
/** Atomic private files on the exclusive task SQL harness, no institutional data.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Documents\PrivateFiles\{FileSchema, PrivateBlobStore, WpdbFileRegistry, BlobDescriptor, ReconciliationSnapshot, FileReconciliation};
use Arn\Intranet\Cms\{SettingsSchema, SettingsRepository, CurrentActor as CmsActor};
// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals, WordPress.PHP.DevelopmentFunctions, WordPress.PHP.DiscouragedPHPFunctions
if ( ! isset( $repo, $owned_tables, $db, $prefix, $ids ) || ! str_starts_with( $prefix, 'arn_tsk_' ) || ! defined( 'ARN_TASKS_SQL_HARNESS' ) ) {
	throw new RuntimeException( 'Ensaio isolado obrigatório.' ); }
$file_schema = new FileSchema( $db );
foreach ( array( 'files', 'audit', 'schema' ) as $part ) {
	$table = $file_schema->table( $part );
	$assert( '0' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) ), 'tabela de ficheiros exclusiva ausente' );
	$owned_tables[] = $table;
}
$file_schema->install();
if ( \Arn\Intranet\Cms\Configuration::current() ) {
	$cms_schema = new SettingsSchema( $db );
	foreach ( array( 'state', 'audit', 'schema' ) as $part ) {
		$owned_tables[] = $cms_schema->table( $part ); }
	$cms_schema->install();
	wp_set_current_user( $admin );
	( new SettingsRepository( $db, new CmsActor( $db, $clock ), $clock ) )->initialize();
}
$attachment_base = sys_get_temp_dir() . '/arn-task-files-' . bin2hex( random_bytes( 8 ) );
mkdir( $attachment_base, 0700 );
mkdir( $attachment_base . '/private', 0700 );
mkdir( $attachment_base . '/public', 0700 );
$attachment_png  = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC', true );
$attachment_path = $attachment_base . '/public/input.png';
file_put_contents( $attachment_path, $attachment_png );
$attachment_store = new PrivateBlobStore( $attachment_base . '/private', array( $attachment_base . '/public' ), array( 'image/png' ), 1048576 );
$attachment_blobs = array();
$make_blob        = static function () use ( $attachment_store, $attachment_path, &$attachment_blobs ): BlobDescriptor {
	$blob               = $attachment_store->importFile( $attachment_path );
	$attachment_blobs[] = $blob;
	return $blob;
};
try {
	wp_set_current_user( $admin );
	$service->changeState( $ids['a'], \Arn\Intranet\Authorization\AccountState::Active, $auth->find( $ids['a'] )->revision, $context() );
	$login( $ids['manager'] );
	$task = $repo->create( new \Arn\Intranet\Tasks\TaskContent( 'Anexos sintéticos', '', 'normal', null ), $ids['a'], bin2hex( random_bytes( 16 ) ) );
	$login( $ids['a'] );
	$original  = $make_blob();
	$thumbnail = $make_blob();
	$blob      = new BlobDescriptor( $original->id, $original->sha256, $original->size, $original->mime, array( 'thumbnail' => $thumbnail ) );
	$task      = $repo->attach( $task->id, $blob, 'Imagem sintética', 1, $ids['a'] );
	$assert( 2 === $task->revision && 1 === count( $repo->find( $task->id )->attachments ), 'anexo persistido e reconstituído' );
	$registry = new WpdbFileRegistry( $db );
	$assert( $registry->find( $blob->id )?->version === 2 && $registry->find( $thumbnail->id )?->object_type === 'task_attachment', 'original e variante associados à revisão' );
	foreach ( array( 'original', 'thumbnail' ) as $variant ) {
		$file = $repo->file( $task->id, $blob->id, $variant, $attachment_store );
		$assert( null !== $file && stream_get_contents( $file['handle'] ) === $attachment_png, 'bytes autorizados ' . $variant );
		fclose( $file['handle'] );
	}
	$assert( null === $repo->file( $task->id, $thumbnail->id, 'original', $attachment_store ), 'ID directo de derivado recusado' );
	$assert( null === $repo->file( $task->id + 9000, $blob->id, 'original', $attachment_store ), 'objecto errado recusado' );
	$counts    = static function () use ( $db, $schema, $file_schema ): array {
		$result = array();
		foreach ( array( $schema->table( 'tasks' ), $schema->table( 'audit' ), $schema->table( 'notifications' ), $file_schema->table( 'files' ), $file_schema->table( 'audit' ) ) as $table ) {
			$result[] = $db->get_results( $db->prepare( 'SELECT * FROM %i', $table ), ARRAY_A );
		} return $result;
	};
	$before    = $counts();
	$candidate = $make_blob();
	$trigger   = $prefix . 'attachment_fault';
	$assert( false !== $db->query( $db->prepare( 'CREATE TRIGGER %i BEFORE INSERT ON %i FOR EACH ROW SIGNAL SQLSTATE %s SET MESSAGE_TEXT=%s', $trigger, $schema->table( 'audit' ), '45000', 'Falha de anexo sintética' ) ), 'injecção SQL própria' );
	try {
		$unready( static fn() => $repo->attach( $task->id, $candidate, 'Recusado', 2, $ids['a'] ) ); } finally {
		$db->query( $db->prepare( 'DROP TRIGGER %i', $trigger ) ); }
		$assert( $before === $counts() && null === $registry->find( $candidate->id ), 'rollback de ficheiro auditoria tarefa e aviso' );
		try {
			$repo->attach( $task->id, $candidate, 'Revisão antiga', 1, $ids['a'] );
			$assert( false, 'CAS obrigatório' );
		} catch ( DomainException ) {
			$assert( $before === $counts(), 'CAS não escreve' ); }

		// A grant can expire while SQL/file locks are held, without any concurrent write.
		wp_set_current_user( $admin );
		foreach ( $auth->find( $ids['a'] )->stored_grants as $stored ) {
			if ( $stored->grant->revoked ) {
				continue; }
			$service->revokeGrant( $ids['a'], $stored->id, $auth->find( $ids['a'] )->revision, $context() );
		}
		$expiring = $service->createGrant( new \Arn\Intranet\Authorization\Grant( $ids['a'], \Arn\Intranet\Authorization\Role::Employee, \Arn\Intranet\Authorization\Scope::own(), $now, $now + 1 ), $auth->find( $ids['a'] )->revision, $context() );
		$login( $ids['a'] );
		foreach ( array( 2, 3 ) as $expires_on ) {
			$calls         = 0;
			$moving_clock  = static function () use ( &$calls, $now, $expires_on ): int {
				return ++$calls >= $expires_on ? $now + 2 : $now;
			};
			$timed         = new \Arn\Intranet\Tasks\TaskRepository( $db, $actors, $moving_clock );
			$before_expiry = $counts();
			try {
				$timed->attach( $task->id, $candidate, 'Concessão expirada', 2, $ids['a'] );
				$assert( false, 'expiração durante escrita recusada' );
			} catch ( DomainException ) {
				$assert( $calls >= $expires_on && $before_expiry === $counts(), 'expiração antes/depois de persistir reverte tudo' );
			}
			$calls = 0;
			$assert( null === $timed->file( $task->id, $blob->id, 'original', $attachment_store ) && $calls >= $expires_on, 'expiração antes/depois de abrir não entrega bytes' );
		}
		wp_set_current_user( $admin );
		$service->revokeGrant( $ids['a'], $expiring, $auth->find( $ids['a'] )->revision, $context() );
		$service->createGrant( new \Arn\Intranet\Authorization\Grant( $ids['a'], \Arn\Intranet\Authorization\Role::Employee, \Arn\Intranet\Authorization\Scope::own(), 0, null ), $auth->find( $ids['a'] )->revision, $context() );
		$login( $ids['b'] );
		$assert( null === $repo->file( $task->id, $blob->id, 'original', $attachment_store ), 'outra unidade recusada' );
		wp_set_current_user( $admin );
		$assert( null === $repo->file( $task->id, $blob->id, 'original', $attachment_store ), 'administrador técnico recusado' );
		$login( $ids['manager'] );
		$task = $repo->change(
			$task->id,
			'reassign',
			2,
			array(
				'assignee_id' => $ids['manager'],
				'reason'      => 'Transferência fictícia',
			)
		);
	$login( $ids['a'] );
	$assert( null === $repo->file( $task->id, $blob->id, 'original', $attachment_store ), 'antigo responsável perde bytes' );
	$login( $ids['manager'] );
	$file = $repo->file( $task->id, $blob->id, 'original', $attachment_store );
	$assert( null !== $file, 'novo responsável conserva anexo antigo' );
	fclose( $file['handle'] );
	$report = ( new FileReconciliation( ( new ReconciliationSnapshot( $db ) )->read( ... ), $attachment_store ) )->run();
	$bad    = array_filter( $report['findings'], static fn( array $finding ): bool => str_starts_with( $finding['code'], 'consumer_' ) );
	$assert( $report['complete'] && $report['coverage']['tasks'] && ! $bad, 'reconciliação reconhece tarefas e variantes' );

	$expected_snapshot = ( new ReconciliationSnapshot( $db ) )->read();
	$assert( in_array( $thumbnail->id, array_column( $expected_snapshot['tasks'], 'blob_id' ), true ), 'snapshot inclui derivado auditado' );
	$missing_variant = $expected_snapshot;
	foreach ( array( 'files', 'audit' ) as $part ) {
		$missing_variant[ $part ] = array_values( array_filter( $missing_variant[ $part ], static fn( array $row ): bool => $row['blob_id'] !== $thumbnail->id ) );
	}
	$observed = ( new FileReconciliation( static fn(): array => $missing_variant, $attachment_store ) )->run();
	$assert( in_array( 'consumer_unregistered', array_column( $observed['findings'], 'code' ), true ), 'derivado esperado sem registo/auditoria detectado' );
	$divergent = $expected_snapshot;
	foreach ( $divergent['tasks'] as &$reference ) {
		if ( $reference['blob_id'] === $thumbnail->id ) {
			$reference['sha256'] = str_repeat( 'f', 64 ); }
	} unset( $reference );
	$observed = ( new FileReconciliation( static fn(): array => $divergent, $attachment_store ) )->run();
	$assert( in_array( 'consumer_mismatch', array_column( $observed['findings'], 'code' ), true ), 'bytes e registo consistentes mas descritor divergente detectados' );
	$task = $repo->change( $task->id, 'cancel', 3, array( 'reason' => 'Fecho fictício' ) );
	try {
		$repo->attach( $task->id, $candidate, 'Terminal', 4, $ids['manager'] );
		$assert( false, 'terminal fechado' );
	} catch ( DomainException ) {
		$assert( true, 'terminal recusa novo anexo' ); }
	$file = $repo->file( $task->id, $blob->id, 'original', $attachment_store );
	$assert( null !== $file, 'terminal conserva leitura' );
	fclose( $file['handle'] );
	wp_set_current_user( $admin );
	$service->changeState( $ids['manager'], \Arn\Intranet\Authorization\AccountState::Suspended, $auth->find( $ids['manager'] )->revision, $context() );
	wp_set_current_user( $ids['manager'] );
	$assert( null === $repo->file( $task->id, $blob->id, 'original', $attachment_store ), 'suspensão fecha download' );
} finally {
	foreach ( $attachment_blobs as $owned_blob ) {
		unlink( $attachment_base . '/private/' . $owned_blob->id ); }
	unlink( $attachment_path );
	rmdir( $attachment_base . '/private' );
	rmdir( $attachment_base . '/public' );
	rmdir( $attachment_base );
}
