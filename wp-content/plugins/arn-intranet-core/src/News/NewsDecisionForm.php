<?php
/** Opaque editorial CAS transport.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

// phpcs:disable Squiz.Commenting.FunctionComment
/** Binds tokens to the actor, object, operation, version and revision. */
final class NewsDecisionForm {
	/** Produce an opaque HMAC without sending the signed tuple. */
	public static function token( Article $article, int $actor_id, string $operation, int $version, string $key ): string {
		$parts = array( 'arn-news-cas-v1', $actor_id, $article->id, $operation, $version, $article->revision );
		return hash_hmac( 'sha256', implode( "\0", $parts ), $key );
	}

	/** Verify a decision token in constant time. */
	public static function verify( mixed $token, Article $article, int $actor_id, string $operation, int $version, string $key ): bool {
		return is_string( $token ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $token )
			&& hash_equals( self::token( $article, $actor_id, $operation, $version, $key ), $token );
	}

	/** Derive a dedicated key from the WordPress nonce secret. */
	public static function serverKey(): string {
		return hash_hmac( 'sha256', 'arn-news-cas-key-v1', wp_salt( 'nonce' ) );
	}
}
