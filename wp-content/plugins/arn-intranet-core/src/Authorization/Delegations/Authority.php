<?php
/** Designation, canonical capability and a current native session are all required.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Delegations;

use Arn\Intranet\Authorization\{Action, AuthorizationPolicy, Request, Scope, AccountState};
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Authorization\Storage\{AuthorizationSnapshot, WpdbAuthorizationRepository};
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use RuntimeException;
// phpcs:disable Squiz.Commenting, WordPress.PHP.YodaConditions, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class Authority {
	private array $roster   = array();
	private array $accounts = array();
	private array $session  = array();
	private string $token   = '';
	private int $actor      = 0;
	public function __construct( private readonly IdentityWpdb $db, private readonly Closure $principal, private readonly Closure $sessions, private readonly Closure $tokens, private readonly Closure $clock, private readonly Closure $fixtures ) {}
	public function lock(): int {
		if ( ! $this->db->inTransaction() ) {
			throw new RuntimeException( 'Transacção requerida.' ); }
		$this->roster = ( $this->fixtures )( $this->db );
		if ( array_keys( $this->roster ) !== array( 'issuer', 'delegate', 'target' ) || count( array_unique( $this->roster, SORT_REGULAR ) ) !== 3 ) {
			throw new RuntimeException( 'Fixtures inválidas.' ); }
		$ids = array_values( $this->roster );
		sort( $ids, SORT_NUMERIC );
		$this->accounts = array();
		foreach ( $ids as $id ) {
			if ( ! is_int( $id ) || $id < 1 ) {
				throw new RuntimeException( 'Fixtures inválidas.' ); }
			$this->accounts[ $id ] = ( new WpdbAuthorizationRepository( $this->db ) )->findInTransaction( $id );
		}
		$this->actor   = ( $this->principal )();
		$this->session = ( $this->sessions )( $this->actor );
		$this->token   = ( $this->tokens )();
		$this->assertCurrent();
		return $this->actor;
	}
	public function assertCurrent(): void {
		$a = $this->accounts[ $this->actor ] ?? null;
		if ( ! $this->db->inTransaction() || ! in_array( $this->actor, array( $this->roster['issuer'], $this->roster['delegate'] ), true ) || $this->actor !== ( $this->principal )() || $this->session !== ( $this->sessions )( $this->actor ) || strlen( $this->token ) < 16 || ! hash_equals( $this->token, ( $this->tokens )() ) || null === $a || ! LocalAccountSessionPolicy::valid( $a, $this->actor, $this->session, true ) || ! $this->canManage( $this->roster['target'] ) || ( $this->actor === $this->roster['issuer'] && ! $this->canManage( $this->roster['delegate'] ) ) ) {
			throw new RuntimeException( 'Autoridade de delegações indisponível.' ); }
	}
	private function canManage( int $id ): bool {
		$a = $this->accounts[ $this->actor ] ?? null;
		return null !== $a && ( new AuthorizationPolicy() )->allows( $a->toAccount( true ), new Request( Action::ManageGrants, Scope::people( array( $id ) ), $id, 0, true, true, true, true ), $a->policyGrants(), $this->now() );
	}
	public function roster(): array {
		return $this->roster; }
	public function now(): int {
		return ( $this->clock )(); }
	public function actor(): AuthorizationSnapshot {
		$this->assertCurrent();
		return $this->accounts[ $this->actor ];
	}
	public function target(): AuthorizationSnapshot {
		$t = $this->accounts[ $this->roster['target'] ] ?? null;
		if ( null === $t || AccountState::Active !== $t->state ) {
			throw new RuntimeException( 'Conta destinatária indisponível.' ); }
		return $t;
	}
	public function targetInfo(): ?AuthorizationSnapshot {
		return $this->accounts[ $this->roster['target'] ] ?? null;
	}
	public function delegateActive(): bool {
		return AccountState::Active === ( $this->accounts[ $this->roster['delegate'] ]?->state ?? null );
	}
}
