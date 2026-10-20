<?php
/** Closed transport and recovery without WordPress side effects.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Cms\{SettingsInput, SettingsPage, SettingsState, UploadPolicy};
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class CmsSettingsPageTest extends TestCase {
	private function source(): object {
		return new class() {
			public SettingsState $state;
			public bool $allowed          = true;
			public bool $revoke_on_change = false;
			public int $writes            = 0;
			public array $events          = array();
			public function __construct() {
				$this->state = SettingsState::initial(); }
			public function read(): SettingsState {
				if ( ! $this->allowed ) {
					throw new DomainException( 'Refused' ); }
				return $this->state;
			}
			public function history( int $before ): array {
				$this->read();
				return array_values( array_filter( $this->events, static fn( array $event ): bool => $event['after']['revision'] < $before ) );
			}
			public function change( UploadPolicy $policy, int $revision, string $reason ): SettingsState {
				if ( $this->revoke_on_change ) {
					$this->allowed = false; }
				$before      = $this->read();
				$this->state = $before->change( $policy, $revision, 7, 100, $reason );
				if ( $this->state !== $before ) {
					++$this->writes;
					array_unshift(
						$this->events,
						array(
							'before' => $before->toArray(),
							'after'  => $this->state->toArray(),
						)
					);
				}
				return $this->state;
			}
		};
	}
	private function post(): array {
		return SettingsInput::fields( SettingsState::initial() ) + array(
			'nonce'    => 'valid',
			'revision' => '1',
		);
	}
	public function testGetDoesNotWriteAndUsesBoundedHistory(): void {
		$source = $this->source();
		$model  = SettingsPage::decide( 'GET', array(), array(), $source, 7, static fn(): bool => false );
		self::assertSame( 1, $model['state']->revision );
		self::assertSame( 0, $source->writes );
		self::assertFalse( $model['saved'] );
		self::assertSame( array(), $model['history'] );
	}
	public function testForgedShapeNonceAndQueryRefuseBeforeWriting(): void {
		$post           = $this->post();
		$post['reason'] = 'Ensaio';
		$source         = $this->source();
		foreach ( array( $post + array( 'role' => 'administrator' ), array_replace( $post, array( 'revision' => array( '1' ) ) ), array_replace( $post, array( 'document_bytes' => 12 ) ), array_replace( $post, array( 'document_mimes' => array( array( 'application/pdf' ) ) ) ) ) as $bad ) {
			self::assertNull( SettingsPage::decide( 'POST', array(), $bad, $source, 7, static fn(): bool => true ) );
		}
		self::assertNull( SettingsPage::decide( 'POST', array(), $post, $source, 7, static fn(): bool => false ) );
		self::assertNull( SettingsPage::decide( 'POST', array( 'before' => '2' ), $post, $source, 7, static fn(): bool => true ) );
		self::assertNull( SettingsPage::decide( 'GET', array( 'before' => array( '2' ) ), array(), $source, 7, static fn(): bool => true ) );
		self::assertNull( SettingsPage::decide( 'DELETE', array(), array(), $source, 7, static fn(): bool => true ) );
		self::assertSame( 0, $source->writes );
	}
	public function testValidChangeAndNoOp(): void {
		$source                 = $this->source();
		$post                   = $this->post();
		$post['reason']         = 'Redução fictícia';
		$post['document_bytes'] = '512';
		self::assertSame( array( 'saved' => true ), SettingsPage::decide( 'POST', array(), $post, $source, 7, static fn(): bool => true ) );
		self::assertSame( 2, $source->state->revision );
		$post['revision'] = '2';
		SettingsPage::decide( 'POST', array(), $post, $source, 7, static fn(): bool => true );
		self::assertSame( 1, $source->writes );
	}
	public function testConflictPreservesFieldsAndShowsCurrentRevision(): void {
		$source                          = $this->source();
		$values                          = UploadPolicy::defaults()->toArray();
		$values['document']['max_bytes'] = 100;
		$source->change( UploadPolicy::fromArray( $values ), 1, 'Outra alteração' );
		$post                   = $this->post();
		$post['reason']         = 'O meu motivo';
		$post['document_bytes'] = '200';
		$model                  = SettingsPage::decide( 'POST', array(), $post, $source, 7, static fn(): bool => true );
		self::assertSame( '200', $model['fields']['document_bytes'] );
		self::assertSame( 'O meu motivo', $model['fields']['reason'] );
		self::assertSame( 100, $model['state']->policy->toArray()['document']['max_bytes'] );
		self::assertSame( 2, $model['state']->revision );
		self::assertNotSame( '', $model['error'] );
		self::assertSame( 1, $source->writes );
	}
	public function testInvalidValuesRecoverWithoutWritesAndRevocationRemovesForm(): void {
		$source         = $this->source();
		$post           = $this->post();
		$post['reason'] = 'Motivo';
		foreach ( array( '0', '-1', '1.5', '1048577', '1e3', '' ) as $invalid ) {
			$post['news_image_bytes'] = $invalid;
			$model                    = SettingsPage::decide( 'POST', array(), $post, $source, 7, static fn(): bool => true );
			self::assertSame( $invalid, $model['fields']['news_image_bytes'] );
			self::assertNotSame( '', $model['error'] );
		}
		$post           = $this->post();
		$post['reason'] = 'Motivo';
		unset( $post['document_mimes'] );
		$model = SettingsPage::decide( 'POST', array(), $post, $source, 7, static fn(): bool => true );
		self::assertSame( array(), $model['fields']['document_mimes'] );
		self::assertSame( 0, $source->writes );
		$post                     = $this->post();
		$post['reason']           = 'Motivo';
		$source->revoke_on_change = true;
		self::assertNull( SettingsPage::decide( 'POST', array(), $post, $source, 7, static fn(): bool => true ) );
		self::assertSame( 0, $source->writes );
	}
}
