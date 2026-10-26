<?php
/** Explicit local attachment opt-in, independent of existing task access.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Support;

use Arn\Intranet\Documents\PrivateFiles\LocalPrivateFiles;

// phpcs:disable Squiz.Commenting
final class AttachmentConfiguration {
	public static function readable(): bool {
		return Configuration::current() && LocalPrivateFiles::configured() && defined( 'ARN_ALLOW_LOCAL_TICKET_ATTACHMENTS' ) && true === ARN_ALLOW_LOCAL_TICKET_ATTACHMENTS;
	}

	public static function enabled(): bool {
		return self::readable() && defined( 'ARN_ALLOW_LOCAL_MEDIA_PROCESSING' ) && true === ARN_ALLOW_LOCAL_MEDIA_PROCESSING;
	}
}
