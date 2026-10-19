<?php
/** Strict literal search input shared by the active modules.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Search;

// phpcs:disable Squiz.Commenting -- Closed read-only input contract.
final readonly class Query {
	public const TYPES = array(
		''               => 'Todos os tipos',
		'directory'      => 'Funcionários',
		'news'           => 'Notícias',
		'documents'      => 'Documentos e regulamentos',
		'communications' => 'Comunicados',
		'calendar'       => 'Eventos',
	);
	private function __construct( public string $q, public string $type, public int $page ) {}

	public static function parse( array $input ): ?self {
		if ( array_diff( array_keys( $input ), array( 'q', 'type', 'arn_search_page', 'arn_search', 'page_id' ) ) ) {
			return null;
		}
		foreach ( $input as $value ) {
			if ( ! is_string( $value ) ) {
				return null; }
		}
		$q    = $input['q'] ?? '';
		$type = $input['type'] ?? '';
		$page = $input['arn_search_page'] ?? '1';
		if ( ! mb_check_encoding( $q, 'UTF-8' ) || strlen( $q ) > 160 || mb_strlen( $q, 'UTF-8' ) > 100
			|| 0 !== preg_match( '/[\p{C}<>\x{2028}\x{2029}]/u', $q ) || ! isset( self::TYPES[ $type ] )
			|| 1 !== preg_match( '/\A[1-9][0-9]{0,5}\z/D', $page )
			|| ( isset( $input['arn_search'] ) && '1' !== $input['arn_search'] )
			|| ( isset( $input['page_id'] ) && ( 1 !== preg_match( '/\A[1-9][0-9]*\z/D', $input['page_id'] ) || (string) (int) $input['page_id'] !== $input['page_id'] ) ) ) {
			return null;
		}
		return new self( (string) preg_replace( '/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $q ), $type, (int) $page );
	}
}
