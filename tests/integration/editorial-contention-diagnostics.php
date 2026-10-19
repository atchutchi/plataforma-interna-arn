<?php
/** Calibrate editorial observations without accepting failed contention as success.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
$assert = static function ( bool $ok ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Calibração editorial não confirmou a prova esperada.' );
	}
};
$state  = static function () use ( $assert ): string {
	$database = $GLOBALS['wpdb'];
	$tables   = $database->get_col( 'SHOW TABLES' );
	$assert( is_array( $tables ) && '' === $database->last_error );
	sort( $tables );
	foreach ( $tables as $table ) {
		$assert( 1 !== preg_match( '/\Aarn_ed_[a-f0-9]{10}_/', $table ) );
	}
	$rows = array();
	foreach ( array( $database->users, $database->usermeta ) as $table ) {
		$rows[] = $database->get_results( $database->prepare( 'SELECT * FROM %i ORDER BY 1', $table ), ARRAY_A );
		$assert( is_array( end( $rows ) ) && '' === $database->last_error );
	}
	return hash( 'sha256', wp_json_encode( array( $tables, $rows ) ) );
};
$before = $state();
$cases  = array( 'none', 'early-worker', 'observer-query', 'revocation-observer-query' );
$only   = getenv( 'ARN_EDITORIAL_DIAGNOSTIC_ONLY' );
if ( false !== $only ) {
	$assert( in_array( $only, $cases, true ) );
	$cases = array( $only );
}
foreach ( $cases as $case ) {
	$output     = tmpfile();
	$error_file = tmpfile();
	$process    = null;
	$pipes      = array();
	try {
		$assert( is_resource( $output ) && is_resource( $error_file ) );
		$process = proc_open(
			array( PHP_BINARY, $_SERVER['argv'][0], '--path=' . ABSPATH, '--user=' . wp_get_current_user()->user_login, 'eval-file', __DIR__ . '/news-editorial.php' ),
			array(
				0 => array( 'pipe', 'r' ),
				1 => $output,
				2 => $error_file,
			),
			$pipes,
			getcwd(),
			array_merge( getenv(), array( 'ARN_EDITORIAL_DIAGNOSTIC_FAULT' => $case ) )
		);
		$assert( is_resource( $process ) );
		fclose( $pipes[0] );
		$exit    = proc_close( $process );
		$process = null;
		rewind( $output );
		rewind( $error_file );
		$stdout = stream_get_contents( $output );
		$stderr = stream_get_contents( $error_file );
		$assert( is_string( $stdout ) && is_string( $stderr ) );
		$observations = array();
		$completions  = array();
		$revocations  = array();
		foreach ( explode( "\n", $stdout ) as $line ) {
			foreach ( array(
				'Editorial contention: ' => 'wait',
				'Editorial completion: ' => 'done',
				'Editorial revocation: ' => 'revocation',
			) as $start => $record_kind ) {
				if ( str_starts_with( $line, $start ) ) {
					$value = json_decode( substr( $line, strlen( $start ) ), true, 32, JSON_THROW_ON_ERROR );
					$assert( is_array( $value ) );
					if ( 'wait' === $record_kind ) {
						$observations[] = $value;
					} elseif ( 'done' === $record_kind ) {
						$completions[] = $value;
					} else {
						$revocations[] = $value;
					}
				}
			}
		}
		if ( 'none' === $case ) {
			$assert( 0 === $exit && '' === $stderr && str_contains( $stdout, 'Editorial exclusivo: 59 verificações.' ) );
			$assert( array( 'decision-first', 'decision-second', 'revocation' ) === array_column( $observations, 'scenario' ) && 3 === count( $completions ) );
			foreach ( $observations as $observation ) {
				$assert( 'wait-observed' === $observation['classification'] && $observation['primary']['known'] && $observation['primary']['count'] > 0 && ! $observation['primary']['sql_error'] );
			}
			$outcomes = array_column( $completions, 'outcome' );
			sort( $outcomes );
			$assert( array( 'EDITORIAL_CONFIRMED', 'EDITORIAL_REFUSED' ) === $outcomes );
			foreach ( $completions as $completion ) {
				$assert( $completion['diagnostic']['trace']['complete'] && 0 === $completion['diagnostic']['exit_code'] );
			}
		} elseif ( 'revocation-observer-query' === $case ) {
			$proof   = $revocations[0] ?? array();
			$records = $proof['trace']['records'] ?? array();
			$seen    = is_array( $records ) ? array_column( $records, 'phase' ) : array();
			WP_CLI::log(
				'Editorial revocation calibration facts: ' . wp_json_encode(
					array(
						'exit'                           => $exit,
						'observation_count'              => count( $observations ),
						'completion_count'               => count( $completions ),
						'revocation_count'               => count( $revocations ),
						'outer_static_refusal'           => str_contains( $stdout . $stderr, 'Persistência de autorização indisponível.' ),
						'primary_errno_1054'             => 1054 === ( $observations[2]['primary']['errno'] ?? null ),
						'secondary_wait_seen'            => true === ( $observations[2]['secondary_wait_seen'] ?? null ),
						'authorization_comparison_known' => is_bool( $proof['authorization_restored'] ?? null ),
						'authorization_restored'         => true === ( $proof['authorization_restored'] ?? null ),
						'parent_trace_complete'          => true === ( $proof['trace']['complete'] ?? null ),
						'parent_start_seen'              => in_array( 'revocation-start', $seen, true ),
						'parent_hook_seen'               => in_array( 'revocation-hook', $seen, true ),
						'parent_hook_failed_seen'        => in_array( 'revocation-hook-failed', $seen, true ),
						'parent_refusal_seen'            => in_array( 'revocation-refused', $seen, true ),
						'parent_probe_seen'              => in_array( 'revocation-probe', $seen, true ),
					)
				)
			);
			$assert( $exit > 0 && 3 === count( $observations ) && 2 === count( $completions ) && 1 === count( $revocations ) );
			$assert( str_contains( $stdout . $stderr, 'Persistência de autorização indisponível.' ) );
			$assert( array( 'wait-observed', 'wait-observed', 'observer-query-failed' ) === array_column( $observations, 'classification' ) );
			$assert( 'revocation' === $observations[2]['scenario'] && 1054 === $observations[2]['primary']['errno'] && $observations[2]['secondary_wait_seen'] );
			$revocation = $revocations[0];
			$phases     = array_column( $revocation['trace']['records'], 'phase' );
			$assert( 'refused' === $revocation['outcome'] && $revocation['authorization_restored'] && $revocation['trace']['complete'] );
			$assert( array( 'revocation-start', 'revocation-hook', 'revocation-hook-failed', 'revocation-refused' ) === $phases );
			$assert(
				array(
					'sql_error' => true,
					'errno'     => 1054,
				) === $revocation['trace']['records'][2]['facts']
			);
		} else {
			$assert( $exit > 0 && 1 === count( $observations ) && array() === $completions && str_contains( $stdout . $stderr, 'processo concorrente espera num lock real' ) );
			$observation = $observations[0];
			// Publish only typed facts, before an assertion can discard the child trace.
			WP_CLI::log(
				'Editorial calibration facts: ' . wp_json_encode(
					array(
						'case'                  => $case,
						'exit'                  => $exit,
						'primary_unknown'       => false === ( $observation['primary']['known'] ?? null ),
						'primary_sql_error'     => true === ( $observation['primary']['sql_error'] ?? null ),
						'primary_errno_1054'    => 1054 === ( $observation['primary']['errno'] ?? null ),
						'secondary_wait_seen'   => true === ( $observation['secondary_wait_seen'] ?? null ),
						'state_lock_wait'       => 'LOCK WAIT' === ( $observation['transaction_state'] ?? null ),
						'state_running'         => 'RUNNING' === ( $observation['transaction_state'] ?? null ),
						'state_unknown'         => 'unknown' === ( $observation['transaction_state'] ?? null ),
						'worker_running'        => true === ( $observation['worker']['running'] ?? null ),
						'worker_exited'         => false === ( $observation['worker']['running'] ?? null ),
						'trace_complete'        => true === ( $observation['worker']['trace']['complete'] ?? null ),
						'authority_enter_seen'  => in_array( 'authority-enter', array_column( $observation['worker']['trace']['records'] ?? array(), 'phase' ), true ),
						'authority_locked_seen' => in_array( 'authority-locked', array_column( $observation['worker']['trace']['records'] ?? array(), 'phase' ), true ),
						'worker_refused_seen'   => in_array( 'refused', array_column( $observation['worker']['trace']['records'] ?? array(), 'phase' ), true ),
						'worker_failed_seen'    => in_array( 'failed', array_column( $observation['worker']['trace']['records'] ?? array(), 'phase' ), true ),
					)
				)
			);
			$assert( 'decision-first' === $observation['scenario'] && $observation['worker']['trace']['complete'] );
			$phases = array_column( $observation['worker']['trace']['records'], 'phase' );
			if ( 'early-worker' === $case ) {
				$assert( 'worker-before-marker' === $observation['classification'] && 0 === $observation['connection'] && false === $observation['worker']['running'] );
				$assert( $observation['worker']['ready'] && $observation['worker']['released'] && ! $observation['worker']['entered'] );
				$assert( in_array( 'refused', $phases, true ) && ! in_array( 'authority-enter', $phases, true ) );
			} else {
				$assert( 'observer-query-failed' === $observation['classification'] && ! $observation['primary']['known'] && $observation['primary']['sql_error'] && 1054 === $observation['primary']['errno'] );
				$assert( $observation['secondary_wait_seen'] && 'LOCK WAIT' === $observation['transaction_state'] && $observation['worker']['running'] );
				$assert( in_array( 'authority-enter', $phases, true ) );
			}
		}
		$assert( $before === $state() );
		WP_CLI::log(
			'Editorial diagnostic calibration: ' . wp_json_encode(
				array(
					'case'            => $case,
					'exit'            => $exit,
					'classifications' => array_column( $observations, 'classification' ),
					'native_users_and_table_list_preserved' => true,
				)
			)
		);
	} finally {
		if ( is_resource( $process ) ) {
			proc_terminate( $process );
			proc_close( $process );
		}
		foreach ( array( $output, $error_file ) as $handle ) {
			if ( is_resource( $handle ) ) {
				fclose( $handle );
			}
		}
	}
}
WP_CLI::success( 'Cenários de diagnóstico editorial calibrados, sem aceitar falhas injectadas como sucesso do teste original.' );
