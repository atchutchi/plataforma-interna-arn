<?php
/** Independent fictional professional records.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Register;

use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
final readonly class Catalog {
	public function __construct( public int $revision, public array $people ) {
		if ( $revision < 0 || count( $people ) > 999 || ( 0 === $revision && array() !== $people ) ) {
			throw new RuntimeException( 'Cadastro inválido.' ); }
		$emails = array();
		foreach ( $people as $id => $p ) {
			if ( ! is_string( $id ) || 1 !== preg_match( '/^LAB-PER-(?!000)[0-9]{3}$/D', $id ) || ! is_array( $p ) || array_keys( $p ) !== array( 'name', 'email', 'unit_id' ) || ! is_string( $p['name'] ) || ! is_string( $p['email'] ) || ! is_string( $p['unit_id'] ) || ! self::name( $p['name'] ) || ! self::email( $p['email'] ) || ! in_array( $p['unit_id'], array( 'LAB-A', 'LAB-B', 'LAB-ROOT' ), true ) || isset( $emails[ $p['email'] ] ) ) {
				throw new RuntimeException( 'Cadastro inválido.' ); }
			$emails[ $p['email'] ] = true;
		}
		$sorted = $people;
		ksort( $sorted, SORT_STRING );
		if ( $sorted !== $people ) {
			throw new RuntimeException( 'Ordem do cadastro inválida.' ); }
	}
	public static function name( string $name ): bool {
		return 1 === preg_match( '//u', $name ) && mb_strlen( $name, 'UTF-8' ) <= 100 && str_starts_with( $name, 'Pessoa fictícia ' ) && 0 === preg_match( '/(?:\A[\p{Z}\s]|[\p{Z}\s]\z|\p{C}|[<>])/u', $name );
	}
	public static function email( string $email ): bool {
		return 1 === preg_match( '/^(?=[^@]{1,64}@)(?![^@]*\.\.)[a-z0-9](?:[a-z0-9._+-]*[a-z0-9])?@example\.invalid$/D', $email );
	}
	public function encode(): string {
		return json_encode( $this->people, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE );
	}
	public static function decode( int $revision, string $json ): self {
		if ( strlen( $json ) > 1048576 ) {
			throw new RuntimeException( 'Cadastro acima do limite.' ); }
		$people = json_decode( $json, true, 16, JSON_THROW_ON_ERROR );
		if ( ! is_array( $people ) ) {
			throw new RuntimeException( 'Cadastro inválido.' ); }
		return new self( $revision, $people );
	}
}
