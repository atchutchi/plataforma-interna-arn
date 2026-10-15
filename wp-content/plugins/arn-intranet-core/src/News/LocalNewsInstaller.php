<?php
/** Closed legacy CLI installer and retained path/grant validators.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use RuntimeException;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors

/** Never registered on activation or a web request. */
final class LocalNewsInstaller {
	private const ERROR = 'Instalação fictícia de notícias recusada.';

	public function __construct( private readonly ?string $prefix = null, private readonly ?string $private_root = null ) {}

	public static function eligible( string $environment, mixed $newsroom, mixed $files, mixed $fictional, mixed $storage, mixed $editorial, bool $cli, bool $admin ): bool {
		return NewsConfiguration::enabled( $environment, $newsroom, $files, $fictional, $storage, $editorial ) && $cli && $admin;
	}

	/** Legacy two-person installation is closed after the editorial schema change. */
	public function install(): array {
		throw new RuntimeException( self::ERROR );
	}

	/** Both arguments are canonical absolute paths from realpath in the caller. */
	public static function allowedLocation( string $root, string $project ): bool {
		$normalize = static function ( string $path ): string {
			$path = rtrim( str_replace( '\\', '/', $path ), '/' );
			return '\\' === DIRECTORY_SEPARATOR ? strtolower( $path ) : $path;
		};
		$root      = $normalize( $root );
		$project   = $normalize( $project );
		if ( '' === $root || '' === $project || str_contains( $root, "\0" ) || str_contains( $project, "\0" )
			|| 1 === preg_match( '~(^|/)\.\.?(/|$)~', $root ) || 1 === preg_match( '~(^|/)\.\.?(/|$)~', $project ) ) {
			return false;
		}
		if ( $root === $project . '/.git/tools/local-private-files' ) {
			return true;
		}
		return $root !== $project && ! str_starts_with( $root, $project . '/' );
	}

	/** Any extra, expired, duplicate or revoked grant blocks setup. */
	public static function existingGrantId( AuthorizationSnapshot $snapshot, Role $role, Scope $scope ): ?int {
		$id = null;
		foreach ( $snapshot->stored_grants as $stored ) {
			$grant = $stored->grant;
			if ( $grant->role !== $role || $grant->scope->type !== $scope->type || $grant->scope->targets !== $scope->targets || $grant->revoked || null !== $grant->expires_at || $grant->starts_at > time() || null !== $id ) {
				throw new RuntimeException( self::ERROR );
			}
			$id = $stored->id;
		}
		return $id;
	}
}
