<?php
/** A notice source must not open unrelated service authorization readers.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\EditorialPreview\HomeNotificationSources;
use PHPUnit\Framework\Attributes\{RunInSeparateProcess, PreserveGlobalState};
use PHPUnit\Framework\TestCase;
// phpcs:disable Squiz.Commenting, Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval, Squiz.PHP.Eval.Discouraged -- Literal test doubles in an isolated process only.
final class HomeNotificationSourceIsolationTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function testOnlyTheRequestedSourceResolvesDestinations(): void {
		eval( 'namespace Arn\\Intranet\\F112; final class Navigation { public static int $calls=0; public static function destinations(): array { ++self::$calls; return ["communications"=>["url"=>"/comunicados/"],"calendar"=>["url"=>"/calendario/"]]; } } final class Portal { public function __construct(string $module) {} public function notices(int $page): array { return ["items"=>[]]; } }' );
		eval( 'namespace Arn\\Intranet\\Tasks; final class Navigation { public static int $calls=0; public static function destinations(): array { ++self::$calls; return ["tasks_own"=>["url"=>"/minhas-tarefas/"]]; } } final class Portal { public function notices(int $page): array { return ["items"=>[]]; } }' );
		foreach ( array( 'Requests', 'Support', 'Leave' ) as $namespace ) {
			eval( 'namespace Arn\\Intranet\\' . $namespace . '; final class Navigation { public static int $calls=0; public static function destinations(): array { ++self::$calls; return []; } }' );
		}
		ArnWordPressDoubles::$logged_in = true;
		function wp_get_environment_type(): string {
			return 'local'; }
		function is_admin(): bool {
			return false; }
		function add_query_arg( array $args, string $url ): string {
			return $url . '?' . http_build_query( $args ); }
		foreach ( array( 'communications', 'calendar', 'tasks_own' ) as $source ) {
			self::assertNotNull( HomeNotificationSources::read( $source ) );
		}
		self::assertSame( 0, \Arn\Intranet\Requests\Navigation::$calls );
		self::assertSame( 0, \Arn\Intranet\Support\Navigation::$calls );
		self::assertSame( 0, \Arn\Intranet\Leave\Navigation::$calls );
		self::assertSame( 2, \Arn\Intranet\F112\Navigation::$calls );
		self::assertSame( 1, \Arn\Intranet\Tasks\Navigation::$calls );
	}
}
