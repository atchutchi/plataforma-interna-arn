<?php
/** Read-only reconciliation against disposable physical fixtures.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Documents\PrivateFiles\{BlobDescriptor, FileReconciliation, PrivateBlobStore, ReconciliationPage};
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
final class FileReconciliationTest extends TestCase {
	private string $base;
	private PrivateBlobStore $store;
	private BlobDescriptor $blob;
	private array $data;

	protected function setUp(): void {
		$this->base = sys_get_temp_dir() . '/arn-reconciliation-unit-' . bin2hex( random_bytes( 8 ) );
		mkdir( $this->base );
		mkdir( $this->base . '/private' );
		mkdir( $this->base . '/public' );
		file_put_contents( $this->base . '/public/source.pdf', "%PDF-1.4\n%%EOF\n" );
		$this->store               = new PrivateBlobStore( $this->base . '/private', array( $this->base . '/public' ), array( 'application/pdf' ), 1024 );
		$this->blob                = $this->store->importFile( $this->base . '/public/source.pdf' );
		$this->data                = array(
			'files'     => array(
				array(
					'blob_id'     => $this->blob->id,
					'sha256'      => $this->blob->sha256,
					'size_bytes'  => $this->blob->size,
					'mime'        => $this->blob->mime,
					'object_type' => 'document',
					'object_id'   => 10,
					'version'     => 1,
					'variant'     => 'original',
					'actor_id'    => 2,
					'created_at'  => 100,
				),
			),
			'audit'     => array(
				array(
					'blob_id'     => $this->blob->id,
					'event'       => 'file_registered',
					'actor_id'    => 2,
					'occurred_at' => 100,
				),
			),
			'documents' => array(),
			'news'      => array(),
		);
		$this->data['documents'][] = $this->data['files'][0] + array( 'parent_id' => 10 );
	}

	protected function tearDown(): void {
		foreach ( array( 'private', 'public' ) as $folder ) {
			foreach ( glob( $this->base . '/' . $folder . '/*' ) as $path ) {
				if ( is_dir( $path ) && ! is_link( $path ) ) {
					rmdir( $path );
				} else {
					unlink( $path );
				}
			}
			rmdir( $this->base . '/' . $folder );
		}
		rmdir( $this->base );
	}

	private function report(): array {
		return ( new FileReconciliation( fn(): array => $this->data, $this->store ) )->run();
	}

	private function codes(): array {
		return array_column( $this->report()['findings'], 'code' );
	}

	public function testHealthySnapshotDoesNotChangeBytesOrRows(): void {
		$before = $this->store->inventory();
		$data   = $this->data;
		$r      = $this->report();
		self::assertTrue( $r['complete'] );
		self::assertSame( 1, $r['checked'] );
		self::assertSame( 0, $r['total_findings'] );
		self::assertSame( $before, $this->store->inventory() );
		self::assertSame( $data, $this->data );
		self::assertSame( $this->blob->sha256, hash_file( 'sha256', $this->base . '/private/' . $this->blob->id ) );
	}

	public function testMissingBytesAndOrphanCandidateAreDifferent(): void {
		$id = str_repeat( 'b', 32 );
		rename( $this->base . '/private/' . $this->blob->id, $this->base . '/private/' . $id );
		self::assertContains( 'file_missing', $this->codes() );
		self::assertContains( 'orphan_candidate', $this->codes() );
		self::assertSame( $this->blob->sha256, hash_file( 'sha256', $this->base . '/private/' . $id ) );
	}
	public function testPdfDerivativesMatchAllConsumersWithoutWeakeningActorChecks(): void {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- One-pixel synthetic PNG, no executable code.
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC', true );
		file_put_contents( $this->base . '/public/preview.png', $png );
		$this->store = new PrivateBlobStore( $this->base . '/private', array( $this->base . '/public' ), array( 'application/pdf', 'image/png' ), 1024 );
		$preview     = $this->store->importFile( $this->base . '/public/preview.png' );
		$base        = $this->data;
		foreach ( array(
			'document'        => 'documents',
			'news_asset'      => 'news',
			'task_attachment' => 'tasks',
		) as $type => $consumer ) {
			$this->data                            = $base;
			$this->data['files'][0]['object_type'] = $type;
			$this->data['files'][]                 = array_replace(
				$this->data['files'][0],
				array(
					'blob_id'    => $preview->id,
					'sha256'     => $preview->sha256,
					'size_bytes' => $preview->size,
					'mime'       => 'image/png',
					'variant'    => 'preview',
				)
			);
			$this->data['audit'][]                 = array_replace( $this->data['audit'][0], array( 'blob_id' => $preview->id ) );
			$this->data['documents']               = array();
			$this->data[ $consumer ]               = array(
				$this->data['files'][0] + array(
					'parent_id'      => 10,
					'parent_version' => 1,
				),
			);
			if ( 'tasks' === $consumer ) {
				$this->data['tasks'][] = $this->data['files'][1] + array( 'parent_id' => 10 );
			}
			self::assertSame( array(), $this->codes(), $type );
			$this->data['files'][1]['actor_id'] = 99;
			self::assertContains( 'consumer_mismatch', $this->codes(), $type );
		}
	}

	public function testChangedHashAndMimeAreReportedWithoutRepair(): void {
		foreach ( array(
			'sha256'     => str_repeat( 'a', 64 ),
			'mime'       => 'image/png',
			'size_bytes' => 2,
		) as $field => $bad ) {
			$saved                            = $this->data;
			$this->data['files'][0][ $field ] = $bad;
			self::assertContains( 'file_mismatch', $this->codes() );
			self::assertContains( 'consumer_mismatch', $this->codes() );
			$this->data = $saved;
		}
		self::assertSame( $this->blob->sha256, hash_file( 'sha256', $this->base . '/private/' . $this->blob->id ) );
	}

	public function testAuditMissingDuplicateWrongActorAndUnregistered(): void {
		$event               = $this->data['audit'][0];
		$this->data['audit'] = array();
		self::assertContains( 'audit_missing', $this->codes() );
		$this->data['audit'] = array( $event, $event );
		self::assertContains( 'audit_mismatch', $this->codes() );
		$this->data['audit'] = array( array_replace( $event, array( 'actor_id' => 3 ) ) );
		self::assertContains( 'audit_mismatch', $this->codes() );
		$this->data['audit'] = array( array_replace( $event, array( 'occurred_at' => 200 ) ) );
		self::assertContains( 'audit_mismatch', $this->codes() );
		$this->data['audit'] = array( array_replace( $event, array( 'blob_id' => str_repeat( 'f', 32 ) ) ) );
		self::assertContains( 'audit_unregistered', $this->codes() );
	}

	public function testConsumerAbsenceCorruptionAndParentAreNotHidden(): void {
		$row                     = $this->data['documents'][0];
		$this->data['documents'] = array();
		self::assertContains( 'consumer_missing', $this->codes() );
		$this->data['documents'] = array( array_replace( $row, array( 'parent_id' => null ) ) );
		self::assertContains( 'consumer_parent_missing', $this->codes() );
		$this->data['files'] = array();
		self::assertContains( 'consumer_unregistered', $this->codes() );
	}

	public function testNewsUsesAssetIdentityAndChecksVersionParent(): void {
		$this->data['files'][0]['object_type'] = 'news_asset';
		$this->data['news']                    = array(
			$this->data['files'][0] + array(
				'parent_id'      => 77,
				'parent_version' => 4,
			),
		);
		$this->data['documents']               = array();
		self::assertSame( array(), $this->codes() );
		$this->data['news'][0]['parent_version'] = null;
		self::assertContains( 'consumer_parent_missing', $this->codes() );
		$this->data['news'][0]['object_id'] = 77;
		self::assertContains( 'consumer_mismatch', $this->codes() );
	}

	public function testAuditedTaskDerivativeSurvivesTotalRegistryAndBinaryLoss(): void {
		$this->data['files'][0]['object_type'] = 'task_attachment';
		$this->data['tasks']                   = array( $this->data['files'][0] + array( 'parent_id' => 10 ) );
		$this->data['documents']               = array();
		self::assertSame( array(), $this->codes() );
		$missing               = array_replace(
			$this->data['tasks'][0],
			array(
				'blob_id' => str_repeat( 'd', 32 ),
				'variant' => 'thumbnail',
				'mime'    => 'image/png',
			)
		);
		$this->data['tasks'][] = $missing;
		self::assertContains( 'consumer_unregistered', $this->codes() );
		$this->data['files'][] = array_replace( $missing, array( 'sha256' => str_repeat( 'e', 64 ) ) );
		self::assertContains( 'consumer_mismatch', $this->codes() );
	}

	public function testAbsentModuleAndUnknownTypeAreIncomplete(): void {
		$this->data['documents'] = null;
		self::assertFalse( $this->report()['complete'] );
		self::assertContains( 'consumer_unavailable', $this->codes() );
		$this->data['files'][0]['object_type'] = 'unsupported';
		self::assertContains( 'consumer_unsupported', $this->codes() );
		$this->data['files'][0]['blob_id'] = '../secret';
		self::assertContains( 'record_invalid', $this->codes() );
		self::assertStringNotContainsString( '../secret', json_encode( $this->report() ) );
	}

	public function testUnknownNamesDirectoriesAndLinksAreNotTraversed(): void {
		file_put_contents( $this->base . '/private/secret-name.txt', 'private' );
		mkdir( $this->base . '/private/' . str_repeat( 'c', 32 ) );
		self::assertContains( 'unrecognized_entry', $this->codes() );
		self::assertContains( 'unsafe_entry', $this->codes() );
		self::assertStringNotContainsString( 'secret-name', json_encode( $this->report() ) );
		self::assertStringNotContainsString( $this->base, json_encode( $this->report() ) );
	}

	public function testSymlinkIsNeverReadOrRemoved(): void {
		$target = $this->base . '/private/' . str_repeat( 'e', 32 );
		if ( ! @symlink( $this->base . '/public/source.pdf', $target ) ) {
			self::markTestSkipped( 'Symlink privilege unavailable.' );
		}
		self::assertContains( 'unsafe_entry', $this->codes() );
		self::assertTrue( is_link( $target ) );
		self::assertFileExists( $this->base . '/public/source.pdf' );
	}

	public function testByteAndEntryLimitsDoNotClaimIntegrity(): void {
		self::assertSame( 'limit', $this->store->diagnose( $this->blob, 1 ) );
		file_put_contents( $this->base . '/private/' . $this->blob->id, str_repeat( 'x', 1025 ) );
		self::assertFalse( $this->report()['complete'] );
		self::assertContains( 'file_limit', $this->codes() );
		file_put_contents( $this->base . '/private/extra', 'x' );
		$this->expectException( RuntimeException::class );
		$this->store->inventory( 1 );
	}

	public function testLocalCatalogRejectsImpossibleTuples(): void {
		$this->data['documents']               = array();
		$this->data['files'][0]['object_type'] = 'local_document';
		self::assertContains( 'consumer_mismatch', $this->codes() );
		$this->data['files'][0]['object_id'] = 1;
		self::assertSame( array(), $this->codes() );
		$this->data['files'][0]['version'] = 99;
		self::assertContains( 'consumer_mismatch', $this->codes() );
		$this->data['files'][0]['version'] = 1;
		$this->data['files'][0]['variant'] = 'thumbnail';
		self::assertContains( 'consumer_mismatch', $this->codes() );
	}

	public function testActualReadAccountingIncludesFailuresAndEnforcesBudget(): void {
		$bytes = null;
		self::assertSame( 'verified', $this->store->diagnose( $this->blob, 1024, $bytes ) );
		self::assertSame( $this->blob->size, $bytes );
		file_put_contents( $this->base . '/private/' . $this->blob->id, str_repeat( 'x', $this->blob->size ) );
		self::assertSame( 'unreadable', $this->store->diagnose( $this->blob, 1024, $bytes ) );
		self::assertSame( $this->blob->size, $bytes );
		$handle = fopen( $this->base . '/public/source.pdf', 'rb' );
		$method = new ReflectionMethod( PrivateBlobStore::class, 'inspectHandle' );
		try {
			$method->invokeArgs( $this->store, array( $handle, 1, &$bytes ) );
			self::fail( 'Read budget ignored.' );
		} catch ( RuntimeException ) {
			self::assertSame( 1, $bytes );
		} finally {
			fclose( $handle );
		}
	}

	public function testChangedSqlAndPhysicalInventoryAreInconclusive(): void {
		$calls  = 0;
		$source = function () use ( &$calls ): array {
			++$calls;
			if ( 2 === $calls ) {
				$this->data['audit'] = array();
			}
			return $this->data;
		};
		$r      = ( new FileReconciliation( $source, $this->store ) )->run();
		self::assertFalse( $r['complete'] );
		self::assertContains( 'source_changed', array_column( $r['findings'], 'code' ) );
		$source = function (): array {
			file_put_contents( $this->base . '/private/new-file', 'x' );
			return $this->data;
		};
		$r      = ( new FileReconciliation( $source, $this->store ) )->run();
		self::assertFalse( $r['complete'] );
		self::assertContains( 'source_changed', array_column( $r['findings'], 'code' ) );
	}

	public function testDatabaseFailureDoesNotLeakReasonOrClaimClean(): void {
		$r = ( new FileReconciliation( static fn() => throw new RuntimeException( '/private/password' ), $this->store ) )->run();
		self::assertFalse( $r['complete'] );
		self::assertSame( 'incomplete', $r['findings'][0]['code'] );
		self::assertStringNotContainsString( 'password', json_encode( $r ) );
	}

	public function testReportIsBoundedAndNeverContainsArbitraryNames(): void {
		for ( $i = 0; $i < 205; ++$i ) {
			file_put_contents( $this->base . '/private/' . str_pad( dechex( $i ), 32, '0', STR_PAD_LEFT ), 'x' );
		}
		$r = $this->report();
		self::assertSame( 200, count( $r['findings'] ) );
		self::assertSame( 205, $r['total_findings'] );
		self::assertCount( 206, $this->store->inventory() );
	}

	public function testTechnicalGateRequiresExactLocalOptInAndNativeSession(): void {
		$valid = array( 'local', true, 3, 3, 'fixture@example.invalid', true, true );
		self::assertTrue( ReconciliationPage::enabled( ...$valid ) );
		foreach ( array(
			0 => 'production',
			1 => 'true',
			2 => '3',
			3 => 4,
			4 => 'person@example.org',
			5 => false,
			6 => false,
		) as $key => $bad ) {
			$args         = $valid;
			$args[ $key ] = $bad;
			self::assertFalse( ReconciliationPage::enabled( ...$args ) );
		}
	}

	public function testHttpShapeDoesNotAcceptRepairPathsOrMethods(): void {
		$get    = array( 'page' => 'arn-file-reconciliation' );
		$post   = array(
			'nonce'  => 'good',
			'action' => 'diagnose',
		);
		$verify = static fn( string $nonce ): bool => 'good' === $nonce;
		self::assertTrue( ReconciliationPage::requestValid( 'GET', $get, array(), $verify ) );
		self::assertTrue( ReconciliationPage::requestValid( 'POST', $get, $post, $verify ) );
		self::assertFalse( ReconciliationPage::requestValid( 'POST', $get, array_replace( $post, array( 'nonce' => 'bad' ) ), $verify ) );
		self::assertFalse( ReconciliationPage::requestValid( 'POST', $get, array_replace( $post, array( 'action' => 'repair' ) ), $verify ) );
		self::assertFalse( ReconciliationPage::requestValid( 'POST', $get, $post + array( 'path' => '/private' ), $verify ) );
		foreach ( array( 'HEAD', 'DELETE', 'PUT', 'OPTIONS' ) as $method ) {
			self::assertFalse( ReconciliationPage::requestValid( $method, $get, array(), $verify ) );
		}
	}
}
