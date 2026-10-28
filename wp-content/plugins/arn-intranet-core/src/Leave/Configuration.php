<?php
/** Leave requires its own explicit local gate and the existing fictional roster.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Leave;

// phpcs:disable Squiz.Commenting
final class Configuration {
	public static function current(): bool {
		return defined( 'ARN_ALLOW_LOCAL_LEAVE' ) && true === ARN_ALLOW_LOCAL_LEAVE && \Arn\Intranet\Requests\Configuration::current();}
}
