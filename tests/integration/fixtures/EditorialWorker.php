<?php
/** Own-process lifecycle and robust shared-lock markers for integration.
 *
 * @package ArnIntranet
 */

namespace Arn\Intranet\Tests\Fixtures;

use RuntimeException;

require_once __DIR__ . '/EditorialContentionTrace.php';

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open

/** Owns only its random temporary marker files and process. */
final class EditorialWorker {
	private $process;
	private array $pipes = array();
	private array $markers;
	private readonly EditorialContentionTrace $trace;
	private ?int $exit_code = null;
	public function __construct( string $prefix, int $editor, int $article, array $participants ) {
		$root          = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . bin2hex( random_bytes( 8 ) );
		$this->markers = array(
			'ready'   => $root . '.ready',
			'go'      => $root . '.go',
			'entered' => $root . '.entered',
			'trace'   => $root . '.trace',
		);
		$this->trace   = new EditorialContentionTrace( $this->markers['trace'] );
		$context       = array(
			'prefix'       => $prefix,
			'editor'       => $editor,
			'article'      => $article,
			'participants' => $participants,
		) + $this->markers;
		$command       = array( PHP_BINARY, $_SERVER['argv'][0], '--path=' . ABSPATH, '--user=' . wp_get_current_user()->user_login, 'eval-file', dirname( __DIR__ ) . '/news-editorial-worker.php' );
		$this->process = proc_open(
			$command,
			array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$this->pipes,
			getcwd(),
			array_merge( getenv(), array( 'ARN_EDITORIAL_CONTENTION' => wp_json_encode( $context ) ) )
		);
		if ( ! is_resource( $this->process ) ) {
			throw new RuntimeException( 'Processo exclusivo indisponível.' );
		}
		fclose( $this->pipes[0] );
	}
	public function ready(): bool {
		$deadline = microtime( true ) + 20;
		while ( ! is_file( $this->markers['ready'] ) && microtime( true ) < $deadline && proc_get_status( $this->process )['running'] ) {
			usleep( 50000 );
		}
		return is_file( $this->markers['ready'] );
	}
	public function start(): int {
		// An empty file is not a valid connection marker.
		file_put_contents( $this->markers['entered'], '' );
		file_put_contents( $this->markers['go'], 'go', LOCK_EX );
		$connection = 0;
		$deadline   = microtime( true ) + 20;
		while ( 0 === $connection && microtime( true ) < $deadline && proc_get_status( $this->process )['running'] ) {
			$handle = fopen( $this->markers['entered'], 'rb' );
			if ( false !== $handle ) {
				if ( flock( $handle, LOCK_SH ) ) {
					$contents = stream_get_contents( $handle );
					flock( $handle, LOCK_UN );
					if ( is_string( $contents ) && ctype_digit( $contents ) && (int) $contents > 0 ) {
						$connection = (int) $contents;
					}
				}
				fclose( $handle );
			}
			if ( 0 === $connection ) {
				usleep( 50000 );
			}
		}
		return $connection;
	}
	public function finish(): string {
		$output = stream_get_contents( $this->pipes[1] );
		$error  = stream_get_contents( $this->pipes[2] );
		fclose( $this->pipes[1] );
		fclose( $this->pipes[2] );
		$exit            = proc_close( $this->process );
		$this->exit_code = $exit;
		$this->process   = null;
		if ( 0 !== $exit || '' !== $error || ! in_array( $output, array( "EDITORIAL_CONFIRMED\n", "EDITORIAL_REFUSED\n" ), true ) ) {
			throw new RuntimeException( 'Processo exclusivo não confirmou resultado válido.' );
		}
		return trim( $output );
	}
	/** Closed fields only. Never read or publish stdout/stderr, argv or environment. */
	public function diagnostic(): array {
		$status = is_resource( $this->process ) ? proc_get_status( $this->process ) : false;
		$exit   = is_array( $status ) && is_int( $status['exitcode'] ) && $status['exitcode'] >= 0 ? $status['exitcode'] : $this->exit_code;
		return array(
			'running'   => is_array( $status ) ? $status['running'] : null,
			'exit_code' => $exit,
			'ready'     => is_file( $this->markers['ready'] ),
			'released'  => is_file( $this->markers['go'] ),
			'entered'   => is_file( $this->markers['entered'] ) && filesize( $this->markers['entered'] ) > 0,
			'trace'     => $this->trace->snapshot(),
		);
	}
	public function close(): void {
		if ( is_resource( $this->process ) ) {
			proc_terminate( $this->process );
			foreach ( $this->pipes as $pipe ) {
				if ( is_resource( $pipe ) ) {
					fclose( $pipe );
				}
			}
			proc_close( $this->process );
		}
		foreach ( $this->markers as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}
	}
}
