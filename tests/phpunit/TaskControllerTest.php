<?php
/** HTTP decisions without a live protected source.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Tasks\Controller;
use DomainException;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting -- Negative source spies and closed HTTP cases are documented by each assertion.

/** Malformed input never reaches authority or a protected task. */
final class TaskControllerTest extends TestCase {

	private function source(): object {
		return new class() {
			public int $calls     = 0;
			public bool $conflict = false;
			public bool $visible  = true;
			public array $last    = array();
			public function access( string $view ): array {
				++$this->calls;
				return array(
					'actor_id' => 7,
					'create'   => 'team' === $view,
				); }
			public function listing( object $selection, string $view ): array {
				++$this->calls;
				return array(
					'items' => array(),
					'total' => 0,
				); }
			public function detail( int $id, string $view ): ?array {
				++$this->calls;
				return $this->visible ? array(
					'id'       => $id,
					'revision' => 2,
					'title'    => 'Tarefa autorizada',
				) : null; }
			public function create( array $post ): int {
				++$this->calls;
				$this->last = $post;
				return 9; }
			public function change( array $post ): int {
				++$this->calls;
				if ( $this->conflict ) {
					throw new DomainException( 'Conflito' );
				} return (int) $post['id']; }
			public function notices( int $page ): array {
				++$this->calls;
				return array( 'items' => array() ); }
			public function markRead( int $id ): void {
				++$this->calls; }
		};
	}

	public function testInvalidTransportAndNoncePrecedeSource(): void {
		$source = $this->source();
		$post   = array(
			'operation'         => 'start',
			'id'                => '1',
			'expected_revision' => '1',
			'reason'            => '',
			'nonce'             => 'bad',
		);
		foreach ( array( array( 'DELETE', array(), array() ), array( 'GET', array( 'entry' => '01' ), array() ), array( 'POST', array( 'entry' => '1' ), $post ), array( 'POST', array( 'entry' => '1' ), $post + array( 'actor_id' => '7' ) ) ) as [$method, $get, $input] ) {
			self::assertNull( Controller::decide( $method, 'own', $get, $input, $source, 7, static fn(): bool => false ) );
		}
		self::assertSame( 0, $source->calls );
	}

	public function testGetAndHeadRemainReadOnly(): void {
		$source = $this->source();
		foreach ( array( 'GET', 'HEAD' ) as $method ) {
			$model = Controller::decide( $method, 'own', array(), array(), $source, 7, static fn(): bool => true );
			self::assertSame( 'list', $model['screen'] );
			self::assertSame( 0, $model['data']['total'] );
		}
		self::assertSame( array(), $source->last );
	}

	public function testCasReturnsAuthorisedFormAndForeignObjectDoesNot(): void {
		$source           = $this->source();
		$source->conflict = true;
		$post             = array(
			'operation'         => 'comment',
			'id'                => '1',
			'expected_revision' => '1',
			'body'              => 'Campo próprio',
			'nonce'             => 'nonce',
		);
		$model            = Controller::decide( 'POST', 'own', array( 'entry' => '1' ), $post, $source, 7, static fn(): bool => true );
		self::assertSame( 'detail', $model['screen'] );
		self::assertArrayHasKey( 'form', $model['errors'] );
		self::assertSame( $post, $model['input'] );
		$source->visible = false;
		self::assertNull( Controller::decide( 'POST', 'own', array( 'entry' => '1' ), $post, $source, 7, static fn(): bool => true ) );
	}

	public function testCreateNonceUsesRequestIdentityAndRedirectsToObject(): void {
		$source = $this->source();
		$post   = array(
			'operation'         => 'create',
			'nonce'             => 'nonce',
			'create_request_id' => str_repeat( 'a', 32 ),
			'title'             => 'Título',
			'description'       => '',
			'priority'          => 'normal',
			'due'               => '',
			'assignee_id'       => '7',
		);
		$seen   = array();
		$model  = Controller::decide(
			'POST',
			'team',
			array( 'new' => '1' ),
			$post,
			$source,
			7,
			static function ( ...$args ) use ( &$seen ): bool {
				$seen = $args;
				return true;
			}
		);
		self::assertSame( array( 'nonce', 7, 'team', 'create', 0, str_repeat( 'a', 32 ) ), $seen );
		self::assertSame( 9, $model['saved_id'] );
		self::assertSame( $post, $source->last );
		$get = Controller::decide( 'GET', 'team', array( 'new' => '1' ), array(), $source, 7, static fn(): bool => true );
		self::assertMatchesRegularExpression( '/\A[a-f0-9]{32}\z/D', $get['create_request_id'] );
	}
}
