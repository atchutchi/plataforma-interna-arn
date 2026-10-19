<?php
/** Fictional dashboard acceptance inside the Services fixture ownership boundary.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\F112\Portal as F112Portal;
use Arn\Intranet\F112\CurrentActor;
use Arn\Intranet\F112\Audience;
use Arn\Intranet\F112\LocalRoster;
use Arn\Intranet\EditorialPreview\Portal as HomePortal;
use Arn\Intranet\Communications\NoticeRepository;
use Arn\Intranet\Communications\NoticeContent;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions -- Test-only, reached after the exclusive Services preflight.
function arn_home_dashboard_fixture( object $db, array $ids, Closure $login, Closure $assert, Closure $snapshot, array &$owned_anchors ): Closure {
	if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || 'local' !== wp_get_environment_type() ) {
		throw new RuntimeException( 'Dashboard fictício apenas no laboratório local.' );
	}
	$command   = static function ( string $module, string $actor, string $operation, array $input ) use ( $ids, $login, &$owned_anchors ): int {
		$login( $ids[ $actor ] );
		$id = ( new F112Portal( $module ) )->mutate(
			array_merge(
				$input,
				array(
					'operation' => $operation,
					'reason'    => 'Ensaio fictício do painel inicial',
				)
			)
		);
		if ( 'create' === $operation ) {
			$owned_anchors[] = $id;
		}
		return $id;
	};
	$notice    = static function ( string $title, string $audience, bool $published = true ) use ( $command, $login, $ids ): int {
		$actor = 'LAB-A' === $audience ? 'manager' : 'author';
		$id    = $command(
			'communications',
			$actor,
			'create',
			array(
				'title'       => $title,
				'body'        => 'Corpo reservado do ensaio',
				'audience'    => $audience,
				'circuit'     => 'manager' === $actor ? 'team-direct' : 'institutional-review',
				'valid_until' => '',
			)
		);
		if ( $published ) {
			$command(
				'communications',
				$actor,
				'manager' === $actor ? 'publish' : 'submit',
				array(
					'id'       => (string) $id,
					'revision' => '1',
				)
			);
			if ( 'author' === $actor ) {
				$command_source = new F112Portal( 'communications' );
				// The reviewer gets the opaque decision token from the existing management surface.
				$login( $ids['reviewer'] );
				$row = $command_source->managementDetail( $id );
				$command(
					'communications',
					'reviewer',
					'approve',
					array(
						'id'      => (string) $id,
						'version' => (string) $row['version'],
						'token'   => $row['token'],
					)
				);
			}
		}
		return $id;
	};
	$published = array();
	foreach ( range( 1, 4 ) as $number ) {
		$published[] = array( 'communications', 'manager', $notice( 'Comunicado A' . $number, 'LAB-A' ) );
	}
	$notice( 'Comunicado reservado B', 'LAB-B' );
	$published[] = array( 'communications', 'author', $notice( 'Comunicado geral fictício', 'all' ) );
	$notice( 'Rascunho reservado A', 'LAB-A', false );
	$archived = $notice( 'Comunicado arquivado A', 'LAB-A' );
	$command(
		'communications',
		'manager',
		'archive',
		array(
			'id'       => (string) $archived,
			'revision' => '2',
		)
	);
	$command(
		'communications',
		'manager',
		'revise',
		array(
			'id'          => (string) $published[3][2],
			'revision'    => '2',
			'title'       => 'Revisão privada nunca publicada',
			'body'        => 'Texto privado novo',
			'audience'    => 'LAB-A',
			'circuit'     => 'team-direct',
			'valid_until' => '',
		)
	);
	$login( $ids['manager'] );
	$clock           = static fn(): int => time();
	$repo            = new NoticeRepository( $db, new CurrentActor( $db, 'communications', LocalRoster::resolve(), $clock ), $clock, hash( 'sha256', 'dashboard-expired-fixture' ) );
	$expired         = $repo->create( new NoticeContent( 'Comunicado com prazo fictício', 'Corpo com prazo', Audience::unit( 'LAB-A' ), 'team-direct', time() + 300 ), 'Publicação fictícia com prazo' );
	$owned_anchors[] = $expired->id;
	$repo->publish( $expired->id, 1, 'Publicação fictícia antes de expirar' );
	$future       = static fn(): int => time() + 360;
	$future_repo  = new NoticeRepository( $db, new CurrentActor( $db, 'communications', LocalRoster::resolve(), $future ), $future, hash( 'sha256', 'dashboard-expired-fixture' ) );
	$expiry_model = ( new \Arn\Intranet\EditorialPreview\HomeDashboard( static fn(): array => $future_repo->publishedList( \Arn\Intranet\Communications\NoticeSelection::parse( array() ) ) ) )->model(
		array(
			array(
				'id'  => 'communications',
				'url' => '/comunicados/',
			),
		)
	);
	$assert( $expiry_model['communications']['available'] && ! in_array( 'Comunicado com prazo fictício', array_column( $expiry_model['communications']['items'], 'title' ), true ), 'relógio futuro de leitura exclui comunicado expirado sem alterar a autoridade' );
	$command(
		'communications',
		'manager',
		'archive',
		array(
			'id'       => (string) $expired->id,
			'revision' => '2',
		)
	);
	$now   = time();
	$event = static function ( string $title, string $audience, int $start, int $end, bool $published_event = true, bool $all_day = false ) use ( $command ): int {
		$id = $command(
			'calendar',
			'events',
			'create',
			array(
				'title'              => $title,
				'description'        => 'Descrição reservada do ensaio',
				'audience'           => $audience,
				'mode'               => 'presencial',
				'location'           => 'Sala fictícia',
				'url'                => '',
				'all_day'            => $all_day ? '1' : '0',
				'start_at'           => $all_day ? '' : gmdate( 'Y-m-d\TH:i:s\Z', $start ),
				'end_at'             => $all_day ? '' : gmdate( 'Y-m-d\TH:i:s\Z', $end ),
				'start_date'         => $all_day ? gmdate( 'Y-m-d', $start ) : '',
				'end_date_exclusive' => $all_day ? gmdate( 'Y-m-d', $end ) : '',
			)
		);
		if ( $published_event ) {
			$command(
				'calendar',
				'events',
				'publish',
				array(
					'id'       => (string) $id,
					'revision' => '1',
				)
			); }
		return $id;
	};
	foreach ( array( array( 'Evento A em curso', $now - 3600, $now + 3600, false ), array( 'Evento A futuro', $now + 86400, $now + 90000, false ), array( 'Evento A dia inteiro', strtotime( 'tomorrow UTC' ) + 86400, strtotime( 'tomorrow UTC' ) + 172800, true ), array( 'Evento A posterior', $now + 345600, $now + 349200, false ) ) as $entry ) {
		$published[] = array( 'calendar', 'events', $event( $entry[0], 'LAB-A', $entry[1], $entry[2], true, $entry[3] ) );
	}
	$event( 'Evento reservado B', 'LAB-B', $now + 86400, $now + 90000 );
	$event( 'Evento passado A', 'LAB-A', $now - 172800, $now - 169200 );
	$event( 'Evento rascunho A', 'LAB-A', $now + 3600, $now + 7200, false );
	$cancelled = $event( 'Evento cancelado A', 'LAB-A', $now + 3600, $now + 7200 );
	$command(
		'calendar',
		'events',
		'cancel',
		array(
			'id'       => (string) $cancelled,
			'revision' => '2',
		)
	);
	$login( $ids['a'] );
	$connections          = static function () use ( $db ): int {
		$count = $db->get_var( 'SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB=DATABASE()' );
		if ( '' !== $db->last_error || ! is_numeric( $count ) || (int) $count < 1 ) {
			throw new RuntimeException( 'Observação de ligações indisponível.' );
		}
		return (int) $count;
	};
	$baseline_connections = $connections();
	for ( $attempt = 0; $attempt < 12; ++$attempt ) {
		$source = new F112Portal( 'communications' );
		$assert( $source->readable(), 'leitura repetida conserva autoridade F112' );
		unset( $source );
	}
	$assert( $connections() === $baseline_connections, 'portais terminados libertam as próprias ligações dedicadas' );
	$tables = array( 'arn_comm_objects', 'arn_comm_versions', 'arn_comm_audit', 'arn_comm_notifications', 'arn_comm_schema', 'arn_calendar_objects', 'arn_calendar_versions', 'arn_calendar_audit', 'arn_calendar_notifications', 'arn_calendar_schema' );
	$before = array();
	foreach ( $tables as $table ) {
		$before[ $table ] = $snapshot( $table ); }
	$model = HomePortal::model(
		'home',
		array(
			'unit'  => 'LAB-B',
			'state' => 'cancelled',
			'entry' => '9999',
		)
	)['dashboard'];
	$assert( array( 'Comunicado geral fictício', 'Comunicado A4', 'Comunicado A3' ) === array_column( $model['communications']['items'], 'title' ), 'painel limita a três comunicados publicados pela ordem autorizada' );
	$assert( array( 'Evento A em curso', 'Evento A futuro', 'Evento A dia inteiro' ) === array_column( $model['calendar']['items'], 'title' ), 'painel mostra eventos em curso/futuros sem cancelados ou rascunhos' );
	$assert( ! str_contains( wp_json_encode( $model ), 'reservado' ) && ! str_contains( wp_json_encode( $model ), 'privada' ), 'painel não expõe audiência B ou versão privada' );
	foreach ( $before as $table => $rows ) {
		$assert( $snapshot( $table ) === $rows, 'consulta do painel conserva versões, auditoria e recibos ' . $table ); }
	$login( $ids['b'] );
	$other = HomePortal::model( 'home', array() )['dashboard'];
	$assert( array( 'Comunicado geral fictício', 'Comunicado reservado B' ) === array_column( $other['communications']['items'], 'title' ), 'mesmo início projecta apenas comunicados de B e gerais' );
	$assert( array( 'Evento reservado B' ) === array_column( $other['calendar']['items'], 'title' ), 'mesmo início projecta apenas eventos de B' );
	$login( $ids['a'] );
	return static function () use ( $published, $command, $ids, $login, $assert ): void {
		foreach ( $published as $entry ) {
			$login( $ids[ $entry[1] ] );
			$source = new F112Portal( $entry[0] );
			$row    = $source->managementDetail( $entry[2] );
			$command(
				$entry[0],
				$entry[1],
				'archive',
				array(
					'id'       => (string) $entry[2],
					'revision' => (string) $row['revision'],
				)
			);
		}
		$login( $ids['a'] );
		$empty = HomePortal::model( 'home', array() )['dashboard'];
		$assert( $empty['communications']['available'] && array() === $empty['communications']['items'], 'painel vazio exclui expirados, arquivados, rascunhos e outra audiência' );
		$assert( $empty['calendar']['available'] && array() === $empty['calendar']['items'], 'painel vazio exclui passados, cancelados, arquivados e outra audiência' );
	};
}
