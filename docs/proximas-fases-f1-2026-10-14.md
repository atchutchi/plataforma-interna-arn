# Próximas parcelas locais de F1

14 de outubro de 2026. Leitura do backlog README15.3, dos limites de AGENTS e da implementação local. Este roteiro actualiza a orientação de trabalho, sem substituir a auditoria histórica de 13 de outubro ou validar decisões institucionais.

| Fase | Situação e parcela autónoma | Dependência que permanece |
| --- | --- | --- |
| F1-01/02 | Ambiente portátil e Google local já ensaiados. Manter regressões. | Segundo PC, Windows/IIS/MySQL8.4, HTTPS, LAN/VPN e procedimento institucional DCSI. |
| F1-03 | Motor, persistência auditada, estado/sessões, concessões e leitura por objecto fictício implementados. | Matriz, autoridade delegada e contratos de módulos reais em F0-02/06. |
| F1-04 | Parcela implementada no laboratório: notificações internas derivadas de eventos de acesso confirmados, destinatário protegido e recibos idempotentes. Consulta administrativa da auditoria já existe no laboratório. | Eventos dos módulos ainda ausentes, canais externos, falhas de entrega desses canais e validação institucional. |
| F1-05 | Parcela local: modelo persistente auditado de unidades fictícias, gestão por cenários e contratos documentados de cadastro/importação. Evidência em unidades-locais-f1-05.md. Próximo passo: validador de importação sintética sem escrita, antes do cadastro executável. | Organograma, nomes, relações, campos e autoridade aprovados por RH. Não importar funcionários reais. |
| F1-06 | Continuar componentes e navegação para funcionalidades locais já disponíveis, preservando tema e marca. | Navegação por competências e conteúdo dos módulos institucionais. |
| F1-07 | Preparar contratos de directório e perfil depois do cadastro. | F1-05/06 e campos públicos autorizados. |
| F1-08 | Preparar armazenamento privado local e ensaios de entrega autorizada quando o contrato de ficheiros estiver definido. | F0-03/06, auditoria e homologação do armazenamento/IIS. |
| F1-09 a F1-15 | Implementar por módulo após resolver os respectivos contratos e dependências. | Documentos dependem de armazenamento. Notícias de auditoria, tema e anexos. Tarefas de unidades e autorização. Painel agregado só após fontes funcionais. |
| F1-16/17 | Conservar evidência de testes e preparar critérios de aceitação ao longo do desenvolvimento. | Segurança completa, restauro, ambiente representativo, utilizadores piloto, responsáveis e formação. |
| F2/F3 | Mantêm-se fora desta entrega. | Pré-requisitos funcionais e decisões indicadas no README, incluindo regras RH pendentes. |

A parcela F1-04 evita enviar comunicações externas. A fonte é a auditoria confirmada na mesma transacção das alterações de acesso. A projecção interna usa a identidade do evento para não duplicar avisos. Um recibo de leitura não representa aprovação, aceitação formal ou alteração de permissões.

A execução continua no laboratório com contas fictícias, commits em branch, revisão independente, CI no SHA exacto e integração sem deploy. Os pontos que exigem RH/DCSI continuam identificados, sem serem considerados concluídos por testes locais.
