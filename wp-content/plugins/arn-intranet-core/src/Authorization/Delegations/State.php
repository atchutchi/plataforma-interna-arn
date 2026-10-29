<?php
/** Immutable delegation bounds and a record of grants issued under them.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Delegations;

use Arn\Intranet\Authorization\Delegation;
use RuntimeException;
// phpcs:disable Squiz.Commenting, WordPress.PHP.YodaConditions, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
final readonly class State {
	public function __construct( public int $revision, public array $records ) {
		if ( $revision < 0 || $revision > 10000 || count( $records ) > 50 ) {
			throw new RuntimeException( 'Estado de delegações inválido.' ); }
		$seen = array();
		foreach ( $records as $id => $r ) {
			if ( ! is_string( $id ) || 1 !== preg_match( '/^LAB-DEL-[0-9]{6}$/D', $id ) || ! is_array( $r ) || array_keys( $r ) !== array( 'issuer', 'delegate', 'target', 'role', 'scope', 'starts', 'expires', 'revoked', 'grants' ) || ! is_int( $r['issuer'] ) || $r['issuer'] < 1 || ! is_bool( $r['revoked'] ) || ! is_array( $r['grants'] ) || ! array_is_list( $r['grants'] ) || count( $r['grants'] ) > 50 || $r['issuer'] === $r['delegate'] || $r['issuer'] === $r['target'] ) {
				throw new RuntimeException( 'Delegação persistida inválida.' ); }
			self::delegation( $r );
			foreach ( $r['grants'] as $grant_id ) {
				if ( ! is_int( $grant_id ) || $grant_id < 1 || isset( $seen[ $grant_id ] ) ) {
					throw new RuntimeException( 'Concessão duplicada ou inválida.' ); }
				$seen[ $grant_id ] = true;
			}
		}
		$sorted = $records;
		ksort( $sorted, SORT_STRING );
		if ( $records !== $sorted ) {
			throw new RuntimeException( 'Ordem inválida.' ); }
	}
	public static function delegation( array $r ): Delegation {
		$c = Command::from(
			array(
				'action'          => 'issue',
				'delegation'      => '',
				'delegate'        => $r['delegate'],
				'target'          => $r['target'],
				'role'            => $r['role'],
				'scope'           => $r['scope'],
				'starts'          => $r['starts'],
				'expires'         => $r['expires'],
				'revision'        => 0,
				'target_revision' => 0,
				'reason'          => 'Validação interna',
				'request_id'      => str_repeat( '0', 64 ),
			)
		);
		return new Delegation( $c->delegate, array( $c->target ), array( $c->roleValue() ), $c->scopeValue(), $c->starts, $c->expires, $r['revoked'] );
	}
	public function apply( Command $c, int $actor, int $grant_id ): self {
		if ( $actor < 1 || $this->revision !== $c->revision || $this->revision >= 10000 ) {
			throw new RuntimeException( 'Revisão de delegações divergente.' ); }
		$next = $this->records;
		if ( 'issue' === $c->action ) {
			if ( $actor === $c->delegate || $actor === $c->target || 0 !== $grant_id ) {
				throw new RuntimeException( 'Autopromoção recusada.' ); }
			$id          = 'LAB-DEL-' . str_pad( (string) ( $this->revision + 1 ), 6, '0', STR_PAD_LEFT );
			$next[ $id ] = array(
				'issuer'   => $actor,
				'delegate' => $c->delegate,
				'target'   => $c->target,
				'role'     => $c->role,
				'scope'    => $c->scope,
				'starts'   => $c->starts,
				'expires'  => $c->expires,
				'revoked'  => false,
				'grants'   => array(),
			);
		} else {
			$r = $next[ $c->delegation ] ?? null;
			if ( null === $r || $r['revoked'] || $r['delegate'] !== $c->delegate || $r['target'] !== $c->target || $r['role'] !== $c->role || $r['scope'] !== $c->scope ) {
				throw new RuntimeException( 'Limites ou estado divergentes.' ); }
			if ( 'revoke' === $c->action ) {
				if ( $actor !== $r['issuer'] || 0 !== $grant_id || $c->starts !== $r['starts'] || $c->expires !== $r['expires'] ) {
					throw new RuntimeException( 'Revogação inválida.' ); }
				$next[ $c->delegation ]['revoked'] = true;
			} else {
				if ( $actor !== $r['delegate'] || $grant_id < 1 ) {
					throw new RuntimeException( 'Atribuição inválida.' ); }
				$next[ $c->delegation ]['grants'][] = $grant_id;
			}
		}
		ksort( $next, SORT_STRING );
		return new self( $this->revision + 1, $next );
	}
	public static function decode( int $revision, string $json ): self {
		if ( strlen( $json ) > 500000 ) {
			throw new RuntimeException( 'Estado inválido.' ); }
		$r = json_decode( $json, true, 12, JSON_THROW_ON_ERROR );
		if ( ! is_array( $r ) ) {
			throw new RuntimeException( 'Estado inválido.' ); }
		$s = new self( $revision, $r );
		if ( $s->encode() !== $json ) {
			throw new RuntimeException( 'Estado não canónico.' ); }
		return $s;
	}
	public function encode(): string {
		return json_encode( $this->records, JSON_THROW_ON_ERROR );
	}
}
