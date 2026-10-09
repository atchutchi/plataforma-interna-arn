<?php
/**
 * Regras de acesso que não podem depender do WordPress.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Access\LocalAccessPolicy;
use Arn\Intranet\Access\LocalFixtureUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cobre o modo fictício e a ausência de promoção por email.
 */
final class LocalAccessPolicyTest extends TestCase {

	/**
	 * O modo fictício só passa com flag explícita e ambiente local.
	 *
	 * @param string $environment Ambiente WordPress.
	 * @param bool   $flag        Autorização explícita.
	 * @param bool   $expected    Resultado admissível.
	 */
	#[DataProvider( 'fictionalAccessCases' )]
	public function testFictionalAccessStaysOnTheLocalComputer( string $environment, bool $flag, bool $expected ): void {
		self::assertSame(
			$expected,
			LocalAccessPolicy::fictionalAccessAllowed( $environment, $flag )
		);
	}

	/**
	 * Casos de ambiente e flag.
	 *
	 * @return array<string, array{0: string, 1: bool, 2: bool}>
	 */
	public static function fictionalAccessCases(): array {
		return array(
			'local autorizado'      => array( 'local', true, true ),
			'local sem flag'        => array( 'local', false, false ),
			'desenvolvimento'       => array( 'development', true, false ),
			'homologacao'           => array( 'staging', true, false ),
			'producao'              => array( 'production', true, false ),
			'ambiente desconhecido' => array( '', true, false ),
		);
	}

	/**
	 * Email, domínio e as três contas designadas não concedem administração.
	 */
	public function testEmailNeverGrantsAdministration(): void {
		$emails = array_merge(
			LocalAccessPolicy::EMAILS_REQUIRING_EXPLICIT_ASSIGNMENT,
			array(
				'pessoa@arn.gw',
				'Alguem@ARN.GW',
				'conta@arn.local',
				LocalFixtureUser::EMAIL,
				'',
				'nao-e-email',
			)
		);

		foreach ( $emails as $email ) {
			self::assertFalse( LocalAccessPolicy::emailGrantsAdministration( $email ) );
		}
	}

	/**
	 * A conta fictícia não usa o domínio institucional nem um papel de administração.
	 */
	public function testFixtureUserIsLocalAndUnprivileged(): void {
		self::assertStringEndsWith( '@example.test', LocalFixtureUser::EMAIL );
		self::assertNotSame( 'administrator', LocalFixtureUser::ROLE );
		self::assertFalse( LocalAccessPolicy::emailGrantsAdministration( LocalFixtureUser::EMAIL ) );
	}
}
