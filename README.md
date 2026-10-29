# Plataforma interna da ARN

Portal WordPress da ARN para notícias, documentos, comunicação interna, calendário, tarefas e serviços administrativos. O objectivo de entrega inclui F1, F2 e integração com Google Calendar. Ainda não está pronto para deploy.

Este README apresenta o estado actual e o trabalho necessário para concluir o site. Os requisitos, decisões, histórias e fontes estão em [Requisitos da plataforma](docs/requisitos-plataforma.md). Os relatórios de cada módulo guardam a evidência. O histórico de comandos, contagens antigas, prompts e atribuições ultrapassadas foi retirado daqui e permanece no histórico Git.

## Concluído no laboratório

“Concluído local” significa que a parcela indicada está implementada e ensaiada com o âmbito descrito no relatório. O estado da integração é rastreável na PR correspondente. Não significa aprovação institucional ou homologação no servidor. Não voltar a implementar estas entregas.

| Parcela concluída local | Evidência e limites |
| --- | --- |
| F1-16: navegação pessoal/equipa numa leitura autorizada | [Medição e aceitação](docs/desempenho-navegacao-servicos-f1-16.md). Pedidos e suporte eliminam uma transacção repetida. As vistas e a guarda final são preservadas. A latência e capacidade globais continuam pendentes. |
| F1-17: manuais e inventário de fontes por commit | [Preparação da entrega](docs/entrega/instalacao-recuperacao.md), [utilização](docs/entrega/manual-utilizacao.md) e [administração](docs/entrega/manual-administracao.md). Inventário imutável sem dados privados. Não é um instalador ou uma release homologada. |
| F2-03: férias e ausências futuras | [Aceitação local](docs/ferias-ausencias-locais-f2-03.md). Calendário fictício versionado, saldo de 22 dias, sobreposição pendente bloqueada sem reserva, decisão RH e cancelamento futuro auditados. Períodos entre anos e cancelamentos iniciados continuam bloqueados. Fontes institucionais em PEN-14. |
| F2-05/06: pedidos, suporte e férias no painel, pesquisa e avisos | [Composição](docs/composicao-servicos-locais-f2-05-06.md) e [férias](docs/ferias-ausencias-locais-f2-03.md). Pendências próprias, pesquisa autorizada e ligações nativas. Consultar o painel não marca avisos. Desempenho global ainda por aceitar. |
| F2-04: suporte TI e tickets | [Aceitação local](docs/suporte-tickets-locais-f2-04.md). Abertura, assunção por técnico competente, respostas, solução, anexos privados, histórico e avisos. Reabertura, fecho adicional, reatribuição e SLA exigem definição TI. |
| F2-01/02: pedidos internos e decisões | [Aceitação local](docs/pedidos-internos-locais-f2-01-02.md). Tipos versionados, circuito de decisão, substituição com competência existente, anexos privados, histórico e avisos. PEN-06 conserva os tipos e circuitos institucionais pendentes. |
| Fundação WordPress, tema ARN, logótipo, favicon, tipografia, páginas e menus | [Tema e revisão editorial](docs/portal-editorial-local-f1-06.md). A evolução dos módulos conserva o tema existente. |
| F1-02: login Google OIDC, callback, vínculo issuer/sub e sessão | [Login Google](docs/google-login-local-f1-02.md). Segredos externos ao Git e vínculo auditado. |
| F1-03: motor de autorização, persistência de funções/âmbitos/estado, sessões e gestão fictícia de concessões | [Persistência](docs/persistencia-autorizacao-f1-03.md), [sessões](docs/sessoes-estado-local-f1-03.md) e [concessões](docs/gestao-concessoes-local-f1-03.md). Matriz institucional por validar. |
| F1-04: auditoria das operações entregues e notificações internas por destinatário | [Notificações de acesso](docs/notificacoes-locais-f1-04.md) e [notificações do painel](docs/painel-notificacoes-local-f1-14.md). Sem emails externos. |
| F1-05: unidades fictícias e pré-validação de importações sintéticas sem escrita | [Unidades](docs/unidades-locais-f1-05.md) e [validador](docs/validacao-sintetica-f1-05.md). Não é um importador operacional de pessoas. |
| F1-05: cadastro fictício e importação auditada | [Aceitação do cadastro](docs/cadastro-importacao-auditada-local-f1-05.md). CSV com validação prévia, confirmação ligada à sessão/revisões, recibos idempotentes e auditoria atómica. Criação e actualização em lote sem contas, vínculos Google ou permissões automáticas. |
| F1-07: directório e perfil autorizados | Implementação integrada pelas PRs #25/#27. Validar os dados profissionais e permissões com RH antes da utilização institucional. |
| F1-08: armazenamento imutável, registo/auditoria e entrega privada por objecto/versão | [Ficheiros privados](docs/ficheiros-privados-locais-f1-08.md). Ligado a notícias, documentos e tarefas. Lacunas operacionais na tabela seguinte. |
| F1-08/09/10: quarentena, análise antimalware e derivados privados em notícias/documentos | [Aceitação de processamento](docs/processamento-media-privada-f1-08.md). ClamAV isolado, PNG thumbnail/preview, registo atómico e audiência por versão. 34 verificações Playwright e 24 SQL. Activação local explícita. A ligação dos formulários à fila está descrita na parcela seguinte. |
| F1-08: fila técnica, lotes e gestão auditada da quarentena | [Aceitação da fila](docs/fila-media-quarentena-local-f1-08.md). Recepção rápida, worker CLI limitado, recuperação manual, repetição, retenção, cancelamento e arquivo sem apagar bytes. Ligada aos formulários na parcela seguinte. |
| F1-08/09/10/13: formulários ligados à fila privada | [Aceitação local](docs/ligacao-fila-formularios-local-f1-08.md). Notícias, documentos e anexos de tarefas com contexto persistente, análise técnica e conclusão pelo autor. Revalidação de sessão, competência, revisão e bytes. Recibos idempotentes e bloqueio de resultados indeterminados. |
| F1-08: agendamento supervisionado da fila | [Supervisão local](docs/supervisao-fila-local-f1-08.md). Sessão CLI finita com ciclos espaçados, estado no painel, paragem cooperativa e reconhecimento auditado de interrupções. Sem serviço persistente instalado. |
| F1-08/09/10/13: pré-visualizações privadas de PDF | [Aceitação PDF](docs/previews-pdf-privados-local-f1-08.md). Primeira página e miniatura estáticas, conversão limitada após antimalware e consulta autorizada nos três módulos. Original intacto e PDFs antigos sem reprocessamento automático. Activação explícita no laboratório. |
| F1-08: diagnóstico operacional de media | [Aceitação operacional](docs/estado-operacional-media-local-f1-08.md). Ensaios explícitos do scanner, GD e PDF, inventário limitado e recibos privados. GET sem gravação, reenvio idempotente e interrupções visíveis. Não actualiza dependências nem apaga originais. |
| F1-08/09/10: reconciliação de ficheiros, apenas diagnóstico | [Aceitação e recuperação](docs/reconciliacao-ficheiros-local-f1-08.md). Compara bytes, registo, auditoria e referências de notícias/documentos. A cobertura de tarefas e derivados está na aceitação de anexos. Detecta possíveis órfãos sem apagar ou reparar. 33 verificações Playwright e 23 integradas. |
| F1-08/09/10: fronteira da media nativa pública | [Aceitação](docs/fronteira-media-nativa-f1-08.md), [PR #60](https://github.com/atchutchi/plataforma-interna-arn/pull/60). Uploads/derivados nativos, REST/AJAX, oEmbed, exportação e projecções de attachments fechados. 37 verificações Playwright e 48 verificações integradas. Os uploads privados continuam funcionais. |
| F1-09: catálogo de documentos e regulamentos, versões, publicação e download | [Catálogo documental](docs/catalogo-documental-local-f1-09.md). Competência de gestão distinta de administração técnica. |
| F1-10/F1-11: autoria, destaque, galeria, anexos, submissão, decisão e publicação de notícias | [Autoria](docs/noticias-locais-f1-10.md) e [aceitação editorial](docs/qa-publicacao-editorial-local-f1-11.md). Só a versão aprovada chega à audiência autorizada. |
| F1-12: comunicados e calendário interno | [Aceitação](docs/qa-comunicados-calendario-local-f1-12.md). A integração Google Calendar ainda falta. |
| F1-13: tarefas próprias/de equipa, estados, comentários, histórico, avisos e pesquisa | [Aceitação de tarefas](docs/qa-tarefas-locais-f1-13.md). Anexos concluídos na parcela seguinte. |
| F1-08/13: anexos privados de tarefas | [Aceitação de anexos](docs/anexos-tarefas-privados-local-f1-08-13.md). Formulário, quarentena/antimalware, originais/derivados, revisão auditada, autorização corrente e reconciliação. 42 verificações Playwright e 135 SQL. |
| F1-14: painel inicial e pesquisa integrada com autorização | [Pesquisa](docs/pesquisa-integrada-autorizada-local-f1-14.md) e [notificações](docs/painel-notificacoes-local-f1-14.md). Inclui comunicados, eventos e tarefas. |
| F1-15: painel administrativo e parâmetros auditados de uploads | [Aceitação CMS](docs/qa-parametros-cms-local-f1-15.md). PR #59 integrada em 97274ac com CI verde. Limites e tipos aplicados na transacção dos consumidores. |

O pré-cadastro Workspace tem documentação própria em docs e dados privados externos ao Git. Esta lista não autoriza renovar vínculos, alterar contas reais ou atribuir permissões.

<a id="plano-conclusao"></a>
## Falta desenvolver, por esta ordem

| Ordem | Entrega | Estado | Critério de fecho |
| --- | --- | --- | --- |
| 1 | F1-08: capacidade operacional de media | Parcial | Manutenção de assinaturas/descodificadores e política operacional de capacidade/conservação. Definir recuperação auditada de conclusões indeterminadas e operação persistente no servidor. A admissão, fila, quarentena, formulários, previews PDF, diagnóstico operacional e supervisão em sessões CLI finitas estão concluídos no laboratório. |
| 2 | F1-03/05: lacunas de cadastro e delegações | Parcial | Manutenção individual de estados, associação explícita profissional-conta e delegações persistentes. A importação e actualização em lote auditadas estão concluídas com dados fictícios. RH valida a origem, hierarquia, campos e atribuições reais. |
| 3 | Google Calendar | Direcção confirmada, configuração pendente | A plataforma publica num calendário Google dedicado. Preparar envio idempotente, revogação, erros e reconciliação. Seleccionar calendário e audiência com consentimento específico e segredos externos ao Git antes de envios reais. Não inferir permissões de Calendar a partir do login Google. |
| 4 | F1-16: QA global e recuperação | Parcial por módulo | Matriz completa por objecto, REST/AJAX/media, acessibilidade, carga e concorrência. A [navegação conjunta de pedidos/suporte](docs/desempenho-navegacao-servicos-f1-16.md) reduz consultas repetidas, mas ainda falta aceitar latência e capacidade do painel. Implementar recuperação controlada e auditada de ficheiros e ensaiar restauro e permissões recuperadas. |
| 5 | F1-17: pacote e homologação da entrega | Parcial | Manuais, inventário das fontes e guião de instalação/recuperação preparados. Falta adaptar os módulos dependentes de elencos e âmbitos fictícios à fonte institucional autorizada, pacote instalável com dependências e versões efectivas, migrações institucionais, ensaio de upgrade/restauro, rollback demonstrado e aceitação piloto. Não usar instaladores de fixtures como migrações. |

F1-04/06/14/15 acompanham os módulos novos. Não são tarefas para reconstruir os módulos já aceites. F3 fora do requisito Google Calendar fica sujeito a prioridade e fonte oficial aprovadas. As [decisões locais de 26 de outubro](docs/decisoes-laboratorio-2026-10-26.md) confirmam também que o fim de uma delegação bloqueia novas atribuições e conserva a validade própria das permissões já concedidas.

<a id="pendencias"></a>
## Falta validar antes do deploy

| Frente | Resultado necessário |
| --- | --- |
| RH e coordenação | Organograma/cadastro, matriz de competências, delegações, tipos de pedido, fontes dos saldos, regras de férias e aprovações. PEN-01/05/06/14. |
| Editorial e documentos | Equipa, audiências, classificação, origem oficial, conservação, arquivo e aprovação do material real. PEN-07/08. |
| DCSI e identidade | Nome HTTPS, DNS, callback Google institucional, política de sessões/MFA e entrada/saída de pessoas. PEN-02/03/10. |
| Infraestrutura Windows | Inventário autorizado do servidor .205, IIS, PHP 8.4 NTS, MySQL 8.4, ACL NTFS, volumes privados, rede LAN/VPN e saída Google. INF-01 a INF-09. |
| Operação | Backups, retenção, RPO/RTO, restauro ensaiado, monitorização, logs, incidentes e responsáveis. PEN-09. |
| Aceitação | Segundo PC, zoom nativo/leitor de ecrã, pilotos por perfil, instalação representativa e aceite dos fluxos reais. F1-16/F1-17 e INF-10. |

Não aceder ao controlador de domínio, DNS ou produção durante o desenvolvimento. O servidor .205 está fora do domínio por decisão do responsável. As provas PHP/MariaDB locais não homologam IIS/MySQL 8.4. Nenhuma integração desta branch executa deploy.

## Como continuar

1. Ler AGENTS.md, o módulo relevante e este backlog. Conferir origin/main, branches e PRs. Não reutilizar PRs históricas substituídas nem reactivar tarefas antigas.
2. Criar branch a partir da versão integrada, preservar checkouts usados por serviços em execução e implementar uma entrega coerente com dados fictícios.
3. Agrupar testes no fecho: PHP, Node, lint/build, integração pertinente e Playwright do circuito real. Verificar os dados e ficheiros que devem ficar inalterados.
4. Actualizar a evidência e o estado desta tabela. Fazer commit/push, revisão e PR. Só integrar com todos os controlos verdes no SHA exacto. Confirmar também a CI de main.
5. Continuar para a primeira lacuna independente. Pedir intervenção apenas para acesso/segredo indispensável, decisão institucional que mude negócio, custo, produção ou acção irreversível.

Não atribuir permissões a partir de emails, inventários ou papéis técnicos. Não guardar originais, dados de pessoas, credenciais, cookies ou relatórios privados no Git. Não reinstalar fixtures, repor revisões antigas ou alterar contas reais para fazer testes passar.

## Ambientes e comandos

WordPress, PHP 8.4 e tema/plugin próprios. Node 24.21.0 para ferramentas. O ambiente reproduzível usa wp-env e MariaDB 11.8, exclusivamente em loopback. A instalação local portátil é uma alternativa de laboratório, não a stack de produção.

O laboratório fictício 8892 recebeu a parcela CMS e é o alvo desta continuação. A demonstração 8888, os anexos reais de revisão e os pré-cadastros reais não devem ser actualizados implicitamente. Segredos e artefactos de QA ficam fora do checkout e do document root.

Com Docker disponível e licenciado, usar os scripts do projecto, sem executar wp-env directamente:

~~~sh
npm ci
npm run env:start:tests
npm run env:check:tests
npm run composer:install
npm test
npm run lint
npm run build
npm run test:php
npm run lint:php
npm run test:native-media:integration
npm run test:file-reconciliation:integration
npm run test:media-processing:integration
npm run env:stop:tests
~~~

Os restantes ensaios de integração estão em package.json. No runtime portátil, usar PHP/Node/WP-CLI instalados e configuração privada do laboratório. Não copiar caminhos ou credenciais de outro PC. Ler [condições técnicas](docs/requisitos-plataforma.md#qualidade) e AGENTS.md antes de arrancar serviços.

## Documentação de referência

[Requisitos, decisões e histórias](docs/requisitos-plataforma.md), [design do portal](DESIGN.md), [revisão de branches](docs/revisao-branches-proximas-fases-2026-10-20.md) e relatórios de aceitação ligados nas tabelas.

A transcrição documental nominal e as designações administrativas iniciais permanecem no histórico privado do README em 97274ac, secções 4.1 e 5.4. Não são dados operacionais nem autorização de acessos. O catálogo de unidades no código conserva as entradas documentais e as ambiguidades identificadas.

O site fica pronto para disponibilização quando F1, F2 e Google Calendar estiverem concluídos no âmbito aprovado, o restauro e a homologação tiverem passado, os pilotos tiverem aceite os fluxos e houver release e rollback identificados. “Concluído local” não deve ocultar nenhum destes passos.
