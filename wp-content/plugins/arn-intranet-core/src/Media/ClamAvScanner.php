<?php
/** Local ClamAV admission adapter.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Closure;
use Throwable;

// phpcs:disable Squiz.Commenting
/** Only a complete clean scan with recent official signatures admits a file. */
final readonly class ClamAvScanner {

	public function __construct( private string $executable, private string $database, private ?Closure $runner = null ) {}

	public function scan( string $path, string $job, ?float $deadline = null ): string {
		try {
			$seconds = min( 60, (int) floor( ( $deadline ?? microtime( true ) + 61 ) - microtime( true ) ) );
			if ( $seconds < 1 ) {
				return 'inconclusive';
			}
			$database = '\\' === DIRECTORY_SEPARATOR ? str_replace( '/', '\\', $this->database ) : $this->database;
			$path     = '\\' === DIRECTORY_SEPARATOR ? str_replace( '/', '\\', $path ) : $path;
			$job      = '\\' === DIRECTORY_SEPARATOR ? str_replace( '/', '\\', $job ) : $job;
			$command  = array( $this->executable, '--database=' . $database, '--official-db-only=yes', '--fail-if-cvd-older-than=3', '--alert-encrypted=yes', '--alert-exceeds-max=yes', '--max-filesize=2M', '--max-scansize=16M', '--max-files=100', '--max-recursion=8', '--max-scantime=15000', '--follow-file-symlinks=0', '--follow-dir-symlinks=0', '--tempdir=' . $job, '--', $path );
			$result   = null === $this->runner ? LocalProcess::run( $command, $job, $seconds ) : ( $this->runner )( $command, $job );
			if ( 1 === $result['code'] ) {
				return 'rejected';
			}
			if ( 0 !== $result['code'] || '' !== trim( $result['err'] ) || 1 !== preg_match( '/^Scanned files: 1\s*$/m', $result['out'] ) || 1 !== preg_match( '/^Infected files: 0\s*$/m', $result['out'] ) || preg_match( '/ERROR|WARNING|^Total errors: [1-9]/m', $result['out'] ) ) {
				return 'inconclusive';
			}
			return 'clean';
		} catch ( Throwable ) {
			return 'inconclusive';
		}
	}
}
