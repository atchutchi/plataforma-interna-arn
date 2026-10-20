<?php
/** Deferred native composition, with connection lifetime bounded to each call.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class LocalSettings {
	public function read(): SettingsState {
		return $this->call( static fn( SettingsRepository $repository ): SettingsState => $repository->read() );
	}
	public function history( int $before ): array {
		return $this->call( static fn( SettingsRepository $repository ): array => $repository->history( $before, 20 ) );
	}
	public function change( UploadPolicy $policy, int $revision, string $reason ): SettingsState {
		return $this->call( static fn( SettingsRepository $repository ): SettingsState => $repository->change( $policy, $revision, $reason ) );
	}
	private function call( Closure $operation ): mixed {
		if ( ! Configuration::current() ) {
			throw new RuntimeException( 'Parâmetros indisponíveis.' );
		}
		global $wpdb;
		$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		try {
			$db->set_prefix( $wpdb->prefix );
			return $operation( new SettingsRepository( $db, new CurrentActor( $db, time( ... ) ), time( ... ) ) );
		} finally {
			$db->close();
		}
	}
}
