<?php
/** Professional status is distinct from account access.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Lifecycle;

use RuntimeException;
// phpcs:disable WordPress.PHP.YodaConditions -- Structured record comparisons.
// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
final readonly class State {
	public const LABELS = array(
		'Undefined' => 'Por definir',
		'Active'    => 'Activo',
		'Suspended' => 'Suspenso',
		'Closed'    => 'Encerrado',
	);
	public function __construct( public int $revision, public array $people ) {
		if ( $revision < 0 || $revision > 10000 || count( $people ) > 999 ) {
			throw new RuntimeException( 'Estado profissional inválido.' ); }
		$seen = array();
		foreach ( $people as $id => $p ) {
			if ( ! is_string( $id ) || 1 !== preg_match( '/^LAB-PER-(?!000)[0-9]{3}$/D', $id ) || ! is_array( $p ) || array_keys( $p ) !== array( 'state', 'account' ) || ! is_string( $p['state'] ) || ! isset( self::LABELS[ $p['state'] ] ) || ! is_int( $p['account'] ) || $p['account'] < 0 || ( 0 !== $p['account'] && isset( $seen[ $p['account'] ] ) ) ) {
				throw new RuntimeException( 'Vínculo profissional inválido.' ); }
			if ( $p['account'] > 0 ) {
				$seen[ $p['account'] ] = true; }
		}
		$sorted = $people;
		ksort( $sorted, SORT_STRING );
		if ( $sorted !== $people ) {
			throw new RuntimeException( 'Ordem profissional inválida.' ); }
	}
	public static function decode( int $revision, string $json ): self {
		if ( strlen( $json ) > 200000 ) {
			throw new RuntimeException( 'Estado profissional inválido.' ); }
		$p = json_decode( $json, true, 8, JSON_THROW_ON_ERROR );
		if ( ! is_array( $p ) ) {
			throw new RuntimeException( 'Estado profissional inválido.' ); }
		$s = new self( $revision, $p );
		if ( $s->encode() !== $json ) {
			throw new RuntimeException( 'Estado profissional não canónico.' ); }
		return $s;
	}
	public function encode(): string {
		return json_encode( $this->people, JSON_THROW_ON_ERROR );
	}
	public function person( string $id ): array {
		return $this->people[ $id ] ?? array(
			'state'   => 'Undefined',
			'account' => 0,
		);
	}
	public function apply( Command $command ): self {
		if ( $this->revision !== $command->revision || $this->revision >= 10000 ) {
			throw new RuntimeException( 'Revisão profissional divergente.' ); }
		$p = $this->person( $command->person );
		if ( 'link' === $command->action ) {
			if ( 0 !== $p['account'] ) {
				throw new RuntimeException( 'Associação existente. Substituição recusada.' ); }
			$p['account'] = $command->account;
		} else {
			if ( $p['state'] === $command->state || $p['account'] !== $command->account ) {
				throw new RuntimeException( 'Estado ou associação divergente.' ); }
			$p['state'] = $command->state;
		}
		$next                     = $this->people;
		$next[ $command->person ] = $p;
		ksort( $next, SORT_STRING );
		return new self( $this->revision + 1, $next );
	}
}
