<?php
/**
 * O escape nativo do WordPress não pode ser confundido com transformação externa.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\PreRegistration\WorkspacePreRegistrationCommand;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/doubles/IdentityWpdbDouble.php';

// phpcs:disable Squiz.Commenting, WordPress.WP.GlobalVariablesOverride.Prohibited -- Fixture de callbacks globais, sem executar SQL ou substituir o core.
final class WorkspaceRuntimeFilterTest extends TestCase {
	protected function tearDown(): void {
		unset( $GLOBALS['wp_filter'] );}
	public function testNativePlaceholderEscapeIsAllowed(): void {
		$GLOBALS['wp_filter'] = array(
			'query' => (object) array(
				'callbacks' => array(
					0 => array(
						array(
							'function'      => array( new \wpdb( '', '', '', '' ), 'remove_placeholder_escape' ),
							'accepted_args' => 1,
						),
					),
				),
			),
		);
		WorkspacePreRegistrationCommand::assertSqlFilters();
		self::assertTrue( true );
	}
	public function testOtherQueryMutationIsRefused(): void {
		$GLOBALS['wp_filter'] = array(
			'query' => (object) array(
				'callbacks' => array(
					0 => array(
						array(
							'function'      => static fn( string $sql ): string=>$sql,
							'accepted_args' => 1,
						),
					),
				),
			),
		);
		$this->expectException( RuntimeException::class );
		WorkspacePreRegistrationCommand::assertSqlFilters();
	}
}
