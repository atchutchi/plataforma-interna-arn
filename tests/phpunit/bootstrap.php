<?php
/**
 * Carrega as regras puras sem iniciar o WordPress.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

require dirname( __DIR__, 2 ) . '/wp-content/plugins/arn-intranet-core/src/Autoloader.php';

\Arn\Intranet\Autoloader::register( dirname( __DIR__, 2 ) . '/wp-content/plugins/arn-intranet-core/src' );
