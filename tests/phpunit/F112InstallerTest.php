<?php
/**
 * F1-12 local fictional composition.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure installer contract tests without WordPress functions.
declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\F112\{LocalInstaller, LocalRoster, Pages, PostTypes};
use PHPUnit\Framework\TestCase;

final class F112InstallerTest extends TestCase {
	public function test_completion_marker_rejects_partial_or_foreign_resource_maps(): void {
		$prefix = 'arn_f112_0123456789_';
		$proof  = array(
			'version' => 1,
			'prefix'  => $prefix,
			'users'   => array(
				'a'        => 1,
				'b'        => 2,
				'manager'  => 3,
				'author'   => 4,
				'reviewer' => 5,
				'events'   => 6,
			),
			'pages'   => array(
				'communications' => 11,
				'calendar'       => 12,
			),
		);
		self::assertSame( $proof, \Arn\Intranet\F112\InstallationState::decode( json_encode( $proof ), $prefix ) );
		$partial = $proof;
		unset( $partial['pages']['calendar'] );
		self::assertNull( \Arn\Intranet\F112\InstallationState::decode( json_encode( $partial ), $prefix ) );
		$foreign           = $proof;
		$foreign['prefix'] = 'wp_';
		self::assertNull( \Arn\Intranet\F112\InstallationState::decode( json_encode( $foreign ), $prefix ) );
		$duplicate               = $proof;
		$duplicate['users']['a'] = 2;
		self::assertNull( \Arn\Intranet\F112\InstallationState::decode( json_encode( $duplicate ), $prefix ) );
	}
	public function test_roster_is_closed_and_institutional_people_have_no_invented_unit(): void {
		$definitions = LocalRoster::definitions();
		self::assertCount( 6, $definitions );
		self::assertSame( array( 'LAB-A' ), $definitions['manager']['units'] );
		foreach ( array( 'author', 'reviewer', 'events' ) as $label ) {
			self::assertSame( array(), $definitions[ $label ]['units'] );
		}
		self::assertTrue( LocalInstaller::validPrefix( 'arn_f112_0123456789_' ) );
		self::assertFalse( LocalInstaller::validPrefix( 'wp_' ) );
		self::assertSame( 'arn_f112_0123456789_a', LocalRoster::definitions( 'arn_f112_0123456789_' )['a']['login'] );
	}

	public function test_installer_requires_both_explicit_modules_cli_and_admin(): void {
		self::assertTrue( LocalInstaller::eligible( 'local', true, true, true, true, true, true, true ) );
		foreach ( array( false, null, 0, 1, 'true' ) as $flag ) {
			self::assertFalse( LocalInstaller::eligible( 'local', $flag, true, true, true, true, true, true ) );
			self::assertFalse( LocalInstaller::eligible( 'local', true, $flag, true, true, true, true, true ) );
		}
		self::assertFalse( LocalInstaller::eligible( 'development', true, true, true, true, true, true, true ) );
		self::assertFalse( LocalInstaller::eligible( 'local', true, true, true, true, true, false, true ) );
		self::assertFalse( LocalInstaller::eligible( 'local', true, true, true, true, true, true, false ) );
	}

	public function test_native_anchors_offer_no_publishing_surface(): void {
		foreach ( array( 'arn_comunicado', 'arn_evento' ) as $type ) {
			$args = PostTypes::arguments( $type );
			foreach ( array( 'public', 'publicly_queryable', 'show_ui', 'show_in_rest', 'rewrite', 'query_var', 'has_archive' ) as $key ) {
				self::assertFalse( $args[ $key ] );
			}
			self::assertTrue( $args['exclude_from_search'] );
			self::assertFalse( $args['supports'] );
			self::assertSame( 'do_not_allow', $args['capabilities']['edit_posts'] ?? null );
			self::assertSame( 'do_not_allow', $args['capabilities']['read_private_posts'] ?? null );
		}
		self::assertSame( 'comunicados', ( new Pages() )->slug( 'communications' ) );
		self::assertSame( '<!-- wp:arn/f112-view {"module":"calendar"} /-->', Pages::content( 'calendar' ) );
	}
}
