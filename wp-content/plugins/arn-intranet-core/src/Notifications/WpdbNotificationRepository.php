<?php
/** Projecção durável dos eventos confirmados por destinatário.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Notifications;

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Authorization\Storage\AuthorizationSchema;
use RuntimeException;
use Throwable;
// SQL interno preparado, sem cache e sem dados externos em erros.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** A leitura e escrita confirmam estado e revisão sob bloqueio. */
final class WpdbNotificationRepository {
	/** Dependência interna fixada.
	 *
	 * @var NotificationSchema
	 */
	private readonly NotificationSchema $schema;
	/** Dependência interna fixada.
	 *
	 * @var AuthorizationSchema
	 */
	private readonly AuthorizationSchema $authorization;
	/** Recebe dependências internas e fixa a fronteira.
	 *
	 * @param IdentityWpdb $database Ligação exclusiva sem reconnect.
	 *
	 * @throws RuntimeException Quando a fronteira ou a persistência recusa a operação.
	 */
	public function __construct( private readonly IdentityWpdb $database ) {
		$database->suppress_errors( true );
		$this->schema        = new NotificationSchema( $database );
		$this->authorization = new AuthorizationSchema( $database );
	}
	/** Instala explicitamente a tabela de recibos.
	 *
	 * @throws RuntimeException Quando a fronteira ou a persistência recusa a operação.
	 */
	public function installSchema(): void {
		$this->schema->install();
	}
	/** Filtra destinatário e eventos em SQL antes do limite.
	 *
	 * @param int      $user_id Destinatário autorizado.
	 *
	 * @param int      $revision Revisão esperada da sessão.
	 *
	 * @param int|null $before Cursor exclusivo ou null.
	 *
	 * @throws RuntimeException Quando a fronteira ou a persistência recusa a operação.
	 */
	public function page( int $user_id, int $revision, ?int $before = null ): array {
		if ( null !== $before && $before < 1 ) {
			throw new RuntimeException( 'Notificações indisponíveis.' );
		}
		return $this->transaction(
			$user_id,
			$revision,
			function () use ( $user_id, $revision, $before ): array {
				$sql = $this->database->prepare( 'SELECT a.id,a.event,a.occurred_at,r.read_at FROM %i a LEFT JOIN %i r ON r.user_id=a.user_id AND r.event_id=a.id WHERE a.user_id=%d AND a.revision<=%d AND a.event IN (%s,%s,%s,%s)', $this->authorization->table( 'audit' ), $this->schema->table, $user_id, $revision, ...Notification::EVENTS );
				if ( null !== $before ) {
					$sql .= $this->database->prepare( ' AND a.id<%d', $before );
				}
				$rows = $this->rows( $sql . ' ORDER BY a.id DESC LIMIT 21' );
				$more = count( $rows ) > 20;
				$rows = array_map( Notification::project( ... ), array_slice( $rows, 0, 20 ) );
				return array(
					'rows' => $rows,
					'next' => $more ? $rows[19]['id'] : null,
				);
			}
		);
	}
	/** Conserva o primeiro recibo sob bloqueio da conta.
	 *
	 * @param int $user_id Destinatário autorizado.
	 *
	 * @param int $revision Revisão esperada da sessão.
	 *
	 * @param int $event_id Evento a confirmar.
	 *
	 * @param int $read_at Instante UTC Unix.
	 *
	 * @throws RuntimeException Quando a fronteira ou a persistência recusa a operação.
	 */
	public function markRead( int $user_id, int $revision, int $event_id, int $read_at ): void {
		if ( $event_id < 1 || $read_at < 1 ) {
			throw new RuntimeException( 'Notificações indisponíveis.' );
		}
		$this->transaction(
			$user_id,
			$revision,
			function () use ( $user_id, $revision, $event_id, $read_at ): void {
				$events = $this->rows( $this->database->prepare( 'SELECT id FROM %i WHERE id=%d AND user_id=%d AND revision<=%d AND event IN (%s,%s,%s,%s)', $this->authorization->table( 'audit' ), $event_id, $user_id, $revision, ...Notification::EVENTS ) );
				if ( 1 !== count( $events ) ) {
					throw new RuntimeException( 'Notificações indisponíveis.' );
				}
				$this->execute( $this->database->prepare( 'INSERT INTO %i (user_id,event_id,read_at) VALUES (%d,%d,%d) ON DUPLICATE KEY UPDATE read_at=read_at', $this->schema->table, $user_id, $event_id, $read_at ) );
			}
		);
	}
	/** Revalida estado e revisão dentro da transacção.
	 *
	 * @param int      $user_id Destinatário autorizado.
	 *
	 * @param int      $revision Revisão esperada da sessão.
	 *
	 * @param callable $operation Operação sob bloqueio.
	 *
	 * @throws RuntimeException Quando a fronteira ou a persistência recusa a operação.
	 */
	private function transaction( int $user_id, int $revision, callable $operation ): mixed {
		if ( $user_id < 1 || $revision < 1 ) {
			throw new RuntimeException( 'Notificações indisponíveis.' );
		}
		$this->schema->validate();
		$this->authorization->validate();
		$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
		$this->execute( 'START TRANSACTION' );
		try {
			$accounts = $this->rows( $this->database->prepare( 'SELECT state,revision FROM %i WHERE user_id=%d FOR UPDATE', $this->authorization->table( 'accounts' ), $user_id ) );
			if ( 1 !== count( $accounts ) || 'Active' !== $accounts[0]->state || (string) $revision !== (string) $accounts[0]->revision ) {
				throw new RuntimeException( 'Notificações indisponíveis.' );
			}
			$result = $operation();
			$this->execute( 'COMMIT' );
			return $result;
		} catch ( Throwable ) {
			$this->database->query( 'ROLLBACK' );
			throw new RuntimeException( 'Notificações indisponíveis.' );
		}
	}
	/** Lê resultados ou recusa erros SQL de forma estática.
	 *
	 * @param string $sql Comando interno preparado.
	 *
	 * @throws RuntimeException Quando a fronteira ou a persistência recusa a operação.
	 */
	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Notificações indisponíveis.' );
		}
		return $rows;
	}
	/** Executa SQL preparado e recusa falhas.
	 *
	 * @param string $sql Comando interno preparado.
	 *
	 * @throws RuntimeException Quando a fronteira ou a persistência recusa a operação.
	 */
	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Notificações indisponíveis.' );
		}
	}
}
