<?php
/** Sections of the native dashboard, using only registered authorised destinations.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Administration;

// phpcs:disable Squiz.Commenting -- Closed presentation catalogue, no business authority or writes.
final class DashboardSections {
	public static function visible( array $links, array $menu, array $submenu, array $capabilities, string $environment ): array {
		if ( true !== ( $capabilities['read'] ?? false ) ) {
			return array();
		}
		$groups = array(
			'management' => array(
				'title' => 'Gestão de conteúdos e serviços',
				'links' => array(),
			),
			'cms'        => array(
				'title' => 'Páginas e recursos WordPress',
				'links' => array(),
			),
			'laboratory' => array(
				'title' => 'Ferramentas do laboratório local',
				'links' => array(),
			),
			'account'    => array(
				'title' => 'A minha conta WordPress',
				'links' => array(),
			),
		);
		foreach ( $links as $link ) {
			$group = match ( $link['id'] ?? '' ) {
				'pages', 'posts', 'media' => 'cms', 'google', 'probe' => 'laboratory', 'profile' => 'account', default => null };
			if ( null !== $group ) {
				$groups[ $group ]['links'][] = $link;
			}
		}
		if ( 'local' === $environment ) {
			foreach ( array(
				array( 'news', 'arn-newsroom', 'Notícias', 'Preparar notícias e consultar a fila editorial autorizada.', 'edit-page' ),
				array( 'documents', 'arn-document-catalog', 'Catálogo documental', 'Gerir documentos, versões e destinatários autorizados.', 'media-document' ),
				array( 'communications', 'arn-communications', 'Comunicados', 'Gerir comunicados no teu âmbito de competência.', 'megaphone' ),
				array( 'calendar', 'arn-calendar', 'Calendário', 'Gerir eventos no teu âmbito de competência.', 'calendar-alt' ),
				array( 'settings', 'arn-cms-settings', 'Parâmetros do CMS', 'Definir limites de novos ficheiros e consultar alterações.', 'admin-settings' ),
			) as [$id, $slug, $title, $description, $icon] ) {
				if ( self::registered( $menu, $slug, 'read' ) ) {
					$groups['management']['links'][] = compact( 'id', 'title', 'description', 'icon' ) + array( 'path' => 'admin.php?page=' . $slug );
				}
			}
			if ( true === ( $capabilities['manage_options'] ?? false ) ) {
				foreach ( array(
					array( 'accounts', 'arn-local-account-state', 'Estado das contas', 'Consultar e alterar o estado das contas fictícias.', 'admin-users' ),
					array( 'grants', 'arn-local-grants', 'Funções e âmbitos', 'Gerir concessões fictícias com revisão e auditoria.', 'groups' ),
					array( 'units', 'arn-local-units', 'Unidades', 'Gerir a estrutura fictícia e consultar o seu histórico.', 'networking' ),
					array( 'import', 'arn-local-import-preview', 'Validar importação', 'Validar cenários sintéticos sem importar dados.', 'media-spreadsheet' ),
				) as [$id, $slug, $title, $description, $icon] ) {
					if ( self::registered( $submenu['tools.php'] ?? array(), $slug, 'manage_options' ) ) {
						$groups['laboratory']['links'][] = compact( 'id', 'title', 'description', 'icon' ) + array( 'path' => 'tools.php?page=' . $slug );
					}
				}
			}
		}
		return array_filter( $groups, static fn( array $group ): bool => array() !== $group['links'] );
	}

	private static function registered( mixed $items, string $slug, string $capability ): bool {
		if ( ! is_array( $items ) ) {
			return false;
		}
		foreach ( $items as $item ) {
			if ( is_array( $item ) && ( $item[1] ?? null ) === $capability && ( $item[2] ?? null ) === $slug ) {
				return true;
			}
		}
		return false;
	}
}
