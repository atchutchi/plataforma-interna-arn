<?php
/** Private request page, rendered only from the authorized controller model.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) || ! is_array( $args['tickets'] ?? null ) ) {
	return;
}
\Arn\Intranet\Support\View::render( $args['tickets'] );
