<?php
/** Closed, explicit command. No email-based identity inference.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Lifecycle;

use Arn\Intranet\Authorization\Storage\ChangeContext;
use RuntimeException;
// phpcs:disable WordPress.PHP.YodaConditions -- Structured record comparisons.
// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
final readonly class Command {
	public function __construct( public string $action, public string $person, public string $state, public int $account, public int $revision, public int $catalog_revision, public int $account_revision, public string $reason, public string $request_id ) {
		if ( ! in_array( $action, array( 'link', 'state' ), true ) || 1 !== preg_match( '/^LAB-PER-(?!000)[0-9]{3}$/D', $person ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $request_id ) || $revision < 0 || $revision >= 10000 || $catalog_revision < 1 || $catalog_revision > 10000 || $account < 0 || $account_revision < 0 || ( ( 0 === $account ) !== ( 0 === $account_revision ) ) || ( 'link' === $action && ( $account < 1 || '' !== $state ) ) || ( 'state' === $action && ! in_array( $state, array( 'Active', 'Suspended', 'Closed' ), true ) ) ) {
			throw new RuntimeException( 'Decisão profissional inválida.' ); }
		new ChangeContext( 1, $reason, 'lifecycle/' . $request_id, 1 );
	}
	public function hash(): string {
		return hash( 'sha256', json_encode( get_object_vars( $this ), JSON_THROW_ON_ERROR ) );
	}
}
