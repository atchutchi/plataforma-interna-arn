<?php
/**
 * Dobra de leitura para crescimento determinístico durante a cópia.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Documents\PrivateFiles;

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.WP.AlternativeFunctions

/** Estado activado apenas pelos testes do armazenamento. */
final class PrivateFilesReadControl {
	/**
	 * Fonte a modificar uma vez.
	 *
	 * @var string|null
	 */
	public static ?string $source = null;
	/**
	 * Bytes acrescentados à fonte.
	 *
	 * @var string
	 */
	public static string $append = '';
	/**
	 * Confirma que a dobra foi executada.
	 *
	 * @var bool
	 */
	public static bool $fired = false;

	/** Repõe a dobra depois de cada ensaio. */
	public static function reset(): void {
		self::$source = null;
		self::$append = '';
		self::$fired  = false;
	}
}

/**
 * Acrescenta bytes após o fstat inicial e antes da primeira leitura da fonte.
 *
 * @param resource $stream Handle da fonte ou do binário.
 * @param int      $length Tamanho pedido.
 * @return string|false Resultado nativo.
 */
function fread( $stream, int $length ): string|false {
	$uri = stream_get_meta_data( $stream )['uri'] ?? null;
	if ( ! PrivateFilesReadControl::$fired && null !== PrivateFilesReadControl::$source && PrivateFilesReadControl::$source === $uri ) {
		PrivateFilesReadControl::$fired = true;
		\file_put_contents( $uri, PrivateFilesReadControl::$append, FILE_APPEND );
	}
	return \fread( $stream, $length );
}
