<?php
/** Non-destructive comparison of registry, audit, consumers and bytes.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

use Closure;
use Throwable;

// phpcs:disable Squiz.Commenting
/** All findings are observations, never authority to delete or repair. */
final class FileReconciliation {
	public const LABELS = array(
		'file_missing'            => 'Binário em falta',
		'file_mismatch'           => 'Conteúdo divergente',
		'file_unreadable'         => 'Binário não verificável',
		'file_limit'              => 'Limite de leitura atingido',
		'file_changed'            => 'Binário alterado durante a leitura',
		'record_invalid'          => 'Registo inválido',
		'audit_missing'           => 'Auditoria em falta',
		'audit_mismatch'          => 'Auditoria divergente ou duplicada',
		'audit_unregistered'      => 'Auditoria sem registo',
		'consumer_missing'        => 'Referência de negócio em falta',
		'consumer_mismatch'       => 'Referência de negócio divergente',
		'consumer_unregistered'   => 'Referência sem registo de ficheiro',
		'consumer_parent_missing' => 'Objecto ou versão de negócio em falta',
		'consumer_unavailable'    => 'Módulo de origem não instalado',
		'consumer_unsupported'    => 'Tipo sem adaptador de reconciliação',
		'orphan_candidate'        => 'Possível órfão privado',
		'unrecognized_entry'      => 'Entrada não reconhecida no armazenamento',
		'unsafe_entry'            => 'Entrada não regular ou com ligações',
		'source_changed'          => 'Fontes alteradas durante a análise',
		'incomplete'              => 'Análise incompleta ou indisponível',
	);

	public function __construct( private readonly Closure $snapshot, private readonly PrivateBlobStore $store ) {}

	public function run(): array {
		$report = array(
			'id'             => bin2hex( random_bytes( 16 ) ),
			'started_at'     => time(),
			'complete'       => true,
			'checked'        => 0,
			'findings'       => array(),
			'total_findings' => 0,
			'coverage'       => array(),
		);
		$add    = static function ( string $code, ?string $id = null ) use ( &$report ): void {
			++$report['total_findings'];
			if ( count( $report['findings'] ) < 200 ) {
				$report['findings'][] = array(
					'code' => $code,
					'id'   => is_string( $id ) && 1 === preg_match( '/^[a-f0-9]{32}$/D', $id ) ? $id : null,
				);
			}
		};
		try {
			$inventory          = $this->store->inventory();
			$data               = ( $this->snapshot )();
			$report['coverage'] = array(
				'documents' => null !== $data['documents'],
				'news'      => null !== $data['news'],
			);
			$files              = array_column( $data['files'], null, 'blob_id' );
			$audit              = array();
			foreach ( $data['audit'] as $event ) {
				$audit[ $event['blob_id'] ][] = $event;
				if ( ! isset( $files[ $event['blob_id'] ] ) ) {
					$add( 'audit_unregistered', $event['blob_id'] );
				}
			}
			$consumers = array();
			foreach ( array(
				'document'   => 'documents',
				'news_asset' => 'news',
			) as $type => $source ) {
				$consumers[ $type ] = null === $data[ $source ] ? null : array_column( $data[ $source ], null, 'blob_id' );
				foreach ( $data[ $source ] ?? array() as $row ) {
					$id = $row['blob_id'];
					if ( null === $row['parent_id'] || ( 'news' === $source && null === $row['parent_version'] ) ) {
						$add( 'consumer_parent_missing', $id );
					}
					if ( ! isset( $files[ $id ] ) ) {
						$add( 'consumer_unregistered', $id );
					} elseif ( ! self::matches( $files[ $id ], $row, $type ) ) {
						$add( 'consumer_mismatch', $id );
					}
				}
			}
			$budget   = 67108864;
			$deadline = microtime( true ) + 10;
			foreach ( $files as $id => $row ) {
				try {
					$record = new FileRecord( new BlobDescriptor( $id, $row['sha256'], (int) $row['size_bytes'], $row['mime'] ), $row['object_type'], (int) $row['object_id'], (int) $row['version'], $row['variant'], (int) $row['actor_id'], (int) $row['created_at'] );
				} catch ( Throwable ) {
					$add( 'record_invalid', $id );
					continue;
				}
				$events = $audit[ $id ] ?? array();
				if ( array() === $events ) {
					$add( 'audit_missing', $id );
				} elseif ( 1 !== count( $events ) || 'file_registered' !== $events[0]['event'] || (string) $events[0]['actor_id'] !== (string) $row['actor_id'] || (string) $events[0]['occurred_at'] !== (string) $row['created_at'] ) {
					$add( 'audit_mismatch', $id );
				}
				$type = $record->object_type;
				if ( array_key_exists( $type, $consumers ) ) {
					if ( null === $consumers[ $type ] ) {
						$report['complete'] = false;
						$add( 'consumer_unavailable', $id );
					} elseif ( ! isset( $consumers[ $type ][ $id ] ) ) {
						$add( 'consumer_missing', $id );
					}
				} elseif ( 'local_document' === $type ) {
					$mimes = array(
						'original'  => 'application/pdf',
						'thumbnail' => 'image/png',
						'preview'   => 'image/jpeg',
					);
					if ( ! in_array( $record->object_id, array( 1, 2, 3 ), true ) || ! ( 2 === $record->version || ( 1 === $record->version && 'original' === $record->variant ) ) || $mimes[ $record->variant ] !== $record->blob->mime ) {
						$add( 'consumer_mismatch', $id );
					}
				} else {
					$report['complete'] = false;
					$add( 'consumer_unsupported', $id );
				}
				if ( ! isset( $inventory[ $id ] ) ) {
					$add( 'file_missing', $id );
					continue;
				}
				$read_bytes = 0;
				$status     = $budget < 1 || microtime( true ) > $deadline ? 'limit' : $this->store->diagnose( $record->blob, $budget, $read_bytes );
				$budget    -= $read_bytes;
				if ( 'verified' === $status ) {
					++$report['checked'];
				} else {
					$add( 'file_' . $status, $id );
					if ( 'mismatch' !== $status ) {
						$report['complete'] = false;
					}
				}
			}
			foreach ( $inventory as $id => $stat ) {
				if ( str_starts_with( $id, 'unknown:' ) ) {
					$add( 'unrecognized_entry' );
				} elseif ( 0100000 !== ( $stat['mode'] & 0170000 ) || 1 !== $stat['nlink'] ) {
					$add( 'unsafe_entry', $id );
				} elseif ( ! isset( $files[ $id ] ) ) {
					$add( 'orphan_candidate', $id );
				}
			}
			if ( $this->store->inventory() !== $inventory || ( $this->snapshot )() !== $data ) {
				$report['complete'] = false;
				$add( 'source_changed' );
			}
		} catch ( Throwable ) {
			$report['complete'] = false;
			$add( 'incomplete' );
		}
		$report['finished_at'] = time();
		return $report;
	}

	private static function matches( array $file, array $consumer, string $type ): bool {
		if ( $file['object_type'] !== $type || 'original' !== $file['variant'] ) {
			return false;
		}
		foreach ( array( 'object_id', 'version', 'sha256', 'size_bytes', 'mime', 'actor_id', 'created_at' ) as $key ) {
			if ( (string) $file[ $key ] !== (string) $consumer[ $key ] ) {
				return false;
			}
		}
		return true;
	}
}
