<?php
/**
 * Catálogo documental das unidades.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Organization;

/**
 * Transcreve as 37 entradas da secção 4.1 do README e o agrupamento da secção 4.6.
 *
 * Não contém pessoas. As relações vêm do anexo e ficam Documental até RH confirmar.
 * As entradas sem superior expresso ficam Pendente. Nenhuma designação é inventada.
 */
final class DocumentaryCatalog {

	/**
	 * Fonte transcrita.
	 */
	public const SOURCE = 'Departamentos-ARN.docx, 9 de outubro de 2026, README secções 4.1 e 4.6';

	/**
	 * Unidades na ordem do anexo.
	 *
	 * @return Unit[]
	 */
	public static function units(): array {
		$doc = ParentStatus::Documental;
		$pen = ParentStatus::Pendente;

		return array(
			new Unit( 'CA', 'CA', 'Conselho de Administração', UnitType::Orgao, null, $pen ),
			new Unit( 'SEC-CA', 'Secretaria CA', 'Secretaria', UnitType::Secretariado, null, $pen ),
			new Unit( 'CONS-CA', 'Conselheiros CA', 'Conselheiros', UnitType::PorClassificar, null, $pen ),

			new Unit( 'DCSI', 'DCSI', 'Direção de Comunicação e Sistema de Informação', UnitType::Direcao, null, $pen ),
			new Unit( 'DSU', 'DSU', null, UnitType::PorClassificar, 'DCSI', $doc ),
			new Unit( 'DSGI', 'DSGI', null, UnitType::PorClassificar, 'DCSI', $doc ),
			new Unit( 'DSIC', 'DSIC', null, UnitType::PorClassificar, 'DCSI', $doc ),
			new Unit( 'NIC', 'NIC', 'Núcleo Informático e Comunicação', UnitType::Nucleo, null, $pen ),
			new Unit( 'SOS-DNS', 'SOS-DNS', 'Serviço de Operação de Sistema DNS', UnitType::Servico, null, $pen ),
			new Unit( 'SGND', 'SGND', 'Serviço de Gestão de Nomes de Dominios', UnitType::Servico, null, $pen ),

			new Unit( 'DF', 'DF', 'Direção Financeiro', UnitType::Direcao, null, $pen ),
			new Unit( 'DFC', 'DFC', 'Departamento de Finanças e Contabilidade', UnitType::Departamento, 'DF', $doc ),
			new Unit( 'DPL', 'DPL', 'Departamento de Patrimônio e Logistíca', UnitType::Departamento, 'DF', $doc ),

			new Unit( 'DRE', 'DRE', 'Direção de Radiocomunicação e Engenharia', UnitType::Direcao, null, $pen ),
			new Unit( 'DRS', 'DRS', 'Departamento de Redes e Serviços', UnitType::Departamento, 'DRE', $doc ),
			new Unit( 'DRE-DGE', 'DGE', 'Departamento de Gestão de Espetro', UnitType::Departamento, 'DRE', $doc ),
			new Unit( 'DFV', 'DFV', 'Departamento de Fiscalização e Vistorias', UnitType::Departamento, 'DRE', $doc ),

			new Unit( 'DCT-QS', 'DCT-Q&S', 'Direção de Controlo de Tráfego e Qualidade de Serviço', UnitType::Direcao, null, $pen ),
			new Unit( 'DQS', 'DQ&S', 'Departamento de Qualidade de Serviço', UnitType::Departamento, 'DCT-QS', $doc ),
			new Unit( 'DCT', 'DCT', 'Departamento de Controlo de Tráfego', UnitType::Departamento, 'DCT-QS', $doc ),

			new Unit( 'DMAO', 'DMAO', 'Direção do Mercado e Acompanhamento de Operadoras', UnitType::Direcao, null, $pen ),
			new Unit( 'DSAU', 'DSAU', 'Departamento de serviço de Acesso Universal', UnitType::Departamento, 'DMAO', $doc ),
			new Unit( 'DMTC', 'DMTC', 'Departamento de mercado, Tarifas e Custos', UnitType::Departamento, 'DMAO', $doc ),
			new Unit( 'DEEP', 'DEEP', 'Departamento de Estudo, Estatistica e Planeamento', UnitType::Departamento, 'DMAO', $doc ),

			new Unit( 'DRH', 'DRH', 'Direção de Recursos Humanos', UnitType::Direcao, null, $pen ),
			new Unit( 'DRB', 'DRB', 'Departamento de Renumerações e Benefícios', UnitType::Departamento, 'DRH', $doc ),
			new Unit( 'DARHF', 'DARHF', 'Departamento de administração de RH e Formação', UnitType::Departamento, 'DRH', $doc ),
			new Unit( 'ARQUIVO', 'Arquivo', null, UnitType::PorClassificar, null, $pen ),
			new Unit( 'RECEPCAO', 'Recepção', null, UnitType::PorClassificar, null, $pen ),

			new Unit( 'DRAJDC', 'DRAJDC', 'Direção de Regulamentação, Assuntos Jurídicos e Defesa do Consumidor', UnitType::Direcao, null, $pen ),
			new Unit( 'DLR', 'DLR', 'Departamento de Licenciamento e Regulamentação', UnitType::Departamento, 'DRAJDC', $doc ),
			new Unit( 'DAJDC', 'DAJDC', 'Departamento dos Assuntos Jurídicos e Defesa dos Consumidores', UnitType::Departamento, 'DRAJDC', $doc ),

			new Unit( 'DREC', 'DREC', 'Direção de Relações Exteriores e Cooperação', UnitType::Direcao, null, $pen ),
			new Unit( 'DREC-DGE', 'DGE', 'Departamento de Gestão e Estratégia', UnitType::Departamento, 'DREC', $doc ),
			new Unit( 'DCRP', 'DCRP', 'Departamento de Comunicação e Relações Públicas', UnitType::Departamento, 'DREC', $doc ),
			new Unit( 'DRIC', 'DRIC', 'Departamento de Relações Institutionais e Cooperação', UnitType::Departamento, 'DREC', $doc ),

			new Unit( 'FAU', 'FAU', 'Fundo de Acesso Universal', UnitType::Fundo, null, $pen ),
		);
	}

	/**
	 * Hierarquia construída a partir do catálogo.
	 */
	public static function hierarchy(): UnitHierarchy {
		return UnitHierarchy::fromList( self::units() );
	}
}
