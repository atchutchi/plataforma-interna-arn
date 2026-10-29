<?php
/** Explicit delegation command with independent grant validity.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Delegations;

use Arn\Intranet\Authorization\{Role, Scope, Grant};
use Arn\Intranet\Authorization\Storage\ChangeContext;
use RuntimeException;
// phpcs:disable Squiz.Commenting, WordPress.PHP.YodaConditions, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
final readonly class Command {
	public const KEYS = array( 'action', 'delegation', 'delegate', 'target', 'role', 'scope', 'starts', 'expires', 'revision', 'target_revision', 'reason', 'request_id' );
	public function __construct( public string $action, public string $delegation, public int $delegate, public int $target, public string $role, public string $scope, public int $starts, public int $expires, public int $revision, public int $target_revision, public string $reason, public string $request_id ) {
		if ( ! in_array( $action, array( 'issue', 'revoke', 'grant' ), true ) || ( 'issue' === $action ? '' !== $delegation : 1 !== preg_match( '/^LAB-DEL-[0-9]{6}$/D', $delegation ) ) || $delegate < 1 || $target < 1 || $delegate === $target || ! in_array( $role, array( 'Employee', 'UnitManager' ), true ) || ! in_array( $scope, array( 'own', 'LAB-A', 'LAB-B' ), true ) || $starts < 0 || $expires <= $starts || $revision < 0 || $revision >= 10000 || ( 'grant' === $action ? $target_revision < 1 : 0 !== $target_revision ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $request_id ) ) {
			throw new RuntimeException( 'Decisão de delegação inválida.' ); }
		new ChangeContext( 1, $reason, 'delegation/' . $request_id, 1 );
		if ( ( 'Employee' === $role && 'own' !== $scope ) || ( 'UnitManager' === $role && 'own' === $scope ) ) {
			throw new RuntimeException( 'Função e âmbito incompatíveis no laboratório.' ); }
	}
	public function values(): array {
		return get_object_vars( $this );
	}
	public static function from( array $values ): self {
		if ( array_keys( $values ) !== self::KEYS ) {
			throw new RuntimeException( 'Comando inválido.' ); }
		foreach ( array( 'delegate', 'target', 'starts', 'expires', 'revision', 'target_revision' ) as $key ) {
			if ( ! is_int( $values[ $key ] ) ) {
				throw new RuntimeException( 'Comando inválido.' ); }
		}
		foreach ( array_diff( self::KEYS, array( 'delegate', 'target', 'starts', 'expires', 'revision', 'target_revision' ) ) as $key ) {
			if ( ! is_string( $values[ $key ] ) ) {
				throw new RuntimeException( 'Comando inválido.' ); }
		}
		return new self( ...array_values( $values ) );
	}
	public function hash(): string {
		return hash( 'sha256', json_encode( $this->values(), JSON_THROW_ON_ERROR ) );
	}
	public function scopeValue(): Scope {
		return 'own' === $this->scope ? Scope::own() : Scope::units( array( $this->scope ) );
	}
	public function roleValue(): Role {
		return 'Employee' === $this->role ? Role::Employee : Role::UnitManager;
	}
	public function grant(): Grant {
		return new Grant( $this->target, $this->roleValue(), $this->scopeValue(), $this->starts, $this->expires );
	}
}
