<?php
/**
 * Adaptadores em memória para testar os callbacks sem WordPress nem base de dados.
 * Não substituem a verificação integrada no wp-env.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

require_once __DIR__ . '/doubles/WP_User.php';
require_once __DIR__ . '/doubles/WP_Error.php';
require_once __DIR__ . '/doubles/ArnWordPressDoubles.php';
require_once __DIR__ . '/doubles/WP_Session_Tokens.php';
require_once __DIR__ . '/doubles/functions.php';
