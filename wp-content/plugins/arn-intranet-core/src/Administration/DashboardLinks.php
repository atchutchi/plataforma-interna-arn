<?php
/**
 * Catálogo de atalhos para recursos já existentes no WordPress.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Administration;

/** Filtra a apresentação. Cada destino conserva a sua própria autorização. */
final class DashboardLinks {

	/**
	 * Escolhe atalhos por capacidades efectivas e menus registados.
	 *
	 * @param array  $capabilities     Capacidades booleanas do WordPress.
	 * @param string $environment      Ambiente efectivo.
	 * @param bool   $google_registered Menu Google disponível.
	 * @param bool   $probe_registered  Menu dos ensaios disponível.
	 * @return array Lista de atalhos com valores estáticos.
	 */
	public static function visible( array $capabilities, string $environment, bool $google_registered, bool $probe_registered ): array {
		if ( true !== ( $capabilities['read'] ?? false ) ) {
			return array();
		}

		$catalog = array(
			array(
				'id'          => 'pages',
				'capability'  => 'edit_pages',
				'title'       => 'Páginas',
				'description' => 'Organizar a informação institucional.',
				'path'        => 'edit.php?post_type=page',
				'icon'        => 'admin-page',
			),
			array(
				'id'          => 'posts',
				'capability'  => 'edit_posts',
				'title'       => 'Artigos WordPress',
				'description' => 'Gerir artigos nativos do WordPress.',
				'path'        => 'edit.php',
				'icon'        => 'admin-post',
			),
			array(
				'id'          => 'media',
				'capability'  => 'upload_files',
				'title'       => 'Multimédia',
				'description' => 'Gerir imagens e recursos do CMS.',
				'path'        => 'upload.php',
				'icon'        => 'admin-media',
			),
			array(
				'id'          => 'google',
				'capability'  => 'manage_options',
				'title'       => 'Acesso Google local',
				'description' => 'Consultar a ferramenta de associação do laboratório.',
				'path'        => 'tools.php?page=arn-google-access',
				'icon'        => 'lock',
			),
			array(
				'id'          => 'probe',
				'capability'  => 'manage_options',
				'title'       => 'Ensaios de acesso local',
				'description' => 'Verificar as decisões com cenários fictícios.',
				'path'        => 'tools.php?page=arn-authorization-probe',
				'icon'        => 'shield',
			),
			array(
				'id'          => 'profile',
				'capability'  => 'read',
				'title'       => 'O meu perfil',
				'description' => 'Consultar o perfil da tua conta WordPress.',
				'path'        => 'profile.php',
				'icon'        => 'admin-users',
			),
		);
		return array_values(
			array_filter(
				$catalog,
				static function ( array $link ) use ( $capabilities, $environment, $google_registered, $probe_registered ): bool {
					if ( true !== ( $capabilities[ $link['capability'] ] ?? false ) ) {
						return false;
					}
					if ( 'google' === $link['id'] ) {
						return 'local' === $environment && $google_registered;
					}
					if ( 'probe' === $link['id'] ) {
						return 'local' === $environment && $probe_registered;
					}
					return true;
				}
			)
		);
	}
}
