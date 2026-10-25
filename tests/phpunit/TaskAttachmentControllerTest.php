<?php
/** Upload failures must recheck visibility before rendering any protected task.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\Tasks\Controller;
use PHPUnit\Framework\Attributes\{PreserveGlobalState, RunInSeparateProcess};
use PHPUnit\Framework\TestCase;
// phpcs:disable Squiz.Commenting, Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions.json_encode_json_encode
final class TaskAttachmentControllerTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function testUploadRechecksAuthorityAfterProcessingFailure(): void {
		function wp_get_environment_type(): string {
			return 'local'; }
		foreach ( array( 'ARN_ALLOW_LOCAL_TASKS', 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE', 'ARN_ALLOW_LOCAL_TASK_ATTACHMENTS', 'ARN_ALLOW_LOCAL_PRIVATE_FILES', 'ARN_ALLOW_LOCAL_MEDIA_PROCESSING' ) as $flag ) {
			define( $flag, true ); }
		$source = new class() {
			public int $calls    = 0;
			public bool $visible = true;
			public bool $revoke  = false;
			public function access( string $view ): array {
				++$this->calls;
				return array( 'actor_id' => 7 ); }
			public function detail( int $id, string $view ): ?array {
				++$this->calls;
				return $this->visible ? array(
					'id'         => 1,
					'operations' => array( 'attach' ),
					'title'      => 'Private task',
				) : null; }
			public function attach( array $post, string $path, string $view ): int {
				++$this->calls;
				$this->visible = ! $this->revoke;
				throw new RuntimeException( 'Scanner failure with private detail' ); }
		};
		$post   = array(
			'operation'         => 'attach',
			'id'                => '1',
			'expected_revision' => '1',
			'label'             => 'Ficheiro',
			'nonce'             => 'nonce',
		);
		$files  = array(
			'attachment' => array(
				'tmp_name' => '/tmp/private-upload',
				'error'    => UPLOAD_ERR_OK,
				'size'     => 30,
			),
		);
		self::assertNull( Controller::decide( 'POST', 'own', array( 'entry' => '1' ), $post, $source, 7, static fn(): bool => false, $files, static fn(): bool => true ) );
		self::assertSame( 0, $source->calls );
		$model = Controller::decide( 'POST', 'own', array( 'entry' => '1' ), $post, $source, 7, static fn(): bool => true, $files, static fn(): bool => true );
		self::assertSame( 'Private task', $model['data']['title'] );
		self::assertArrayHasKey( 'form', $model['errors'] );
		self::assertStringNotContainsString( 'Scanner failure', json_encode( $model ) );
		self::assertStringNotContainsString( '/tmp/private-upload', json_encode( $model ) );
		$source->revoke = true;
		self::assertNull( Controller::decide( 'POST', 'own', array( 'entry' => '1' ), $post, $source, 7, static fn(): bool => true, $files, static fn(): bool => true ) );
	}
}
