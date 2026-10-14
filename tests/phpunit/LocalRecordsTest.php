<?php
/** Ensaios da leitura por objecto.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Records;

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\LocalGrantPresets;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\StoredGrant;
use PHPUnit\Framework\TestCase;

/** O consumidor recebe apenas linhas já autorizadas. */
final class LocalRecordsTest extends TestCase {
	/** O catálogo contém apenas três provas calculadas no servidor. */
	public function testCatalogHasOnlySyntheticObjectsAndServerOwnership(): void {
		$records = LocalRecordCatalog::records( 7 );
		self::assertSame( array( 'proprio', 'unidade-a', 'unidade-b' ), array_keys( $records ) );
		self::assertSame( 7, $records['proprio']['request']->owner_id );
		self::assertSame( 0, $records['unidade-a']['request']->owner_id );
		self::assertSame( 0, $records['unidade-b']['request']->owner_id );
		self::assertSame( array( 'unidade-ficticia-a' ), $records['unidade-a']['request']->scope->targets );
		self::assertSame( array( 'unidade-ficticia-b' ), $records['unidade-b']['request']->scope->targets );
		foreach ( $records as $record ) {
			self::assertSame( \Arn\Intranet\Authorization\Action::ReadDirectory, $record['request']->action );
			self::assertTrue( $record['request']->module_ready && $record['request']->state_allowed && $record['request']->audience_allowed );
		}
	}

	/** Snapshot sintético com concessões mistas.
	 *
	 * @param array $grants Concessões.
	 */
	private function snapshot( array $grants ): AuthorizationSnapshot {
		return new AuthorizationSnapshot( 7, AccountState::Active, 3, array_map( static fn( $grant, $index ) => new StoredGrant( $index + 1, $grant ), $grants, array_keys( $grants ) ) );
	}
	/** Metadados internos da sessão corrente. */
	private function session(): array {
		return array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => 7,
			'arn_authz_revision' => 3,
		);
	}
	/** Concessões sem vigência não revelam títulos nem alteram contagens. */
	public function testMixedGrantsFilterBeforeReturningRows(): void {
		$snapshot = $this->snapshot( array( LocalGrantPresets::grant( 'employee-own', 7, 0 ), LocalGrantPresets::grant( 'unit-manager-a', 7, 0 ), new Grant( 7, Role::UnitManager, Scope::units( array( 'unidade-ficticia-b' ) ), 0, 10 ) ) );
		$rows     = LocalRecordSelection::visible( $snapshot, 7, $this->session(), 10 );
		self::assertSame( array( 'proprio', 'unidade-a' ), array_keys( $rows ) );
		self::assertCount( 2, $rows );
		self::assertSame( 'Registo fictício próprio', $rows['proprio']['title'] );
		self::assertNull( LocalRecordSelection::detail( $rows, 'unidade-b' ) );
		foreach ( array( 'missing', array( 'proprio' ), null, 1, ' proprio', 'PROPRIO', 'proprio/' ) as $key ) {
			self::assertNull( LocalRecordSelection::detail( $rows, $key ) );
		}
		self::assertSame( $rows['unidade-a'], LocalRecordSelection::detail( $rows, 'unidade-a' ) );
	}
	/** Revogação e fim exclusivo são respeitados pelo mesmo motor. */
	public function testRevokedAndExpiredGrantsNeverRevealRecords(): void {
		$snapshot = $this->snapshot( array( new Grant( 7, Role::Employee, Scope::own(), 0, null, true ), new Grant( 7, Role::UnitManager, Scope::units( array( 'unidade-ficticia-a' ) ), 0, 10 ) ) );
		self::assertSame( array(), LocalRecordSelection::visible( $snapshot, 7, $this->session(), 10 ) );
	}
	/** Titular, estado e geração têm de concordar. */
	public function testInvalidSessionFailsClosed(): void {
		$snapshot = $this->snapshot( array( LocalGrantPresets::grant( 'employee-own', 7, 0 ) ) );
		foreach ( array( array(), array_replace( $this->session(), array( 'arn_authz_revision' => 2 ) ), array_replace( $this->session(), array( 'arn_authz_user_id' => 8 ) ), array_replace( $this->session(), array( 'arn_authz_local' => 'true' ) ) ) as $session ) {
			self::assertSame( array(), LocalRecordSelection::visible( $snapshot, 7, $session, 10 ) );
		}
		self::assertSame( array(), LocalRecordSelection::visible( $snapshot, 8, $this->session(), 10 ) );
		self::assertSame( array(), LocalRecordSelection::visible( null, 7, $this->session(), 10 ) );
		self::assertSame( array(), LocalRecordSelection::visible( new AuthorizationSnapshot( 7, AccountState::Suspended, 3, $snapshot->stored_grants ), 7, $this->session(), 10 ) );
	}
}
