<?php
/** Current HR authority on the repository connection.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Register;

use Arn\Intranet\Authorization\{Action, AuthorizationPolicy, Request, Scope};
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Authorization\Storage\{AuthorizationSnapshot, WpdbAuthorizationRepository};
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use RuntimeException;
// phpcs:disable WordPress.PHP.YodaConditions -- Structured server values compared in business order.
// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class Authority {
	private ?AuthorizationSnapshot $snapshot = null;
	private array $session                   = array();
	private int $actor                       = 0;
	private string $token                    = '';
	public function __construct( private readonly IdentityWpdb $db, private readonly Closure $principal, private readonly Closure $session_reader, private readonly Closure $token_reader, private readonly Closure $clock ) {}
	public static function permits( ?AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		if ( null === $snapshot || ! LocalAccountSessionPolicy::valid( $snapshot, $snapshot->user_id, $session, true ) ) {
			return false; }
		foreach ( array( Action::ReadHrRecord, Action::WriteHrRecord ) as $action ) {
			if ( ! ( new AuthorizationPolicy() )->allows( $snapshot->toAccount( true ), new Request( $action, Scope::institutional(), 0, 0, true, true, true, true ), $snapshot->policyGrants(), $now ) ) {
				return false; }
		}
		return true;
	}
	public function lock(): int {
		$this->actor = ( $this->principal )();
		if ( ! $this->db->inTransaction() || $this->actor < 1 ) {
			throw new RuntimeException( 'Cadastro indisponível.' ); }
		$this->snapshot = ( new WpdbAuthorizationRepository( $this->db ) )->findInTransaction( $this->actor );
		$this->session  = ( $this->session_reader )( $this->actor );
		$this->token    = ( $this->token_reader )();
		$this->assertCurrent();
		return $this->actor;
	}
	public function assertCurrent(): void {
		if ( ! $this->db->inTransaction() || $this->actor !== ( $this->principal )() || $this->session !== ( $this->session_reader )( $this->actor ) || strlen( $this->token ) < 16 || ! hash_equals( $this->token, ( $this->token_reader )() ) || ! self::permits( $this->snapshot, $this->session, ( $this->clock )() ) ) {
			throw new RuntimeException( 'Cadastro indisponível.' ); }
	}
	public function token(): string {
		$this->assertCurrent();
		return $this->token;
	}
}
