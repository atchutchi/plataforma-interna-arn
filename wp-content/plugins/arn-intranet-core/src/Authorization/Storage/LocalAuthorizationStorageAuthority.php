<?php
/** Autoridade limitada ao laboratório local.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Storage;

use Arn\Intranet\Access\LocalFixtureAccess;
use Arn\Intranet\Access\LocalFixtureUser;
use WP_User;
/** Não atribui autoridade institucional ou papéis WordPress. */
final class LocalAuthorizationStorageAuthority implements AuthorizationStorageAuthority {
	/**
	 * Verifica o actor efectivo do laboratório.
	 *
	 * @param int $actor_id Actor da operação.
	 */
	public function canManage( int $actor_id ): bool {
		return $actor_id > 0 && get_current_user_id() === $actor_id && 'local' === wp_get_environment_type() && defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) && true === ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE && current_user_can( 'manage_options' );
	}
	/**
	 * Exige uma conta actual com marca persistente.
	 *
	 * @param int $user_id Conta fictícia destinatária.
	 */
	public function canUseAccount( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		$user = get_userdata( $user_id );
		return $user instanceof WP_User && '1' === get_user_meta( $user_id, LocalFixtureUser::META_KEY, true ) && LocalFixtureAccess::isFixture( $user );
	}
}
