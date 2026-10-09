<?php
/**
 * Conta fictícia exclusiva do ambiente local.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Access;

/**
 * Dados da conta de teste. Não representa uma pessoa da ARN.
 */
final class LocalFixtureUser {

	public const LOGIN = 'ana.teste';

	public const EMAIL = 'ana.teste@example.test';

	public const DISPLAY_NAME = 'Ana Teste';

	public const ROLE = 'subscriber';

	public const PASSWORD = 'fixture-local-ana';

	/**
	 * Identifica a fixture mesmo que o perfil seja alterado no ambiente local.
	 */
	public const META_KEY = '_arn_intranet_local_fixture';
}
