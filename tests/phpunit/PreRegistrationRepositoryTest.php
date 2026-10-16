<?php
/**
 * Integridade do registo persistido, sem motor SQL.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Identity\PreRegistration\PreRegistration;
use Arn\Intranet\Identity\PreRegistration\PreRegistrationState;
use PHPUnit\Framework\TestCase;

/** As constraints e o rollback são exercitados no ensaio SQL separado. */
final class PreRegistrationRepositoryTest extends TestCase {
	/**
	 * Prepara uma linha exclusivamente fictícia.
	 *
	 * @return array
	 */
	private function row(): array {
		return array(
			'id'               => str_repeat( 'a', 32 ),
			'user_id'          => '71',
			'display_name'     => 'Pessoa Fictícia',
			'primary_email'    => 'fixture@example.invalid',
			'state'            => 'AwaitingGoogle',
			'revision'         => '1',
			'authorized_by'    => '1',
			'source_sha256'    => str_repeat( 'b', 64 ),
			'inventory_sha256' => str_repeat( 'c', 64 ),
			'observed_at'      => '1894701600',
			'expires_at'       => '1894788000',
			'disabled_from'    => null,
		);
	}

	/** Uma linha íntegra conserva as referências e o estado fechado. */
	public function testStoredRecordIsStrictlyTypedAndInitiallyUnlinked(): void {
		$record = PreRegistration::fromRow( $this->row() );
		self::assertSame( 71, $record->user_id );
		self::assertSame( PreRegistrationState::AwaitingGoogle, $record->state );
		self::assertSame( 1, $record->revision );
		self::assertSame( 86400, $record->expires_at - $record->observed_at );
	}

	/** Dados malformados não recuperam acesso através de coerção. */
	public function testCorruptPersistedRecordsAreRejectedWithoutPayload(): void {
		foreach ( array( array( 'user_id', '01' ), array( 'revision', '0' ), array( 'revision', '99999999999999999999999' ), array( 'state', 'Active' ), array( 'disabled_from', 'Linked' ), array( 'id', 'invalid' ), array( 'source_sha256', 'invalid' ), array( 'expires_at', '1894788001' ) ) as [ $field, $value ] ) {
			$row           = $this->row();
			$row[ $field ] = $value;
			try {
				PreRegistration::fromRow( $row );
				self::fail( 'Linha malformada aceite.' );
			} catch ( RuntimeException $error ) {
				self::assertSame( 'Pré-cadastro indisponível.', $error->getMessage() ); }
		}
	}
}
