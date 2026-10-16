<?php
/**
 * Guardas anteriores ao primeiro vínculo, com estado em memória.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\PreRegistration;

use ArnWordPressDoubles;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_Error;
use WP_User;

// phpcs:disable Squiz.Commenting, Universal.Files.SeparateFunctionsFromOO.Mixed -- Doubles do namespace mantêm os hooks isolados.
function wp_get_session_token(): string {
	return WordPressPreRegistrationAccessTest::$token; }
function add_action( string $hook, callable $callback, int $priority, int $accepted_args ): void {
	add_filter( $hook, $callback, $priority, $accepted_args ); }

/** Só o ensaio WordPress/SQL prova o comportamento nativo real. */
final class WordPressPreRegistrationAccessTest extends TestCase {
	public static string $token = 'old';
	private mixed $snapshot;
	private bool $enabled = true;
	private WordPressPreRegistrationAccess $guard;

	protected function setUp(): void {
		ArnWordPressDoubles::reset();
		self::$token                    = 'old';
		$this->enabled                  = true;
		ArnWordPressDoubles::$users[10] = new WP_User( 10, 'fixture.workspace', 'fixture@example.invalid' );
		ArnWordPressDoubles::$users[20] = new WP_User( 20, 'fixture.previous', 'previous@example.invalid' );
		ArnWordPressDoubles::$sessions  = array(
			10 => array(
				'old'   => array(),
				'fresh' => array(),
			),
			20 => array( 'previous' => array() ),
		);
		$this->snapshot                 = $this->record( 'AwaitingGoogle', 1 );
		$this->guard                    = new WordPressPreRegistrationAccess(
			function ( int $id ): ?PreRegistration {
				if ( 20 === $id ) {
					return null;
				}if ( $this->snapshot instanceof \Throwable ) {
					throw $this->snapshot;
				}return $this->snapshot;
			},
			fn(): bool=>$this->enabled
		);
	}
	private function record( string $state, int $revision ): PreRegistration {
		return PreRegistration::fromRow(
			array(
				'id'               => str_repeat( 'a', 32 ),
				'user_id'          => '10',
				'display_name'     => 'Pessoa Fictícia',
				'primary_email'    => 'fixture@example.invalid',
				'state'            => $state,
				'revision'         => (string) $revision,
				'authorized_by'    => '1',
				'source_sha256'    => str_repeat( 'b', 64 ),
				'inventory_sha256' => str_repeat( 'c', 64 ),
				'observed_at'      => '1894701600',
				'expires_at'       => '1894788000',
				'disabled_from'    => 'Disabled' === $state ? 'Linked' : null,
			)
		);}

	public function testAwaitingAccountRejectsEveryNativeAuthenticationChannel(): void {
		$user = ArnWordPressDoubles::$users[10];
		self::assertInstanceOf( WP_Error::class, $this->guard->filterAuthentication( $user ) );
		self::assertFalse( $this->guard->filterPasswordReset( true, 10 ) );
		self::assertFalse( $this->guard->filterApplicationPasswords( true, $user ) );
		$error = new WP_Error( '' );
		$this->guard->validatePasswordReset( $error, $user );
		self::assertSame( 'arn_workspace_google_required', $error->get_error_code() );
		self::assertFalse( $this->guard->filterCurrentUser( 10 ) );
		self::assertSame( array(), ArnWordPressDoubles::$sessions[10] );
		self::assertCount( 1, ArnWordPressDoubles::$sessions[20] );
	}
	public function testOtherAccountsAndExistingErrorsArePreserved(): void {
		$user = ArnWordPressDoubles::$users[20];
		self::assertSame( $user, $this->guard->filterAuthentication( $user ) );
		self::assertTrue( $this->guard->filterPasswordReset( true, 20 ) );
		self::assertTrue( $this->guard->filterApplicationPasswords( true, $user ) );
		self::assertSame( 20, $this->guard->filterCurrentUser( 20 ) );
		$error = new WP_Error( 'existing', 'Mensagem anterior' );
		self::assertSame( $error, $this->guard->filterAuthentication( $error ) );
		self::assertSame( $error, $this->guard->filterPasswordReset( $error, 20 ) );
	}
	public function testLinkedAccountRequiresExactFederatedRevision(): void {
		$this->snapshot                           = $this->record( 'Linked', 3 );
		ArnWordPressDoubles::$sessions[10]['old'] = array(
			'arn_oidc'               => true,
			'arn_workspace_revision' => 3,
		);
		self::assertSame( 10, $this->guard->filterCurrentUser( 10 ) );
		self::assertInstanceOf( WP_Error::class, $this->guard->filterAuthentication( ArnWordPressDoubles::$users[10] ) );
		ArnWordPressDoubles::$sessions[10]['old']['arn_workspace_revision'] = 2;
		self::assertFalse( $this->guard->filterCurrentUser( 10 ) );
		self::assertArrayHasKey( 'fresh', ArnWordPressDoubles::$sessions[10] );
	}
	public function testDisabledFlagAndCorruptStorageCannotRestoreNativeAccess(): void {
		foreach ( array( $this->record( 'Disabled', 4 ), new RuntimeException( 'private detail' ) )as$snapshot ) {
			$this->snapshot = $snapshot;
			self::assertInstanceOf( WP_Error::class, $this->guard->filterAuthentication( ArnWordPressDoubles::$users[10] ) );
			self::assertFalse( $this->guard->filterPasswordReset( true, 10 ) );}
		$this->snapshot                           = $this->record( 'Linked', 3 );
		$this->enabled                            = false;
		ArnWordPressDoubles::$sessions[10]['old'] = array(
			'arn_oidc'               => true,
			'arn_workspace_revision' => 3,
		);
		self::assertFalse( $this->guard->filterCurrentUser( 10 ) );
		self::assertSame( array(), ArnWordPressDoubles::$sessions[10] );
	}
	public function testMissingRecordWithSessionMarkerIsNotAnOrdinaryAccount(): void {
		$this->snapshot                           = null;
		ArnWordPressDoubles::$sessions[10]['old'] = array( 'arn_workspace_revision' => 1 );
		self::assertFalse( $this->guard->filterCurrentUser( 10 ) );}
	public function testHooksAreRegisteredEvenWhenLoginIsDisabled(): void {
		$this->enabled = false;
		$this->guard->register();
		foreach ( array( 'authenticate', 'determine_current_user', 'allow_password_reset', 'validate_password_reset', 'wp_is_application_passwords_available_for_user', 'send_auth_cookies' )as$hook ) {
			self::assertSame( PHP_INT_MAX, ArnWordPressDoubles::$filters[ $hook ]['priority'] );}}
	public function testBareNativeCookieIsBlockedBeforeAndAfterBinding(): void {
		self::assertFalse( $this->guard->filterSendAuthCookies( true, 0, 0, 10, 'auth', 'old' ) );
		$this->snapshot = $this->record( 'Linked', 3 );
		self::assertFalse( $this->guard->filterSendAuthCookies( true, 0, 0, 10, 'auth', 'old' ) );
		ArnWordPressDoubles::$sessions[10]['old'] = array(
			'arn_oidc'               => true,
			'arn_workspace_revision' => 3,
		);
		self::assertTrue( $this->guard->filterSendAuthCookies( true, 0, 0, 10, 'auth', 'old' ) );}
}
