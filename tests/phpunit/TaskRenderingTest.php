<?php
/** Task surfaces escape private text and preserve filtered navigation.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Tasks\TaskSelection;
use PHPUnit\Framework\Attributes\{PreserveGlobalState, RunInSeparateProcess};
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions -- Rendering in an isolated process with minimal WordPress presentation doubles.
final class TaskRenderingTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function testEscapingPrivateReasonsFormsAndPagination(): void {
		define( 'ABSPATH', dirname( __DIR__, 2 ) );
		function esc_html( string $value ): string {
			return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
		function esc_attr( string $value ): string {
			return esc_html( $value ); }
		function esc_textarea( string $value ): string {
			return esc_html( $value ); }
		function esc_url( string $value ): string {
			return esc_html( $value ); }
		function get_permalink(): string {
			return 'http://localhost/minhas-tarefas/'; }
		function home_url( string $path ): string {
			return 'http://localhost' . $path; }
		function wp_create_nonce( string $action ): string {
			return hash( 'sha256', $action ); }
		function selected( mixed $a, mixed $b ): void {
			if ( (string) $a === (string) $b ) {
				echo 'selected="selected"'; } }
		function add_query_arg( array|string $key, string $value, ?string $url = null ): string {
			$fields = is_array( $key ) ? $key : array( $key => $value );
			return ( $url ?? $value ) . '?' . http_build_query( $fields );
		}
		$model  = array(
			'view'   => 'own',
			'screen' => 'detail',
			'access' => array(
				'actor_id' => 7,
				'create'   => false,
			),
			'query'  => array(
				'selection' => TaskSelection::parse( array() ),
				'get'       => array(),
			),
			'errors' => array( 'body' => 'Corrige o comentário' ),
			'input'  => array(
				'operation'         => 'comment',
				'body'              => '</textarea><script>alert(1)</script>',
				'expected_revision' => '1',
			),
			'data'   => array(
				'id'                => 9,
				'revision'          => 2,
				'title'             => '<script>Título</script>',
				'description'       => '<img src=x onerror=alert(1)>',
				'state'             => 'open',
				'priority'          => 'normal',
				'overdue'           => false,
				'assignee'          => 'Alfa',
				'author'            => 'Gestor',
				'unit_id'           => 'LAB-A',
				'due_at'            => null,
				'updated_at'        => 1700000000,
				'operations'        => array( 'comment' ),
				'comments'          => array(
					array(
						'actor'       => 'Alfa',
						'occurred_at' => 1700000000,
						'body'        => '<script>comentário</script>',
					),
				),
				'history'           => array(
					array(
						'operation'   => 'created',
						'actor'       => 'Gestor',
						'occurred_at' => 1700000000,
						'revision'    => 1,
						'state'       => 'open',
						'due_at'      => null,
						'reason'      => 'MOTIVO-INTERNO',
						'assignee'    => 'Alfa',
					),
				),
				'create_request_id' => 'NAO-PROJECTAR',
				'after'             => array( 'secret' => 'NAO-PROJECTAR' ),
			),
		);
		$render = static function ( array $model ): string {
			$args = array( 'tasks' => $model );
			ob_start();
			include dirname( __DIR__, 2 ) . '/wp-content/themes/arn-intranet/portal/tasks.php';
			return (string) ob_get_clean();
		};
		$html   = $render( $model );
		self::assertStringNotContainsString( '<script>', $html );
		self::assertStringNotContainsString( '<img src=', $html );
		self::assertStringContainsString( '&lt;/textarea&gt;&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		self::assertStringContainsString( 'aria-invalid="true"', $html );
		self::assertStringContainsString( 'name="expected_revision" value="1"', $html );
		self::assertStringNotContainsString( 'NAO-PROJECTAR', $html );
		self::assertStringNotContainsString( 'MOTIVO-INTERNO', $html );
		$model['view'] = 'team';
		$html          = $render( $model );
		self::assertStringContainsString( 'MOTIVO-INTERNO', $html );
		self::assertStringContainsString( 'Responsável: Alfa', $html );
		$model['screen'] = 'list';
		$model['view']   = 'own';
		$model['errors'] = array();
		$model['query']  = array(
			'selection' => TaskSelection::parse(
				array(
					'page' => '2',
					'q'    => 'alfa',
				)
			),
			'get'       => array(
				'q'              => 'alfa',
				'arn_tasks_page' => '2',
			),
		);
		$model['data']   = array(
			'items' => array(),
			'total' => 1,
			'page'  => 2,
			'pages' => 3,
		);
		$html            = $render( $model );
		self::assertStringContainsString( 'q=alfa&amp;arn_tasks_page=1', $html );
		self::assertStringContainsString( 'q=alfa&amp;arn_tasks_page=3', $html );
		self::assertStringContainsString( '>1 tarefa</h2>', $html );
	}
}
