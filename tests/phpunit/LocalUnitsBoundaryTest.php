<?php
/** Guardas e entradas sem ligação à base de dados.
 *
 * @package ArnIntranet
 */

// Test doubles require namespace blocks and stand-alone functions.
// phpcs:disable Universal.Namespaces, Universal.Files.SeparateFunctionsFromOO.Mixed, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamTag, Squiz.Commenting.FunctionCommentThrowTag.Missing, Squiz.Commenting.VariableComment.MissingVar, WordPress.Security.EscapeOutput.ExceptionNotEscaped
declare(strict_types=1);
namespace Arn\Intranet\Organization\Storage {
	/** Operação wp_get_environment_type. */
	function wp_get_environment_type(): string {
		return \LocalUnitsBoundaryTest::$environment; }
	/** Operação get_current_user_id. */
	function get_current_user_id(): int {
		return \LocalUnitsBoundaryTest::$actor; }
	/** Operação current_user_can. */
	function current_user_can( string $capability ): bool {
		return 'manage_options' === $capability && \LocalUnitsBoundaryTest::$admin; }
}

namespace Arn\Intranet\Organization {
	/** Operação wp_die. */
	function wp_die( string $message, string $title, array $args ): never {
		throw new \RuntimeException( 'Recusado', $args['response'] ); }
	/** Operação wp_verify_nonce. */
	function wp_verify_nonce( string $nonce, string $action ): bool {
		return 'valid' === $nonce && 'arn-local-units' === $action; }
	/** Operação get_current_user_id. */
	function get_current_user_id(): int {
		return \LocalUnitsBoundaryTest::$actor; }
	/** Operação wp_unslash. */
	function wp_unslash( string $value ): string {
		return stripslashes( $value ); }
}

namespace {
	use Arn\Intranet\Organization\LocalUnitsPage;
	use Arn\Intranet\Organization\Storage\LocalUnitInstaller;
	use PHPUnit\Framework\Attributes\{RunTestsInSeparateProcesses, PreserveGlobalState, DataProvider};
	use PHPUnit\Framework\TestCase;
	#[RunTestsInSeparateProcesses]
	#[PreserveGlobalState( false )]
	/** Ensaios de unidades locais. */
	final class LocalUnitsBoundaryTest extends TestCase {
		/** Estado do ensaio local. */
		public static string $environment = 'local';
		/** Estado do ensaio local. */
		public static int $actor = 7;
		/** Estado do ensaio local. */
		public static bool $admin = true;
		#[DataProvider( 'flags' )]
		/** Verifica a exigência de flag booleana verdadeira. */
		public function testFlagsAreStrict( mixed $value, bool $allowed ): void {
			if ( null !== $value ) {
				define( 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE', $value ); }
			self::assertSame( $allowed, LocalUnitInstaller::allowed() );
		}
		/** Operação flags. */
		public static function flags(): array {
			return array( array( null, false ), array( false, false ), array( 'true', false ), array( true, true ) ); }
		#[DataProvider( 'inputs' )]
		/** Verifica a recusa de pedidos inválidos antes da persistência. */
		public function testInvalidRequestsNeverOpenStorage( string $field, mixed $value, int $status ): void {
			define( 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE', true );
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_POST                     = array(
				'action'   => 'arn-local-units',
				'_wpnonce' => 'valid',
				'scenario' => 'initialize',
				'revision' => '0',
				'reason'   => 'Teste local',
			);
			if ( 'method' === $field ) {
				$_SERVER['REQUEST_METHOD'] = $value; } elseif ( 'actor' === $field ) {
				self::$actor = $value; } elseif ( 'admin' === $field ) {
					self::$admin = $value; } elseif ( 'environment' === $field ) {
					self::$environment = $value; } else {
							$_POST[ $field ] = $value; }
					$page = new LocalUnitsPage(
						static function () {
							self::fail( 'Persistência aberta antes de validar.' );
						}
					);
			$this->expectExceptionCode( $status );
			$page->handlePost();
		}
		/** Operação inputs. */
		public static function inputs(): array {
			return array( array( 'method', 'GET', 405 ), array( 'actor', 0, 403 ), array( 'admin', false, 403 ), array( 'environment', 'production', 403 ), array( '_wpnonce', 'bad', 403 ), array( '_wpnonce', array(), 403 ), array( 'action', 'other', 400 ), array( 'scenario', array(), 400 ), array( 'scenario', 'custom', 400 ), array( 'revision', '00', 400 ), array( 'revision', '-1', 400 ), array( 'revision', '9223372036854775808', 400 ), array( 'reason', '', 400 ), array( 'reason', ' motivo', 400 ), array( 'reason', str_repeat( 'a', 501 ), 400 ), array( 'reason', "a\nb", 400 ) );
		}
	}
}
