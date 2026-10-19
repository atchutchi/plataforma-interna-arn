<?php
/** The native readers and policies, with explicit ownership of adapter resources.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Search;

use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Documents\PrivateFiles\WpdbFileRegistry;
use Arn\Intranet\Documents\Catalog\{LocalDocumentCatalog, CurrentDocumentActor, WpdbDocumentRepository, WpdbDocumentAuthority};
use Arn\Intranet\News\{LocalNewsroom, CurrentNewsActor, WpdbNewsRepository, WpdbNewsAuthority};
use Arn\Intranet\People\{LocalPeopleDirectory, WpdbPeopleAuthority, WpdbPeopleRepository};

// phpcs:disable Squiz.Commenting -- Dependency factories preserve the native principal, session and policies.
final class Readers {
	public static function news( ReadConnections $connections ): LocalNewsroom {
		$current = new CurrentNewsActor( static fn(): WpdbAuthorizationRepository => new WpdbAuthorizationRepository( $connections->open() ) );
		return new LocalNewsroom(
			$current,
			static function () use ( $connections, $current ): array {
				$db = $connections->open();
				return array( new WpdbNewsRepository( $db, new WpdbNewsAuthority( $db, $current, null, $current->participantUnits( ... ) ) ), new WpdbFileRegistry( $db ) );
			}
		);
	}
	public static function documents( ReadConnections $connections ): LocalDocumentCatalog {
		$current = new CurrentDocumentActor( static fn(): WpdbAuthorizationRepository => new WpdbAuthorizationRepository( $connections->open() ) );
		return new LocalDocumentCatalog(
			$current,
			static function () use ( $connections, $current ): array {
				$db = $connections->open();
				return array( new WpdbDocumentRepository( $db, new WpdbDocumentAuthority( $db, $current ) ), new WpdbFileRegistry( $db ) );
			}
		);
	}
	public static function people( ReadConnections $connections ): LocalPeopleDirectory {
		return new LocalPeopleDirectory(
			authority_factory: static fn(): WpdbPeopleAuthority => new WpdbPeopleAuthority( $connections->open() ),
			profiles_factory: static function () use ( $connections ): WpdbPeopleRepository {
				$db = $connections->open();
				return new WpdbPeopleRepository( $db, new WpdbPeopleAuthority( $db ) );
			}
		);
	}
}
