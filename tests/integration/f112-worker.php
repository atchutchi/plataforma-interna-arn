<?php
/** Exclusive CAS worker, with conflict distinct from infrastructure failure.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.DB.DirectDatabaseQuery
use Arn\Intranet\Calendar\{EventContent, EventRepository};
use Arn\Intranet\Communications\{NoticeContent, NoticeRepository};
use Arn\Intranet\F112\CurrentActor;
use Arn\Intranet\Identity\IdentityWpdb;

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! defined( 'ARN_F112_SQL_HARNESS' ) || true !== ARN_F112_SQL_HARNESS || 'local' !== wp_get_environment_type() || ! current_user_can( 'manage_options' ) || 3 !== count( $args ) ) {
	throw new RuntimeException( 'Worker F112 recusado.' );
}
[$payload_path, $ready_path, $result_path] = $args;
$directory                                 = realpath( dirname( $payload_path ) );
if ( false === $directory || 1 !== preg_match( '/\Aarn_f112_worker_[a-f0-9]{16}\z/', basename( $directory ) ) || realpath( dirname( $ready_path ) ) !== $directory || realpath( dirname( $result_path ) ) !== $directory ) {
	throw new RuntimeException( 'Directório de worker recusado.' );
}
$stage  = 'payload';
$result = array(
	'result' => 'technical_failure',
	'phase'  => $stage,
);
$exit   = 5;
$db     = null;
try {
	$data = json_decode( file_get_contents( $payload_path ), true, 64, JSON_THROW_ON_ERROR );
	if ( ! is_array( $data ) || 1 !== preg_match( '/\Aarn_f112_[a-f0-9]{10}_\z/', $data['prefix'] ?? '' ) || ! in_array( $data['module'] ?? null, array( 'communications', 'calendar' ), true ) || ! is_array( $data['roster'] ?? null ) || ! is_array( $data['session'] ?? null ) || ! is_int( $data['actor'] ?? null ) || ! is_int( $data['id'] ?? null ) || ! is_int( $data['now'] ?? null ) || ! is_int( $data['expected'] ?? null ) ) {
		throw new RuntimeException( 'Payload sintético inválido.' );
	}
	foreach ( array( 'ARN_ALLOW_LOCAL_COMMUNICATIONS', 'ARN_ALLOW_LOCAL_CALENDAR', 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) as $flag ) {
		if ( ! defined( $flag ) ) {
			define( $flag, true );
		}
		if ( true !== constant( $flag ) ) {
			throw new RuntimeException( 'Gate de worker recusado.' );
		}
	}
	$stage = 'connection';
	$db    = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$db->set_prefix( $data['prefix'] );
	$db->suppress_errors( true );
	$clock          = static fn(): int => $data['now'];
	$resolver_calls = 0;
	$resolver       = static function () use ( $data, $result_path, &$resolver_calls ): array {
		++$resolver_calls;
		file_put_contents(
			$result_path . '.phase',
			json_encode(
				array(
					'phase' => 'authority-resolution',
					'call'  => $resolver_calls,
				),
				JSON_THROW_ON_ERROR
			),
			LOCK_EX
		);
		return array( $data['actor'], $data['session'] );
	};
	$actors         = new CurrentActor( $db, $data['module'], $data['roster'], $clock, $resolver );
	add_filter(
		'query',
		static function ( string $sql ) use ( $data, $result_path ): string {
			if ( str_contains( $sql, $data['prefix'] ) || str_contains( $sql, 'information_schema' ) ) {
				// Strip every SQL string literal before retaining only the statement shape.
				$shape = preg_replace( "/'(?:[^'\\\\]|\\\\.)*'/s", "'?'", $sql );
				$shape = preg_replace( '/\s+/', ' ', $shape );
				file_put_contents( $result_path . '.query', substr( $shape, 0, 240 ), LOCK_EX );
			}
			return $sql;
		},
		PHP_INT_MAX
	);
	$repo    = 'communications' === $data['module'] ? new NoticeRepository( $db, $actors, $clock, str_repeat( 'synthetic-key-', 4 ) ) : new EventRepository( $db, $actors, $clock, str_repeat( 'synthetic-key-', 4 ) );
	$content = 'communications' === $data['module'] ? NoticeContent::fromArray( $data['content'] ) : EventContent::fromArray( $data['content'] );
	$stage   = 'ready';
	$ready   = json_encode(
		array(
			'phase'         => $stage,
			'connection_id' => (int) $db->get_var( 'SELECT CONNECTION_ID()' ),
		),
		JSON_THROW_ON_ERROR
	);
	if ( strlen( $ready ) !== file_put_contents( $ready_path . '.tmp', $ready, LOCK_EX ) || ! rename( $ready_path . '.tmp', $ready_path ) ) {
		throw new RuntimeException( 'Sinal de worker indisponível.' );
	}
	$stage  = 'revise';
	$after  = $repo->revise( $data['id'], $content, $data['expected'], 'Ensaio concorrente F112.' );
	$result = array(
		'result'   => 'committed',
		'revision' => $after->revision,
	);
	$exit   = 0;
} catch ( DomainException $error ) {
	if ( 'Conflito F112.' === $error->getMessage() ) {
		$result = array( 'result' => 'conflict' );
		$exit   = 3;
	} else {
		$result = array(
			'result' => 'unexpected_denial',
			'phase'  => $stage,
		);
		$exit   = 4;
	}
} catch ( Throwable $error ) {
	$result = array(
		'result'    => 'technical_failure',
		'phase'     => $stage,
		'exception' => get_class( $error ),
	);
} finally {
	if ( $db instanceof IdentityWpdb ) {
		$db->query( 'ROLLBACK' );
		$db->close();
	}
}
$bytes = json_encode( $result, JSON_THROW_ON_ERROR );
if ( strlen( $bytes ) !== file_put_contents( $result_path . '.tmp', $bytes, LOCK_EX ) || ! rename( $result_path . '.tmp', $result_path ) ) {
	exit( 6 );
}
echo esc_html( 'F112 worker: ' . $result['result'] . PHP_EOL );
exit( $exit ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Integer CLI exit status, never response text.
