<?php
/**
 * Visibilidade dos atalhos administrativos existentes.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Administration\DashboardLinks;
use PHPUnit\Framework\TestCase;

/** Confere capacidades efectivas e disponibilidade sem conceder acesso. */
final class DashboardLinksTest extends TestCase {

	/** A ausência de leitura impede qualquer atalho. */
	public function testNoLinksWithoutReadCapability(): void {
		self::assertSame( array(), DashboardLinks::visible( array( 'manage_options' => true ), 'local', true, true ) );
	}

	/** Subscriber recebe apenas o perfil, mesmo com ferramentas registadas. */
	public function testSubscriberHasOnlyProfile(): void {
		$links = DashboardLinks::visible( array( 'read' => true ), 'local', true, true );
		self::assertSame( array( 'profile' ), array_column( $links, 'id' ) );
		self::assertSame( 'profile.php', $links[0]['path'] );
	}

	/** Editor vê os recursos que pode gerir, sem ferramentas administrativas. */
	public function testEditorGetsContentLinks(): void {
		$links = DashboardLinks::visible(
			array(
				'read'         => true,
				'edit_pages'   => true,
				'edit_posts'   => true,
				'upload_files' => true,
			),
			'local',
			true,
			true
		);
		self::assertSame( array( 'pages', 'posts', 'media', 'profile' ), array_column( $links, 'id' ) );
		self::assertSame( 'edit.php?post_type=page', $links[0]['path'] );
	}

	/** As duas ferramentas precisam de ambiente local e registo efectivo. */
	public function testAdministrativeToolsRequireLocalRegistration(): void {
		$caps = array(
			'read'           => true,
			'manage_options' => true,
		);
		self::assertSame( array( 'google', 'probe', 'profile' ), array_column( DashboardLinks::visible( $caps, 'local', true, true ), 'id' ) );
		self::assertSame( array( 'google', 'profile' ), array_column( DashboardLinks::visible( $caps, 'local', true, false ), 'id' ) );
		self::assertSame( array( 'profile' ), array_column( DashboardLinks::visible( $caps, 'local', false, false ), 'id' ) );
		foreach ( array( 'development', 'staging', 'production', '' ) as $environment ) {
			self::assertSame( array( 'profile' ), array_column( DashboardLinks::visible( $caps, $environment, true, true ), 'id' ) );
		}
	}

	/** Valores falsos ou mal tipados não tornam recursos visíveis. */
	public function testCapabilitiesRequireBooleanTrue(): void {
		foreach ( array( false, 1, 'true', null ) as $value ) {
			self::assertSame( array(), DashboardLinks::visible( array( 'read' => $value ), 'local', true, true ) );
			$links = DashboardLinks::visible(
				array(
					'read'           => true,
					'edit_pages'     => $value,
					'edit_posts'     => $value,
					'upload_files'   => $value,
					'manage_options' => $value,
				),
				'local',
				true,
				true
			);
			self::assertSame( array( 'profile' ), array_column( $links, 'id' ) );
		}
	}
}
