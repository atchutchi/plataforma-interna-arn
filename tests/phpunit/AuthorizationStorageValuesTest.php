<?php
/**
 * Valores persistentes puros.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\ScopeCodec;
use Arn\Intranet\Authorization\Storage\StoredGrant;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Exercita valores e formatos sem converter dados inválidos. */
final class AuthorizationStorageValuesTest extends TestCase {

	/** Rejeita contexto inválido e verifica fronteiras em bytes. */
	public function testContextBoundaries(): void {
		$valid = new ChangeContext( 1, str_repeat( 'á', 250 ), str_repeat( 'A', 120 ), 0 );
		self::assertSame( 500, strlen( $valid->reason ) );
		foreach ( array( array( 0, 'Razão', 'R-1', 0 ), array( 1, '', 'R-1', 0 ), array( 1, ' razão', 'R-1', 0 ), array( 1, "razão\n", 'R-1', 0 ), array( 1, "\xff", 'R-1', 0 ), array( 1, str_repeat( 'á', 251 ), 'R-1', 0 ), array( 1, 'Razão', 'á', 0 ), array( 1, 'Razão', '_ref', 0 ), array( 1, 'Razão', 'R@1', 0 ), array( 1, 'Razão', str_repeat( 'A', 121 ), 0 ), array( 1, 'Razão', 'R-1', -1 ), array( 1, "A\u{0085}B", 'R-1', 0 ) ) as $args ) {
			try {
				new ChangeContext( ...$args );
				self::fail( 'Contexto inválido aceite.' );
			} catch ( InvalidArgumentException $exception ) {
				self::assertSame( 'Contexto inválido.', $exception->getMessage() );
			}
		}
	}

	/** JSON exige array verdadeiro e tipos exactos. */
	public function testScopeCodecStrictnessAndCanonicalRoundtrip(): void {
		foreach ( array( array( 'People', '[2,1]' ), array( 'Units', '["b","a"]' ), array( 'Queues', '["b","a"]' ), array( 'Audiences', '["b","a"]' ), array( 'Own', '[]' ), array( 'Institutional', '[]' ) ) as $args ) {
			$scope = ScopeCodec::decode( ...$args );
			$json  = ScopeCodec::encode( $scope );
			self::assertSame( $json, ScopeCodec::encode( ScopeCodec::decode( $args[0], $json ) ) );
		}
		self::assertSame( '[1,2]', ScopeCodec::encode( Scope::people( array( 2, 1 ) ) ) );
		self::assertSame( '[9223372036854775806,9223372036854775807]', ScopeCodec::encode( Scope::people( array( PHP_INT_MAX, PHP_INT_MAX - 1 ) ) ) );
		self::assertSame( '["a","b"]', ScopeCodec::encode( Scope::units( array( 'b', 'a' ) ) ) );
		foreach ( array( array( 'people', '[1]' ), array( 'Unknown', '[]' ), array( 'People', '{"0":1}' ), array( 'People', '{}' ), array( 'People', '[1.0]' ), array( 'People', '[9223372036854775808]' ), array( 'People', '[1,"2"]' ), array( 'People', '[-1]' ), array( 'People', '[0]' ), array( 'People', '[1,1]' ), array( 'People', '[]' ), array( 'Own', '[1]' ), array( 'Institutional', '["a"]' ), array( 'Units', '[1]' ), array( 'Units', '["a","a"]' ), array( 'People', 'null' ), array( 'People', '[' ) ) as $args ) {
			try {
				ScopeCodec::decode( ...$args );
				self::fail( 'Âmbito inválido aceite.' );
			} catch ( InvalidArgumentException $exception ) {
				self::assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	/** Snapshots não retêm referências externas. */
	public function testSnapshotCopiesReferencesAndProvidesPolicyInputs(): void {
		$grant    = new Grant( 2, Role::Employee, Scope::own(), 0, null );
		$stored   = new StoredGrant( 1, $grant );
		$list     = array( &$stored );
		$snapshot = new AuthorizationSnapshot( 2, AccountState::Active, 1, $list );
		$stored   = new StoredGrant( 3, $grant );
		$list[]   = $stored;
		self::assertCount( 1, $snapshot->stored_grants );
		self::assertSame( 1, $snapshot->stored_grants[0]->id );
		self::assertSame( array( $grant ), $snapshot->policyGrants() );
		self::assertTrue( $snapshot->toAccount( true )->session_valid );
		self::assertFalse( $snapshot->toAccount( false )->session_valid );
		self::assertSame( 2, $snapshot->toAccount( false )->user_id );
		self::assertSame( AccountState::Active, $snapshot->toAccount( false )->state );
		$this->expectException( \Error::class );
		$snapshot->stored_grants[] = $stored;
	}

	/** Concessões revogadas/expiradas são preservadas para o motor. */
	public function testSnapshotPreservesHistoricalGrants(): void {
		$expired  = new Grant( 2, Role::Employee, Scope::own(), 0, 1 );
		$revoked  = new Grant( 2, Role::Employee, Scope::own(), 0, null, true );
		$snapshot = new AuthorizationSnapshot( 2, AccountState::Suspended, 4, array( new StoredGrant( 1, $expired ), new StoredGrant( 2, $revoked ) ) );
		self::assertSame( array( $expired, $revoked ), $snapshot->policyGrants() );
	}

	/** Recusa mistura de contas, IDs duplicados e valores inválidos. */
	public function testSnapshotRejectsInvalidInputs(): void {
		$one   = new StoredGrant( 1, new Grant( 2, Role::Employee, Scope::own(), 0, null ) );
		$other = new StoredGrant( 2, new Grant( 3, Role::Employee, Scope::own(), 0, null ) );
		foreach ( array( array( 0, AccountState::Pending, 1, array() ), array( 2, AccountState::Pending, 0, array() ), array( 2, AccountState::Pending, 1, array( $one, $one ) ), array( 2, AccountState::Pending, 1, array( $other ) ), array( 2, AccountState::Pending, 1, array( $one, 'invalid' ) ) ) as $args ) {
			try {
				new AuthorizationSnapshot( ...$args );
				self::fail( 'Snapshot inválido aceite.' );
			} catch ( InvalidArgumentException $exception ) {
				self::assertSame( 'Snapshot inválido.', $exception->getMessage() );
			}
		}
		$this->expectException( InvalidArgumentException::class );
		new StoredGrant( 0, $one->grant );
	}
}
