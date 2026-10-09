<?php
/**
 * Tipos de unidade orgânica.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Organization;

/**
 * Tipos previstos na secção 4.2. Por classificar cobre entradas sem tipo expresso no anexo.
 */
enum UnitType: string {
	case Orgao          = 'orgao';
	case Direcao        = 'direcao';
	case Departamento   = 'departamento';
	case Nucleo         = 'nucleo';
	case Servico        = 'servico';
	case Secretariado   = 'secretariado';
	case Gabinete       = 'gabinete';
	case Fundo          = 'fundo';
	case Outro          = 'outro';
	case PorClassificar = 'por-classificar';
}
