<?php
/**
 * Estado de validação da relação superior.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Organization;

/**
 * Uma relação só é definitiva depois de RH a confirmar.
 */
enum ParentStatus: string {
	/**
	 * Unidade sem superior, por decisão confirmada.
	 */
	case Raiz = 'raiz';

	/**
	 * Relação confirmada por RH e coordenação.
	 */
	case Confirmada = 'confirmada';

	/**
	 * Relação lida no anexo, ainda por confirmar.
	 */
	case Documental = 'documental';

	/**
	 * Relação desconhecida. Não deve aparecer como posição definitiva.
	 */
	case Pendente = 'pendente';
}
