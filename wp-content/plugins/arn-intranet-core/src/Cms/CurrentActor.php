<?php
/** Native laboratory authority on the settings connection.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\Storage\{AuthorizationSchema, WpdbAuthorizationRepository};
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use DomainException;
use RuntimeException;
use WP_User;

// phpcs:disable Squiz.Commenting -- Closed local roster and native session proof.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
/** No WordPress capability substitutes the dedicated business grant. */
final class CurrentActor implements SettingsAuthority {
	public const LOGINS = array( 'cms.gestor.teste', 'cms.recusado.teste' );

	public function __construct( private readonly IdentityWpdb $database, private readonly Closure $clock, private readonly ?Closure $resolver = null ) {}

	public function connection(): IdentityWpdb {
		return $this->database;
	}

	public function locked(): SettingsActor {
		if ( ! Configuration::current() || ! $this->database->inTransaction() ) {
			throw new RuntimeException( 'Autoridade de parâmetros indisponível.' );
		}
		$resolved = $this->resolve();
		if ( null === $resolved ) {
			throw new DomainException( 'Sessão de parâmetros indisponível.' );
		}
		( new AuthorizationSchema( $this->database ) )->validate();
		$snapshot = ( new WpdbAuthorizationRepository( $this->database ) )->findInTransaction( $resolved[0] );
		$user     = get_userdata( $resolved[0] );
		if ( null === $snapshot || ! $user instanceof WP_User || ! in_array( $user->user_login, self::LOGINS, true ) || $user->user_login . '@example.invalid' !== $user->user_email || array( 'subscriber' ) !== array_values( $user->roles ) || '1' !== get_user_meta( $resolved[0], LocalFixtureUser::META_KEY, true ) || get_user_meta( $resolved[0], '_arn_cms_fixture', true ) !== $this->database->prefix ) {
			throw new DomainException( 'Conta de parâmetros indisponível.' );
		}
		$session = null === $this->resolver ? $this->nativeSession( $resolved[0], $resolved[1] ) : $resolved[1];
		return new SettingsActor( $snapshot, $session );
	}

	private function resolve(): ?array {
		$value = null !== $this->resolver ? ( $this->resolver )() : array( get_current_user_id(), array( 'native_token' => wp_get_session_token() ) );
		return is_array( $value ) && array_is_list( $value ) && 2 === count( $value ) && is_int( $value[0] ) && $value[0] > 0 && is_array( $value[1] ) ? $value : null;
	}

	private function nativeSession( int $id, array $proof ): array {
		$token = $proof['native_token'] ?? null;
		if ( ! is_string( $token ) ) {
			return array();
		}
		return ( new NativeSession( $this->database, $GLOBALS['wpdb']->usermeta ) )->read( $id, $token, $this->now() );
	}

	private function now(): int {
		$now = ( $this->clock )();
		if ( ! is_int( $now ) || $now < 0 ) {
			throw new RuntimeException( 'Relógio de parâmetros indisponível.' );
		}
		return $now;
	}
}
