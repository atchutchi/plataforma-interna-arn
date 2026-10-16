<?php
/**
 * Entrada nominativa sem competências.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity\PreRegistration;

use RuntimeException;
/**
 * Conserva os valores exactos que cabem nos campos nativos WordPress.
 */
final readonly class WorkspaceEntry {
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param string $display_name Nome apresentado.
	 * @param string $primary_email Email primário canónico.
	 * @throws \RuntimeException Quando a validação ou a leitura protegida falha.
	 */
	public function __construct( public string $display_name, public string $primary_email ) {
		if ( ! mb_check_encoding( $display_name, 'UTF-8' ) || '' === $display_name || trim( $display_name ) !== $display_name || mb_strlen( $display_name, 'UTF-8' ) > 250 || 1 === preg_match( '/[\x00-\x1f\x7f]/u', $display_name ) || strlen( $primary_email ) > 100 || strtolower( $primary_email ) !== $primary_email || false === filter_var( $primary_email, FILTER_VALIDATE_EMAIL ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante, sem dados da origem.
			throw new RuntimeException( 'Lote Workspace inválido.' );
		}
	}
}
