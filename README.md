# Plataforma interna da ARN

Documento de referência para desenvolver e acompanhar a plataforma interna da Autoridade Reguladora Nacional das TIC da Guiné-Bissau.

Continuação F1-15, gestão no painel, 19 de outubro: a branch `feat/painel-gestao-autorizada` parte da PR #48 em `7538741`. O Painel ARN reúne gestão funcional disponível, recursos WordPress, ferramentas locais e conta, pelas capacidades e menus registados no pedido corrente. Não concede competências nem escreve parâmetros críticos. O [registo da entrega](docs/painel-gestao-autorizada-local-f1-15.md) identifica critérios, validação e limites. Apenas laboratório fictício, sem merge ou interferência na PR #41/Issue #37 da Albertina.

Continuação F1-14, pesquisa integrada, 19 de outubro: a branch `feat/pesquisa-integrada-autorizada` parte da PR #47 em `c9af88d`. O formulário do Início reúne pesquisa básica de funcionários, notícias aprovadas, documentos/regulamentos publicados, comunicados e eventos, com filtro por tipo e paginação comum. As fontes conservam a autoridade e só título/nome, tipo e ligação passam para a lista. Não consulta originais administrativos ou versões privadas. O [registo da pesquisa](docs/pesquisa-integrada-autorizada-local-f1-14.md) documenta contratos, ensaios e limites. A entrega fica separada, sem merge, produção ou interferência na PR #41/Issue #37 da Albertina. Tarefas, módulos futuros e homologação mantêm os pré-requisitos.

Continuação do painel inicial, 19 de outubro: a branch `feat/painel-comunicados-eventos` parte da PR #46 em `a18939c`. O Início reúne os acessos autorizados, até três comunicados recentes e até três eventos em curso/futuros, pelas fontes publicadas da F1-12. A consulta não marca avisos como lidos. A apresentação distingue estados vazios e fontes indisponíveis e conserva as notícias editoriais. O [registo do painel](docs/painel-inicial-local-f1-14.md) documenta comportamento, validação e limites. A entrega inicia a agregação da F1-14, mantendo pesquisa e restantes módulos pendentes. Sem merge ou interferência na PR #41/Issue #37 da Albertina.

Continuação F1-06, Início, 19 de outubro: a branch `feat/inicio-acessos-autorizados` parte da PR #45 no SHA `8d613ea`. O Início mostra os mesmos atalhos autorizados de Serviços através de um componente comum, com estado vazio e títulos acessíveis distintos. A consulta não concede acessos nem altera dados de negócio ou auditoria. O [registo do Início](docs/inicio-acessos-autorizados-local-f1-06.md) identifica a reprodução inicial, 73 verificações WordPress/MariaDB, 863 testes PHP, QA Playwright dos quatro perfis fictícios, preservação e limites. A entrega depende da #45, sem merge. A parcela não conclui F1-06/F1-14 nem interfere na PR #41/Issue #37 da Albertina.

Continuação F1-06, menus guardados, 19 de outubro: a branch `feat/navegacao-menus-autorizados` parte de `feat/portal-servicos-local` no SHA `1c71c51` da PR #44. A navegação nativa guardada no CMS passa a conservar os primeiros rótulos permitidos e a retirar destinos de módulo recusados ou duplicados, incluindo submenus. Não grava o menu nem atribui acessos. O [registo da navegação](docs/navegacao-menus-guardados-local-f1-06.md) documenta as regressões, revisão, 49 verificações WordPress/MariaDB, três testes puros, Playwright e limites. Os testes de teclado finais esperam pela abertura visual efectiva dos submenus e conservam os logs das falhas iniciais. A entrega fica numa PR dependente da #44, sem merge. PR #41 e Issue #37 da Albertina ficam preservadas. F1-06 continua Em curso e F1-13 mantém os próprios pré-requisitos.

Continuação do front end, 19 de outubro: a branch `feat/portal-servicos-local`, baseada em `test/editorial-contention-diagnostics` no SHA `10512f9`, liga a página Serviços aos mesmos destinos autorizados do menu People/F1-12. Conserva estados vazios e serviços futuros sem acções fictícias, sem conceder acessos. Tem cinco testes puros novos, 32 verificações WordPress/MariaDB, QA Playwright e revisão independente. O [registo de Serviços](docs/servicos-autorizados-local-f1-06.md) distingue os percursos positivos People/F1-12, estados vazio e autorizado no browser, a correcção do menu automático e os deltas nativos. A PR de interface fica separada da PR #43, sem merge. Não conclui F1-06, F1-14 ou a aceitação institucional e não interfere na PR #41/Issue #37.

Diagnóstico editorial, 19 de outubro de 2026: a branch independente `test/editorial-contention-diagnostics` acrescenta observação segura dos processos, fases, erros SQL e duas ligações de observação para a [Issue #31](https://github.com/atchutchi/plataforma-interna-arn/issues/31), na [PR #43](https://github.com/atchutchi/plataforma-interna-arn/pull/43). Foi reproduzida uma cache de metadados sem actualização durante sondagem rápida e preparado um intervalo de 150 ms, conservando concorrência real, janela total de 15 segundos e asserções originais. O [registo de diagnóstico](docs/diagnostico-concorrencia-editorial-2026-10-19.md) separa o mecanismo demonstrado, as falhas injectadas, a primeira CI vermelha da calibração e a atribuição histórica ainda pendente. Sem alterações de produto, merge ou interferência na PR #41 e Issue #37 da Albertina. Esta entrega não conclui a Issue #31 nem qualquer fase institucional.

A continuação da PR #43 acrescenta uma quarta calibração: uma falha do observador dentro do hook de COMMIT pode receber a mesma mensagem genérica de persistência que apareceu durante a revogação histórica. O trace do pai conserva as fases e o erro tipado. O cenário exige restauração das quatro tabelas de autorização após rollback. Não atribui a causa histórica a essa hipótese nem exige ausência de uma decisão legítima depois de uma revogação falhada. As 59 verificações do caso normal e a proibição de merge mantêm-se.

Versão da documentação: 1.8. Data: 19 de outubro de 2026. F1-07 está integrada em main pela [PR #25](https://github.com/atchutchi/plataforma-interna-arn/pull/25), merge `a1a3fbb9a007e0d0c52b4d8a7713c030792a321b`, depois de receber a [PR #27](https://github.com/atchutchi/plataforma-interna-arn/pull/27). A proposta da [PR #26](https://github.com/atchutchi/plataforma-interna-arn/pull/26) foi implementada na branch independente `feat/f1-12-comunicados-calendario`, com activação e aceitação fictícias neste laboratório. O [estado de execução](docs/estado-execucao-f1-12.md) e a [QA dos 32 critérios](docs/qa-comunicados-calendario-local-f1-12.md) distinguem código, testes e activação. As tarefas da Albertina permanecem nas Issues [#28](https://github.com/atchutchi/plataforma-interna-arn/issues/28) e [#29](https://github.com/atchutchi/plataforma-interna-arn/issues/29). Integração Git, activação local e aceitação institucional são estados diferentes. Este documento é o ponto de continuidade obrigatório.

Estado do projecto: estão integradas as parcelas locais WordPress de identidade Google OIDC, vínculo/sessão, autorização/estado auditados, notificações, unidades, pré-validação sintética, tema/painel ARN, armazenamento privado, documentos e notícias até publicação por audiência e arquivo. F1-07 acrescenta directório, perfil próprio e equipa. F1-12 acrescenta comunicados e calendário com SQL/auditoria/avisos, CMS e front end, instalação explícita e aceitação WordPress/Playwright fictícia neste laboratório. A parcela F1-12 foi integrada na PR #32, merge 365346a, depois da CI no SHA entregue. F1-13 tem preparação documental atribuída à colaboradora, sem código ou critérios já aprovados. F1-12 mantém pendências institucionais. F1-13 a F1-17, lacunas comuns, F2 e infraestrutura continuam por concluir. Não reiniciar a arquitectura ou instalações existentes.

A entrega de implementação F1-12 está na [PR #32](https://github.com/atchutchi/plataforma-interna-arn/pull/32). Consultar aí o estado Git e a CI do SHA entregue. As [decisões de implementação](docs/decisoes-implementacao-f1-12.md) e a [operação local](docs/comunicados-calendario-local-f1-12.md) acompanham a QA. Esta entrega não concede acessos aos nomes/email do Workspace.

Pré-cadastro Workspace, 16 de outubro: implementação revista e integrada na PR #33, merge ece43e0. Foram criadas e auditadas neste laboratório as 73 contas nominativas autorizadas, sem roles, capabilities individuais, grants, perfis People, emails ou sessões durante a criação. As quatro contas genéricas continuam excluídas. A aplicação acrescentou apenas 73 utilizadores, 292 metadados, 73 pré-cadastros e 73 eventos, preservando todas as linhas anteriores. A retoma conservou integralmente as 52 tabelas e os 89 ficheiros privados. O login Google real do titular preservou o vínculo à conta administrativa anterior e registou um conflito privado, sem associar o novo pré-cadastro. As 73 entradas continuam AwaitingGoogle. O primeiro vínculo foi provado com identidades fictícias num WordPress separado. O prazo inicial termina em 17 de outubro de 2026 às 10:55:33 UTC. Depois exige revalidação explícita de origem, sem renovação automática. Consulta a [operação](docs/pre-cadastro-workspace-local.md) e a [QA](docs/qa-pre-cadastro-workspace-local.md) para testes, deltas nativos e limites. Dados, configuração, segredos e provas identificáveis permanecem fora do GitHub. F1-03 institucional, F1-05, F1-12 e as tarefas da Albertina conservam os seus limites.

A [QA desta preparação](docs/qa-preparacao-workspace-local-2026-10-16.md) regista 594 verificações da fonte, 55 testes Node, lint/build e dez verificações Playwright do login já existente. A comparação antes/depois do browser conservou 49 tabelas, sessões, configuração e 89 ficheiros privados. Estes resultados pertencem à preparação documental e não substituem os ensaios posteriores da implementação.

Revisão de 19 de outubro: a PR #41 em 28304c1 substitui tecnicamente a proposta #39, mas ainda não foi integrada. A branch fix/albertina-pr41-review-findings em 9cd87c3 corrige os três achados anteriores e tem CI verde. A revisão adicional e o reprodutor SQL local encontraram dez falhas em doze cenários, com seis sentinelas confirmadas antes da recusa. Não fazer merge. O [relatório das branches e dos testes](docs/revisao-branches-pr41-2026-10-19.md) separa os resultados de cada SHA e motor. O [prompt actualizado para a Albertina](docs/prompt-albertina-pr41-issue37-2026-10-19.txt) define a correcção da PR #41 e a conclusão da entrega documental da Issue #37, entretanto publicada em 4b90341 com 35 critérios propostos Por executar e ainda sem PR. F1-13 continua sem implementação e sem critérios executados.

Revisão das Issues #36/#37 em 16 de outubro: a Albertina publicou a [PR #39](https://github.com/atchutchi/plataforma-interna-arn/pull/39) para #36, head 12c94a0, com CI aprovada. A revisão independente e seis contra-exemplos na MariaDB real encontraram divergências no estado transaccional. Em dois cenários, as linhas sentinela ficaram confirmadas depois do ROLLBACK. Não foi feito merge ou activação desse candidato. A #37 ainda não tinha entrega publicada na consulta, pelo que a conciliação permanece com a colaboradora. O [relatório](docs/revisao-pr39-e-issues36-37-2026-10-16.md) identifica reproduções, fronteiras a corrigir e limites. A [preparação técnica da F1-13](docs/preparacao-tecnica-f1-13-2026-10-16.md) avança os contratos e a verificação do próximo módulo sem alterar os três documentos atribuídos à Albertina, criar produto ou aprovar políticas. A implementação SQL depende da correcção transaccional e da especificação conciliada e revista. Os 32 critérios F1-13 continuam Por executar.

Entregas da Albertina recebidas em 16 de outubro: [PR #34](https://github.com/atchutchi/plataforma-interna-arn/pull/34), relatório parcial do segundo PC, merge b0a85ae, e [PR #35](https://github.com/atchutchi/plataforma-interna-arn/pull/35), proposta F1-13 e 32 critérios Por executar, merge 0e7ffcd. Cada head teve 12 checks aprovados antes do merge. A falha MySQL 8.0.44, o REST incompleto e o zoom nativo não foram corrigidos por estas integrações documentais. As regras propostas da F1-13 não ficam aprovadas pelo merge. Foram atribuídas à Albertina as novas Issues [#36](https://github.com/atchutchi/plataforma-interna-arn/issues/36), correcção transaccional MySQL, e [#37](https://github.com/atchutchi/plataforma-interna-arn/issues/37), conciliação documental F1-13. A QA pendente da #28 mantém-se aberta. Seguir a [coordenação actualizada](docs/tarefas-albertina-2026-10-16.md).

F1-11 foi integrado após revisão das correcções e 11 checks aprovados no SHA `5d8f93f2d1a11e90707dac309ece3ed4f9e7cec2`. Neste laboratório, o esquema 2 e as cinco fixtures foram activados explicitamente. Playwright confirmou autoria, rejeição/correcção, dois editores, continuidade da publicação durante revisão, mudança de audiência, suspensão de sessões, arquivo, avisos e fecho por flag. Seguir [operação F1-11](docs/publicacao-editorial-local-f1-11.md) e [QA F1-11](docs/qa-publicacao-editorial-local-f1-11.md), conservando os limites declarados. F1-11 permanece Em curso institucionalmente. Os 22 originais reais continuam exclusivamente na revisão administrativa, incluindo a categoria Comunicação sindical. Não os publicar ou colocar no Git.

O objetivo é disponibilizar um CMS WordPress privado, organizado pela estrutura da ARN, onde os funcionários consultam informação, colaboram e acompanham os processos autorizados para a sua função.

F1-07 integrada: a revisão transversal, os fixes e66a07d/be649bf e a aceitação local foram concluídos e recebidos pelas PRs #27/#25. A composição final foi revista, sem achados por resolver. O head 7e8ff4a passou os 11 checks depois de uma reexecução da job editorial falhada, sem alteração de código. A ocorrência e a pendência de diagnóstico estão no [registo de coordenação](docs/tarefas-albertina-2026-10-16.md). Mantém-se a evidência local de 759 testes PHP/3051 asserções, quatro omissões Windows, 43/41 verificações SQL/instalador, 55 testes Node e QA Playwright. People já está activo com três perfis fictícios e LAB-B activa na revisão global 6. Os 22 originais conservam hashes/metadados e revisão só administradora. Não repetir baselines iniciais. Zoom nativo não comprovado e delta tardio não identificado em wp_options continuam explicitados na [QA](docs/qa-directorio-perfil-local-f1-07.md).

A passagem ALB-F1-07-QA de 15 de outubro é histórica e a entrega local já foi integrada. O responsável autorizou novas atribuições em 16 de outubro: [#28, validação do segundo PC](https://github.com/atchutchi/plataforma-interna-arn/issues/28) e [#29, especificação F1-13](https://github.com/atchutchi/plataforma-interna-arn/issues/29), ambas atribuídas a Albertina4264 no GitHub. Seguir o [guião actual](docs/tarefas-albertina-2026-10-16.md) e o [prompt actual](docs/prompt-albertina-2026-10-16.txt). Não se alterou a branch ou o laboratório da colaboradora. A continuação central executa o plano F1-12 na branch própria, conforme o estado de execução, sem interferir nas Issues #28/#29.

## Índice

1. [Objetivo e âmbito](#objetivo)
2. [Decisões confirmadas e propostas](#decisoes)
3. [Arquitetura e tecnologias](#arquitetura)
   - [Infraestrutura Windows e rede observada](#infraestrutura-windows)
   - [DNS, HTTPS e identidade](#dns-identidade)
   - [Plano técnico de preparação](#plano-infraestrutura)
4. [Estrutura institucional e funcionários](#organizacao)
   - [Lista fornecida de departamentos e pessoal](#lista-pessoal)
5. [Perfis e permissões](#permissoes)
   - [Contas administrativas iniciais](#administradores-iniciais)
6. [Estrutura de páginas e gestão pelo CMS](#paginas)
7. [Autenticação e ciclo de vida do acesso](#autenticacao)
8. [Dados, documentos e armazenamento](#dados)
9. [Catálogo das 46 histórias originais](#historias)
10. [Melhorias propostas às histórias](#melhorias)
11. [Fluxos de trabalho](#fluxos)
12. [Requisitos de qualidade e operação](#qualidade)
13. [Entregas por fases](#fases)
14. [Organização futura do código](#codigo)
15. [Responsabilidades e tarefas de desenvolvimento](#tarefas)
   - [Plano mestre de conclusão para agentes](#plano-conclusao)
16. [Forma de colaboração no repositório](#colaboracao)
   - [Instalações nos computadores](#instalacao-pcs)
   - [Preparação e comandos Git](#preparacao-git)
   - [Primeira entrega local](#primeira-entrega)
   - [Prompt para ChatGPT Work/Codex](#prompt-codex)
17. [Verificação e critérios de conclusão](#verificacao)
18. [Decisões pendentes](#pendencias)
19. [Fontes e histórico de decisões](#fontes)

<a id="objetivo"></a>
## 1. Objetivo e âmbito

A plataforma deve funcionar como ponto de entrada para a informação e os serviços internos da ARN. Deve organizar funcionários por unidade orgânica, permitir publicação de conteúdos através do CMS e aplicar permissões sobre cada ação e cada registo.

Os módulos previstos são:

- Autenticação e controlo de acesso.
- Painel pessoal.
- Diretório de funcionários, unidades orgânicas e organograma.
- Notícias com aprovação editorial, galerias e anexos.
- Comunicados institucionais e de equipa.
- Documentos institucionais e administrativos.
- Tarefas e acompanhamento de equipas.
- Calendário de eventos.
- Pedidos internos, férias e calendário de ausências.
- Suporte informático através de tickets.
- Notificações, pesquisa e perfil pessoal.
- Administração, configuração e auditoria.

A implementação seguirá as 46 histórias do anexo User Stories Portal Interno Empresa Com Notícias, adaptadas às decisões confirmadas neste documento. As melhorias da secção 10 complementam essas histórias e conservam a rastreabilidade dos códigos originais.

O arranque local segue a secção 16.6. A configuração institucional do Google Workspace, a importação de dados reais e a instalação na rede ARN continuam sujeitas às dependências das respetivas tarefas.

<a id="decisoes"></a>
## 2. Decisões confirmadas e propostas

### 2.1 Decisões confirmadas pelo responsável do projeto

| ID | Tema | Decisão |
| --- | --- | --- |
| DEC-01 | Plataforma | WordPress como CMS. |
| DEC-02 | Entrega actual | Desenvolver código e documentação por tarefas, branches e PRs verificadas através de ChatGPT Work/Codex e GitHub. A decisão documental inicial fica no histórico. Cursor é opcional. Sem deploy nesta sessão. |
| DEC-03 | Organização | Integrar os funcionários por direção e demais unidades orgânicas. |
| DEC-04 | Autenticação | Usar as contas institucionais do Google Workspace já utilizado pela ARN, no domínio arn.gw. |
| DEC-05 | Alojamento | Usar um servidor Windows na infraestrutura interna da ARN. A natureza física ou virtual do novo host será inventariada. |
| DEC-06 | Rede | Permitir acesso apenas pela rede ARN ou por VPN. |
| DEC-07 | Desenvolvimento | Entregar por fases. |
| DEC-08 | Notícias | Submissão pelos funcionários e aprovação por equipa editorial designada. |
| DEC-09 | Catálogo institucional | A lista Departamentos-ARN.docx, recebida em 9 de outubro, detalha o catálogo inicial. Transcrição na secção 4.1 e ambiguidades na secção 4.6. |
| DEC-10 | Requisitos | Rever as histórias fornecidas e documentar melhorias necessárias. |
| DEC-11 | Servidor do portal | Panthera-Onca, 192.168.17.205, Windows Server 2019 Standard, versão 1809, build 17763, 64 bits. Fora de arn.local por decisão de 13 de outubro de 2026. Produção nativa com IIS, PHP 8.4 x64 NTS/FastCGI e MySQL 8.4 LTS, após homologação. |
| DEC-12 | Definições institucionais | A arquitetura está documentada e a nova lista fornece unidades e nomes. Permanecem para validação as lacunas de hierarquia, cargos e identidade, a equipa editorial, a distribuição nominal de tarefas, as fontes operacionais, os circuitos e o calendário. |
| DEC-13 | Controlador de domínio | O responsável confirmou que 192.168.17.151 é controlador de domínio de arn.local. Mantém as funções AD DS e DNS. |
| DEC-14 | Associação ao domínio | Panthera-Onca foi retirado de arn.local e deve permanecer fora do domínio. Não depender de GPO, Kerberos, LDAP, conta de computador ou autenticação AD. .151 mantém AD DS/DNS. DNS institucional apenas se aprovado pela DCSI. Esta decisão substitui a versão 0.5. |
| DEC-15 | Administração inicial | Registar as três contas administrativas indicadas pelo responsável na secção 5.4, com autenticação Google e atribuição explícita de permissões no portal. |
| DEC-16 | Lista de departamentos e pessoal | Incluir no README a lista fornecida pelo responsável. Registar a grafia de origem, as faltas de informação e os pontos de validação, sem criar contas ou atribuir cargos por inferência. |
| DEC-17 | Trabalho conjunto | Atchutchi e um colaborador trabalharão em computadores separados. Usar clones independentes, branches por tarefa, commits por avanço verificável e push da branch de trabalho, com revisão antes de integrar em main. |

| DEC-18 | Desenvolvimento | ChatGPT Work/Codex, GitHub e ambiente local separado. Cursor é opcional e não acrescenta dependências ao código. |
| DEC-19 | Férias | 22 dias úteis por funcionário e por ano civil, repartíveis por vários pedidos prévios. Aprovação final por RH. Sem cadeia adicional presumida. Regras na secção 11.5. |

DEC-04 atualiza a forma de executar US-F01. A página da ARN disponibiliza a entrada institucional e encaminha a autenticação para o Google. A palavra-passe é introduzida no serviço Google, segundo a política da instituição.

### 2.2 Propostas técnicas desta versão

São recomendações para orientar o desenvolvimento: WordPress numa instalação única, tema próprio, plugin institucional, integração OIDC, IIS com PHP por FastCGI, MySQL 8.4 LTS, armazenamento privado de anexos e ferramentas de desenvolvimento reproduzíveis.

As propostas de melhoria ainda dependentes de decisão funcional estão identificadas na secção 10. Os pontos que precisam de informação institucional adicional estão na secção 18, com responsável e momento de resolução.

Uma decisão confirmada não significa que a configuração ou funcionalidade já esteja implementada.

<a id="arquitetura"></a>
## 3. Arquitetura e tecnologias

### 3.1 Arquitetura proposta

Uma instalação WordPress servirá o portal, o CMS e os módulos internos. O tema tratará da apresentação. O plugin institucional concentrará regras de negócio, autorização, dados e processos.

Esta separação permite alterar o design sem perder a estrutura das unidades, os conteúdos ou os processos. A documentação WordPress recomenda manter tipos de conteúdo no plugin quando precisam de sobreviver à mudança de tema. [W2]

~~~mermaid
flowchart TD
    Rede["Clientes na rede ARN ou VPN"] -->|"HTTPS"| IIS["IIS 10 em 192.168.17.205"]
    Rede -->|"DNS institucional se aprovado"| DNS["DNS e AD DS em 192.168.17.151"]
    IIS --> PHP["PHP x64 NTS por FastCGI"]
    PHP --> Portal["WordPress ARN"]
    Google["Google Workspace"] -->|"Identidade OIDC"| Portal
    Portal --> Tema["Tema de blocos ARN"]
    Portal --> Core["Plugin institucional"]
    Core --> Base["MySQL 8.4 LTS local"]
    Core --> Arquivo["Ficheiros privados"]
    Core --> Logs["Auditoria"]
~~~

A ligação ao Google representa a autenticação dos utilizadores. Panthera-Onca permanece fora de arn.local. O uso de DNS institucional depende de aprovação da DCSI e não cria dependência de AD. O acesso será limitado à rede ARN ou VPN. A aplicação usa Google Workspace e autorização local WordPress, sem GPO, Kerberos ou LDAP.

### 3.2 Stack de referência

| Camada | Tecnologia proposta | Função e condição de adoção |
| --- | --- | --- |
| CMS | WordPress estável e mantido | Conteúdos, administração, editor, utilizadores e mecanismos nativos. Versão exata validada no início da implementação. |
| Backend | PHP 8.4 x64 Non-Thread Safe, com atualização mantida | Executar através de php-cgi.exe e FastCGI no IIS. Validar extensões, runtime C++ e compatibilidade das dependências. [W13] |
| Regras da ARN | Plugin próprio arn-intranet-core | Unidades, vínculos, permissões, fluxos, dados de trabalho, ficheiros privados e auditoria. |
| Frontend | Tema de blocos arn-intranet | HTML, CSS, theme.json, padrões, templates e JavaScript para interação. |
| Editor | Editor de blocos integrado no WordPress | Edição de conteúdos por utilizadores autorizados. Blocos e opções disponíveis conforme a função. |
| Componentes dinâmicos | JavaScript e pacotes WordPress | React quando necessário para blocos ou componentes do editor. Sem aplicação frontend autónoma na arquitetura base. |
| Base de dados | MySQL Community Server 8.4 LTS x64 | Serviço Windows. A matriz MySQL inclui Windows Server 2019. Base própria do portal, acesso local e credenciais restritas. [W14] |
| Servidor | Windows Server 2019 Standard no host 192.168.17.205, fora de arn.local | Sistema reportado no anexo. IP e associação ao domínio confirmados pelo responsável nas correções posteriores. Inventariar configuração, recursos, atualizações, serviços e coexistência. |
| Servidor web | IIS 10, CGI/FastCGI e URL Rewrite | Site e Application Pool exclusivos do portal. Regras de encaminhamento em web.config e HTTPS no nome canónico. [W13] [W16] |
| Identidade | Google Workspace por OpenID Connect | Identidade institucional, com autorização local no portal. |
| Cliente OIDC | Facile PHP OpenID Client 1.0.0 | Biblioteca mantida com adaptador ARN. Homologada localmente. Falta HTTPS/IIS/MySQL/LAN/VPN. Ver docs/google-login-local-f1-02.md. |
| Ficheiros | Armazenamento privado sob controlo da ARN | Entrega através de autorização no servidor. Abrange anexos, imagens, miniaturas e versões. |
| Ambiente local | ChatGPT Work/Codex e GitHub, com ambiente local separado em WSL 2, Ubuntu 24.04 LTS e wp-env com Docker | Percurso comum para os dois PCs. Node.js 24 LTS e Git no Ubuntu. wp-env usa MariaDB, com versão fixada, e PHP 8.4. A homologação valida Windows/IIS e MySQL 8.4. Docker Desktop requer licença adequada à utilização institucional e não será instalado no servidor de produção. [W10] [W31] [W32] [W33] [W34] [W35] |
| Ferramentas | WP-CLI, Composer e npm | Gestão técnica, dependências PHP e construção de recursos do tema e dos blocos. |
| Qualidade | WordPress Coding Standards, PHPUnit e testes de navegador | Verificar regras de acesso, estados, integrações e fluxos completos. |
| Colaboração | GitHub, branches e pull requests | Histórico de alterações e revisão. As tarefas ficam inicialmente registadas neste README. |

O Hosting Handbook do WordPress recomenda PHP 8.4 ou superior e distingue versões mantidas de versões aceites apenas por compatibilidade. A seleção das versões de produção deve seguir suporte ativo ou de segurança e compatibilidade verificada. O mínimo técnico de execução não serve, por si só, como critério para uma instalação nova. [W1]

MySQL 8.4 LTS substitui a preferência inicial por MariaDB neste ambiente Windows. A política de pacotes MariaDB Community identifica Windows Server 2019 como plataforma descontinuada em janeiro de 2024. Por isso, esta revisão não assume MariaDB 11.4 como combinação oficialmente suportada nesse sistema. [W14] [W15]

### 3.3 Alternativas consideradas

| Opção | Avaliação para este projeto |
| --- | --- |
| WordPress integrado, tema próprio e plugin institucional | Proposta base. Reúne CMS e aplicação, preserva os mecanismos WordPress e concentra a manutenção. |
| Windows com IIS, PHP por FastCGI e MySQL | Proposta de alojamento nativo para o ambiente indicado. A homologação cobre WordPress, extensões e controlos específicos do IIS. |
| WordPress como backend e frontend separado em Next.js | Acrescenta aplicação, integração, sessões e manutenção. Reavaliar apenas se uma necessidade concreta o justificar. |
| Conjunto de plugins independentes para RH, tarefas, suporte e intranet | Exige validar integração de permissões, dados e atualizações. Aproveitar componentes adequados, mantendo as regras da ARN centralizadas. |

As direções serão unidades da mesma instituição dentro da mesma instalação. Não se propõe um site WordPress separado por direção.

### 3.4 Política de dependências

Cada dependência deve ter função identificada, licença conhecida, manutenção verificável e compatibilidade testada. Registar versão, origem, responsável pela atualização e dependências relevantes.

O cliente OIDC é um candidato, ainda sem aprovação técnica para produção. A homologação deve validar autenticação Google, assinatura e claims, bloqueio de contas não autorizadas, associação de identidades e revogação local. Se falhar os critérios, avaliar uma integração com biblioteca oficial mantida, sem escrever um protocolo de autenticação próprio.

Plugins de pagamento, licenças comerciais e serviços externos adicionais exigem uma necessidade definida e decisão de aquisição. O percurso local proposto utiliza Docker Desktop, cuja utilização por entidades governamentais requer subscrição paga. Confirmar a cobertura institucional antes de o utilizar. Esta documentação não compra nem ativa licenças. [W34]

<a id="infraestrutura-windows"></a>
### 3.5 Infraestrutura Windows e rede observada

O responsável confirmou que .151 mantém AD DS e DNS de arn.local. Em 13 de outubro de 2026 confirmou que Panthera-Onca, .205, foi retirado do domínio e deve permanecer fora dele. Windows Server 2019 Standard, WindowsVersion 1809, build 17763 e arquitectura 64 bits foram comunicados nessa decisão. Hyper-V está instalado. WSL não está instalado e wsl.exe não existe no servidor. Não instalar WSL ou Docker Desktop em .205 para executar a intranet. Não foi feita inspeção remota. Os recursos do anexo anterior continuam sujeitos a inventário.

| Equipamento | Papel definido | Relação com o domínio |
| --- | --- | --- |
| 192.168.17.151 | Controlador de domínio e DNS institucional | Controlador de arn.local, confirmado pelo responsável. |
| 192.168.17.205, Panthera-Onca | Host escolhido para o futuro portal WordPress e a sua base de dados | Servidor fora de arn.local, conforme decisão do responsável em 13 de outubro de 2026. |

Dados comunicados para o servidor do portal. A decisão de 13 de outubro de 2026 substitui as referências anteriores à pertença ao domínio:

| Elemento | Informação disponível | Tratamento no plano |
| --- | --- | --- |
| Sistema operativo | Windows Server 2019 Standard, 1809, build 17763, 64 bits | Informação confirmada pelo responsável. Actualizações e recursos efectivos por inventariar. |
| Hyper-V | Instalado | Não implica que WSL ou Docker estejam disponíveis. |
| WSL | Não instalado. wsl.exe ausente | Não instalar WSL ou Docker Desktop para alojar o portal. |
| Nome do servidor | Panthera-Onca | Referência para inventário e administração. O nome web do portal será próprio. |
| Fabricante | Hewlett Packard Enterprise | Informação do anexo. Não permite concluir se o sistema apresentado é físico ou virtual. |
| Natureza do host | Ainda não confirmada para este servidor | Inventariar hardware ou recursos atribuídos, conforme se trate de instalação física ou VM. |
| Memória | 32 GB reportados | Não representam RAM livre ou reservada ao portal. Medir utilização e margem disponível. |
| Processador | Intel Xeon Silver 4114 reportado | Confirmar capacidade disponível e processadores lógicos ou vCPU, conforme o host. |
| IPv4 do alvo | 192.168.17.205 | Endereço de referência corrigido pelo responsável. Conferir configuração efetiva, estabilidade e ausência de conflito no inventário. |
| Máscara | 255.255.255.0 | Rede local 192.168.17.0/24. |
| Gateway | 192.168.17.1 | Equipamento de encaminhamento indicado na configuração. |
| Estabilidade do endereço | Método de atribuição ainda por inventariar em .205 | Validar endereço fixo ou reserva institucional adequada, sem presumir a configuração DHCP deste host. |
| DNS do servidor | Configuração efectiva por validar pela DCSI | Pode consultar .151 se aprovado. Validar nomes internos e externos sem dependência de serviços AD. |
| Domínio Windows | Fora de arn.local | Manter fora do domínio. Usar configuração local e identidades de serviço locais autorizadas. |
| VPN | Acesso por VPN confirmado como requisito | Sub-rede de clientes, rotas, DNS e regras de firewall ainda por inventariar. |

O gateway .1 não define o conjunto de endereços livres. O endereço de rede é 192.168.17.0 e o de broadcast é 192.168.17.255. Qualquer novo endereço deve ser verificado no inventário e nos serviços de atribuição existentes.

### 3.6 Localização da aplicação e funções de domínio

O host escolhido para a aplicação é 192.168.17.205. O papel de .151 como controlador de domínio está confirmado pelo responsável e deixa de ser uma questão em aberto. A preparação de IIS, PHP, MySQL e ficheiros privados será feita no servidor .205, após inventariar serviços existentes, portas, volumes, atualizações e capacidade.

| Componente | Localização e responsabilidade |
| --- | --- |
| WordPress, PHP, IIS, MySQL e documentos do portal | Servidor 192.168.17.205, fora de arn.local. |
| Active Directory e DNS | Servidor 192.168.17.151, no domínio arn.local. |
| Autenticação dos funcionários | Google Workspace por OIDC. |
| Autorizações funcionais | WordPress e plugin institucional, conforme função, unidade e objeto. |

A gestão de .205 usa contas e configurações locais autorizadas. O Application Pool terá identidade local própria e a base de dados ficará no mesmo servidor. As permissões funcionais são do WordPress e do plugin. Configurar explicitamente hora, certificados, firewall, manutenção e recuperação, sem pressupor políticas do domínio. [W17] [W5]

O portal em .205 e o controlador em .151 conservam funções separadas. A aplicação não exige associação ao domínio nem alterações a AD DS. [W19]

### 3.7 Configuração proposta para IIS e serviços

Criar um site IIS e um Application Pool exclusivos do portal em .205. Usar ApplicationPoolIdentity e conceder ACL NTFS à identidade local IIS AppPool\ARNPortal, se ARNPortal for o nome aprovado para o pool. O nome é uma proposta de configuração. As identidades de pool permitem separar o acesso de cada aplicação aos ficheiros sem criar uma conta de domínio. [W17]

Definir contas administrativas locais nominativas e grupos de acesso aprovados pela DCSI. Usar identidades locais restritas para serviços e tarefas. As contas Windows são distintas das contas Google dos funcionários e das funções de gestão do CMS. Não pressupor autenticação ou contas AD em .205.

Configuração de referência:

- IIS 10 com CGI/FastCGI, conteúdo estático, documentos predefinidos, logging e URL Rewrite.
- PHP 8.4 x64 NTS, obtido de distribuição oficial, associado ao handler php-cgi.exe. Usar php.exe apenas nas tarefas CLI.
- Runtime Visual C++ v14 x64 mantido e compatível com o binário PHP. Homologar o conjunto na tarefa INF-06. [W13] [W24]
- Extensões necessárias ao conjunto escolhido, incluindo mysqli, curl, openssl, mbstring, fileinfo, zip, intl e tratamento de imagens. Validar OPcache e os limites de memória e uploads.
- Pool em 64 bits e sem runtime .NET para a aplicação PHP. Definir reciclagem e limites a partir dos ensaios, evitando interromper trabalhos longos.
- Autenticação anónima no IIS configurada para usar a identidade do pool, permitindo à aplicação processar a entrada e o callback OIDC. A autorização de conteúdos pertence ao WordPress. Não ativar implicitamente autenticação integrada Windows ou Basic para o portal. [W25]
- Binding HTTPS associado ao nome canónico aprovado, com certificado correspondente. Configurar WordPress para usar esse mesmo endereço.
- Regras web.config limitadas ao site, preservando o encaminhamento de permalinks, REST, administração e callback real do plugin.
- MySQL 8.4 LTS como serviço Windows, com diretório de dados e conta de serviço próprios. Escutar em loopback para o acesso local do PHP.
- Base de dados exclusiva do portal, conta da aplicação limitada a essa base e procedimento de manutenção para alterações de esquema.

O Application Pool limita o processo e os acessos ao sistema de ficheiros. As permissões por funcionário e por direção continuam a exigir verificação no plugin.

<a id="dns-identidade"></a>
### 3.8 DNS, HTTPS e identidade

| Nome ou sistema | Finalidade |
| --- | --- |
| arn.local | Domínio Active Directory existente, com controlador em 192.168.17.151. |
| Google Workspace institucional | Autenticação dos funcionários, conforme DEC-04. |
| intranet.arn.gw | Nome canónico proposto para o portal, a resolver internamente para 192.168.17.205. Precisa de validação e configuração. |
| 192.168.17.205 | IP escolhido para o servidor do portal, fora de arn.local. |
| 192.168.17.151 | Controlador AD DS/DNS. Uso como DNS de .205 sujeito a aprovação da DCSI. |

As regras Google para aplicações web exigem HTTPS, host admissível e sufixo público válido. O callback de produção não deve usar o IP privado nem um nome terminado em .local. A proposta intranet.arn.gw permite manter arn.local para AD e utilizar um nome adequado ao login Google. [W5]

O nome de produção proposto deve resolver para 192.168.17.205 através do DNS usado na rede ARN e na VPN. O nome e o endereço de homologação serão próprios desse ambiente. O caminho exato do callback será o fornecido pelo plugin homologado, não um caminho inventado nesta fase. O browser regressa ao portal pela rede ou VPN. O servidor efetua a troca de código com o Google através de ligação de saída.

A alteração DNS deve limitar-se ao nome necessário e respeitar as zonas atuais. No DNS institucional .151, se não existir configuração equivalente, avaliar uma zona interna intranet.arn.gw com registo A na raiz apontado para .205. A DCSI cria e gere o registo do nome web do portal, sem criar conta de computador ou associação de .205 ao domínio. Evitar criar uma zona arn.gw incompleta que oculte os registos públicos utilizados pelo correio e pelos restantes serviços. A documentação Microsoft descreve zonas e resolução diferenciada por contexto. [W20]

Para HTTPS, a proposta preferencial é certificado público para o nome do portal, com emissão e renovação ACME DNS-01. O desafio usa um registo TXT público e funciona sem expor o servidor web à Internet. A emissão depende de controlo autorizado do DNS e de um procedimento de renovação e instalação no IIS. [W21]

Como alternativa, usar PKI interna se a ARN assegurar confiança na cadeia em todos os dispositivos autorizados, incluindo VPN. Em .205, instalar e validar explicitamente a confiança local, sem depender de GPO. Nos clientes de domínio, a DCSI pode usar políticas próprias de distribuição. Nos restantes clientes, definir procedimento autorizado. Estabelecer VPN não instala confiança. Emissão, instalação e renovação do certificado do site exigem procedimento próprio. [W22] [W28]

Nas chamadas HTTPS originadas por WordPress/PHP, validar a cadeia no cliente HTTP utilizado. O WordPress admite um ficheiro próprio de autoridades de certificação. A homologação deve testar estes pedidos, além do browser, mantendo ativa a verificação TLS. [W29]

O domínio AD e as OUs não fazem parte da autenticação nem da operação obrigatória da intranet. Qualquer proposta futura de integração AD exige decisão expressa e não altera DEC-14 automaticamente.

### 3.9 Fluxos de rede e firewall

As regras serão aplicadas localmente no Windows Firewall de .205 e nos equipamentos competentes, preservando AD DS, DNS e VPN. Confirmar o perfil de rede e as regras locais efectivas sem pressupor perfil Domain ou GPO. Delimitar explicitamente as origens LAN e VPN autorizadas. [W26]

| Fluxo | Política proposta |
| --- | --- |
| Clientes ARN para o portal | TCP 443, a partir das redes internas autorizadas. A rede observada é 192.168.17.0/24. |
| Clientes VPN para o portal | TCP 443, a partir da sub-rede ou origem efetiva da VPN, depois de verificada. Não presumir que pertence ao mesmo /24. |
| HTTP para o portal | TCP 80 apenas para redirecionamento interno, se necessário. ACME DNS-01 não exige abertura pública desta porta. |
| PHP para MySQL no mesmo servidor .205 | Ligação por loopback, com MySQL sem escuta na LAN. Sem publicação da porta 3306. |
| Administração Windows | RDP apenas a partir de origens e contas administrativas autorizadas pela DCSI. |
| Resolução DNS | Consultar o DNS institucional .151, se aprovado pela DCSI, a partir de .205 e dos clientes autorizados, por UDP/TCP 53. Preservar a resolução dos nomes externos necessários. |
| Dependências AD de .205 | Não exigir GPO, Kerberos, LDAP, conta de computador nem autenticação AD. Apenas DNS institucional se aprovado. Hora e administração locais com procedimento definido pela DCSI. |
| Saída para Google | HTTPS dos browsers e do servidor para os serviços necessários ao OIDC. Considerar proxies, validação de certificados e sincronização de hora. |
| Certificados e atualizações | Saída estritamente necessária pelos mecanismos aprovados de emissão, renovação e distribuição. |
| Internet para o portal | Sem publicação ou encaminhamento público para o IIS, a base de dados ou o RDP. |

Acesso por VPN, DNS interno e sessão WordPress são controlos complementares. A resolução do nome não substitui as regras de rede, e estar na rede não concede acesso ao conteúdo.

<a id="organizacao"></a>
## 4. Estrutura institucional e funcionários

<a id="lista-pessoal"></a>
### 4.1 Lista fornecida de departamentos e pessoal

Fonte: Departamentos-ARN.docx, fornecido pelo responsável do projeto em 9 de outubro de 2026. A tabela reproduz as 37 entradas preenchidas e as 73 menções nominais, na ordem e grafia do anexo. A última linha vazia foi omitida. O documento contém oito direções expressamente designadas e vinte entradas com hífen sob essas direções, além dos restantes órgãos, serviços e estruturas.

O hífen inicial foi conservado porque faz parte da apresentação da lista. As células de significado vazias estão identificadas como Não indicado no anexo. A coluna de contagem é apenas uma ajuda à conferência. Não representa contas criadas, cargos aprovados ou verificação de identidade.

| Sigla ou entrada do anexo | Significado no anexo | Pessoal indicado no anexo | Nomes |
| --- | --- | --- | --- |
| CA | Conselho de Administração | Herry Mané, João Agaristo Vieira, João Moreira | 3 |
| Secretaria CA | Secretaria | Samanta Cardoso, Albam Taf | 2 |
| Conselheiros CA | Conselheiros | Augusto Mario Silva, Luis Seabra, Otaniel Batista, Nelson de Barros, Ismael Sadilu | 5 |
| DCSI | Direção de Comunicação e Sistema de Informação | Frederik Djata | 1 |
| -DSU | Não indicado no anexo | Moises Mantam Biagué | 1 |
| -DSGI | Não indicado no anexo | Atchutchi Ferreira | 1 |
| -DSIC | Não indicado no anexo | Lyssarides Pereira, Juscelino Lopes | 2 |
| NIC | Núcleo Informático e Comunicação | Davi D’Almada | 1 |
| SOS-DNS | Serviço de Operação de Sistema DNS | Clayton Correia | 1 |
| SGND | Serviço de Gestão de Nomes de Dominios | Djara Injai e Milaldina Teixeira | 2 |
| DF | Direção Financeiro | Mohamed Djahate | 1 |
| -DFC | Departamento de Finanças e Contabilidade | Almir Semedo, Catube Nhaté, Honorinda Mendonça e Ludmila Quadé Djaló, Samuel Sanhá | 5 |
| -DPL | Departamento de Patrimônio e Logistíca | Epinafanio fernandes, Lamine Camará, Domingos Iala, Infa Manafá | 4 |
| DRE | Direção de Radiocomunicação e Engenharia | Clode Sanha | 1 |
| -DRS | Departamento de Redes e Serviços | Florentino Miranda e Wassila Aiem | 2 |
| -DGE | Departamento de Gestão de Espetro | Eduardo Nhaga e Isabel Fernandes | 2 |
| -DFV | Departamento de Fiscalização e Vistorias | Cigei Lopes a e Joel Manuel | 2 |
| DCT-Q&S | Direção de Controlo de Tráfego e Qualidade de Serviço | Antonio Sani | 1 |
| -DQ&S | Departamento de Qualidade de Serviço | Patrick Carvalho | 1 |
| -DCT | Departamento de Controlo de Tráfego | Nicádio Injai | 1 |
| DMAO | Direção do Mercado e Acompanhamento de Operadoras | Filemon Sambú | 1 |
| -DSAU | Departamento de serviço de Acesso Universal | Alimatu Turé, Nicácia Camssamá, Isa-Nén Sande | 3 |
| -DMTC | Departamento de mercado, Tarifas e Custos | Idrissa Só, Emilia Vieira | 2 |
| -DEEP | Departamento de Estudo, Estatistica e Planeamento | Ermelinda Nhaga, Rui Sigá | 2 |
| DRH | Direção de Recursos Humanos | Edmundo Oliveira | 1 |
| -DRB | Departamento de Renumerações e Benefícios | Aymone Mango, Nadilé Mendonça | 2 |
| -DARHF | Departamento de administração de RH e Formação | Edmila Ié, Miralda dos Santos, Esperança Tavares | 3 |
| Arquivo | Não indicado no anexo | Judite Baticam, Domingos Quindam-Ghol, Elizabete Lima | 3 |
| Recepção | Não indicado no anexo | M’bomba Antonio Injai | 1 |
| DRAJDC | Direção de Regulamentação, Assuntos Jurídicos e Defesa do Consumidor | Vladmir Jorge Gomes | 1 |
| -DLR | Departamento de Licenciamento e Regulamentação | Fernando Tchuda | 1 |
| -DAJDC | Departamento dos Assuntos Jurídicos e Defesa dos Consumidores | Lucio Pires Junior, Buli Camará | 2 |
| DREC | Direção de Relações Exteriores e Cooperação | Abdel Jaquité | 1 |
| -DGE | Departamento de Gestão e Estratégia | Yasmine, Jonelly Cabral | 2 |
| -DCRP | Departamento de Comunicação e Relações Públicas | Gisela Tenan Lopes | 1 |
| -DRIC | Departamento de Relações Institutionais e Cooperação | Mariama Mané | 1 |
| FAU | Fundo de Acesso Universal | Nivaldo Pereira, Anabela Alo Fernandes, Diogo Monteiro, Abrão Có, Vladmir Correia Landim, Tchungana, Michel | 7 |

Total documental: 73 nomes, sem repetição nominal exata na lista. Na linha DFV, a contagem interpreta Cigei Lopes a e Joel Manuel como duas menções e conserva o texto para confirmação. O anexo não fornece emails, identificadores profissionais, cargos individuais ou datas de validade.

As pessoas apresentadas diretamente na linha de uma direção não são automaticamente consideradas diretores, chefias ou aprovadores. A coluna Pessoal indica colocação na lista e não nomeação para funções. O mesmo se aplica aos administradores técnicos, editores e colaboradores GitHub.

### 4.2 Modelo da unidade orgânica

Cada unidade deve ter identificador estável, designação, sigla, tipo, unidade superior quando aplicável, descrição funcional, responsável designado, estado e datas de validade.

O modelo deve suportar órgão, direção, departamento, núcleo, serviço, secretariado, gabinete, fundo e outro tipo aprovado. A hierarquia deve impedir ciclos. Alterar o nome de uma unidade não deve alterar o seu identificador nem perder associações.

A sigla não é uma chave globalmente única. Os dois departamentos DGE terão identificadores distintos, com códigos de referência como DRE-DGE e DREC-DGE, conservando a sigla apresentada no anexo. Registar fonte e estado de validação das relações superiores. Uma relação desconhecida deve permanecer pendente e não ser apresentada como posição definitiva no organograma.

Uma unidade desativada conserva o histórico. A desativação exige resolver vínculos ativos e encaminhar processos pendentes. Não se deve eliminar uma unidade apenas para a retirar do organograma corrente.

### 4.3 Registo do funcionário

| Grupo | Dados previstos |
| --- | --- |
| Identificação profissional | Identificador interno, nome e email institucional. |
| Enquadramento | Unidade principal, departamento quando aplicável, cargo ou função e responsável hierárquico. |
| Contacto de trabalho | Contacto profissional e localização de trabalho, quando aplicáveis. |
| Perfil | Fotografia e campos de apresentação autorizados pela ARN. |
| Acesso | Conta WordPress associada, identificador estável da identidade Google, estado e funções autorizadas. |
| Histórico | Datas de entrada, alteração de vínculo, transferência e desativação necessárias ao portal. |
| Participação transversal | Equipas ou comissões autorizadas, quando esta funcionalidade for ativada. |

O diretório geral apresenta apenas os campos profissionais aprovados para partilha interna. Dados de saúde, bancários, documentos de identificação e processos individuais não pertencem ao diretório.

RH valida os dados profissionais. A administração de acessos atribui permissões segundo a decisão funcional autorizada. O funcionário altera apenas os campos de autosserviço definidos.

### 4.4 Entrada dos funcionários no sistema

A lista da secção 4.1 é a referência documental inicial para o cadastro. Antes de uma importação operacional, RH valida a grafia, identificadores profissionais, unidades, cargos e emails institucionais. Prever importação controlada por CSV para evitar criação manual repetitiva.

O código de importação e os exemplos usam dados fictícios. O README nominativo não é um ficheiro executável de criação de contas. Não gerar emails a partir dos nomes, associar contas por semelhança de nome ou deduzir a identidade Google a partir da ordem das linhas.

A importação deve oferecer validação prévia, detetar duplicados e unidades inexistentes, apresentar erros por registo e produzir resumo de alterações. Uma importação não deve atribuir privilégios técnicos nem desativar pessoas por mera ausência numa lista incompleta.

A correspondência com a conta Google exige identidade validada e registo local autorizado. Ficheiros reais de importação permanecem nos sistemas internos aprovados.

### 4.5 OUs do Active Directory como referência futura

A imagem do Active Directory mostra uma OU ARN com várias OUs filhas. As OUs representam organização técnica, delegação e políticas, sem equivalência automática com o organograma institucional. [W18]

O catálogo documental atual segue a lista da secção 4.1. A designação anterior NIC.gw, a entrada NIC no novo anexo e as OUs com nomes próximos precisam de correspondência validada. As entradas observadas DCT-QOS e DCGT&QoS não serão fundidas automaticamente com DCT-Q&S.

Uma eventual análise documental de OUs só pode ocorrer numa fase posterior expressamente autorizada. A intranet não depende de importação ou sincronização AD. A pertença a OU não atribui funções WordPress. DEC-14 mantém o servidor fora do domínio.

### 4.6 Pontos de validação da lista

| Ponto | Informação recebida | Tratamento no desenvolvimento |
| --- | --- | --- |
| Agrupamento das direções | DSU, DSGI e DSIC seguem DCSI. DFC e DPL seguem DF. DRS, DGE e DFV seguem DRE. DQ&S e DCT seguem DCT-Q&S. DSAU, DMTC e DEEP seguem DMAO. DRB e DARHF seguem DRH. DLR e DAJDC seguem DRAJDC. DGE, DCRP e DRIC seguem DREC. | Usar este agrupamento documental para preparar o modelo e a validação. RH confirma a hierarquia operacional antes de publicar o organograma. |
| Duas entradas DGE | Departamento de Gestão de Espetro na DRE e Departamento de Gestão e Estratégia na DREC. | Manter unidades e identificadores distintos. Não deduplicar pela sigla. |
| DSU, DSGI e DSIC | O anexo não preenche o significado. | Conservar as siglas e deixar a designação extensa pendente. |
| NIC e NIC.gw | O novo anexo indica NIC, Núcleo Informático e Comunicação. O responsável tinha indicado NIC.gw no catálogo inicial. | Confirmar equivalência, designação canónica e eventuais aliases. Não criar duas unidades operacionais nem fundi-las sem validação. |
| SOS-DNS e SGND | Aparecem depois de NIC, sem hífen ou indicação expressa da unidade superior. | Registar ambos os serviços. Manter a ligação superior por confirmar. |
| Arquivo e Recepção | Aparecem após as linhas da DRH, sem indicação expressa de subordinação. | Representar as entradas sem assumir dependência da DRH. |
| CA, Secretaria CA, Conselheiros CA e FAU | As designações e o pessoal foram fornecidos. Não há descrição de competências, presidência ou circuito de aprovação. | Preservar as designações e validar as relações de governação, cargos e limites de acesso. |
| Grafia e nomes incompletos | Entre outros, o anexo contém Cigei Lopes a, Epinafanio fernandes, Yasmine, Tchungana e Michel. Também contém Direção Financeiro, Renumerações e Institutionais. | Manter a transcrição de origem. RH valida a grafia canónica e a identificação completa antes da importação. |
| Contas e permissões | A lista não contém emails, identificadores de identidade ou funções no portal. | Completar o cadastro por fonte aprovada e atribuir permissões explicitamente. Não derivar privilégios da linha, cargo presumido ou OU. |

Estes pontos esclarecem parcialmente PEN-01 e PEN-05. Não impedem construir a fundação técnica nem testar o modelo com dados fictícios. Continuam necessários antes de importar pessoas reais, publicar o organograma definitivo ou ativar autorizações dependentes da hierarquia.

<a id="permissoes"></a>
## 5. Perfis e permissões

### 5.1 Três dimensões de autorização

A autorização combina:

1. Função: quais as ações permitidas.
2. Âmbito: quais as unidades, equipas ou pessoas abrangidas.
3. Registo: relação do utilizador com o conteúdo e estado do processo.

Pertencer à DCSI, DRH ou outra direção não atribui automaticamente todas as permissões funcionais dessa área. Ser diretor também não atribui o papel técnico Administrator do WordPress.

WordPress fornece papéis e capabilities personalizadas. As regras por unidade, titular, destinatário e estado terão de ser implementadas no plugin institucional. [W3]

### 5.2 Matriz funcional

| Perfil funcional | Ações principais | Âmbito |
| --- | --- | --- |
| Funcionário | Consultar portal, gerir campos próprios, acompanhar tarefas, criar pedidos e tickets, preparar notícias | Próprio registo e conteúdos autorizados. |
| Responsável de unidade | Acompanhar equipa, atribuir tarefas, validar pedidos e publicar comunicados de equipa | Unidade ou equipa para a qual recebeu responsabilidade. |
| RH | Gerir dados profissionais, documentos administrativos, férias e ausências | Processos e pessoas incluídos na competência atribuída. |
| Suporte TI | Consultar, assumir, responder e resolver tickets | Fila e tickets autorizados para o serviço de suporte. |
| Equipa editorial | Analisar notícias, aprovar, rejeitar e gerir publicação institucional | Fila editorial e públicos para os quais recebeu autorização. |
| Administração de acessos | Gerir contas locais, funções, âmbitos e bloqueios | Competências administrativas delegadas. |
| Administração técnica | Manter WordPress, tema, plugins, ambientes e operação | Acesso privilegiado restrito aos responsáveis técnicos designados. |
| Órgãos de direção e fiscalização | Consultar ou decidir matérias atribuídas | Perfil a definir por necessidade. A posição institucional não implica acesso universal a processos RH. |

Uma pessoa pode acumular funções autorizadas. O CMS deve permitir identificar a origem, o âmbito e a duração das permissões.

Contas com controlo do servidor, da base de dados ou com capacidade de instalar código têm acesso técnico privilegiado. A matriz funcional não constitui isolamento contra esses administradores. Esse acesso exige gestão própria, atribuição restrita e registo das operações.

### 5.3 Audiência e confidencialidade

Os conteúdos devem distinguir classificação e destinatários.

| Audiência | Exemplos |
| --- | --- |
| Toda a ARN | Notícias institucionais aprovadas, procedimentos gerais e eventos comuns. |
| Uma ou várias unidades autorizadas | Documentação de trabalho e comunicados de equipas. |
| Pessoas ou intervenientes expressamente autorizados | Pedido individual, tarefa atribuída, ticket e documento pessoal de RH. |

Todas estas audiências são internas. “Publicado” significa disponível aos utilizadores autorizados dentro do portal.

A autorização deve ser aplicada nas páginas, consultas à base de dados, APIs, pesquisa, exportações, contagens, notificações e ficheiros. Ocultar um botão ou filtrar apenas o menu não cumpre este requisito.

<a id="administradores-iniciais"></a>
### 5.4 Contas administrativas iniciais

O responsável indicou as seguintes contas institucionais para a administração inicial da plataforma:

| Conta Google Workspace | Função indicada | Estado |
| --- | --- | --- |
| ferreira.atchutchi@arn.gw | Administração da plataforma | Designada para a configuração inicial. Ativação por executar. |
| clayton.correia@arn.gw | Administração da plataforma | Designada para a configuração inicial. Ativação por executar. |
| admin@arn.gw | Administração da plataforma | Designada para a configuração inicial. Ativação por executar. |

Google Workspace fornece a identidade. O WordPress e o plugin institucional atribuem os perfis e capabilities locais, mantendo a distinção entre administração de acessos e administração técnica da secção 5.2. O domínio arn.gw, o nome do email ou um privilégio na consola Google não concedem automaticamente privilégios no portal. [W3] [W5]

A configuração inicial deve confirmar a identidade Google que autentica com cada conta indicada e associá-la ao registo local autorizado. O vínculo segue a validação do emissor e do identificador estável sub da secção 7.2. Conferir a conta efetiva de autenticação quando o endereço indicado funcionar como alias, sem atribuir privilégios apenas por coincidência de email. As atribuições, alterações e revogações ficam registadas em auditoria.

Estas contas identificam os administradores iniciais do portal. As competências efetivas e a sua ativação são preparadas em F0-04, F1-02 e F1-03. A designação de operadores Windows, colaboradores GitHub e equipa editorial segue os respetivos processos já documentados.

<a id="paginas"></a>
## 6. Estrutura de páginas e gestão pelo CMS

### 6.1 Navegação prevista

| Área | Conteúdo e ações | Fase |
| --- | --- | --- |
| Entrar | Identidade ARN, entrada Google Workspace e instrução de acesso pela rede ou VPN | F1 |
| Painel | Nome, notificações, tarefas, próximos eventos e informação recente autorizada | F1 |
| ARN e unidades | Apresentação interna, organograma validado e páginas das unidades | F1 |
| Funcionários | Pesquisa por nome, unidade e cargo. Perfil profissional autorizado | F1 |
| Notícias | Notícias aprovadas, pesquisa e filtros básicos | F1 |
| As minhas notícias | Rascunhos, submissões, decisões e correções do autor | F1 |
| Revisão editorial | Fila de análise e decisão da equipa editorial | F1 |
| Comunicados | Avisos da instituição e das equipas autorizadas | F1 |
| Documentos | Categorias, versões atuais e acesso protegido | F1 |
| Tarefas | Minhas tarefas e gestão de equipa quando autorizada | F1 |
| Calendário | Consulta de eventos. Criação por responsáveis designados | F1 |
| Pedidos | Submissão, acompanhamento e decisões dos processos definidos | F2 |
| Férias e ausências | Pedidos de férias, tratamento RH e disponibilidade autorizada | F2 |
| Suporte | Tickets do funcionário e fila do serviço TI | F2 |
| Notificações | Avisos por destinatário e marcação como lidos | F1 |
| Meu perfil | Consulta e edição dos campos autorizados | F1 |
| Administração | Utilizadores, estrutura, funções, configurações e auditoria | F1 |

A pesquisa básica acompanha cada módulo quando este entra em serviço. Pesquisa avançada entre múltiplos sistemas fica para evolução.

O painel apresenta apenas módulos ativados. Estados sem dados devem explicar a situação e oferecer a ação adequada. Módulos de fases futuras não devem apresentar números fictícios nem ações inoperantes.

### 6.2 Requisitos para ser um CMS

Os utilizadores autorizados devem conseguir executar pelo painel de gestão, sem editar código:

- Criar e atualizar páginas institucionais, notícias, comunicados e eventos.
- Editar menus, blocos e padrões dentro dos limites da identidade visual aprovada.
- Gerir unidades, funcionários e associações.
- Organizar documentos, categorias, versões e destinatários.
- Gerir a fila editorial e consultar o histórico de decisões.
- Atribuir funções e âmbitos através de operações administrativas controladas.
- Configurar parâmetros expostos pela aplicação, incluindo tipos e limites de anexos.

As configurações críticas devem exigir permissões próprias e registo de alteração. As regras de segurança e a validação de processos permanecem no plugin, mesmo quando o utilizador muda opções pela interface.

Os funcionários usam uma interface de portal com as ações adequadas à sua função. O acesso a wp-admin é concedido conforme a necessidade. As APIs e ações de administração mantêm verificações de autorização em ambos os casos.

<a id="autenticacao"></a>
## 7. Autenticação e ciclo de vida do acesso

### 7.1 Sequência de entrada

1. O funcionário estabelece acesso pela rede ARN ou VPN.
2. Abre a página de entrada do portal.
3. Escolhe a autenticação institucional Google Workspace.
4. O Google autentica a pessoa segundo a política institucional.
5. O browser regressa ao endereço de retorno registado para o portal.
6. O servidor valida a resposta de identidade.
7. O plugin confirma a associação a um funcionário autorizado e ativo.
8. WordPress estabelece a sessão e abre o painel com as permissões aplicáveis.

O endereço de retorno OIDC precisa de estar acessível ao browser na rede interna ou VPN. A troca de código por tokens é uma ligação de saída do servidor. Esta arquitetura não exige tornar o portal acessível publicamente. O fluxo e a validação do endereço de retorno seguem a documentação Google. [W5]

### 7.2 Requisitos da integração Google

Usar Authorization Code Flow, endereço HTTPS de retorno registado exatamente e cliente OAuth institucional. Validar assinatura, emissor, destinatário, expiração, state e nonce. Para a configuração inicial da ARN, validar a claim hd com o valor arn.gw e associar a identidade estável por sub. O sufixo do email não substitui estas verificações. Pedir apenas openid e email. [W5]

O primeiro vínculo a uma conta previamente cadastrada deve ser controlado. Uma mudança de email não deve criar outro funcionário. Uma conta com o mesmo email e identificador Google diferente exige nova validação.

A criação automática irrestrita de utilizadores deve ficar desativada. Uma identidade Google válida, por si só, não autoriza entrada no portal nem atribui funções.

O login exige conectividade de saída para o Google nos dispositivos e no servidor. Durante indisponibilidade do fornecedor de identidade, mostrar erro controlado e canal de suporte. A rede interna não elimina esta dependência.

A página de entrada, o retorno OIDC e os recursos estritamente necessários ao login precisam de exceções ao bloqueio por sessão. Essas exceções mantêm a restrição de rede e não expõem conteúdos institucionais.

### 7.3 Sessões, recuperação e saída

A palavra-passe institucional e a sua recuperação pertencem ao Google Workspace. A aplicação não deve apresentar um formulário para recolher a palavra-passe Google.

A sessão Google e a sessão do portal são distintas. Suspender uma conta Google não deve ser tratado como revogação automática das sessões WordPress. [W7]

O procedimento de saída de um funcionário deve desativar a autorização local, revogar todas as sessões WordPress, tratar as permissões e processos pendentes e coordenar a suspensão no Workspace. WordPress dispõe de mecanismos de revogação de sessões por utilizador. [W8]

Verificar o estado local da conta em cada pedido autenticado. Aplicar as alterações de âmbito nas consultas seguintes e invalidar caches associados. O histórico dos documentos e decisões deve conservar a autoria necessária.

Definir antes da entrada em serviço duração máxima, inatividade, encerramento de sessão e gestão de dispositivos partilhados. A política MFA será aplicada no Google Workspace, com prioridade aos acessos privilegiados. Não pressupor que um plugin WordPress ativa MFA no Workspace.

Uma eventual conta técnica de recuperação local exige procedimento próprio e acesso restrito. A necessidade, guarda, utilização e auditoria dessa exceção estão em PEN-10. O acesso corrente dos funcionários segue Google Workspace.

### 7.4 Autenticação da API

A interface integrada utiliza as sessões WordPress. Pedidos de alteração precisam de proteção contra CSRF e verificação de capabilities e do registo. Um nonce não substitui autorização.

Cada endpoint personalizado deve definir a respetiva verificação de acesso. A REST API necessária ao editor deve continuar a funcionar para os utilizadores autorizados. [W4]

As contas dos funcionários utilizam exclusivamente a autenticação Google. Bloquear o login WordPress por palavra-passe local e a recuperação local de palavra-passe para essas contas, incluindo rotas nativas e alternativas. A eventual exceção técnica de recuperação segue PEN-10 e não se aplica ao acesso corrente dos funcionários.

Desativar XML-RPC e application passwords enquanto não existir uma integração expressamente autorizada e documentada. A instalação do cliente OIDC, por si só, não demonstra que estas vias ficaram bloqueadas.

<a id="dados"></a>
## 8. Dados, documentos e armazenamento

### 8.1 Entidades e persistência proposta

| Entidade | Persistência prevista | Responsabilidade |
| --- | --- | --- |
| Conta | Utilizador WordPress e metadados autorizados | Ligação à identidade Google, estado e preferências. |
| Unidade | Tipo de conteúdo hierárquico arn_unidade | Estrutura institucional gerida no CMS. |
| Vínculo profissional | Metadados para o vínculo corrente e histórico versionado pelo plugin | Unidade principal, função e alterações aprovadas. |
| Notícia | Tipo de conteúdo arn_noticia | Conteúdo, versões, aprovação, público e histórico editorial. |
| Comunicado | Tipo de conteúdo arn_comunicado | Mensagem, destinatários, validade e circuito aplicável. |
| Documento | Tipo de conteúdo arn_documento e registo de versões | Metadados, proprietário institucional, ficheiro e autorização. |
| Evento | Tipo de conteúdo arn_evento | Datas, local ou ligação, organizador e audiência. |
| Tarefa | Tabelas do módulo de tarefas | Responsável, prazo, estado e histórico. |
| Pedido | Tabelas de pedidos e decisões | Referência, tipo, formulário, etapas e intervenientes. |
| Pedido de férias | Extensão transaccional de pedidos em F2 | Funcionário, ano de referência, início, fim, dias úteis calculados, estado, submissão, decisão, responsável e data da decisão, observação e histórico. |
| Direito anual e movimentos de férias | Registo por funcionário e ano civil | Direito padrão 22, dias gozados, futuros aprovados, saldo disponível e movimentos auditados. Aprovação e saldo gravados atomicamente para impedir excesso concorrente. |
| Calendário RH | Configuração versionada de feriados e dias de trabalho | Feriados oficiais validados por RH. Guardar referência à versão usada no cálculo e no histórico. |
| Ticket | Tabelas de tickets e mensagens | Solicitante, técnico, respostas e resolução. |
| Notificação | Tabela por destinatário | Evento de origem, leitura e entrega. |
| Auditoria | Registo específico com acesso restrito | Ação, interveniente, objeto, resultado e data. |
| Ficheiro privado | Armazenamento privado e metadados ligados ao objeto | Conteúdo binário, versões e regras de entrega. |

Os nomes de tabelas, índices, migrações e relações serão especificados na tarefa F0-06. Usar o prefixo configurado do WordPress. Avaliar metadados nativos antes de criar tabelas, reservando tabelas próprias para relações e consultas transacionais que as justifiquem. [W9]

O plugin deve separar acesso aos dados, regras de negócio, autorização e apresentação. Todos os registos precisam de identificadores estáveis e datas. Usar UTC nos registos técnicos e apresentação de datas em português.

### 8.2 Regras de integridade

- Não permitir decisões contraditórias sobre a mesma versão de um pedido ou notícia.
- Verificar novamente estado, versão e autorização ao gravar uma alteração.
- Guardar a decisão e o evento de notificação de forma consistente.
- Preservar autor, aprovador e contexto histórico quando um funcionário muda de unidade.
- Evitar referências sem objeto válido, atribuições a contas desativadas e unidades hierárquicas circulares.
- Versionar alterações de estrutura de dados e prever atualização e recuperação de versão.

### 8.3 Ficheiros efetivamente privados

WordPress dispõe de diretórios e URLs próprios para uploads. Proteger a página de um documento não demonstra proteção do ficheiro. A arquitetura deve tratar a entrega do binário como uma operação autorizada. [W11]

Proposta: guardar ficheiros institucionais fora da raiz física do site IIS. O plugin valida sessão, conta, permissão e objeto e entrega o ficheiro através de streaming controlado em PHP. Não criar um diretório virtual IIS nem uma partilha de utilizadores que permita contornar esta verificação.

A entrega deve validar o identificador do ficheiro, resolver apenas caminhos internos autorizados, impedir travessia de diretórios e emitir cabeçalhos adequados ao tipo de conteúdo. Homologar ficheiros maiores, pedidos parciais quando necessários e consumo de memória. Uma futura otimização da entrega exige manter a autorização por objeto.

Imagens, miniaturas, pré-visualizações, galerias, anexos de notícias em análise e versões anteriores seguem a mesma regra. A biblioteca de media, endpoints e listagens não devem permitir a um autor consultar os ficheiros privados de outro.

O autor de uma notícia mantém acesso aos próprios ficheiros durante edição. A equipa editorial autorizada acede ao material submetido. Os restantes funcionários só recebem acesso quando a versão correspondente é aprovada e a audiência os inclui.

Definir lista de tipos admitidos, limites por ficheiro e por envio, validação do tipo real e tratamento de ficheiros suspeitos. Bloquear executáveis e ficheiros ativos não autorizados. Os valores operacionais serão aprovados em PEN-08.

Uma alteração ao documento pai deve atualizar a autorização das versões, derivados e caches. A aprovação não deve ser contornada pela substituição posterior do ficheiro já aprovado.

#### Organização proposta das pastas Windows

Os caminhos seguintes são exemplos de planeamento. A existência e capacidade do volume D: não foram verificadas. Usar o volume de dados aprovado durante o inventário.

| Caminho de referência | Finalidade | Acesso de serviço |
| --- | --- | --- |
| D:\ARN\Portal\public | Raiz física IIS com WordPress, tema e plugins | Leitura e execução para o pool. Escrita de código apenas pelo processo de manutenção autorizado. |
| D:\ARN\Portal\private | Ficheiros institucionais e derivados privados | Acesso do serviço para guardar e transmitir após autorização. Sem diretório virtual IIS nem partilha para funcionários. |
| D:\ARN\Portal\runtime | Temporários de upload, processamento e cache local controlada | Escrita limitada ao serviço. Sem execução de uploads como código. |
| D:\ARN\Portal\config | Configuração protegida e segredos necessários ao serviço | Leitura mínima pelo processo. Alteração apenas por administradores autorizados. |
| D:\ARN\Portal\logs | Registos de aplicação e processamento | Escrita pelo serviço e leitura por operadores autorizados. |
| D:\ARN\Data\MySQL | Dados do serviço MySQL | Acesso pela identidade do MySQL. Sem acesso direto pelo pool IIS. |
| Destino de backup fora do servidor da aplicação | Cópias protegidas e recuperáveis | Identidade de backup e operadores autorizados. Destino e autenticação a definir. |

Rever heranças NTFS para evitar permissões amplas de escrita. As ACL protegem o sistema de ficheiros entre processos e contas do Windows. O plugin continua responsável por decidir qual funcionário pode receber cada ficheiro.

As regras de localização e entrega abrangem também os uploads nativos e as imagens que o editor de blocos produz. Não deve existir uma segunda cópia desprotegida em wp-content/uploads.

O CMS mantém a edição de conteúdos, menus e configurações autorizadas na base de dados. Alterações a ficheiros de código seguem o procedimento de entrega técnica.

### 8.4 Relação com os sistemas existentes

As fontes do projeto referem Google Workspace, Primavera e Alfresco. A existência dessas referências não confirma as configurações, interfaces ou licenças atualmente disponíveis.

| Domínio | Orientação inicial |
| --- | --- |
| Identidade | Google Workspace é a decisão confirmada para autenticação. |
| Dados profissionais mínimos do portal | Validação por RH. Definir origem e processo de atualização em PEN-05. |
| Notícias, tarefas e conteúdo criado no portal | WordPress e módulos institucionais são a origem desses registos. |
| Documentos oficiais | Definir por categoria se a origem é o portal ou Alfresco/outro repositório aprovado. |
| Férias e saldos | RH identifica o sistema oficial e as regras antes de ativar o módulo. |
| Calendários externos | Integração futura, mediante necessidade e regras de partilha. |

Uma ligação a um documento no Alfresco ou Drive conserva as permissões do sistema de origem. A autenticação no portal não concede acesso automaticamente ao sistema externo.

Qualquer integração futura deve identificar origem oficial, proprietário dos dados, campos, sentido de sincronização, frequência, autorização, tratamento de erros e reconciliação. O login Google não exige acesso a Gmail, Drive ou ao diretório completo de funcionários.

<a id="historias"></a>
## 9. Catálogo das 46 histórias originais

Esta secção consolida os comportamentos do anexo. Os códigos originais mantêm-se. Os critérios devem ser lidos com as regras transversais de acesso, ficheiros, auditoria e estados deste README.

F1 corresponde ao núcleo do portal. F2 corresponde aos serviços administrativos e suporte. A atribuição por fases é uma proposta de execução coerente com a opção confirmada de desenvolvimento faseado.

### 9.1 Funcionário

| Código | História | Critérios de aceitação consolidados | Fase |
| --- | --- | --- | --- |
| US-F01 | Iniciar sessão para aceder ao espaço de trabalho | Página de entrada institucional. Autenticação Google validada. Conta local autorizada e ativa. Erro controlado em falha. Acesso ao painel e bloqueio de áreas não autorizadas. Atualização expressa do formulário de credenciais previsto no anexo. | F1 |
| US-F02 | Consultar painel pessoal | Apresenta nome, notificações, tarefas pendentes, próximos eventos, comunicados recentes e acessos rápidos. Todos os dados respeitam a autorização. Prevê estados sem dados. | F1 |
| US-F03 | Consultar colegas | Pesquisa por nome. Apresenta nome, cargo e unidade. Mostra apenas os dados profissionais autorizados. | F1 |
| US-F04 | Consultar tarefas | Mostra título, descrição, prioridade, prazo e estado. Permite filtrar por estado. Apresenta apenas tarefas acessíveis à pessoa. | F1 |
| US-F05 | Atualizar uma tarefa própria | O funcionário atualiza o estado das tarefas atribuídas. O servidor valida a relação e a transição. A alteração fica no histórico e é visível ao responsável autorizado. | F1 |
| US-F06 | Consultar documentos | Organização por categorias. Consulta e descarga autorizadas. Documentos restritos não surgem para pessoas sem acesso. Proteção inclui ficheiro, versões e pré-visualizações. | F1 |
| US-F07 | Consultar calendário | Eventos apresentam título, data, hora e local ou ligação. É possível consultar eventos futuros. Eventos privados respeitam a audiência. | F1 |
| US-F08 | Submeter e acompanhar pedidos | Seleção do tipo de pedido. Validação dos campos e anexos permitidos. Emissão de referência única. Consulta do estado e histórico autorizado. | F2 |
| US-F09 | Abrir ticket de suporte | Assunto, descrição e prioridade. Anexo permitido. Identificação única. Acompanhamento do estado pelo solicitante. | F2 |
| US-F10 | Consultar e atualizar perfil | Apresenta os dados da pessoa. Apenas campos de autosserviço são editáveis. As alterações são validadas e guardadas. Unidade, cargo e funções exigem o processo institucional competente. | F1 |

### 9.2 Responsável de unidade

| Código | História | Critérios de aceitação consolidados | Fase |
| --- | --- | --- | --- |
| US-M01 | Consultar a equipa | Lista pessoas da unidade autorizada e os dados profissionais necessários. Dados de gestão de outras unidades ficam restritos. Mantém acesso ao diretório institucional comum. | F1 |
| US-M02 | Criar e atribuir tarefas | Indica título, descrição, responsável, prioridade e prazo. Atribui dentro do âmbito autorizado. O funcionário recebe notificação e vê a tarefa no painel. | F1 |
| US-M03 | Acompanhar tarefas da equipa | Filtra por funcionário e estado. Identifica prazos e atrasos. Permite abrir detalhes autorizados. | F1 |
| US-M04 | Validar pedidos | Consulta pendências da etapa atribuída. Aprova, rejeita ou devolve segundo o circuito definido. Justifica rejeição ou devolução. O funcionário recebe a decisão. | F2 |
| US-M05 | Publicar comunicados | Regista título, conteúdo e destinatários. Publicação limitada ao âmbito autorizado. Notifica destinatários. Comunicado institucional segue regra editorial própria. | F1 |

### 9.3 Recursos Humanos

| Código | História | Critérios de aceitação consolidados | Fase |
| --- | --- | --- | --- |
| US-RH01 | Gerir registos de funcionários | Pesquisa e consulta dos dados autorizados. Criação, alteração e desativação conforme a competência atribuída. Operações importantes registadas. Coordenação com contas e vínculos. | F1 |
| US-RH02 | Processar pedidos de férias | RH decide o período com aprovação final, sem cadeia adicional presumida. Direito anual de 22 dias úteis, repartível por vários pedidos prévios. Validar saldo, feriados e sobreposição. Auditar aprovação, rejeição, alteração e cancelamento. | F2 |
| US-RH03 | Gerir documentos administrativos | Carrega documentos permitidos, organiza categorias e define destinatários. Documentos confidenciais têm acesso restrito, incluindo os binários. | F1 |
| US-RH04 | Consultar ausências | Calendário colectivo mostra apenas ausências aprovadas, com funcionário, unidade e datas. Filtra por unidade e período. Não expõe motivos nem documentos confidenciais. | F2 |

### 9.4 Suporte informático

| Código | História | Critérios de aceitação consolidados | Fase |
| --- | --- | --- | --- |
| US-TI01 | Consultar tickets do suporte | Lista tickets novos, em tratamento e resolvidos da fila autorizada. Filtra por prioridade e estado. Apresenta solicitante e descrição. | F2 |
| US-TI02 | Assumir um ticket | Técnico autorizado assume atendimento. O sistema regista responsável e transição para Em tratamento. Impede dupla atribuição contraditória. | F2 |
| US-TI03 | Responder e atualizar ticket | Acrescenta resposta técnica e altera estado conforme permissão. O solicitante consulta as atualizações que lhe são destinadas. | F2 |
| US-TI04 | Resolver ticket | Regista solução, data de resolução e técnico. Altera estado e notifica o solicitante. | F2 |

### 9.5 Administração

| Código | História | Critérios de aceitação consolidados | Fase |
| --- | --- | --- | --- |
| US-A01 | Gerir contas | Criar, consultar, editar e desativar registos locais autorizados. Associar funções. Conta desativada perde novos acessos e sessões ativas. Operações administrativas ficam registadas. | F1 |
| US-A02 | Gerir funções e permissões | Define ações para funcionários, responsáveis, RH, TI, equipa editorial e administração. Bloqueia acesso direto indevido. Aplica verificações na interface e no servidor. | F1 |
| US-A03 | Gerir unidades | Cria, edita e desativa unidades hierárquicas. Associa funcionários. Preserva relações válidas e histórico. Adapta a referência genérica a departamentos à organização ARN. | F1 |
| US-A04 | Consultar auditoria | Apresenta interveniente, ação, objeto, data e hora. Acesso restrito. Funcionários comuns não alteram nem apagam registos. | F1 |
| US-A05 | Configurar portal | Permite gerir parâmetros autorizados. Configurações críticas exigem competência administrativa e ficam registadas. | F1 |

### 9.6 Histórias transversais

| Código | História | Critérios de aceitação consolidados | Fase |
| --- | --- | --- | --- |
| US-S01 | Receber notificações | Notifica atribuições, decisões, tickets e comunicações conforme o módulo ativo. Permite marcar como lida. Entrega apenas a destinatários autorizados. | F1, com eventos F2 acrescentados nessa fase |
| US-S02 | Pesquisar informação | Pesquisa funcionários e conteúdos dos módulos ativos. Identifica tipo de resultado. Exclui registos, excertos e contagens restritos. | F1, com índices F2 acrescentados nessa fase |
| US-S03 | Proteger a sessão | Permite terminar sessão. Aplica autenticação, autorização, expiração e revogação. Prevê acesso através de rede ARN ou VPN. | F1 |
| US-S04 | Usar vários dispositivos | Páginas adaptadas a computador, tablet e telemóvel. Menus, formulários e botões mantêm utilização e legibilidade. O dispositivo necessita de acesso à rede autorizada. | F1 |

### 9.7 Notícias

| Código | História | Critérios de aceitação consolidados | Fase |
| --- | --- | --- | --- |
| US-NOT-01 | Criar notícia | Título, texto e imagem de destaque. Guarda rascunho sem publicação. Regista autor e data. | F1 |
| US-NOT-02 | Adicionar galeria | Permite várias imagens ligadas à notícia, pré-visualização, remoção e reordenação antes de submissão. Valida formato e tamanho. A galeria aprovada apresenta-se organizada. | F1 |
| US-NOT-03 | Anexar documentos | Adiciona um ou mais ficheiros admitidos. Mostra e permite remover antes de submeter. Valida limites configurados. Mantém associação durante revisão e acesso igual ao da notícia. | F1 |
| US-NOT-04 | Pré-visualizar notícia | Mostra título, texto, destaque, galeria e anexos. Pré-visualização não publica conteúdo. Autor regressa à edição autorizada. | F1 |
| US-NOT-05 | Enviar para aprovação | Ação de submissão altera estado para Pendente. Regista data e notifica equipa editorial. Autor não publica diretamente. | F1 |
| US-NOT-06 | Consultar o estado das próprias notícias | Mostra Rascunho, Pendente, Publicada e Rejeitada. Apresenta motivo da decisão. Permite corrigir e reenviar uma rejeitada. Não dá acesso aos rascunhos alheios. | F1 |
| US-NOT-07 | Receber decisão editorial | Notifica aprovação ou rejeição ao autor. Inclui motivo quando a decisão exige correção e ligação para o conteúdo autorizado. | F1 |
| US-NOT-08 | Consultar fila editorial | Equipa editorial consulta pendências e filtra por autor, data e estado. Abre texto, imagens e anexos completos. Acesso exige permissão explícita. | F1 |
| US-NOT-09 | Aprovar e publicar | Ação editorial autorizada aprova uma versão e disponibiliza-a à audiência interna. Regista aprovador e data. Conteúdo, imagens e anexos dessa versão seguem a decisão. | F1 |
| US-NOT-10 | Rejeitar notícia | Ação editorial impede publicação, regista motivo e notifica autor. Autor corrige e reenvia. A justificação obrigatória é melhoria proposta nesta versão. | F1 |
| US-NOT-11 | Editar antes da aprovação | Autor edita Rascunho ou Rejeitada. Pendente fica bloqueada até devolução autorizada para edição. Alterações à versão publicada seguem nova aprovação. | F1 |
| US-NOT-12 | Consultar notícias publicadas | Lista apenas versões aprovadas acessíveis. Mostra autor, data, texto, imagem, galeria e anexos existentes. Exclui estados privados de outros autores. | F1 |
| US-NOT-13 | Pesquisar e filtrar notícias | Pesquisa por título e texto. Filtros básicos por data e categoria. Resultados respeitam audiência e estado editorial. | F1 |
| US-NOT-14 | Registar auditoria editorial | Regista criação, submissão, aprovação, rejeição, alterações e eliminação autorizada. Inclui interveniente, data, ação e notícia. Funcionários comuns não modificam o histórico. | F1 |

<a id="melhorias"></a>
## 10. Melhorias propostas às histórias

### 10.1 Correções de coerência

| Ponto do anexo | Tratamento proposto |
| --- | --- |
| Formulário de utilizador e palavra-passe em US-F01 | Adaptar à decisão de autenticação Google Workspace. O portal conserva página própria de entrada. |
| “Pública” e “área pública” nas notícias | Usar “publicada no portal para utilizadores autorizados”. |
| Administrador como aprovador de notícias | Substituir pela competência da equipa editorial designada, sem exigir administração técnica. |
| Departamento tratado como lista simples | Representar unidades e relações hierárquicas validadas. |
| Responsável não acede a dados de outras unidades, mas existe diretório comum | Separar contactos profissionais partilhados de processos de gestão e RH restritos. |
| Motivo de rejeição opcional em alguns critérios | Propor motivo obrigatório em rejeições e devoluções para permitir correção e auditoria. |
| Notificações classificadas como prioridade média | Incluir notificação básica na mesma fase das tarefas e decisões que dela dependem. |
| Notícias ausentes do menu e da prioridade inicial | Incluir menu, fila editorial e módulo completo em F1. |
| Pesquisa avançada futura e pesquisa transversal obrigatória | Entregar pesquisa básica por módulo desde a sua ativação. |
| Painel exige eventos, mas calendário é classificado como médio | Incluir calendário básico e regras de gestão em F1. |
| Mistura de português, francês e inglês nos menus | Usar português de Portugal na interface inicial. |
| Edição de notícia publicada sem regra para versão anterior | Manter a versão aprovada enquanto uma revisão segue análise, com retirada autorizada quando necessária. |

### 10.2 Histórias complementares

Estas 12 histórias são propostas para validação funcional. Não substituem os códigos originais. P0 indica um requisito para disponibilizar o núcleo com acesso correto. P1 indica um requisito necessário à ativação do processo correspondente.

| Código | História proposta e critérios principais | Complementa | Prioridade e fase |
| --- | --- | --- | --- |
| US-ARN-01 | Como gestor da estrutura, quero representar unidades hierárquicas. Identificador estável, tipo, unidade superior, responsável e estado. Impedir ciclos. Preservar vínculos e histórico ao renomear ou desativar. Usar o catálogo confirmado sem presumir o tipo de NIC.gw ou DCT-Q&S. | US-A03, US-F03, US-M01 | P0, F1 |
| US-ARN-02 | Como responsável de RH e acessos, quero gerir entrada, transferência, suspensão e saída. Importar lista validada sem duplicados. Associar identidade Google e conta local autorizada. Atualizar âmbitos. Revogar sessões. Encaminhar tarefas pendentes e preservar autoria. Recuperação da identidade segue Workspace. | US-RH01, US-A01, US-F10, US-S03 | P0, F1 |
| US-ARN-03 | Como gestor de permissões, quero atribuir funções com âmbito. Permitir acumulação autorizada e duração definida. Distinguir diretório de dados confidenciais. Aplicar regras a páginas, API, pesquisa, exportações e notificações. Revogar privilégios anteriores nas mudanças aprovadas. | US-A02, US-M01, US-S02 | P0, F1 |
| US-ARN-04 | Como editor designado, quero aprovar versões identificadas. Bloquear alterações durante análise. Exigir motivo para rejeição. Reapreciar revisões. Manter versão publicada aprovada. Registar aprovador, data e público. Outro editor trata pendências durante ausências. | US-NOT-05 a US-NOT-11, US-NOT-14 | P0, F1 |
| US-ARN-05 | Como utilizador autorizado, quero proteção efetiva dos ficheiros. Uma URL direta, miniatura, versão anterior ou endpoint de media não contorna a autorização. Ficheiros de rascunhos e revisões mantêm acesso restrito. Validar tipos e limites antes de disponibilizar. | US-F06, US-RH03, US-NOT-02/03/04 | P0, F1 |
| US-ARN-06 | Como responsável de processo, quero definir etapas e substituições. Cada tipo de pedido tem formulário, circuito e aprovador. Substituição tem âmbito e prazo. Rejeição e devolução exigem motivo. Autoaprovação segue regra expressa. Decisões concorrentes não produzem resultados contraditórios. | US-F08, US-M04, US-RH02 | P1, F2 |
| US-ARN-07 | Como funcionário, quero gerir vários pedidos prévios de férias no ano civil. Direito de 22 dias úteis, sem obrigatoriedade de continuidade. Ver os próprios pedidos, direito anual, dias gozados, dias futuros aprovados e saldo disponível. RH decide dentro da competência. Rejeição ou retirada não consome saldo. Cancelamento futuro aprovado por RH repõe apenas dias não gozados e fica auditado. Calendário colectivo apenas com ausências aprovadas. | US-F08, US-RH02, US-RH04 | P1, F2 |
| US-ARN-08 | Como responsável, quero manter continuidade das tarefas. Definir responsável principal. Registar mudanças de prazo e atribuição. Reatribuir após transferência ou saída. Comentários e anexos seguem acesso da tarefa. Colaboração entre unidades exige grupo autorizado. | US-F04/05, US-M02/03 | P1, continuidade em F1. Grupos transversais em F3 |
| US-ARN-09 | Como responsável documental, quero identificar versão, origem e validade. Mostrar proprietário institucional, versão atual e data de revisão. Arquivar versões sem as apresentar como atuais. Definir destinatários individuais quando necessário. Preservar acesso e referência à fonte oficial. | US-F06, US-RH03 | P1, núcleo em F1. Integrações em F3 |
| US-ARN-10 | Como destinatário, quero receber notificações coerentes. Gerar o aviso apenas após sucesso da operação. Evitar duplicados em reenvios. Registar falhas de entrega. Marcar como lida não equivale a aprovação nem confirmação formal do conteúdo. | US-S01, US-M02, US-NOT-07 | P0, F1. Canais adicionais por decisão |
| US-ARN-11 | Como funcionário, quero usar o portal com teclado e tecnologias de apoio. Campos têm rótulos e erros claros. Foco visível, contraste legível, texto alternativo e comunicação de estados. Validar fluxos principais em vários tamanhos de ecrã. | US-S04 e formulários | P0, F1 |
| US-ARN-12 | Como responsável técnico, quero recuperar o serviço após falha. Copiar base de dados, ficheiros e configuração necessária. Proteger backups e credenciais. Testar restauro isolado. Confirmar dados, permissões e ficheiros após recuperação e registar evidência. | US-A04/05 e operação | P0, antes de disponibilizar F1 |

A autoaprovação editorial, os circuitos de pedidos e os critérios de conclusão das tarefas precisam de decisão funcional. As propostas não criam níveis de aprovação adicionais sem necessidade institucional.

<a id="fluxos"></a>
## 11. Fluxos de trabalho

### 11.1 Notícias

~~~mermaid
flowchart TD
    R["Rascunho"] -->|"Submeter"| P["Pendente de aprovação"]
    P -->|"Aprovar versão"| A["Publicada internamente"]
    P -->|"Rejeitar com motivo"| J["Rejeitada"]
    J -->|"Corrigir"| R
    P -->|"Devolver para edição"| R
    A -->|"Criar revisão"| V["Revisão em rascunho"]
    V -->|"Submeter revisão"| P
    A -->|"Retirar por autorização"| F["Arquivada"]
~~~

O processo editorial trabalha sobre uma versão identificada. Quando existe revisão de notícia publicada, a versão anterior aprovada mantém-se disponível até decisão sobre a nova versão. A audiência recebe apenas o conteúdo efetivamente aprovado.

A equipa editorial visualiza conteúdo, anexos e audiência antes da decisão. As alterações relevantes incluem substituição de imagens, documentos e destinatários. A aprovação verifica que a versão analisada continua a ser a versão submetida.

A autoria original e o interveniente editorial ficam registados separadamente. A eliminação definitiva segue a política de conservação. Arquivar não elimina o histórico nem torna ficheiros acessíveis.

### 11.2 Comunicados

Proposta funcional: responsáveis autorizados publicam comunicados operacionais para a própria equipa. Comunicados dirigidos a toda a ARN passam pela equipa editorial ou por um publicador institucional expressamente designado.

Cada comunicado identifica autor, destinatários, data e eventual validade. Confirmar esta distinção em PEN-07. As notícias conservam sempre o seu circuito de aprovação.

### 11.3 Tarefas

Estados iniciais: A fazer, Em curso e Concluída.

O responsável cria e atribui. O funcionário acompanha e atualiza. Mudanças de prazo, responsável ou estado ficam no histórico. Atraso é calculado a partir do prazo e estado, sem substituir o estado de execução.

Se a ARN exigir validação formal da conclusão, definir a transição e o papel responsável antes de ativar essa opção. Reabertura ou reatribuição exige competência e motivo.

### 11.4 Pedidos internos

Proposta de estados genéricos: Rascunho, Submetido, Em análise, Devolvido para correção, Aprovado, Rejeitado, Retirado e Cancelado. O processo de férias conserva os comportamentos da secção 11.5 e não herda etapas adicionais de outros pedidos.

Os tipos de pedido não usam automaticamente o mesmo circuito. Para cada tipo, definir campos, anexos, referência, sequência de decisão, responsável, substituto, prazo de tratamento e efeito da aprovação.

Guardar o histórico das etapas e impedir uma pessoa de decidir fora da etapa ou âmbito atribuído. Retirada de pedido pendente e cancelamento de pedido aprovado precisam de regras distintas.

O conjunto inicial inclui o processo de férias previsto no anexo. Outros tipos de pedido serão escolhidos em PEN-06. Os exemplos presentes nas fontes, como autorização de despesa, não constituem inclusão automática no âmbito.

### 11.5 Férias e ausências

Regra confirmada em 13 de outubro de 2026: cada funcionário dispõe de 22 dias úteis por ano civil. Os dias podem ser repartidos por vários pedidos e não precisam de ser consecutivos. Cada período exige solicitação prévia. A aprovação final pertence ao RH, dentro da competência atribuída, sem presumir etapa de chefia ou outra cadeia adicional.

Exemplo: 5 dias úteis em maio e 15 em dezembro totalizam 20 dias aprovados e deixam 2 dias disponíveis. Sábados, domingos e feriados oficiais não consomem saldo. O calendário de feriados deve ser configurável, versionado e validado pelo RH. Não usar um calendário inventado como fonte oficial. Horários especiais e eventual proporcionalidade do direito ficam pendentes de decisão RH.

Cada pedido identifica funcionário, ano de referência, data inicial, data final, número calculado de dias úteis, estado, data de submissão, decisão, responsável e data da decisão, observação quando aplicável e histórico. Estados mínimos: rascunho, submetido, em análise, aprovado, rejeitado, retirado e cancelado. A modelação genérica pode acrescentar devolução para correcção sem perder estes comportamentos.

Fluxo mínimo: rascunho passa a submetido após validação e depois a em análise. RH aprova ou rejeita. Um pedido pendente pode ser retirado segundo a regra a detalhar em F2-01. Retirada e rejeição não reduzem o saldo. Um pedido aprovado reserva os dias úteis correspondentes. Alterar ou cancelar um período aprovado exige decisão RH e histórico, sem substituir silenciosamente a versão aprovada.

O saldo apresenta separadamente direito anual, dias gozados, dias futuros aprovados e saldo disponível. O disponível corresponde ao direito menos gozados e futuros aprovados, sem dupla contagem. Não aprovar mais dias que o saldo disponível. Validar novamente o saldo e a sobreposição na mesma transacção da aprovação, incluindo decisões concorrentes. O sistema não aceita períodos do mesmo funcionário sobrepostos a pedidos aprovados.

Proposta a validar por RH antes de implementar F2: bloquear submissão de períodos sobrepostos a pedidos do mesmo funcionário em submetido ou em análise. Rascunhos, rejeitados, retirados e cancelados não bloqueiam. Pedidos pendentes não reservam saldo. A aprovação revalida sempre datas e saldo. Esta proposta evita pedidos concorrentes duplicados e ainda não é uma decisão institucional confirmada.

Cancelamento de férias futuras aprovado por RH repõe apenas os dias ainda não gozados. Não devolver automaticamente dias gozados. Qualquer correcção administrativa desses dias exige competência RH, motivo e auditoria. Para períodos já iniciados, o tratamento de alteração ou cancelamento parcial e o momento de classificação como gozado precisam de detalhe RH em F2-01.

O funcionário vê os próprios pedidos e saldo. RH gere apenas pessoas e pedidos abrangidos pela competência atribuída. O calendário colectivo mostra apenas ausências aprovadas e pode apresentar funcionário, unidade e datas. Não apresenta motivos clínicos, observações confidenciais ou documentos individuais. Todas as decisões ficam auditadas.

Não implementar transporte para o ano seguinte, antecipação do ano seguinte, venda, compensação financeira ou acumulação plurianual. O direito é anual. O saldo remanescente em 31 de dezembro não tem perda ou transporte presumidos. Pedidos que cruzem anos exigem uma regra expressa RH antes de implementação, sem consumir automaticamente o direito de um ano pelo outro. A origem operacional dos saldos, eventual reconciliação e decisões pendentes estão em PEN-05/06/14.

F2 só começa quando os pré-requisitos do backlog estiverem prontos. Estas regras ficam especificadas agora e não constituem implementação do módulo.

### 11.6 Suporte

Estados iniciais: Novo, Em tratamento e Resolvido. Fecho e reabertura serão definidos com o serviço TI.

O solicitante recebe referência. Um técnico autorizado assume o ticket, responde e regista a solução. Caso existam notas internas, a interface deve distingui-las das respostas visíveis ao solicitante, incluindo nos emails e anexos.

### 11.7 Notificações

Notificações internas no portal acompanham F1. Emails adicionais dependem de configuração e decisão sobre conteúdo e canal.

Os eventos incluem atribuição de tarefa, submissão editorial, decisão editorial, publicação para destinatários, nova etapa de pedido e atualização de ticket. A entrega respeita a autorização atual e evita revelar dados sensíveis através do título ou assunto.

<a id="qualidade"></a>
## 12. Requisitos de qualidade e operação

### 12.1 Acesso e proteção de dados

- Exigir HTTPS em todos os ambientes acessíveis pela rede.
- Restringir o portal, a administração e os ficheiros à rede ARN ou VPN.
- Bloquear consulta anónima do conteúdo através de páginas, REST, AJAX, feeds, sitemaps, oEmbed e rotas equivalentes.
- Aplicar autorização no servidor por ação, objeto, audiência e estado.
- Validar entradas, escapar saídas e utilizar consultas preparadas.
- Tratar uploads, exportações e ligações diretas como operações protegidas.
- Não usar cache partilhada de páginas autenticadas. Caches de objetos exigem separação por autorização e invalidação correta.
- Guardar segredos fora do Git e limitar acesso aos ambientes.
- Definir retenção e arquivo por categoria documental e de registo.
- Registar ações relevantes sem guardar palavras-passe, tokens ou cópias integrais desnecessárias de dados sensíveis.

Os anteprojetos e políticas fornecidos orientam a análise de requisitos. Este README não afirma que a escolha de tecnologias, por si só, demonstra conformidade legal.

### 12.2 Usabilidade e acessibilidade

Interface inicial em português de Portugal, navegação consistente, linguagem clara e adaptação a telemóvel. O design deve usar a identidade visual da ARN sem comprometer contraste, leitura e utilização por teclado.

Definir estados de carregamento, ausência de dados, erro, sessão expirada e ação sem permissão. Preservar rascunhos quando um erro recuperável impede a submissão. Confirmar as ações com impacto antes de as executar.

### 12.3 Desempenho

Aplicar paginação em listas, filtros no servidor e processamento eficiente de imagens. Evitar carregar todos os funcionários, documentos ou eventos numa única resposta.

Definir volume de utilizadores, simultaneidade, documentos, tamanhos e metas de resposta em PEN-04. A aceitação de desempenho usa esse cenário validado e mede rede interna e VPN.

### 12.4 Ambientes e operação

Separar desenvolvimento, homologação e produção. A homologação deve representar as versões de produção e usar dados fictícios ou devidamente preparados.

Prever execução de tarefas agendadas pelo Agendador de Tarefas do Windows, monitorização de disponibilidade, erros de aplicação, armazenamento, autenticação e falhas de notificações.

Atualizações de WordPress, tema, plugins e dependências devem passar por homologação. Preparar restauro e procedimento de recuperação antes de uma alteração com impacto na base de dados.

Backups devem incluir base de dados, ficheiros privados e configuração recuperável. Manter cópia fora do servidor da aplicação sob controlo autorizado. Definir frequência, retenção, RPO e RTO com a DCSI antes da entrada em serviço.

Um teste de restauro precisa de confirmar permissões e acesso a anexos, além de confirmar a existência dos ficheiros.

### 12.5 Operação específica no Windows

O inventário de .205 deve confirmar os 32 GB reportados, medir RAM disponível, carga de CPU, armazenamento, latência de disco e consumo dos serviços existentes. Confirmar se o host é físico ou virtual e, quando aplicável, os recursos atribuídos. A memória total comunicada não demonstra capacidade livre nem reserva exclusiva para o portal.

Dimensionar WordPress, processos PHP, MySQL, documentos e crescimento a partir dessa medição. Definir limites de memória e concorrência compatíveis com as outras aplicações do servidor. Homologar com volume e utilização representativos antes de fixar os parâmetros de produção.

Como .205 está fora de arn.local, a DCSI valida administração local, actualizações, proteção do sistema, certificados e regras locais de firewall. Definir uma fonte de hora autorizada e testá-la sem depender da hierarquia AD. Hora correcta suporta TLS, expiração dos tokens e auditoria. [W26] [W27]

Configurar tarefas agendadas para executar trabalhos pendentes, manutenção e verificações de backup. Usar php.exe ou WP-CLI sob identidade restrita definida pela DCSI, local e com os acessos mínimos necessários, registar o resultado e impedir execuções sobrepostas. Quando o agendador assumir integralmente os eventos WordPress, ajustar WP-Cron para evitar duplicação.

Recolher logs IIS, erros PHP, eventos Windows, estado de MySQL e auditoria da aplicação. Definir rotação, retenção, alertas de espaço e monitorização da validade do certificado. Manter os registos fora da raiz publicável.

Usar homologação Windows/IIS para verificar caminhos, ACL NTFS, web.config, FastCGI, extensões, certificados e downloads. Um teste bem-sucedido num contentor de desenvolvimento não prova funcionamento equivalente no IIS.

O processo inicial de entrega será conduzido pela DCSI através de um pacote de versão revisto e identificado pelo commit. Os runners GitHub alojados externamente não têm, por pressuposto, acesso à rede 192.168.17.0/24. Não criar exposição pública do servidor .205 para permitir implantação. Uma futura automação exige agente ou canal interno aprovado e isolado da infraestrutura de domínio.

Antes de atualizar, preparar cópias consistentes da base de dados, ficheiros, configuração e ACL necessárias. Separar os dados persistentes do pacote de código. Repor a aplicação e a sua base de dados segundo o procedimento validado.

A identidade do processo IIS tem permissões locais sobre os dados da aplicação. Não pressupor conta de computador do domínio ou acesso remoto herdado. O Application Pool não deve usar credenciais AD. [W17]

Para backups remotos, definir um agente ou uma identidade técnica autorizada no destino, com credenciais protegidas e acesso limitado às cópias necessárias. Verificar as permissões no destino para a identidade escolhida, sem depender da pertença de .205 ao domínio. Testar a cópia e o restauro no contexto real dessa identidade.

A recuperação do portal incide sobre a aplicação em .205 e preserva os outros serviços eventualmente alojados nesse servidor. O controlador e DNS em .151 mantêm o seu procedimento de recuperação independente. Desfazer uma atualização WordPress não inclui reverter o controlador de domínio.

Windows Server 2019 encontra-se em suporte alargado, com término previsto em janeiro de 2029. Registar revisão do ciclo de vida e plano de atualização antes desse limite. [W23]

### 12.6 Condições do desenvolvimento local

Cada computador executa a sua instância WordPress e possui base de dados, ficheiros e configuração local próprios. Git sincroniza código e configuração sem segredos. Os conteúdos criados pelo editor WordPress ficam na base de dados local e não são transmitidos por commit ou push. Elementos do tema que devam ser partilhados precisam de exportação para templates, padrões ou theme.json versionados.

No wp-env, fixar a versão WordPress, PHP 8.4 e a versão MariaDB escolhida, e versionar a configuração e os lockfiles. O esquema documentado utiliza mariadbVersion. Não usar uma propriedade mysqlVersion inexistente no esquema consultado. A diferença para MySQL 8.4 de produção exige verificar consultas, collations e migrações na homologação. [W10]

Manter dados de desenvolvimento e de testes separados. Usar configuração de testes própria e seguir os comandos da versão wp-env efetivamente fixada. A configuração atual documenta testsEnvironment como opção descontinuada, pelo que não se deve pressupor a criação automática de um segundo ambiente. [W10]

Durante a fundação, contas locais fictícias podem ser usadas apenas na instância de desenvolvimento restrita ao computador. Nunca usar passwords institucionais, contas reais, segredos Google de produção ou dumps reais para esse fim. A configuração de desenvolvimento deve ser explícita e não permitir esse modo em homologação institucional ou produção. A ativação do acesso real depende de F1-02 e dos critérios OIDC, sessões e permissões das secções 5 e 7.

Verificar a exposição efetiva das portas do wp-env e do Docker. Uma URL localhost, por si só, não prova que os serviços estejam inacessíveis pela LAN. Restringir o acesso local e não publicar os contentores na rede ARN nem na Internet. Não executar reset, destroy, limpeza de volumes ou substituição de bases existentes sem decisão explícita do dono desses dados.

<a id="fases"></a>
## 13. Entregas por fases

| Fase | Entrega | Condição de conclusão |
| --- | --- | --- |
| F0 | Documentação, validações institucionais e desenho de execução | Decisões essenciais registadas, responsáveis designados e condições técnicas verificadas. |
| F1 | Núcleo do portal | Google Workspace, rede/VPN, CMS, unidades, funcionários, notícias, documentos, comunicados, tarefas, calendário básico, perfil, pesquisa, notificações e auditoria verificados. |
| F2 | Serviços administrativos e suporte | Pedidos, circuito de aprovação, férias, ausências e tickets completos com permissões e notificações. |
| F3 | Integrações e evolução | Apenas melhorias priorizadas, com origem dos dados, critérios de aceitação e responsável definidos. |

### 13.1 Sequência interna de F1

1. Preparar ambiente, identidade, rede, permissões, auditoria e estrutura institucional.
2. Disponibilizar cadastro, diretório, CMS e armazenamento privado.
3. Disponibilizar notícias, revisão editorial, comunicados e documentos.
4. Disponibilizar tarefas, calendário básico, painel e pesquisa dos módulos ativos.
5. Realizar validação com utilizadores de várias unidades, corrigir problemas e preparar operação.

As notificações e verificações de acesso acompanham cada módulo durante a sua construção. A auditoria não fica para depois da entrada em serviço.

F1 cobre 37 histórias originais. F2 acrescenta as 9 restantes: US-F08, US-F09, US-M04, US-RH02, US-RH04 e US-TI01 a US-TI04. Os eventos e índices das histórias transversais evoluem com os módulos.

F3 admite integração com sistemas institucionais, grupos transversais, automações e relatórios quando existir necessidade aprovada. Não existe calendário de entrega confirmado. Prazos e capacidade serão definidos em PEN-11.

<a id="codigo"></a>
## 14. Organização futura do código

A tabela seguinte descreve a organização prevista. A fundação local versiona o plugin, o tema, a configuração wp-env e as ferramentas indicadas abaixo. Já existem `src/Autoloader.php`, `src/Access/` com a política de portal privado e o modo fictício local, e `src/Organization/` com o modelo de unidade, a hierarquia sem ciclos e o catálogo documental. Os restantes módulos continuam por criar. ChatGPT Work/Codex deve conferir a árvore, main, branches, PRs e CI antes de acrescentar ficheiros.

| Caminho previsto | Finalidade |
| --- | --- |
| README.md | Referência funcional, técnica e de acompanhamento. |
| wp-content/themes/arn-intranet/ | Tema e identidade da interface. |
| wp-content/themes/arn-intranet/theme.json | Configuração de estilos e opções do editor. |
| wp-content/themes/arn-intranet/templates/ | Templates de páginas e conteúdos. |
| wp-content/themes/arn-intranet/parts/ | Partes comuns da interface. |
| wp-content/themes/arn-intranet/patterns/ | Padrões editoriais da ARN. |
| wp-content/themes/arn-intranet/assets/ | Recursos CSS, JavaScript e imagens próprias. |
| wp-content/plugins/arn-intranet-core/ | Plugin institucional. |
| wp-content/plugins/arn-intranet-core/src/Identity/ | Associação Google, estado de conta e sessões. |
| wp-content/plugins/arn-intranet-core/src/Access/ | Capabilities, âmbitos e políticas por objeto. |
| wp-content/plugins/arn-intranet-core/src/Organization/ | Unidades, funcionários e vínculos. |
| wp-content/plugins/arn-intranet-core/src/Editorial/ | Notícias, comunicados e decisões. |
| wp-content/plugins/arn-intranet-core/src/Documents/ | Documentos, versões e entrega privada. |
| wp-content/plugins/arn-intranet-core/src/Tasks/ | Tarefas e histórico. |
| wp-content/plugins/arn-intranet-core/src/Calendar/ | Eventos e visibilidade. |
| wp-content/plugins/arn-intranet-core/src/Requests/ | Pedidos, aprovações, férias e ausências. |
| wp-content/plugins/arn-intranet-core/src/Support/ | Tickets e respostas. |
| wp-content/plugins/arn-intranet-core/src/Notifications/ | Eventos e notificações por destinatário. |
| wp-content/plugins/arn-intranet-core/src/Audit/ | Registo e consulta de operações. |
| wp-content/plugins/arn-intranet-core/src/Infrastructure/ | Acesso a dados, migrações e serviços partilhados. |
| wp-content/plugins/arn-intranet-core/src/Rest/ | Endpoints e ligação aos serviços de domínio. |
| wp-content/plugins/arn-intranet-core/blocks/ | Blocos específicos do portal. |
| tests/ | Verificação das regras, integrações e fluxos. |
| .wp-env.json e configuração de testes | Ambiente local e ambiente de testes separado, com versões documentadas. |
| .nvmrc | Versão Node.js partilhada pelos dois computadores. |
| .editorconfig e .gitattributes | Formatação comum e tratamento consistente de finais de linha. |
| AGENTS.md | Instruções curtas para ChatGPT Work/Codex e outros agentes. Cursor opcional, com referência ao README. |
| composer.json e composer.lock | Dependências PHP e versões reproduzíveis. |
| package.json e package-lock.json | Ferramentas JavaScript e versões reproduzíveis. |
| .github/workflows/ | Verificações automáticas de código, quando implementadas. |
| .gitignore | Exclusão de segredos, dependências geradas, dados reais e ficheiros de execução. |

WordPress core e dependências de terceiros devem ser obtidos por um processo reproduzível. Ficheiros operacionais de funcionários, uploads, backups, exportações e configurações com segredos pertencem aos ambientes autorizados. A lista nominal incluída expressamente na secção 4.1 é uma referência documental e não um conjunto de dados de execução.

O padrão de estrutura do tema segue a documentação WordPress. A divisão interna do plugin é proposta específica deste projeto. [W12]

F1-02 acrescenta classes puras e adaptadores WordPress em `src/Identity/`. Persistência auditada, hooks Google, callback e sessão foram homologados no laboratório local. A homologação institucional continua pendente. Ver docs/google-login-local-f1-02.md.

<a id="tarefas"></a>
## 15. Responsabilidades e tarefas de desenvolvimento

### 15.1 Responsáveis por área

| Área | Responsabilidade | Designação |
| --- | --- | --- |
| Coordenação do projeto | Priorizar, resolver decisões e validar entregas | Atchutchi é o interlocutor atual. Confirmar papéis e substituição em PEN-11. |
| DCSI / infraestrutura | Servidor, rede, VPN, identidade, backups e operação | Responsável técnico a designar. |
| Backend WordPress | Plugin, modelos, autorização e processos | Distribuição entre Atchutchi e o segundo colaborador a registar por tarefa. |
| Frontend WordPress | Tema, componentes, formulários e acessibilidade | Distribuição entre Atchutchi e o segundo colaborador a registar por tarefa. |
| RH | Cadastro, campos, férias, ausências e regras administrativas | Ponto focal a designar. |
| Equipa editorial | Conteúdos, revisão, rejeição e publicação | Membros a designar. |
| Suporte TI | Processo de tickets e atendimento | Ponto focal a designar. |
| Responsáveis de unidade | Validar equipa, dados e necessidades da unidade | Pessoas a designar por unidade. |
| Validação / QA | Verificar critérios com contas e dados de teste | Responsável a designar. |

Uma área funcional não corresponde automaticamente a uma conta GitHub. A pessoa executante de cada tarefa deve ser identificada pelo nome ou utilizador GitHub antes de a tarefa passar a Em curso.

### 15.2 Estado e prioridades

Estados de tarefas: Por iniciar, Pronta, Em curso, Em revisão, Bloqueada, Adiada e Concluída.

Prioridades: P0 para condições de acesso e fundação, P1 para funções essenciais do módulo e P2 para evolução.

A documentação base fica registada nesta versão. As tarefas de implementação começam Por iniciar. As validações institucionais indicadas pelo responsável ficam Adiadas nesta etapa de planeamento técnico. Uma tarefa só fica Concluída com evidência e validação do resultado.

### 15.3 Backlog inicial

| ID | Tarefa | Responsável funcional / técnico | Dependência | Estado | Resultado verificável |
| --- | --- | --- | --- | --- | --- |
| F0-01 | Validar organograma, nomes, tipos e responsáveis | RH + coordenação | PEN-01 | Adiada | Lista de origem transcrita na secção 4.1. Falta validar os pontos da secção 4.6 para concluir a estrutura operacional de US-A03 e US-ARN-01. |
| F0-02 | Validar matriz de funções e delegações | Coordenação + DCSI + RH | F0-01 | Adiada | Quem atribui cada função e respetivo âmbito. |
| F0-03 | Inventariar e validar infraestrutura Windows, rede, DNS, HTTPS e capacidade | DCSI | PEN-03/04, DEC-11/13/14 | Por iniciar | Inventário e desenho de implantação aprovados para .205 fora de arn.local, com AD/DNS preservados em .151. |
| F0-04 | Definir cliente Google e procedimento de acessos | DCSI | PEN-02/03/10, DEC-15 | Por iniciar | Plano OIDC, vínculo das contas administrativas da secção 5.4, competências locais e saída de funcionários. |
| F0-05 | Validar melhorias e repositórios necessários a F1 | RH + editorial + TI | PEN-05/07/08, apenas âmbito F1 | Adiada | Decisões sobre cadastro, documentos, notícias, comunicados e tarefas. Pedidos e férias são detalhados em F2-01. |
| F0-06 | Detalhar dados, estados, migrações e contratos de F1 | Backend + QA | F0-01/02/05 | Por iniciar | Modelo de F1 e critérios de integridade revisáveis. Parcela local de estado, concessões e auditoria preparada em docs/persistencia-autorizacao-f1-03.md. Isso não conclui o modelo completo nem os pré-requisitos institucionais. O detalhe dos modelos administrativos pertence a F2. |
| F0-07 | Designar executantes, prioridades e datas | Coordenação | PEN-11 | Adiada | Responsável individual e prazo por tarefa selecionada. |
| F1-01 | Preparar desenvolvimento e homologação Windows/IIS | Albertina4264 iniciou. Atchutchi validou localmente e pediu revisão via Codex. Homologação: DCSI + desenvolvimento. | Arranque local: secções 16.4 a 16.6. Homologação: F0-03/04, INF-06. | Em curso | Fundação e lockfile integrados pelas PRs #2 e #3. Atchutchi confirmou localhost:8888/8890, 54 testes Node, 63 testes PHP com 284 asserções, lint/build e PHPCS 28/28. CI da PR #3 aprovada. Falta repetição no segundo PC e homologação Windows/IIS/MySQL 8.4, com servidor fora do domínio. |
| F1-02 | Homologar OIDC e associação de contas | Codex a pedido de Atchutchi prepara regras testáveis. Homologação: backend + DCSI. | Login e cliente Google locais autorizados e testados. Activação institucional depende de F1-01 e F0-04. | Em curso | OidcConfiguration, LocalIdentityLink e GoogleAccountPolicy com testes fictícios. Configuração externa, vínculo único por emissor/sub e conta activa autorizada. Facile 1.0.0 homologado localmente, callback Google real, vínculo persistente auditado e sessão WordPress. Logout, revogação e vias alternativas verificados. US-F01 ainda não está homologada institucionalmente. Evidência em docs/google-login-local-f1-02.md. |
| F1-03 | Implementar funções, âmbitos e estado de conta | Backend + QA | F0-02/06, F1-02, DEC-15 | Em curso | Fundação local: motor puro de decisão e delegação, ferramenta de ensaios fictícios e persistência de funções, âmbitos e estado com auditoria atómica e revisão concorrente. Evidência em docs/autorizacao-local-f1-03.md e docs/persistencia-autorizacao-f1-03.md. Sem concessões em contas reais. A matriz em docs/matriz-permissoes-f1-03-proposta.md mantém validação institucional pendente. Ligação do estado às sessões fictícias preparada no laboratório, com invalidação por revisão e ferramenta local de suspensão. Evidência em docs/sessoes-estado-local-f1-03.md. Gestão de concessões fictícias pelo painel, com criação, substituição e revogação auditadas, CAS e invalidação de sessões, verificada em docs/gestao-concessoes-local-f1-03.md. Leitura local de registos sintéticos por âmbito, com lista filtrada e detalhe protegido, descrita em docs/registos-locais-f1-03.md. Delegações persistentes e autorização institucional por objecto continuam dependentes de F0-02/06. Resultado futuro: US-A01/02, US-S03 e US-ARN-02/03 verificados e contas administrativas com permissões explícitas e auditadas. |
| F1-04 | Implementar auditoria e eventos de notificação | Codex a pedido de Atchutchi, backend + QA. | Fundação local F1-03 disponível. Activação institucional depende da sua homologação e dos contratos dos módulos. | Em curso | Consulta administrativa protegida da auditoria de acessos existente. Parcela local de notificações internas a partir de eventos confirmados, com destinatário protegido e recibos de leitura idempotentes. Evidência em docs/notificacoes-locais-f1-04.md. US-A04, US-S01 e US-ARN-10 institucionais, restantes módulos e canais externos continuam pendentes. |
| F1-05 | Implementar unidades, cadastro e importação | Backend + RH | F0-01, F1-03/04 | Em curso | US-A03, US-RH01 e importação validada. Preparação já feita na fundação: modelo `Organization\Unit` com identificador estável, `UnitHierarchy` que recusa ciclos e identificadores repetidos, e `DocumentaryCatalog` com as 37 entradas da secção 4.1, sem pessoas e sem relações confirmadas. Persistência auditada de unidades fictícias e gestão local em docs/unidades-locais-f1-05.md. Contratos de cadastro/importação em docs/contratos-cadastro-importacao-f1-05.md. Pré-validação de cenários CSV sintéticos, propostas e auditoria apenas em memória em docs/validacao-sintetica-f1-05.md, sem escrita ou importação executável. Cadastro de pessoas, importador executável e validação institucional continuam pendentes. |
| F1-06 | Criar tema, navegação e componentes CMS | Frontend + editorial | F1-01/03 | Em curso | Preparação visual local: logótipo, favicon, tipografia, cabeçalho e navegação CMS. Painel administrativo com marca ARN, atalhos por capacidades nativas e esquema de cores opcional. Playwright e editor validados. Páginas funcionais e navegação por competências institucionais continuam condicionadas por F1-03. Evidência em docs/tema-institucional-f1-06.md e docs/painel-administrativo-f1-06.md. Portal editorial local com Início, Notícias, Documentos, Regulamentos e Serviços em docs/portal-editorial-local-f1-06.md. Anexos reais reservados à revisão administrativa, sem publicação institucional ou conclusão dos módulos editoriais. |
| F1-07 | Implementar diretório e perfil | Codex/Atchutchi implementou e aceitou localmente. Albertina verifica segundo PC na Issue #28. RH valida o âmbito institucional. | F1-05/06 | Em curso institucionalmente | Código integrado pelas PRs #27/#25 depois de revisão e 11 checks no head exacto. People activo com três perfis fictícios neste PC. Segundo PC, zoom nativo e decisão RH continuam pendentes. Ver QA para wp_options e coordenação para intermitência CI. |
| F1-08 | Implementar armazenamento e entrega privada | Backend + DCSI + QA | F0-03/06, F1-03/04 | Em curso | Núcleo local de binários imutáveis, referências por objecto/versão/variante e registo auditado atómico. Entrega local sintética verificada com sessão, âmbito e Playwright, sem migração dos anexos reais. Evidência e limites em docs/ficheiros-privados-locais-f1-08.md. Verificação integral de US-ARN-05, media nativa, política de admissão e homologação IIS/MySQL continuam pendentes. |
| F1-09 | Implementar catálogo documental e versões | Backend + frontend + RH | F1-08, F0-05 | Em curso | Parcela local fictícia em docs/catalogo-documental-local-f1-09.md. Classificação, destinatários e homologação institucional pendentes. |
| F1-10 | Implementar criação, galeria e submissão de notícias | Backend + frontend | F1-04/06/08 | Em curso | Parcela local fictícia: rascunhos, versões, destaque, galeria, anexos privados, pré-visualização, envio, fila, avisos e recibos. Seguir docs/noticias-locais-f1-10.md e [evidência de QA local](docs/qa-noticias-local-f1-10.md). Homologação institucional, antimalware, media nativa e IIS/MySQL pendentes. |
| F1-11 | Implementar revisão, publicação e auditoria editorial | Backend + editorial + QA | F1-10, F0-05 | Em curso | Parcela fictícia activada, ensaiada em Playwright e integrada pela PR #24. Revisões e CI concluídos para a entrega local. Homologação institucional e dependências comuns de media/infraestrutura pendentes. Ver QA local e limites gráficos. |
| F1-12 | Implementar comunicados e gestão de eventos | Backend + frontend + responsáveis | F1-04/06, F0-05 | Parcela local implementada e testada. Institucional em curso | Cinco tarefas, revisão independente, correcções e aceitação WordPress/SQL/Playwright dos 32 critérios executadas. PHP800/3612/quatro omissões anteriores, SQL70/70/58/52 e Node55. Seis fixtures e duas páginas activas neste PC. Ver QA/decisões F1-12. PEN-07, escala real e IIS/MySQL pendentes. US-M05 e US-F07. |
| F1-13 | Implementar tarefas e acompanhamento de equipa | Albertina4264 prepara especificação na Issue #29. Implementação posterior pelo backend/frontend. | F1-04/05/06 | Preparação documental atribuída | Propor campos, estados, competência, continuidade, reatribuição, CAS, auditoria e notificações com critérios rastreáveis. Sem implementação ou aprovação institucional nesta tarefa. |
| F1-14 | Integrar painel e pesquisa básica | Backend + frontend | F1-07/09/11/12/13 | Em curso local, parcial | Painel e pesquisa básica autorizada em branches separadas. Tarefas, restantes módulos e homologação pendentes. |
| F1-15 | Completar administração e parâmetros do CMS | Backend + DCSI | F1-05/09/11/12/13 | Em curso local, parcial | Painel com gestão funcional autorizada e ferramentas locais consolidadas. Parâmetros críticos, módulos restantes e homologação pendentes. Ver docs/painel-gestao-autorizada-local-f1-15.md. |
| F1-16 | Validar segurança, acessibilidade e restauro | QA + DCSI + utilizadores piloto | F1-14/15, INF-09, PEN-08/09/10 | Por iniciar | Critérios de F1 aprovados e evidência registada. |
| F1-17 | Formar responsáveis e disponibilizar F1 | Coordenação + DCSI + RH + editorial | F1-16, INF-10, F0-07 | Por iniciar | Operação, suporte e aceitação funcional definidos. |
| F2-01 | Detalhar tipos de pedido, substituição e modelos administrativos | RH + responsáveis + backend | PEN-05/06/14, âmbito administrativo e pré-requisitos F1 | Por iniciar | Modelar 22 dias úteis anuais repartíveis, pedidos prévios e decisão final RH. Validar proposta de sobreposição pendente, calendário, origem de saldo, passagem de ano, cancelamento parcial e datas de gozo. Definir estados, histórico, movimentos e transacções. |
| F2-02 | Implementar pedidos e decisões | Backend + frontend + QA | F2-01, F1-04/08 | Por iniciar | US-F08, US-M04 e US-ARN-06. |
| F2-03 | Implementar férias e ausências | Backend + RH + QA | F2-02, F1-03/04/05/08, PEN-05/06/14 | Por iniciar | US-RH02/04 e US-ARN-07, 22 dias úteis, vários pedidos, saldo separado, autorização por titular e competência RH, reserva e reposição auditadas. Testes da secção 17.2 e calendário colectivo sem motivos confidenciais. |
| F2-04 | Implementar suporte e tickets | Backend + frontend + TI | F1-04/08, processo TI validado | Por iniciar | US-F09 e US-TI01 a US-TI04. |
| F2-05 | Integrar painel, pesquisa e notificações de F2 | Desenvolvimento + QA | F2-03/04 | Por iniciar | Histórias transversais cobrem novos módulos. |
| F2-06 | Validar e disponibilizar serviços | RH + TI + DCSI + QA | F2-05 | Por iniciar | Processos completos e privacidade aprovados. |
| F3-01 | Priorizar integrações institucionais | Coordenação + donos dos dados | F1/F2 conforme necessidade | Por iniciar | Caso de uso, fonte oficial e permissões definidos. |
| F3-02 | Implementar melhoria selecionada | Responsáveis da melhoria | F3-01 | Por iniciar | História específica e critérios aprovados para a entrega. |

Cada tarefa deve referenciar os códigos das histórias aplicáveis e atualizar a evidência quando concluída. A tabela é o backlog e não cria automaticamente Issues ou atribuições. As atribuições confirmadas às Issues #28/#29 são a excepção explicitamente registada abaixo e em 15.3.3.

A [matriz de auditoria F1 de 13 de outubro](docs/auditoria-f1-2026-10-13.md) e o [roteiro de 14 de outubro](docs/proximas-fases-f1-2026-10-14.md) conservam o histórico. Para trabalho novo prevalecem o estado consolidado abaixo e o main com F1-07 já integrado pela PR #25. A branch documental da PR #26 está preservada. A implementação usa feat/f1-12-comunicados-calendario e as atribuições da colaboradora mantêm-se nas Issues #28/#29. A passagem histórica não atribui acessos institucionais ou autorização de implantação.

<a id="plano-conclusao"></a>
### 15.3.1 Plano mestre de conclusão para agentes

Este bloco é a orientação operacional principal para ChatGPT Work, Codex, Cursor ou outro agente que continue o projeto. O objectivo é concluir a plataforma WordPress completa, mantendo a arquitectura existente e sem recomeçar módulos já integrados.

#### Estado consolidado em 16 de outubro de 2026

| Frente | Estado integrado ou branch explicitamente indicada | O que ainda falta para fechar |
| --- | --- | --- |
| F1-01 Ambiente | Parcela local funcional, lockfiles, wp-env, CI e regressões existentes | Segundo PC e homologação representativa Windows Server 2019, IIS, PHP 8.4 e MySQL 8.4 |
| F1-02 Identidade Google | OIDC local, configuração externa, vínculo persistente auditado, callback e sessão testados | Activação institucional, HTTPS, LAN/VPN, cliente/segredos institucionais e procedimento DCSI |
| F1-03 Autorização | Motor local, estado, sessões, concessões, auditoria e leitura por objecto implementados | Validar matriz institucional, delegações reais e aplicar os contratos aos módulos finais |
| F1-04 Auditoria/notificações | Auditoria administrativa e notificações internas locais com recibos | Ligar eventos de todos os módulos, regras institucionais e eventual canal externo |
| F1-05 Organização/cadastro | Unidades fictícias persistentes, 37 entradas documentais e pré-validação sintética | Cadastro de pessoas, importador executável, validação RH, emails e hierarquia definitiva |
| F1-06 Tema/CMS | Tema ARN, favicon, fontes, painel e portal editorial local. PR #44 liga Serviços e menu automático. PR #45 acrescenta menus guardados. A branch feat/inicio-acessos-autorizados liga os atalhos ao Início. Estas parcelas continuam sem merge | Rever as entregas separadas e a CI dos respectivos heads. Completar navegação funcional à medida que os módulos restantes entram e validar institucionalmente |
| F1-07 Directório/perfil | Integrado pelas PRs #27/#25, instalação e aceitação fictícias neste PC | Issue #28 para segundo PC/zoom/acessibilidade. RH/infraestrutura permanecem pendentes. Ver QA para wp_options e coordenação para diagnóstico CI |
| F1-08 Ficheiros privados | Núcleo imutável, entrega privada, auditoria e ensaios locais | Media nativa e derivados, reconciliação, antimalware/política de admissão e homologação IIS |
| F1-09 Documentos | Catálogo local, versões, CMS, publicação corrente e download autorizado | Classificação/destinatários institucionais, origem oficial, media nativa, antimalware e homologação |
| F1-10 Notícias, autoria | Rascunho, versões, destaque, galeria, anexos, pré-visualização, submissão, fila e avisos | Homologação institucional e dependências técnicas comuns de ficheiros |
| F1-11 Notícias, decisão | Integrado em main pela PR #24. Esquema 2 activado e circuito fictício verificado no laboratório de Atchutchi | Validar competências/audiências institucionais, conservação, antimalware, media nativa e homologação IIS/MySQL. Limites gráficos no relatório de QA |
| F1-12 Comunicados/calendário | Implementados, revistos e activos apenas no laboratório com seis fixtures. Aceitação dos 32 critérios registada por camada | Integrar apenas com CI verde no SHA entregue. Conservar PEN-07, zoom nativo/segundo PC, escala e homologação institucional pendentes. Seguir docs/qa-comunicados-calendario-local-f1-12.md e docs/decisoes-implementacao-f1-12.md |
| F1-13 Tarefas | Proposta recebida na PR35, conciliação #37 sem entrega publicada e preparação técnica independente documentada | Corrigir os contra-exemplos transaccionais da PR39, receber e rever a conciliação antes do plano executável. Os 32 critérios continuam Por executar. Não há módulo ou Playwright F1-13 |
| F1-14 Dashboard/pesquisa | Em curso local, branches separadas | Início com acessos, comunicados/eventos e pesquisa autorizada dos módulos locais. Tarefas, restantes módulos e homologação por concluir |
| F1-15 Administração CMS | Gestão funcional autorizada e ferramentas locais reunidas no Painel ARN, entrega parcial em branch própria | Completar parâmetros críticos auditados e restantes operações conforme pré-requisitos. Ver docs/painel-gestao-autorizada-local-f1-15.md |
| F1-16 QA/segurança/restauro | Parcial por CI, unitários, integrações e Playwright locais | Matriz completa de segurança por objecto, acessibilidade, carga, backup/restauro e homologação representativa |
| F1-17 Operação/formação | Por iniciar | Manual operacional, formação, aceitação, pacote de release e disponibilização controlada |
| F2 Pedidos/férias/tickets | Por iniciar | Implementar F2-01 a F2-06 integralmente, incluindo férias e suporte |
| Infraestrutura | Planeada, não executada neste repositório | INF-01 a INF-10, DNS/HTTPS/VPN, IIS/PHP/MySQL, backups, logs, monitorização e recuperação |
| F3 Integrações | Por iniciar | Só depois do núcleo completo, por prioridade e fonte oficial aprovada |

#### Ordem obrigatória de continuação

O agente deve seguir esta sequência, salvo impedimento técnico demonstrável. Não voltar a implementar o que já está em `main`.

1. Conferir main, PRs, branch de trabalho e ambiente do próprio PC. Executar as regressões adequadas à tarefa. O estado da base local de outro colaborador não é um baseline deste PC.
2. Conservar F1-11 integrado e a sua evidência. Não repetir a migração inicial do laboratório de Atchutchi nem reimplementar autoria/decisão/publicação. A homologação institucional continua pendente.
3. Conservar F1-07 integrado pelas PRs #27/#25 e a evidência local. Não repetir a preparação de B ou do elenco. Albertina valida o segundo PC na Issue #28, sem bloquear a preparação independente seguinte. A proposta F1-13 da Issue #29 é documental, não uma antecipação da implementação.
4. F1-12, comunicados e calendário.
5. F1-13, tarefas e acompanhamento de equipa.
6. F1-14, dashboard integrado e pesquisa com autorização.
7. F1-15, consolidar administração e parâmetros do CMS.
8. Completar as lacunas técnicas comuns de F1-08/F1-09/F1-10, sobretudo media nativa, derivados, reconciliação e política de admissão.
9. Implementar F2-01 e F2-02, motor de pedidos e decisões.
10. Implementar F2-03, férias e ausências, respeitando as decisões confirmadas e PEN-14.
11. Implementar F2-04, suporte e tickets.
12. F2-05 e F2-06, integrar painel/pesquisa/notificações e validar os serviços administrativos.
13. F1-16, executar a matriz final de segurança, acessibilidade, desempenho e recuperação sobre o conjunto completo.
14. INF-01 a INF-09, preparar e homologar a infraestrutura Windows/IIS/PHP/MySQL, HTTPS, LAN/VPN, backups, logs e monitorização.
15. F1-17 e INF-10, formar, criar release identificada por commit, instalar de forma controlada e executar aceitação final.
16. F3 apenas depois do portal completo, salvo integração externa explicitamente necessária para fechar um requisito já aprovado.

Para o objectivo actual, a primeira disponibilização final deve incluir F1 e F2. Não considerar o site concluído deixando pedidos, férias ou suporte para uma entrega futura.

#### Regra de autonomia, para evitar interrupções desnecessárias

O agente não deve parar para pedir confirmação sobre detalhes já definidos no README, nas user stories ou no código integrado. Quando uma decisão institucional real continuar pendente, deve:

1. Implementar o mecanismo técnico de forma configurável e segura.
2. Usar apenas fixtures e valores fictícios no laboratório.
3. Não inventar nomes, emails, hierarquias, saldos, aprovadores, políticas ou dados reais.
4. Registar claramente a activação institucional pendente.
5. Continuar imediatamente para a próxima tarefa independente.

Só deve interromper e pedir intervenção do responsável quando for indispensável um segredo, acesso externo, alteração de produção, decisão institucional que muda o comportamento de negócio, dado real que não pode ser substituído por fixture, aquisição/licença, ou acção irreversível.

#### Definição de concluído para cada módulo

Uma tarefa só passa a Concluída quando, no mínimo:

- Os critérios das user stories aplicáveis estão implementados.
- A autorização é validada no servidor por função, âmbito, objecto e estado.
- Operações relevantes geram auditoria coerente.
- Notificações são transaccionais quando o módulo as exige.
- Inputs, uploads, nonces, concorrência e erros têm tratamento explícito.
- A interface funciona em português, teclado e ecrãs pequenos.
- Existem testes unitários para regras, integração para persistência e browser para o fluxo crítico.
- `npm test`, `npm run lint`, `npm run build`, PHPUnit e PHPCS passam.
- A CI passa no SHA exacto da branch.
- A documentação do módulo e o estado no README são actualizados.
- A alteração entra por PR revista. Nunca usar push directo em `main`.
- Limites locais versus homologação institucional ficam explícitos.

#### Protocolo de sessão do agente

No início de cada sessão:

~~~bash
git fetch origin --prune
git switch main
git pull --ff-only origin main
npm ci
~~~

Ler `AGENTS.md`, este plano e a documentação específica do módulo. Escolher a primeira tarefa incompleta da ordem obrigatória que não esteja activa noutra branch ou PR.

Para cada avanço: criar branch por tarefa, implementar uma unidade coerente, testar, rever diff, fazer commit, push, abrir/actualizar PR e pedir/verificar revisão. Integrar apenas com CI verde no SHA exacto. Após o merge, actualizar `main` e continuar automaticamente para a tarefa seguinte.

Não fazer force push, não apagar branches ou dados de outro colaborador para resolver divergências, não alterar a stack WordPress/PHP, não introduzir frontend separado e não fazer deploy durante desenvolvimento local.

#### Critério de conclusão global do site

O portal só deve ser declarado concluído quando todas as histórias F1 e F2 estiverem cobertas, os módulos estiverem integrados no mesmo WordPress, a matriz de segurança e privacidade tiver passado, o restauro tiver sido testado, a autenticação Google institucional estiver homologada, a infraestrutura Windows/IIS/MySQL estiver validada, os dados reais tiverem sido importados por procedimento aprovado, os utilizadores piloto tiverem aceite os fluxos principais e existir release/rollback documentados.

### 15.3.2 Passagem histórica e execução assumida pelo responsável

Em 15 de outubro foi preparada ALB-F1-07-QA para Albertina: fechar a aceitação local do directório, perfil e equipa e devolver uma PR. Em 16 de outubro o responsável pediu que Codex/Atchutchi executasse essa tarefa neste PC. O código existente foi revisto, corrigido e ensaiado. A [entrega actual](docs/entrega-f1-07-2026-10-16.md) substitui a execução da passagem antiga. Não reinicia F1-01 nem inclui implementação F1-12 ou F2.

A consulta histórica da branch `chore/albertina4264-F1-01-fundacao`, de 15 de outubro, registou zero commits exclusivos e 116 commits atrás de main. Esta tarefa não mudou essa branch nem contactou a colaboradora. O [guião](docs/passagem-albertina-f1-07-2026-10-15.md) e o [prompt](docs/prompt-albertina-f1-07-2026-10-15.txt) ficam como histórico, sem autorização para repetir a tarefa em duplicado. A tabela conserva o contrato original de entrega. Para o resultado efectivamente executado prevalecem a entrega actual e a QA.

| Parcela da entrega | Resultado exigido |
| --- | --- |
| Base e ambiente | Registar SHAs, ferramentas e ambiente próprio. Dados, credenciais e estado SQL do PC de Atchutchi não acompanham o Git. Preparar só laboratório local com fixtures. |
| Revisão e instalação | Fechar a revisão transversal e resolver achados. Fazer baseline, pré-requisitos F1-03/F1-05, instalação People explícita e repetição sem alterações indevidas. Não adoptar estados parciais. |
| Aceitação Playwright | Logins nativos das três fixtures, directório, ficha, próprio, equipa A, filtros, CAS, nonce, extras, suspensão/reactivação, sessão antiga, REST, quatro larguras, teclado, marca/fontes e gate desligado com controlos positivos. |
| Preservação e testes | Comprovar autorização/sessões inalteradas pela edição de contacto e preservação dos dados anteriores. Executar testes afectados, integrações exclusivas e CI no SHA entregue. Resultados development negativos não substituem o positivo local. |
| Devolução | Commits e push apenas da branch dela. PR contra `feat/f1-07-directory-profile` enquanto a PR #25 estiver aberta. Entregar SHA, URL, comandos, resultados, limites, ficheiros de evidência sem segredos e próximos passos de Atchutchi. Não fazer merge ou deploy nesta tarefa. |

A entrega #27 já foi recebida na branch F1-07 e a PR #25 integrada em main, depois de revisão e CI exacto. Esta integração ocorreu na tarefa de continuidade posterior ao guião original, sem deploy. Segue a revisão da proposta F1-12 da PR #26. As novas atribuições à colaboradora estão em 15.3.3 e não repetem a implementação F1-07.

As decisões institucionais de F0, importação real, media nativa e antimalware, comunicados/eventos, tarefas, dashboard/pesquisa, administração final, pedidos/férias/tickets F2, segurança/restauro, formação e INF-01 a INF-10 permanecem no plano mestre. O fecho de ALB-F1-07-QA não conclui essas frentes nem autoriza publicação dos anexos reais.

### 15.3.3 Novas tarefas atribuídas a Albertina4264

As Issues [#28](https://github.com/atchutchi/plataforma-interna-arn/issues/28) e [#29](https://github.com/atchutchi/plataforma-interna-arn/issues/29) foram atribuídas à conta GitHub canónica confirmada Albertina4264. A primeira é prioritária: validação independente no segundo PC, zoom/acessibilidade, isolamento e baselines. A segunda prepara a especificação e critérios F1-13, apenas em documentação, enquanto a continuação central trata F1-12.

Usar branches novas qa/albertina-f1-16-segundo-pc e docs/albertina-f1-13-tarefas a partir do main actualizado, depois de preservar qualquer trabalho local ainda não publicado. Não forçar a branch antiga nem copiar segredos, bases ou originais entre PCs. A consulta remota não prova ausência de trabalho no disco da colaboradora.

Cada tarefa devolve uma PR própria contra main, ligada à respectiva Issue. A colaboradora não altera README/AGENTS, branches de Atchutchi ou produção. Os ficheiros previstos não colidem com F1-12. Seguir o [guião](docs/tarefas-albertina-2026-10-16.md) e o [prompt](docs/prompt-albertina-2026-10-16.txt). Não há prazo de entrega inventado nem aprovação institucional nestas atribuições.

<a id="plano-infraestrutura"></a>
### 15.4 Plano técnico de preparação no Windows

As tarefas INF detalham a preparação e verificação da infraestrutura dentro do mesmo projeto. Não constituem instalações já executadas. As pendências institucionais adiadas não impedem documentar ou avaliar tecnicamente este plano.

Executar primeiro a preparação e as verificações INF-04 a INF-08 em homologação Windows/IIS/MySQL representativa de um servidor fora de arn.local, com configuração local explícita. Usar nome DNS, cliente OAuth, segredos e dados próprios desse ambiente. A instalação de produção em .205 recebe a configuração validada através do procedimento de entrega. A homologação não deve reutilizar o endereço de produção nem interferir nos serviços do domínio.

| ID | Tarefa técnica | Responsável | Dependência | Estado | Evidência esperada |
| --- | --- | --- | --- | --- | --- |
| INF-01 | Inventariar funções, recursos, volumes, serviços, bindings e backups de .205 | DCSI | DEC-11/13/14 | Por iniciar | IP, Windows 1809/build 17763/64 bits e ausência de associação a arn.local conferidos no host quando autorizado. Hyper-V instalado e WSL ausente registados. Dados do anexo, natureza física ou virtual, capacidade e serviços registados. |
| INF-02 | Validar coexistência e administração do portal em .205 | DCSI | INF-01, DEC-14 | Por iniciar | Instalação planeada fora de arn.local, com administração local, serviços e portas compatíveis, sem GPO, Kerberos, LDAP ou conta de computador do domínio. |
| INF-03 | Dimensionar aplicação, MySQL, ficheiros e crescimento em .205 | DCSI | INF-02, PEN-04 | Por iniciar | Recursos disponíveis, limites, capacidade de disco e cenário de carga definidos. |
| INF-04 | Preparar nome interno, DNS e alcance pela VPN | DCSI | INF-02, PEN-03 | Por iniciar | DNS institucional resolve o ambiente em teste. Plano de produção aponta o nome do portal para .205. LAN, VPN e restantes nomes arn.gw preservados. |
| INF-05 | Obter certificado e preparar confiança e renovação | DCSI | INF-04 | Por iniciar | Certificado e cadeia disponíveis, distribuição de confiança prevista e procedimento de renovação preparado. |
| INF-06 | Preparar IIS, FastCGI, PHP, MySQL, pastas, ACL e binding HTTPS | DCSI + backend | INF-03/05 | Por iniciar | Serviços nativos no servidor fora do domínio compatíveis com a configuração local aprovada, identidade local do pool, base local, armazenamento privado e HTTPS inicialmente verificado. |
| INF-07 | Verificar identidade Google no percurso LAN e VPN | DCSI + backend | INF-06, F1-02 | Por iniciar | Callback real registado, login concluído nas duas redes e nenhuma publicação pública necessária. |
| INF-08 | Preparar tarefas agendadas, logs, alertas e backups | DCSI | INF-06, PEN-09 | Por iniciar | Execução por identidades autorizadas, falhas observáveis e cópias consistentes fora do servidor. Permissões de backup remoto verificadas no destino. |
| INF-09 | Homologar rede, IIS, anexos, carga e restauro | DCSI + QA | INF-07/08, F1-14/15 | Por iniciar | Testes da secção 17.4 com evidência, incluindo instalação do certificado renovado no binding IIS. |
| INF-10 | Preparar pacote de entrega e recuperação da aplicação | DCSI + desenvolvimento | F1-16 | Por iniciar | Versão identificada por commit, configuração protegida e recuperação sem reversão indevida do AD. |

Nenhuma tarefa INF exige mapeamento de OUs, sincronização AD ou associação de .205 ao domínio. Integrações AD futuras exigem decisão expressa. Responsáveis institucionais continuam por validar.

<a id="colaboracao"></a>
## 16. Forma de colaboração no repositório

### 16.1 Regras de trabalho em dois computadores

Cada pessoa usa a sua conta GitHub, identidade Git, clone local e configuração. O segundo colaborador precisa de acesso de escrita ao repositório e de aceitar o convite enviado pelo proprietário através das definições GitHub. A conta Google do portal não dá acesso automático ao GitHub. [W38]

main representa a versão revista. Cada tarefa tem uma branch curta identificável, por exemplo chore/atchutchi-F1-01-fundacao ou feat/colaborador-F1-06-tema. Evitar branches permanentes por pessoa e não partilhar a mesma pasta de trabalho por rede, OneDrive ou outro sincronizador de ficheiros.

Antes de começar, ler o README, conferir as dependências, registar o executante e consultar trabalho aberto para não duplicar a tarefa. Fazer pull de main com avanço direto e criar a branch a partir da versão atual. Cada avanço coerente e verificado deve produzir um commit e um push da branch de trabalho. A proposta segue o GitHub flow. [W36] [W38]

Antes de atualizar uma branch já em curso, verificar alterações locais e o upstream. Com a árvore limpa, fazer fetch, incorporar o upstream da própria branch por avanço direto quando aplicável e integrar origin/main através de merge. Uma divergência exige análise. Não resolver usando force push, reset --hard, limpeza de ficheiros ou descarte automático do trabalho de outra pessoa.

Abrir pull request para main, com revisão do outro colaborador. O push permite partilhar o avanço mesmo antes de a tarefa estar completa. Uma alteração incompleta pode ficar numa PR em rascunho, com limitações e verificações em falta claramente registadas. Não declarar a tarefa concluída por existir um commit ou uma PR. [W38]

Recomenda-se configurar proteção de main com revisão e verificações exigidas, sem permitir force push. A regra só será considerada ativa depois de configurada e conferida nas definições GitHub. Depois de integrar uma PR, ambos atualizam main antes da próxima tarefa. [W38]

### 16.2 Informação mínima de uma tarefa

| Campo | Conteúdo esperado |
| --- | --- |
| Identificador | Código deste backlog ou Issue que o substitua. |
| Objetivo | Resultado observável para o utilizador ou operação. |
| Histórias | Códigos do anexo e complementos relevantes. |
| Executante | Pessoa e utilizador GitHub responsáveis pela entrega. |
| Revisor | Colaborador que revê o código e responsável pela validação funcional quando aplicável. |
| Dependências | Tarefas e decisões necessárias, distinguindo trabalho local e ativação institucional. |
| Critérios | Comportamentos verificáveis e restrições de acesso. |
| Estado | Um dos estados definidos na secção 15. |
| Evidência | Branch, commit, pull request, verificações executadas e limitações. |
| Continuação | Próximo passo concreto, incluindo o que outra pessoa precisa de saber. |

### 16.3 Atualização da documentação e dos dados

Alterações de âmbito, autenticação, acesso, entidades ou processos devem atualizar este README na mesma alteração que as introduz. Atualizar apenas as secções necessárias à tarefa, para reduzir conflitos entre os dois colaboradores.

Quando forem criadas Issues ou um quadro GitHub Projects, associar os seus links aos IDs do backlog e definir um único local de referência do estado. O acompanhamento do desenvolvimento no GitHub e as tarefas profissionais do futuro portal são processos diferentes.

O repositório está público na data desta versão. A lista nominal da secção 4.1 foi incluída por pedido expresso do responsável. Os exemplos, seeds e testes usam dados fictícios. Documentos de origem, datasets operacionais de funcionários, contactos privados, credenciais, tokens, bases de dados, uploads e ficheiros de produção permanecem nos sistemas aprovados pela ARN.

Versionar código, configurações sem segredos, migrações, fixtures fictícias e lockfiles. Excluir dependências geradas, como node_modules e vendor, instalações WordPress obtidas automaticamente, caches, logs, backups e configurações locais privadas. Um ficheiro de exemplo deve conter apenas valores demonstrativos.

Git não sincroniza a base de dados WordPress, utilizadores, conteúdos editoriais ou anexos. Cada PC mantém os seus dados. A preparação comum usa scripts e migrações idempotentes, que podem repetir-se sem duplicar ou destruir dados. A transferência para homologação e produção é uma tarefa própria, com revisão, cópias e procedimento de recuperação.

<a id="instalacao-pcs"></a>
### 16.4 Instalações nos computadores

Percurso dos PCs Windows: ChatGPT Work/Codex com GitHub e ambiente local separado em Ubuntu 24.04 LTS/WSL 2 e Docker Desktop. Cursor é opcional. Executar Git, Node e os comandos do projeto dentro do Ubuntu. Recomenda-se Windows 11 atualizado e suportado, virtualização ativa e SSD. Docker exige pelo menos 8 GB de RAM no percurso documentado. Para trabalhar com editor, navegador e contentores, recomenda-se 16 GB ou mais como margem prática, sujeita aos recursos dos PCs. [W32] [W33]

| Instalação | Onde | Necessidade | Ligação oficial |
| --- | --- | --- | --- |
| Cursor | Windows | Editor opcional. Não é necessário para desenvolver o projecto. | [Download](https://cursor.com/download) |
| WSL 2 | Windows | Executar o ambiente Linux. Manter a versão atualizada. | [Instalar WSL](https://learn.microsoft.com/en-us/windows/wsl/install) |
| Ubuntu 24.04 LTS | WSL 2 | Mesma distribuição nos dois PCs. | [Guia Ubuntu](https://ubuntu.com/wsl/docs/stable/howto/install-ubuntu-wsl2/) |
| Docker Desktop | Windows | Runtime do wp-env, com backend WSL 2 e integração Ubuntu. Confirmar licença institucional. | [Instalação](https://docs.docker.com/desktop/setup/install/windows-install/) e [WSL](https://docs.docker.com/desktop/features/wsl/) |
| Git | Ubuntu | Clone, pull, commit, push e branches. | [Instalação Linux](https://git-scm.com/install/linux) |
| Node.js 24 LTS e npm | Ubuntu, através de nvm | Ferramentas WordPress e construção de recursos. Fixar a versão partilhada em .nvmrc no arranque. | [Node.js](https://nodejs.org/en/download) e [Node no WSL](https://learn.microsoft.com/en-us/windows/dev-environment/javascript/nodejs-on-wsl) |
| GitHub CLI, gh | Ubuntu | Recomendado para autenticar Git e abrir PRs pelo terminal. O GitHub web continua disponível. | [Instalação Linux](https://github.com/cli/cli/blob/trunk/docs/install_linux.md) |
| Navegador atualizado | Windows | Testes da interface e autenticação. Usar o navegador institucional disponível. | Ferramenta já disponível no PC, quando aplicável. |

Em 9 de outubro de 2026, Node 24 é a LTS mais recente. O projeto deve fixar a versão e atualizá-la conscientemente, para evitar versões diferentes nos dois PCs. [W35]

Docker Desktop requer subscrição paga para entidades governamentais. A existência de apenas dois desenvolvedores não remove essa condição. Confirmar a licença aplicável antes da utilização institucional. Se o runtime escolhido não estiver disponível, registar o impedimento e avaliar uma alternativa comum para ambos os PCs, sem instalar simultaneamente vários conjuntos de serviços. [W34]

WordPress, PHP, MariaDB, WP-CLI, Composer e as ferramentas PHP de teste são fornecidos pelo ambiente local documentado do wp-env. As dependências do projeto serão instaladas pelos seus scripts. Não é necessário começar por instalar PHP, MySQL, Composer, WP-CLI, XAMPP ou Laragon no Windows. Node não é o backend do portal em produção. [W10]

Docker Desktop integra Compose e disponibiliza o acesso ao Docker no WSL. Não instalar um segundo Docker Engine dentro do mesmo Ubuntu usado com essa integração. Esta lista aplica-se aos PCs de desenvolvimento. O servidor .205 seguirá a stack Windows/IIS/PHP/MySQL das secções 3 e 12. [W33]

<a id="preparacao-git"></a>
### 16.5 Preparação inicial e comandos de colaboração

Preparar WSL e as ferramentas locais. ChatGPT Work/Codex trabalha sobre GitHub. Instalar Cursor apenas se o colaborador quiser usar esse editor. Se o WSL já existir, executar wsl --update e conferir wsl --version antes de instalar outra distribuição. O formato atual do Ubuntu 24.04 requer o componente WSL na versão 2.4.10 ou posterior. No PowerShell como administrador, quando a distribuição ainda não estiver instalada: [W32]

~~~powershell
wsl --install -d Ubuntu-24.04
~~~

Reiniciar se solicitado, concluir a criação do utilizador local Ubuntu e depois atualizar e conferir o WSL:

~~~powershell
wsl --update
wsl --version
wsl -l -v
~~~

A distribuição do projeto deve indicar versão 2 em wsl -l -v. Essa informação indica a arquitetura e é diferente da versão do componente apresentada por wsl --version. Instalar Docker Desktop, ativar o backend WSL 2 e a integração com Ubuntu-24.04. Estes procedimentos são executados uma vez em cada PC, conforme as instruções oficiais. [W32] [W33]

No terminal Ubuntu, instalar Git e utilitários de apoio:

~~~bash
sudo apt update
sudo apt install -y git curl ca-certificates unzip
~~~

Instalar nvm pelo guia indicado na secção 16.4 e, no terminal onde esteja carregado, preparar Node:

~~~bash
nvm install 24
nvm use 24
node --version
npm --version
git --version
docker version
docker compose version
~~~

Depois de o projeto passar a ter .nvmrc, usar nvm install e nvm use na raiz para seguir a versão fixada. Instalar também gh seguindo o guia oficial para Ubuntu. Cada pessoa autentica a sua própria conta pelo navegador, sem colocar tokens no README ou no terminal do agente:

~~~bash
gh auth login --hostname github.com --git-protocol https --web
gh auth setup-git
~~~

Este fluxo requer autenticação concluída pelo utilizador. Confirmar o acesso à conta correta e configurar o armazenamento de credenciais adequado ao PC. Não mostrar tokens em logs, capturas ou conversas. [W37]

Clonar uma vez em cada computador. Se a pasta já existir, inspecionar o seu estado em vez de voltar a clonar por cima:

~~~bash
mkdir -p ~/projects
cd ~/projects
git clone https://github.com/atchutchi/plataforma-interna-arn.git
cd plataforma-interna-arn
git config user.name "O TEU NOME"
git config user.email "O TEU EMAIL VERIFICADO NO GITHUB"
git config pull.ff only
git config core.autocrlf input
~~~

Substituir os dois valores de identidade pelos da pessoa que usa o PC. Pode usar-se o email noreply disponibilizado pela respetiva conta GitHub. Conservar a cópia dentro do sistema de ficheiros Ubuntu, por exemplo ~/projects/plataforma-interna-arn. [W36] [W39]

No ChatGPT Work/Codex, usar a URL GitHub e confirmar o clone, a branch e a tarefa antes de alterar ficheiros. O ambiente WordPress local mantém dados próprios. Se for utilizado Cursor opcionalmente, abrir a pasta Ubuntu através da integração WSL. Não adicionar dependências específicas desse editor.

Para uma tarefa nova, com a árvore de trabalho limpa, atualizar main e criar uma branch própria. O exemplo seguinte é a branch de arranque de Atchutchi:

~~~bash
git status --short
git fetch origin --prune
git switch main
git pull --ff-only origin main
git switch -c chore/atchutchi-F1-01-fundacao
~~~

O segundo colaborador escolhe outro nome e outra tarefa. Numa branch existente, com upstream configurado e sem alterações locais por tratar:

~~~bash
git fetch origin --prune
git pull --ff-only
git merge origin/main
~~~

Se algum comando apresentar divergência ou conflito, analisar os ficheiros e preservar o trabalho de ambos. Não continuar uma sequência de comandos como se a atualização tivesse sido concluída. Depois de desenvolver, executar as verificações relevantes, rever git diff, adicionar explicitamente os ficheiros da tarefa e rever git diff --cached. Fazer commit com objetivo claro e associar o código da tarefa.

No primeiro push da branch de exemplo:

~~~bash
git push -u origin chore/atchutchi-F1-01-fundacao
~~~

Nos seguintes, usar git push na mesma branch. Abrir PR para main e registar o que foi verificado. Push envia commits para o GitHub. Não instala o portal em Panthera-Onca nem transfere a base de dados.

Desde o marco local de F1-01 existem `package.json` e `package-lock.json`. Os dois PCs instalam as mesmas versões com npm ci, sem gerar projetos independentes: [W40]

~~~bash
nvm install
nvm use
npm ci
npm run env:start
~~~

`npm run env:start` inicia o ambiente e `npm run env:stop` termina-o, preservando os dados locais. A URL e as portas reais confirmam-se no resultado de `npm run env:status` e `npm run env:check`.

<a id="primeira-entrega"></a>
### 16.6 Primeira entrega local e divisão do trabalho

O primeiro marco de F1-01 é preparar uma base comum, numa única branch, para evitar que cada computador crie uma estrutura incompatível. A proposta inicial atribuía a condução a Atchutchi. Em 9 de outubro de 2026 só existia `main`, sem outra branch nem pull request, e Albertina4264 iniciou este marco. Atchutchi revê a PR. Não criar uma segunda fundação em paralelo.

| Marco | Entrega | Evidência esperada |
| --- | --- | --- |
| Fundação comum | Configurações de desenvolvimento, versões, lockfiles, plugin institucional mínimo e tema de blocos mínimo. | Clone limpo consegue instalar dependências e iniciar WordPress. |
| Repetição no segundo PC | O colaborador atualiza main depois da integração e executa os mesmos comandos. | Ambiente inicia com a mesma versão e sem passos locais ocultos. |
| Backend | Desenvolvimento das tarefas selecionadas de identidade, autorização e organização, seguindo as dependências. | Regras e testes das histórias correspondentes. |
| Frontend | Tema, padrões, navegação e componentes com dados fictícios e contratos de acesso definidos. | Edição no CMS, interação, responsividade e acessibilidade verificadas. |

#### Registo do marco local

Executante inicial: Albertina4264. Revisão técnica para integração pedida por Atchutchi na [PR #2](https://github.com/atchutchi/plataforma-interna-arn/pull/2). Branch: `chore/albertina4264-F1-01-fundacao`. Estado: Em curso. F1-01 não fica concluída nesta entrega.

Versões fixadas. A execução anterior de revisão e a validação local fornecida por Atchutchi em 13 de outubro são evidências distintas:

| Peça | Versão configurada | Observação |
| --- | --- | --- |
| Node.js | 24.21.0 | Observada no Windows deste PC. `.nvmrc` repete esta versão. No Ubuntu do outro PC, usar nvm. |
| @wordpress/env | 11.17.0 | Dependência local, com `package-lock.json`. |
| WordPress | 7.1.3 | Pacote oficial indicado em `.wp-env.json`. Atchutchi confirmou abertura local em 13 de outubro. |
| PHP | 8.4 | `phpVersion` do wp-env. A imagem exata confirma-se quando o contentor arrancar. |
| MariaDB | 11.8 | LTS, imagem `mariadb:11.8`. Não substitui a verificação de MySQL 8.4 na homologação. |
| PHPUnit | 11.5.57 | Declarado em `composer.json`. `composer.lock` gerado no contentor e integrado pela PR #3. |
| WPCS | 3.4.1 | Declarado em `composer.json`. O phpcs corre no contentor. |

Comandos da fundação, a partir da raiz:

~~~bash
nvm install
nvm use
npm ci
npm run env:start
npm run env:check
npm run build
npm run lint
npm test
~~~

O ambiente de testes usa outra base e a porta 8890. Não ativa o acesso fictício:

~~~bash
npm run env:start:tests
npm run env:check:tests
npm run composer:install
npm run test:php
npm run lint:php
npm run env:stop:tests
~~~

`npm run env:stop` para o ambiente de desenvolvimento. Não usar `destroy`, `cleanup` nem `reset` sobre volumes que já tenham dados.

O `@wordpress/env` 11.17.0 ainda cria um segundo ambiente legado, com a mesma configuração, quando `testsEnvironment` não é `false`. As duas configurações desligam essa opção. A documentação mais recente chama a opção de descontinuada; nesta versão instalada o código só a respeita quando o valor é falso. O isolamento dos testes fica em `.wp-env.test.json`.

A configuração Compose produzida diretamente pelo `@wordpress/env` 11.17.0 não limita as portas a localhost. Por isso, os comandos npm passam por `scripts/wp-env.mjs`, que fornece ao Compose um ficheiro temporário com os bindings HTTP e MariaDB em `127.0.0.1`. O mesmo controlo acompanha o arranque, o bootstrap, os comandos PHP e a gestão do ambiente. O ficheiro temporário é removido no fim de cada comando. O mecanismo utiliza `COMPOSE_ENV_FILES`, documentado pelo Docker. [W41]

Antes de arrancar, o script pede ao Compose que interprete uma configuração de prova, sem criar contentores. Se a versão instalada não confirmar ambos os bindings em `127.0.0.1`, o arranque falha. Usar os comandos npm documentados. A execução direta de `wp-env start` não aplica este controlo. Depois de arrancar, `npm run env:check` ou `npm run env:check:tests` inspeciona os contentores do projeto selecionado e reprova qualquer publicação fora de loopback, incluindo um IP concreto da rede local. Também reprova serviços essenciais parados ou respostas incompletas.

As portas HTTP predefinidas são 8888 para desenvolvimento e 8890 para testes. A MariaDB recebe uma porta livre, sempre em loopback. Se houver conflito de HTTP, definir uma porta numérica no ficheiro `.wp-env.override.json` ou `.wp-env.test.override.json` correspondente. O arranque não troca a porta HTTP automaticamente. Não exportar variáveis `WP_ENV_*PORT` nem `COMPOSE_PROJECT_NAME` nesta sessão, pois o script recusa esses valores para preservar os bindings e a separação dos projetos. Manter `testsEnvironment=false` e `phpmyadmin=false` também nos overrides.

A conta local fictícia é `ana.teste`, com email `ana.teste@example.test`, papel `subscriber` e palavra-passe `fixture-local-ana`. O plugin só a cria e permite a sua utilização quando `WP_ENVIRONMENT_TYPE` é `local` e `ARN_ALLOW_FICTIONAL_LOCAL_ACCESS` é o booleano verdadeiro. As novas fixtures recebem o metadado `_arn_intranet_local_fixture=1`. A combinação original de login e email identifica as fixtures legadas. Uma coincidência isolada não transforma outra conta numa fixture. Com o plugin ativo, retirar a flag ou mudar o ambiente bloqueia novos logins, identidades obtidas por cookies, recuperação e application passwords dessa conta. Na primeira tentativa de reutilização de uma sessão, os tokens da fixture são revogados. A conta e a autoria dos conteúdos ficam preservadas.

O ambiente de testes não define essa flag e usa o tipo `development`. As contas da secção 5.4 não são criadas nem promovidas. A palavra-passe predefinida do wp-env, `admin` / `password`, pertence à ferramenta e não é uma credencial Google. O bloqueio implementado nesta fundação abrange a fixture do plugin. A autenticação Google e as permissões gerais do portal pertencem às tarefas seguintes.

No registo inicial da colaboradora, `npm test`, `npm run lint` e `npm run build` foram executados. `npm run env:check` terminou a avisar que o Docker não estava disponível. O arranque original gerou a configuração e parou com `spawn docker ENOENT`, antes de descarregar imagens ou criar contentores. O nome da conta Windows desse PC contém um espaço e entra no `useradd` da imagem. O clone de trabalho deve ficar no Ubuntu, como a secção 16.5 pede. `npm run test:php` e `npm run lint:php` não foram executados nessa entrega. A sessão não era administrador e não instalou WSL nem Docker. A licença institucional do Docker Desktop continua por confirmar.

Registo da colaboradora no segundo avanço, commit `518ad38`, no mesmo dia: autoloader PSR-4 do plugin, portal privado, modelo de unidades e workflow GitHub. `Access\PrivatePortal` redireciona páginas sem sessão para a entrada e exige autenticação na REST e no admin-ajax, exceto o heartbeat. Também desliga feeds e sitemaps, retira links de descoberta e recusa arquivos de autor a visitantes sem sessão. Os arquivos de autor continuam acessíveis a utilizadores autenticados. A regra pura está em `PrivatePortalPolicy`, com testes. O ficheiro `.github/workflows/verificacao.yml` corre Node e PHP em cada push e pull request. Para verificar o PHP no seu PC sem Docker, a colaboradora registou PHP 8.4.25 com Composer temporário, PHPUnit 11.5.57 com 38 testes e 190 asserções aprovados e PHPCS sem erros em 19 ficheiros. A [CI desse commit](https://github.com/atchutchi/plataforma-interna-arn/actions/runs/37949294902) também passou. `vendor/` não é versionado. Neste registo histórico de 9 de outubro, `composer.lock` ainda estava por gerar. A PR #3 resolve essa pendência em 13 de outubro.

#### Revisão para integração na PR #2

O bootstrap passa a executar o binário `bin/wp-env` do pacote instalado. O módulo `lib/cli.js` apenas exporta a CLI e não executava os comandos quando chamado diretamente. Composer, PHPUnit e PHPCS passam a selecionar explicitamente `.wp-env.test.json`. A verificação das portas usa o estado do wp-env e os IDs do respetivo projeto Compose, sem aprovar ou reprovar contentores de outros projetos.

O tema mantém o aviso de desenvolvimento apenas quando o ambiente WordPress é `local`, mesmo que o padrão tenha sido expandido e guardado pelo editor. Os grupos do cabeçalho, rodapé e templates incluem no HTML os estilos declarados nos atributos dos blocos. O CSS do tema fica também disponível no editor.

A revisão incorporou o commit concorrente `518ad38`, preservando os três commits da colaboradora. A guarda REST passa a validar a sessão depois da verificação de cookies e nonce feita pelo core. Um resultado `true` anterior não permite acesso se o utilizador atual tiver sido removido. Pedidos `XMLRPC_REQUEST` recebem 403 no início de `init`, antes do dispatcher, incluindo pingbacks e métodos sem autenticação. A hierarquia passa a recusar uma unidade ativa sob um superior desativado em `fromList`, `add` e `reparent`, preservando o estado quando a operação falha e mantendo as relações inativas para histórico.

Esta proteção PHP não controla ficheiros estáticos servidos diretamente pelo servidor web. A entrega privada de documentos, anexos e derivados continua em F1-08. Os testes de caminhos da política não demonstram proteção dos ficheiros em `uploads`.

Evidência da revisão de código, em 9 de outubro de 2026:

| Verificação | Resultado e alcance |
| --- | --- |
| Dependências Node | `npm ci --ignore-scripts --no-audit --no-fund` instalou as dependências do lockfile. Esta instalação não executou os scripts de instalação dos pacotes. |
| `npm test` | 54 testes aprovados, incluindo bindings LAN/IPv6, isolamento dos projetos, falhas do Docker, seleção do ambiente de testes e execução da ajuda real da CLI wp-env. Os cenários Docker usam respostas controladas. |
| `npm run lint` e `npm run build` | Aprovados. Abrangem a sintaxe JavaScript e o contrato estrutural da fundação. Não equivalem a PHPCS nem a um teste do editor. |
| Sintaxe PHP | Os 28 ficheiros PHP do plugin, tema e testes passaram o parser PHP 8.4.25 em WebAssembly, com `TOKEN_PARSE`. |
| Comportamento PHP | 329 asserções do plugin, 75 do tema e 69 da hierarquia aprovadas em verificações pontuais de revisão. Foram usadas as classes reais e adaptadores WordPress em memória. Estes testes não utilizam PHPUnit nem um WordPress integrado. |
| Modelo Compose | O Compose oficial v5.6.0, com checksum verificado, normalizou HTTP e MariaDB em `127.0.0.1`. Os overrides de desenvolvimento e testes também foram conferidos, com diretórios Compose distintos. A prova executou apenas `version` e `config`, com configurações e cache isoladas, sem arrancar contentores. |
| CI no GitHub | Os jobs Node e PHP verificam cada SHA publicado. A execução final e os resultados de PHPUnit e PHPCS ficam associados à PR #2. O resultado de um commit anterior não substitui a verificação das alterações seguintes. |

A suite Node e as verificações PHP em WebAssembly correram em Linux com Node.js 24.19.0. O runtime 24.21.0 disponível nessa sessão não conseguiu executar JavaScript. O requisito do projeto continua a ser Node.js 24.21.0 e não foi reduzido para acomodar o ambiente de revisão. Repetir `npm ci`, os testes e o arranque na versão exigida nos PCs de desenvolvimento.

Pendências históricas da revisão de 9 de outubro: arranque integrado, bindings reais e geração do lockfile estavam por verificar nessa sessão. A validação local de Atchutchi de 13 de outubro, abaixo, resolve esses pontos no seu PC. A repetição no segundo PC, edição Gutenberg, fluxos com cookies reais e homologação Windows/IIS/MySQL permanecem sem evidência adicional nesta sessão. Nenhuma destas verificações conclui F1-01.

A fundação deve criar apenas a estrutura usada no primeiro marco, evitando ficheiros vazios para todos os módulos futuros. Deve incluir AGENTS.md curto com as regras de colaboração, comandos realmente disponíveis e referência a este README. ChatGPT Work/Codex e os agentes devem ler AGENTS.md antes de trabalhar. Cursor permanece opcional.

Configurar scripts para iniciar, parar, verificar e construir o projeto. Definir um ambiente de testes separado. Executar verificações de instalação, ativação do plugin e tema, sintaxe e construção. Acrescentar testes de comportamento quando existirem regras, sobretudo acesso, identidade, importação e integridade. Uma verificação de segurança ou homologação não pode ser marcada como passada antes de ser executada.

O primeiro marco local não depende de aceder a .151, .205 ou de criar credenciais Google reais. As dependências institucionais continuam a condicionar os marcos seguintes. Não marcar F1-01 inteira como Concluída enquanto faltar homologação Windows/IIS. Não marcar o portal pronto para utilização pela ARN apenas porque o WordPress local abre.

#### Validação local fornecida por Atchutchi em 13 de outubro de 2026

Esta evidência foi comunicada pelo responsável. Não foi repetida sobre o seu ambiente nesta sessão Codex. WordPress abriu em http://localhost:8888 e o ambiente separado de testes em http://localhost:8890. Docker confirmou separação e bindings apenas em 127.0.0.1.

| Verificação fornecida | Resultado |
| --- | --- |
| npm run env:check | Aprovado. |
| npm test | 54 testes aprovados, 0 falhas. |
| npm run lint e npm run build | Aprovados. |
| npm run composer:install | Aprovado. composer.lock gerado no ambiente PHP do projecto. |
| npm run test:php | PHP 8.4.26, PHPUnit 11.5.57, 63 testes, 284 asserções, 0 falhas. |
| npm run lint:php | 28/28 ficheiros aprovados. |

Revisão GitHub nesta sessão: a branch chore/atchutchi-F1-01-validacao-local tinha apenas composer.lock e um commit sobre main. A [PR #3](https://github.com/atchutchi/plataforma-interna-arn/pull/3) foi revista e integrada após CI verde nos dois jobs, no SHA a230ffd4e4eee55a9ec67fba8493d50f39f072fe. [CI da PR](https://github.com/atchutchi/plataforma-interna-arn/actions/runs/38424334638). Executante da revisão: Codex a pedido de Atchutchi. Sem deploy. F1-01 permanece Em curso.

Fluxo actual: PC local, WSL 2/Ubuntu 24.04, Docker Desktop, wp-env, WordPress local, branch Git, GitHub, PR, main, release, homologação e, após autorização própria, Panthera-Onca .205. A release e homologação não são executadas por um push. GitHub é a fonte de verdade. O ambiente local fica separado do trabalho Codex. Cursor é opcional.

Revisão documental 0.9: executante Codex a pedido de Atchutchi, branch `docs/atchutchi-readme-v0.9-infra-ferias-work`. Nesta sessão, `npm test` passou com 54 testes e 0 falhas, lint e build passaram e o diff foi revisto. Node efectivo 24.19.0, sem alterar o requisito 24.21.0. O daemon Docker não está disponível nesta sessão, pelo que não se repetiu wp-env ou os ensaios integrados fornecidos pelo responsável. A primeira tentativa Node ocorreu com a instalação de dependências ainda incompleta e falhou por módulo ausente. A repetição após disponibilidade dos módulos passou. A CI da PR documental deve verificar a versão fixada e as suites PHP antes da integração. PR, SHA e resultados finais ficam associados à alteração no GitHub e no registo de continuidade seguinte.

<a id="prompt-cursor"></a>
<a id="prompt-codex"></a>
### 16.7 Prompt de continuidade para ChatGPT Work/Codex

Para a continuidade actual, ler o [guião de coordenação de 16 de outubro](docs/tarefas-albertina-2026-10-16.md), as Issues #28/#29 e o estado da proposta F1-12 na PR #26. F1-07 já está integrado. O [prompt actual para Albertina](docs/prompt-albertina-2026-10-16.txt) corresponde às duas atribuições confirmadas. O prompt ALB-F1-07-QA e o texto genérico abaixo são históricos. Não repetir a instalação ou reatribuir a implementação concluída.

Copiar o texto seguinte para ChatGPT Work/Codex com acesso ao GitHub. Preencher o utilizador e a tarefa após conferir main, branches, PRs e CI. Não repetir a fundação existente. Cursor pode usar o mesmo contexto opcionalmente.

~~~text
Trabalha comigo no desenvolvimento da plataforma interna da ARN.

REPOSITÓRIO
https://github.com/atchutchi/plataforma-interna-arn

RESPONSÁVEL DESTA SESSÃO
Utilizador GitHub: [PREENCHER]
Tarefa combinada: [PREENCHER. No primeiro arranque: F1-01, fundação local]

CONTEXTO E ÂMBITO
Somos dois colaboradores, em computadores separados. Cada um tem o seu clone,
conta GitHub e dados locais. O README.md é a referência funcional, técnica e
de acompanhamento. Lê-o integralmente antes de alterar o projeto. Lê também
AGENTS.md e regras existentes, e inspeciona a árvore, branches e PRs acessíveis.
Não pressuponhas que o repositório ainda contém apenas documentação.

A fundação da PR #2 e o composer.lock da PR #3 já existem em main. Este pedido
continua a tarefa indicada, preservando o trabalho de Albertina4264. Executa o trabalho, verifica-o e publica os avanços
na branch da tarefa. A instalação na infraestrutura ARN é uma etapa posterior.
Não acedas nem alteres servidores, AD, DNS, Google Workspace ou produção nesta
sessão. Se faltarem fontes referidas no README, regista a ausência e trabalha
nos pontos independentes. Não inventes conteúdo dos anexos.

ARQUITETURA A SEGUIR
Usa WordPress como CMS integrado, numa instalação para toda a ARN.
Backend em PHP 8.4, com regras no plugin wp-content/plugins/arn-intranet-core.
Frontend no tema de blocos wp-content/themes/arn-intranet, com theme.json,
templates, partes, padrões, HTML, CSS e JavaScript. Usa os recursos nativos do
WordPress e React apenas quando necessário aos blocos ou à interação.
Os conteúdos e os campos autorizados devem ser geridos através do CMS.
Não substituas a stack por outra arquitetura sem uma decisão documentada.

Nos PCs, usa ChatGPT Work/Codex e GitHub, com WSL 2 Ubuntu 24.04 LTS + Docker
Desktop com integração WSL e licença institucional adequada. Git e Node 24 LTS
correm no Ubuntu. Usa @wordpress/env como dependência local e versões fixadas.
wp-env usa MariaDB. A homologação futura deve verificar PHP 8.4 e MySQL 8.4 no
IIS. Não inventes uma opção mysqlVersion para o wp-env nem consideres um teste
local como prova de compatibilidade com produção.

Produção planeada: Panthera-Onca, 192.168.17.205, fora de arn.local,
Windows Server 2019 Standard 1809, build 17763, 64 bits, IIS/FastCGI e MySQL 8.4 LTS. O controlador AD DS/DNS está
em 192.168.17.151. O endereço https://intranet.arn.gw é uma proposta a aprovar.
O acesso institucional será pela rede ARN ou VPN, com autenticação Google
Workspace de arn.gw por OIDC e permissões próprias do portal.

DADOS E ACESSO
Segue a lista da secção 4 do README, incluindo as 37 entradas e 73 nomes.
Ela é referência documental. Não a transformes automaticamente em contas,
emails, cargos ou permissões. Mantém distintas as duas unidades DGE. Não
inventes nomes extensos para DSU, DSGI e DSIC, nem relações superiores ainda
pendentes. A hierarquia deve usar identificadores estáveis e impedir ciclos.
Usa apenas pessoas, contas e conteúdos fictícios nos seeds e testes.

A autenticação real seguirá a secção 7: validar assinatura e claims OIDC,
domínio institucional e associação local autorizada. A pertença ao domínio
arn.local, a uma OU ou a arn.gw não concede privilégios por si só. As três
contas administrativas da secção 5.4 precisam de vinculação e atribuição
explícitas, sem promoção automática baseada no email.
Durante a fundação admite-se apenas o acesso de teste fictício e local da
secção 12.6. Esse modo não pode funcionar em homologação institucional ou
produção. Nunca recolhas passwords Google no portal.
Quando implementares módulos, aplica autorização no servidor por função,
unidade e objeto, incluindo API, pesquisa, anexos e derivados. Usa validação,
sanitização, escape, nonces quando aplicáveis e consultas preparadas.

GIT EM CADA SESSÃO
1. Confere a pasta atual, git status, branch, origin e identidade Git. Não
   sobrescrevas alterações existentes nem configures a identidade do colega.
   Se o clone não existir, clona a URL indicada para uma pasta local nova.
2. Faz git fetch origin --prune. Para uma tarefa nova, com a árvore limpa,
   muda para main, executa git pull --ff-only origin main e cria uma branch
   própria com tipo, utilizador, ID e objetivo, por exemplo
   chore/atchutchi-F1-01-fundacao. Confere que a tarefa não está já em curso.
3. Se retomarmos uma branch existente, preserva-a. Com a árvore limpa, atualiza
   o upstream da própria branch por avanço direto quando aplicável e integra
   origin/main por merge. Não executes pull de main cegamente numa branch
   com alterações por tratar. Analisa divergências e conflitos preservando
   o trabalho de ambos. Não uses force push, reset --hard ou clean destrutivo.
4. Divide a tarefa em avanços pequenos e coerentes. Após cada avanço, executa
   as verificações pertinentes, revê git diff, adiciona explicitamente os
   ficheiros da tarefa, revê git diff --cached e faz commit com mensagem clara
   e ID da tarefa. Evita commits de ficheiros alheios e git add . sem revisão.
5. Faz push após cada commit de avanço para a branch da tarefa. No primeiro
   push configura upstream com git push -u origin NOME_DA_BRANCH. Não esperes
   pelo fim de todo o projeto. Confirma o resultado do push e indica o SHA.
6. Abre ou atualiza uma pull request para main. Usa rascunho se houver trabalho
   ou validações em falta. Explica objetivo, histórias, comportamento, testes
   executados e limitações. Integra apenas com autorização do responsável,
   revisão e CI verde no SHA exacto. A sessão de 13 de outubro autoriza essa
   integração. Não faças push directo em main ou implantação em produção.
7. Se faltar autenticação ou o push falhar, conserva o trabalho e os commits.
   Explica o impedimento e os comandos necessários, sem afirmar que publicaste.
   Nunca coloques tokens, passwords ou segredos em código, logs ou mensagens.

PRIMEIRO MARCO, QUANDO A TAREFA FOR A FUNDAÇÃO LOCAL
Confere as ferramentas existentes. Prepara a estrutura mínima útil de F1-01:
plugin institucional ativável, tema de blocos ativável, configuração wp-env,
versões, .nvmrc, package.json e package-lock.json, .gitignore, .gitattributes,
.editorconfig e AGENTS.md curto. Prepara Composer e o respetivo lockfile quando
necessários ao autoload, padrões e testes, executando PHP/Composer no contentor.
Mantém dependências geradas, dados, segredos e WordPress core fora do Git.
Reutiliza o que já existir. Não recries o projeto nem todos os módulos futuros.

Cria e documenta scripts reais env:start, env:stop, build, lint e os testes
aplicáveis. Prepara configuração e dados de testes separados do desenvolvimento.
O segundo PC deve conseguir instalar através de npm ci e dos scripts documentados.
Usa npm install apenas para criar ou alterar dependências e o lockfile de forma
intencional. Confirma a versão efetiva do WordPress, PHP e base de dados.

Verifica que o WordPress abre, o plugin e o tema ativam, a construção funciona
e a configuração é reproduzível. Verifica as portas e restringe o ambiente ao
PC. Usa apenas dados fictícios. Não destruas bases ou volumes existentes.
Regista no README as instruções e o progresso do marco local. F1-01 só estará
concluída quando cumprir também a homologação futura descrita no backlog.

Se a fundação já existir, segue a tarefa combinada e as suas dependências.
Não implementes todas as histórias numa única alteração. Decisões editoriais,
circuitos de aprovação, identidades e calendário ainda pendentes permanecem
registados. Avança no trabalho independente que possa ser executado e verificado.

FORMA DE TRABALHAR E ENTREGA
Apresenta um plano curto e começa a executar. Mantém as regras e contratos
WordPress, usa português nos textos da interface e atualiza apenas as partes
do README afetadas. Acrescenta testes úteis para regras e riscos reais.
Não declares testes aprovados se não os executaste.
No fim de cada avanço, informa o resultado, os ficheiros alterados, os comandos
de verificação e respetivos resultados, a branch, o commit, o resultado do push,
a PR quando existir e o próximo passo para o outro colaborador.
~~~

### 16.8 Continuidade e preparação técnica de F1-02

Estado actual: [login Google local homologado](docs/google-login-local-f1-02.md), com callback real, vínculo auditado e sessão. A descrição seguinte conserva o histórico das entregas anteriores.

Executante: Codex a pedido de Atchutchi. Branch `feature/atchutchi-F1-02-oidc`, criada de main após a [PR #4](https://github.com/atchutchi/plataforma-interna-arn/pull/4). A revisão documental 0.9 teve revisão independente e CI Node/PHP verde no SHA `7917561e14c4e46a61c33242cbe985fb92231662`. Merge da PR #4: `67627433d03431be2caa102bd9837033ef07cb39`. [CI documental](https://github.com/atchutchi/plataforma-interna-arn/actions/runs/38425255491). Sem deploy.

A [preparação de F1-02](docs/oidc-f1-02.md) implementa apenas configuração externa e resolução de vínculo explícito em memória. O domínio Workspace, Client ID, Client Secret e callback vêm de configuração protegida. Sem flag activa, a configuração devolve null e não lê segredos. A política recebe claims apenas depois da validação OIDC completa por um futuro cliente mantido. Exige hd exacto, email verificado, emissor/sub correspondentes e um único vínculo local activo e autorizado. Não associa por email, não cria contas e não atribui privilégios. Nenhum hook chama estas classes e não existe login real nesta entrega.

O candidato OpenID Connect Generic Client 3.11.3 foi examinado, sem instalação. A via sem JWKS, a procura nativa por sub e o vínculo opcional por email exigem controlos adicionais antes de adopção. Não foi encontrada validação de nonce nos três ficheiros principais examinados. Os limites da análise e as fontes primárias estão no documento F1-02. O candidato continua sujeito a homologação e não se escreveu protocolo próprio.

| Verificação nesta sessão | Resultado e alcance |
| --- | --- |
| Suite original em PHP 8.4.26 portátil | 63 testes, 284 asserções e PHPCS 28/28, antes de acrescentar as classes. |
| RED antes de implementar | 42 novos cenários falharam por ausência das classes. |
| PHPUnit após implementar e completar a cobertura | 107 testes, 357 asserções, 0 falhas. Testes puros, sem Google real ou WordPress integrado. |
| PHPCS após corrigir apontamentos | 34/34 ficheiros aprovados com o ruleset existente. |
| npm test | 54 testes aprovados, 0 falhas. Node local 24.19.0. O requisito 24.21.0 continua fixado e é verificado pela CI. |
| npm run lint e npm run build | Aprovados. O build verifica a estrutura e não compila a interface final. |
| Lockfiles | composer.json e composer.lock preservados. Dependências instaladas em vendor não versionado. |

No marco histórico da PR #5, F1-02 continuava Em curso e ainda faltavam persistência, cliente, protocolo, callback e sessão. A continuação abaixo acrescenta apenas a fundação persistente, mantendo as outras dependências. F1-01 continua Em curso. A preparação local não conclui F1-03/04/05 nem autoriza começar F2.

A preparação está na [PR #5](https://github.com/atchutchi/plataforma-interna-arn/pull/5), commit de implementação `70a6bf283b7fc3422e1f98e7c49553552a819842`. Revisão independente aprovada sobre esse commit, com PHPUnit repetido pelo revisor. A [CI inicial](https://github.com/atchutchi/plataforma-interna-arn/actions/runs/38426638454) passou Node e PHP. Foram depois acrescentados dois testes de cobertura sugeridos pelo revisor, sem alteração das classes, resultando em 107 testes e 357 asserções. O SHA final, a CI posterior e a integração ficam no registo GitHub da PR. Integrar apenas depois de confirmar os checks no SHA final. Próximo passo: homologar o cliente OIDC e definir o contrato persistente de vínculo, sem inventar credenciais ou competências.

#### Continuação local: associação persistente auditada

A branch feature/atchutchi-F1-02-persistencia-identidade acrescenta IdentityLinkService, repositório WordPress/MariaDB com constraints binárias e auditoria transaccional. A criação fica inactiva e exige conta local prévia e actor competente. Não existem hooks, associação por email ou criação de contas. A [entrega e limites](docs/identity-persistence-f1-02.md) descreve a ligação dedicada, esquema, autorização e próximos passos.

Verificação local em Node 24.21.0 e PHP 8.4.26: 54 testes Node, 151 testes PHPUnit e 459 asserções aprovados. Revisão independente aprovada, com repetição PHPUnit pelo revisor. O ensaio real no WordPress 7.1.3/MariaDB 11.8.3 passou 21 asserções, incluindo conflitos de identidade, auditoria, rollback e recusa de transacção aninhada. O workflow acrescenta o ensaio integrado no wp-env de testes. O SHA final e a CI ficam associados à PR antes da integração.

O responsável esclareceu que não existe HTTPS de homologação e que trabalha noutro computador. Docker Desktop falhou no arranque neste PC. O laboratório portátil está isolado, sem alterações aos servidores ARN. A conta Google foi consultada no browser a pedido do responsável, sem exportação de funcionários ou credenciais para o Git. A autenticação continua desactivada. F1-02 mantém-se Em curso até cliente, callback, sessão e controlos de vias alternativas. F1-01 continua dependente de IIS/MySQL 8.4. F1-03 e F2 conservam os pré-requisitos.
#### Identidade visual local e verificação do CMS

O tema recebeu o logótipo original no cabeçalho e no ícone do browser, conforme esclarecimento do responsável. Manrope e Source Sans 3 são servidas localmente com licenças OFL. A preparação mantém WordPress como CMS e não altera autenticação. [Registo e limites do tema](docs/tema-institucional-f1-06.md).

Impeccable usou dois agentes isolados para tipografia e pré-scan. UI UX Pro Max orientou a acessibilidade. Playwright no WordPress real passou nas larguras 375/768/1024/1440, menu mobile, teclado e texto a 200% em mobile e desktop. O editor confirmou core/image válido, sem validationIssues. Revisão independente aprovada após correcção da serialização e do reflow. F1-06 está Em curso apenas nesta preparação. Não há módulos F2 ou páginas dependentes de competências implementadas nesta entrega.

A persistência F1-02 foi integrada pela PR #6 após revisão e CI Node/PHP/WordPress/MariaDB verde no SHA f67777e599c4495fc1221a91ad1c09c2c9a037be. Merge cee1100b249b725907650d701ead28014ccb9e13. A CI integrada repetiu as 21 asserções no wp-env. A consulta Google confirmou consentimento interno e cliente Web existente sem callback. Não foram criadas credenciais ou alteradas políticas Google. O adaptador OIDC e a sessão continuam pendentes.
<a id="verificacao"></a>
## 17. Verificação e critérios de conclusão

### 17.1 Cenários obrigatórios antes de disponibilizar F1

| Cenário | Resultado esperado |
| --- | --- |
| Pessoa fora da rede ARN e sem VPN | Não acede ao serviço interno. |
| Pessoa na rede sem sessão | Acede apenas ao processo de entrada e recursos necessários. |
| Conta Google externa ao domínio autorizado | Entrada recusada. |
| Conta Google institucional sem autorização local | Entrada recusada. |
| Conta federada tenta login ou recuperação local de palavra-passe | Operação recusada mesmo conhecendo uma palavra-passe WordPress. Autenticação corrente passa pelo Google. |
| Funcionário desativado com sessão anterior | Pedido seguinte recusado e sessões revogadas pelo procedimento de saída. |
| Funcionário tenta abrir documento de outra unidade sem autorização | Nenhum conteúdo ou metadado restrito é devolvido. |
| Link direto de anexo, miniatura ou versão antiga | Mantém a autorização do objeto e da versão. |
| Autor tenta publicar por API ou alterar notícia pendente | Operação recusada e estado preservado. |
| Dois editores decidem sobre versões diferentes | Apenas a decisão válida sobre a versão corrente produz publicação. |
| Notícia publicada recebe revisão | Leitores continuam a ver a versão aprovada até nova decisão. |
| Pesquisa, contagem ou cache cruza utilizadores | Informação restrita não é divulgada. |
| Funcionário transfere de unidade | Âmbitos mudam segundo a decisão. Histórico e encaminhamento permanecem coerentes. |
| Importação contém duplicados ou unidades inválidas | Erros identificados e ausência de alterações indevidas. |
| Utilizador opera por teclado e em telemóvel | Fluxos principais concluídos com foco, rótulos e mensagens compreensíveis. |
| Restauro em ambiente isolado | Dados, ficheiros, versões e permissões recuperados conforme o plano. |

### 17.2 Verificação de F2

Verificar pedido submetido, devolução, correção, aprovação, rejeição, cancelamento autorizado e substituição de aprovador. Confirmar tratamento de autoaprovação conforme regra aprovada.

Nas férias, criar testes unitários de cálculo ao iniciar F2 e verificar: pedido de 5 dias úteis, dois pedidos parciais no mesmo ano, 5 + 15 = 20 e saldo 2, pedido que completa 22, tentativa de exceder 22, fim de semana, feriado RH, períodos sobrepostos, rejeição, retirada, cancelamento futuro, funcionários distintos com saldos independentes, transição entre anos e acesso indevido ao pedido alheio. Testar reserva concorrente, classificação de gozados/futuros e reposição apenas dos dias não gozados. A regra proposta para pendentes e a passagem de ano só são implementadas depois de validadas pelo RH.

Nos tickets, verificar atribuição, resposta, anexos, resolução e diferença entre nota interna e resposta ao solicitante, caso notas internas sejam adotadas.

### 17.3 Condição para concluir uma funcionalidade

A funcionalidade deve cumprir a história, aplicar autorização no servidor, validar entradas, tratar erros e estados vazios, registar operações relevantes e manter ficheiros e notificações dentro do âmbito correto.

A revisão deve incluir testes das regras críticas e validação funcional pela área responsável. O README, decisões e evidência devem refletir o comportamento entregue.

Os cenários desta secção são critérios de aceitação futura. A fundação existente tem testes de código e validação local descritos na secção 16.6, que não demonstram homologação nem disponibilidade institucional.

### 17.4 Homologação específica Windows e rede

| Verificação | Resultado esperado |
| --- | --- |
| Alvo e separação de serviços | Produção prevista em .205 fora de arn.local, com coexistência validada e sem dependências AD. AD DS/DNS permanecem em .151. Homologação usa endereço próprio. |
| DNS na LAN e VPN | Nome do ambiente em teste resolve para o IP correspondente. O nome de produção proposto aponta para .205. Registos públicos necessários de arn.gw continuam a resolver. |
| HTTPS | Certificado corresponde ao nome e cadeia é confiável. Procedimento de renovação e instalação no binding IIS verificado em homologação. |
| IIS e WordPress | Permalinks, REST, CMS e callback OIDC funcionam com web.config e FastCGI. |
| Login Google | Fluxo completo a partir da LAN e da VPN, incluindo regresso do browser ao portal. |
| Administração inicial | As três contas da secção 5.4 recebem apenas as competências locais atribuídas. Um funcionário comum do mesmo domínio não recebe privilégios administrativos. |
| Bloqueio de rede | Acesso fora das origens autorizadas recusado. Perfil de rede e regras locais efectivas verificados sem depender de perfil Domain ou GPO. |
| MySQL | Serviço acessível pela aplicação local. Porta da base de dados não acessível a clientes da LAN. |
| Pastas e media | Pool tem apenas os acessos necessários. Ficheiros e derivados privados não existem em caminhos públicos alternativos. |
| Gestão do servidor fora do domínio | Ausência de associação a arn.local confirmada. Administração e identidades locais, certificados, fonte de hora e firewall validados. Sem GPO, Kerberos, LDAP ou conta de computador AD. Sem WSL ou Docker Desktop para alojar o portal. |
| Tarefas agendadas | Trabalhos executam com a identidade restrita autorizada, sem sobreposição e com registo de falha. |
| Desempenho | Comportamento dentro das metas aprovadas, sem degradar funções de infraestrutura existentes. |
| Recuperação | Backup remoto acessível pela identidade autorizada e dados, configuração e ACL recuperados em ambiente isolado. Outros serviços de .205 e AD DS/DNS em .151 preservados. |

<a id="pendencias"></a>
## 18. Decisões pendentes

Estas pendências têm resultado esperado e momento de resolução. Não impedem documentar a arquitetura, mas condicionam a implementação correspondente.

A lista recebida esclarece parcialmente PEN-01 e PEN-05, e o responsável confirmou trabalho de dois colaboradores em PCs separados. As restantes validações institucionais continuam adiadas até ao momento necessário. O arranque local da secção 16.6 pode avançar com dados fictícios, executante identificado e ferramentas disponíveis, sem exigir previamente decisões editoriais, circuitos administrativos ou configuração de produção.

| ID | Informação ou decisão necessária | Responsável | Resolver antes de |
| --- | --- | --- | --- |
| PEN-01 | Lista transcrita na secção 4.1. Confirmar as lacunas da secção 4.6, cargos individuais e relações superiores. DCT-Q&S está identificada como direção no anexo. Confirmar NIC/NIC.gw, as designações DSU/DSGI/DSIC e a posição dos serviços, Arquivo, Recepção e FAU. | RH + coordenação | Publicar organograma definitivo e importar pessoas reais. |
| PEN-02 | As três contas administrativas iniciais estão identificadas na secção 5.4. Confirmar a identidade autenticável de cada uma e que os restantes funcionários autorizados possuem conta Workspace. Definir tratamento de exceções e responsáveis pela identidade. | DCSI + RH | Ativar login. |
| PEN-03 | Aprovar nome do portal, desenho DNS, estratégia de certificado, origens e rotas VPN e saída Google. Prever callback conforme o plugin escolhido. A comprovação do callback real pertence a F1-02 e INF-07. | DCSI | Preparar DNS e integração OIDC. |
| PEN-04 | Inventariar recursos, armazenamento e serviços de .205, incluindo natureza física ou virtual e capacidade disponível. Aprovar versões candidatas de IIS, PHP 8.4 x64 NTS e MySQL 8.4 LTS, carga e metas de resposta. A compatibilidade executada pertence a INF-06/F1-01 e a homologação integrada a INF-09. | DCSI | Dimensionar e preparar homologação. |
| PEN-05 | Departamentos-ARN.docx é a fonte nominal inicial. Falta completar identificadores, emails e validação de RH, definir manutenção do cadastro e identificar as fontes operacionais de documentos, férias e saldos. | RH + responsáveis documentais | Importação operacional e cada módulo dependente. |
| PEN-06 | Validar tipos de pedido, substituições e tratamento dos pedidos do próprio aprovador. Nas férias, direito de 22 dias úteis repartíveis e decisão final RH já estão confirmados. Falta validar o detalhe de PEN-14, sem inventar cadeias adicionais. | RH + responsáveis institucionais | Desenvolver F2. |
| PEN-07 | Nomear equipa editorial. Confirmar públicos, comunicados institucionais, rejeição com motivo e regra de aprovação da própria notícia. | Coordenação + editorial | Ativar publicação. |
| PEN-08 | Aprovar dados partilhados no perfil, tipos e limites de anexos, classificação, conservação e arquivo. | RH + DCSI + responsáveis documentais | Carregar dados reais e documentos. |
| PEN-09 | Definir backups, retenção, RPO, RTO e responsabilidade por recuperação e incidentes. | DCSI | Disponibilizar F1. |
| PEN-10 | Definir duração e inatividade de sessão, MFA, encerramento, desativação e eventual recuperação técnica local. | DCSI | Disponibilizar F1. |
| PEN-11 | Atchutchi e Albertina4264 trabalham em PCs separados. F1-07 implementado e integrado por Codex/Atchutchi. Em 16 de outubro foram criadas e atribuídas à colaboradora as Issues #28, segundo PC/QA, e #29, especificação F1-13. Não há novo avanço publicado na branch antiga desde 16890af. Trabalho local não publicado não foi observado. Calendário global por definir. | Coordenação | Seguir 15.3.3 e as Issues. Confirmar commits locais antes de actualizar o clone. Relatórios e propostas só por PR, sem alterar branches de outro executante. |
| PEN-12 | Decidir se serão usados emails de notificação, em que eventos e por que canal institucional. | DCSI + donos dos processos | Ativar canal adicional. |

| PEN-14 | Férias: validar calendário de feriados, fonte dos saldos, proposta de bloqueio de sobreposição pendente, horários especiais, pedidos entre anos, saldo de 31 de dezembro, classificação de dias gozados, cancelamento parcial e correcções auditadas. Sem transporte, antecipação, compensação ou acumulação automáticos. | RH | Implementar e activar F2-03. |

PEN-13 resolvida quanto à identificação e localização: .151 é o controlador e DNS de arn.local. Panthera-Onca, .205, está fora do domínio por decisão de 13 de outubro de 2026, conforme DEC-11/13/14. O inventário e a validação operacional de .205 permanecem Por iniciar em INF-01/02 e PEN-04. A confirmação da localização não substitui a validação operacional nem significa que a instalação foi executada.

<a id="fontes"></a>
## 19. Fontes e histórico de decisões

### 19.1 Base funcional e institucional

A fonte funcional principal é o documento User Stories Portal Interno Empresa Com Notícias, fornecido para este projeto. Contém 46 histórias: 10 de funcionário, 5 de responsável, 4 de RH, 4 de suporte TI, 5 de administração, 4 transversais e 14 de notícias.

Foram analisadas as fontes institucionais disponibilizadas no projeto, incluindo Plano Estratégico, Revisão Funcional, Relatório Final, regulamentos, documentos de interoperabilidade, proteção de dados, cibersegurança e acesso universal, além da identidade visual e do template institucional.

O Plano Estratégico associa a intranet a comunicação interna, partilha de conhecimento e serviços. Os documentos de organização incluem diagnósticos e propostas com nomenclaturas diferentes. A lista inicial deste README seguiu a confirmação do responsável em 8 de outubro de 2026. A versão 0.6 incorpora Departamentos-ARN.docx, fornecido em 9 de outubro, com 37 entradas e 73 menções nominais. Foram conferidos o texto da tabela e as duas páginas do documento. A grafia de origem foi preservada e as lacunas estão na secção 4.6.

As referências internas servem de contexto. O repositório não incorpora os documentos de origem nem transforma propostas institucionais em atos aprovados.

As capturas iniciais mostram .151 e um resumo anterior de Panthera-Onca. As decisões de 8 de outubro sobre o domínio ficam como histórico substituído. Em 13 de outubro, o responsável confirmou .205 fora de arn.local, Windows Server 2019 Standard/1809/build 17763/64 bits, Hyper-V instalado e WSL ausente. Esta informação prevalece no plano actual e não constitui inspeção remota. As três contas administrativas foram indicadas pelo responsável e não recebem privilégios por email. Identificadores de produto, MAC e outros dados sem utilidade para o plano não são reproduzidos.

### 19.2 Referências técnicas

Referências de infraestrutura consultadas em 8 de outubro de 2026 e de desenvolvimento local atualizadas em 9 de outubro de 2026. Confirmar versões e compatibilidade no início da implementação.

- [W1] [WordPress Hosting Handbook, Server Environment](https://make.wordpress.org/hosting/handbook/server-environment/).
- [W2] [WordPress, Registering Custom Post Types](https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/).
- [W3] [WordPress, Roles and Capabilities](https://developer.wordpress.org/plugins/users/roles-and-capabilities/).
- [W4] [WordPress REST API, Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) e [Adding Custom Endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/).
- [W5] [Google, OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect) e [OAuth 2.0 for Web Server Applications](https://developers.google.com/identity/protocols/oauth2/web-server).
- [W6] [OpenID Connect Generic Client, diretório WordPress](https://wordpress.org/plugins/daggerhart-openid-connect-generic/).
- [W7] [Google, Security bundle, sessões da identidade e da aplicação](https://developers.google.com/identity/siwg/security-bundle).
- [W8] [WordPress, revogação das sessões de um utilizador](https://developer.wordpress.org/reference/classes/wp_session_tokens/destroy_all/).
- [W9] [WordPress, Creating Tables with Plugins](https://developer.wordpress.org/plugins/creating-tables-with-plugins/).
- [W10] [WordPress, wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) e [WP-CLI](https://developer.wordpress.org/cli/commands/).
- [W11] [WordPress, wp_upload_dir](https://developer.wordpress.org/reference/functions/wp_upload_dir/).
- [W12] [WordPress, Theme Structure](https://developer.wordpress.org/themes/core-concepts/theme-structure/).
- [W13] [PHP, Installation with IIS for Windows](https://www.php.net/manual/en/install.windows.iis.php) e [distribuições oficiais PHP para Windows](https://www.php.net/downloads.php?os=windows&version=8.4).
- [W14] [MySQL, Supported Platforms](https://www.mysql.com/support/supportedplatforms/database.html) e [instalação MySQL 8.4 no Windows](https://dev.mysql.com/doc/refman/8.4/en/windows-installation.html).
- [W15] [MariaDB Community, Platform Deprecation Policy](https://mariadb.com/docs/release-notes/community-server/about/platform-deprecation-policy).
- [W16] [Microsoft IIS, URL Rewrite](https://www.iis.net/downloads/microsoft/url-rewrite).
- [W17] [Microsoft IIS, Application Pool Identities](https://learn.microsoft.com/en-us/iis/manage/configuring-security/application-pool-identities).
- [W18] [Microsoft, Reviewing OU Design Concepts](https://learn.microsoft.com/en-us/windows-server/identity/ad-ds/plan/reviewing-ou-design-concepts).
- [W19] [Microsoft, Securing Domain Controllers Against Attack](https://learn.microsoft.com/en-us/windows-server/identity/ad-ds/plan/security-best-practices/securing-domain-controllers-against-attack).
- [W20] [Microsoft, DNS Architecture](https://learn.microsoft.com/en-us/windows-server/networking/dns/dns-architecture) e [Split-Brain DNS Deployment](https://learn.microsoft.com/en-us/windows-server/networking/dns/deploy/split-brain-dns-deployment).
- [W21] [Let's Encrypt, DNS-01 Challenge](https://letsencrypt.org/docs/challenge-types/#dns-01-challenge).
- [W22] [Microsoft, distribuição de certificados por Group Policy](https://learn.microsoft.com/en-us/windows-server/identity/ad-fs/deployment/distribute-certificates-to-client-computers-by-using-group-policy).
- [W23] [Microsoft, Windows Server 2019 Lifecycle](https://learn.microsoft.com/en-us/lifecycle/products/windows-server-2019).
- [W24] [Microsoft, Visual C++ Redistributable suportado](https://learn.microsoft.com/en-us/cpp/windows/latest-supported-vc-redist?view=msvc-170).
- [W25] [Microsoft IIS, Anonymous Authentication](https://learn.microsoft.com/en-us/iis/configuration/system.webserver/security/authentication/anonymousauthentication).
- [W26] [Microsoft, Windows Firewall e perfis de rede](https://learn.microsoft.com/en-us/windows/security/operating-system-security/network-security/windows-firewall/).
- [W27] [Microsoft, funcionamento do Windows Time Service](https://learn.microsoft.com/en-us/windows-server/networking/windows-time-service/how-the-windows-time-service-works).
- [W28] [Microsoft, repositórios de certificados do computador e do utilizador](https://learn.microsoft.com/en-us/windows-hardware/drivers/install/local-machine-and-current-user-certificate-stores).
- [W29] [WordPress HTTP API, verificação TLS e autoridades de certificação](https://developer.wordpress.org/reference/classes/wp_http/request/).
- [W30] [Microsoft, configuração de firewall para domínios Active Directory](https://learn.microsoft.com/en-us/troubleshoot/windows-server/active-directory/config-firewall-for-ad-domains-and-trusts).

- [W31] [Cursor, download](https://cursor.com/download), [regras e AGENTS.md](https://cursor.com/docs/rules) e [orientação do suporte sobre WSL integrado](https://forum.cursor.com/t/extentions-icons-dont-load-under-the-extention-tab-on-the-sidebar/159019).
- [W32] [Microsoft, instalar WSL](https://learn.microsoft.com/en-us/windows/wsl/install) e [Canonical, instalar Ubuntu no WSL 2](https://ubuntu.com/wsl/docs/stable/howto/install-ubuntu-wsl2/).
- [W33] [Docker Desktop, instalação no Windows](https://docs.docker.com/desktop/setup/install/windows-install/), [integração WSL 2](https://docs.docker.com/desktop/features/wsl/) e [Docker Compose](https://docs.docker.com/compose/install/).
- [W34] [Docker, licença Desktop e entidades governamentais](https://docs.docker.com/subscription-billing/desktop-license/).
- [W35] [Node.js, versões e suporte](https://nodejs.org/en/about/previous-releases), [download](https://nodejs.org/en/download) e [Microsoft, Node.js no WSL](https://learn.microsoft.com/en-us/windows/dev-environment/javascript/nodejs-on-wsl).
- [W36] [Git, instalação no Linux](https://git-scm.com/install/linux) e [git pull](https://git-scm.com/docs/git-pull).
- [W37] [GitHub CLI, instalação Linux](https://github.com/cli/cli/blob/trunk/docs/install_linux.md), [gh auth login](https://cli.github.com/manual/gh_auth_login) e [gh auth setup-git](https://cli.github.com/manual/gh_auth_setup-git).
- [W38] [GitHub flow](https://docs.github.com/en/get-started/using-github/github-flow), [convidar colaboradores](https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/repository-access-and-collaboration/inviting-collaborators-to-a-personal-repository) e [branches protegidas](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches).
- [W39] [Microsoft, sistemas de ficheiros WSL](https://learn.microsoft.com/en-us/windows/wsl/filesystems) e [GitHub, email de commits](https://docs.github.com/en/account-and-profile/how-tos/email-preferences/setting-your-commit-email-address).
- [W40] [npm, npm ci](https://docs.npmjs.com/cli/v11/commands/npm-ci/).
- [W41] [Docker Compose, COMPOSE_ENV_FILES e precedência das variáveis](https://docs.docker.com/compose/how-tos/environment-variables/envvars/#compose_env_files).

### 19.3 Histórico e decisões actuais

Os registos de 8 e 9 de outubro conservam a rastreabilidade. Todas as referências históricas a Panthera-Onca como membro de arn.local e a Cursor como ferramenta obrigatória estão substituídas pelas decisões de 13 de outubro de 2026, DEC-11/14/18. Não descrevem o estado actual.

| Data | Registo | Estado |
| --- | --- | --- |
| 2026-10-08 | WordPress, portal privado e documentação centrada no README | Confirmado pelo responsável do projeto. |
| 2026-10-08 | Google Workspace, infraestrutura ARN e entrega por fases | Confirmado nas perguntas de definição. |
| 2026-10-08 | Acesso apenas rede ARN ou VPN | Confirmado nas perguntas de definição. |
| 2026-10-08 | Catálogo com DRE, DRAJDC, DMAO, DF, DRH, DCSI, DREC, NIC.gw e DCT-Q&S | Confirmado pelo responsável do projeto. |
| 2026-10-08 | Aprovação de notícias por equipa editorial designada | Confirmado nas perguntas de definição. |
| 2026-10-08 | Arquitetura, 46 histórias consolidadas, 12 complementos e backlog inicial | Documentado para orientar desenvolvimento e validação funcional. |
| 2026-10-08 | Alvo inicial .151 e ambiente arn.local na versão 0.2 | A aplicação passou para Panthera-Onca na versão 0.3. .151 permanece como AD DS/DNS. O IP atual do plano está em DEC-11. |
| 2026-10-08 | IIS, PHP FastCGI e MySQL 8.4 LTS | Proposta técnica atualizada para Windows Server 2019. Substitui a referência inicial a Linux/Nginx e MariaDB. |
| 2026-10-08 | DNS interno, HTTPS, pastas Windows, operação e tarefas INF | Planeamento introduzido na versão 0.2, adaptado ao WORKGROUP na versão 0.3, ao IP corrigido na versão 0.4 e ao servidor membro de domínio na versão 0.5. Execução por iniciar. |
| 2026-10-08 | .151 confirmado como controlador de arn.local e Panthera-Onca escolhido para o portal | Decisão do responsável. PEN-13 resolvida quanto à identificação e localização. |
| 2026-10-08 | Resumo anterior de Panthera-Onca em WORKGROUP, com 32 GB reportados | Informação usada nas versões 0.3 e 0.4. A associação ao domínio foi corrigida pelo responsável na versão 0.5. Recursos e coexistência continuam por inventariar. |
| 2026-10-08 | IP de referência corrigido de 192.168.17.204 para 192.168.17.205 na versão 0.4 | Indicação direta do responsável. Arquitetura, DNS, operação e tarefas passam a usar .205. A configuração do servidor não foi alterada nesta entrega documental. |
| 2026-10-08 | Google Workspace já utilizado pela ARN e três contas administrativas indicadas | Registadas na secção 5.4. Perfis locais e ativação serão executados na implementação. |
| 2026-10-08 | Panthera-Onca em 192.168.17.205, membro de arn.local, na versão 0.5 | Correção direta do responsável. Estado histórico da versão 0.5. Substituído por DEC-14 em 13 de outubro de 2026. .151 mantém AD DS/DNS e o portal usa Google Workspace. |
| 2026-10-08 | Definições institucionais, colaboradores e calendário | Adiadas por indicação do responsável nessa revisão. A nova lista e o arranque em dois PCs esclarecem parte dessas pendências em 0.6. |
| 2026-10-09 | Departamentos-ARN.docx, com 37 entradas e 73 nomes | Transcrição pedida pelo responsável, conferida e incluída na secção 4.1. Validações operacionais pendentes na secção 4.6. |
| 2026-10-09 | Desenvolvimento conjunto em computadores separados | Confirmado pelo responsável. Documentados instalações, Git, revisão, primeiro marco local e prompt para o Cursor. Nenhum ambiente instalado nesta entrega. |
| 2026-10-09 | Preparação local com WSL 2, Node 24 e wp-env | Proposta comum aos dois PCs. Licença Docker institucional e diferença MariaDB local/MySQL de homologação explicitadas. |
| 2026-10-09 | Marco local de F1-01 iniciado por Albertina4264 | WordPress 7.1.3, PHP 8.4, MariaDB 11.8 e @wordpress/env 11.17.0 fixados. Plugin e tema mínimos adicionados. WordPress não foi executado neste PC. F1-01 permanece Em curso. |
| 2026-10-09 | Portal privado, autoloader, modelo de unidades e CI | Guarda inicial de páginas, REST e AJAX, catálogo documental das 37 unidades com relações Documental ou Pendente e verificações GitHub. No commit 518ad38, 38 testes PHP e PHPCS aprovados. Sem alteração à secção 4 nem a decisões institucionais. |
| 2026-10-09 | Revisão da fundação para integração na PR #2, a pedido de Atchutchi | Preservados os três commits da colaboradora. Corrigidos os comandos wp-env, bindings, fixture persistida, guarda REST após validação do core, entrada XML-RPC, hierarquia de unidades ativas e aviso local. Evidência e limites na secção 16.6. F1-01 permanece Em curso. |
| 2026-10-13 | Panthera-Onca retirado de arn.local | DEC-11/14 confirmadas pelo responsável. Windows 2019 Standard, 1809, build 17763, 64 bits. Hyper-V instalado, WSL ausente. Substitui referências históricas ao domínio. Sem alteração de servidor nesta sessão. |
| 2026-10-13 | Continuidade através de ChatGPT Work/Codex e GitHub | DEC-18. Cursor opcional. Branch, testes, revisão, PR, main, release e homologação antes da produção. |
| 2026-10-13 | composer.lock regularizado | PR #3 integrada. SHA da branch `a230ffd4e4eee55a9ec67fba8493d50f39f072fe`, merge `2e743c16013e698535e2748bb79c4aa417c5f2b3`. CI Node e PHP aprovada no SHA. |
| 2026-10-13 | Férias anuais repartíveis | DEC-19: 22 dias úteis por ano civil, solicitação prévia e decisão final RH. F2 ainda por iniciar. Pendências funcionais explicitadas em PEN-14. |
| 2026-10-14 | Consolidação após PRs #20 a #22 | F1-08, F1-09 e a parcela local de F1-10 estão integradas em main. PR #22 teve workflow Verificação aprovada no SHA `39b7d0fe349c95e3776e461d211c15a52d76dfe7`. README 1.0 passa a definir a sequência obrigatória para concluir F1, F2, homologação e release. |
| 2026-10-15 | F1-11 integrado pela PR #24 | Decisão, publicação por audiência, avisos e arquivo fictícios revistos e verificados no laboratório. Merge aebf4ced459b4677a163a2d5f95eb3f5c916dbd7 após CI exacto. Homologação institucional pendente. |
| 2026-10-15 | F1-07 implementado na PR #25 em rascunho | Modelo, persistência, páginas, navegação e ensaios exclusivos no candidato 598b897. CI aprovado, activação e Playwright normais ainda não executados. Não está integrado em main. |
| 2026-10-15 | README 1.1 e passagem ALB-F1-07-QA | Pedido do responsável para Albertina continuar na sua branch e devolver a Atchutchi. Preparados guião e prompt, sem mensagem enviada, mudança da branch dela, activação normal ou deploy. |
| 2026-10-16 | Aceitação F1-07 assumida por Codex/Atchutchi | Pedido directo do responsável para executar a tarefa antes preparada para Albertina. Fixes revistos e66a07d/be649bf, activação local, três perfis fictícios e circuito WordPress/Playwright. Entrega separada, sem alterar branch da colaboradora ou produção. README 1.3 referencia também a proposta F1-12 da PR #26. |
| 2026-10-16 | Integração F1-07 e novas tarefas da colaboradora | PR #27 recebida, PR #25 integrada após revisão/CI exacto com ocorrência editorial registada. Issues #28/#29 efectivamente atribuídas a Albertina4264. README 1.4 separa QA no segundo PC, proposta documental F1-13 e continuação central F1-12. Sem deploy ou alteração da branch/laboratório da colaboradora. |

Após cada integração, ambos os colaboradores actualizam main e usam o fluxo da secção 16.6. A validação local de Atchutchi está registada e o lockfile já está em main. Falta repetição no segundo PC e homologação Windows/IIS/MySQL 8.4. O inventário e a validação de .205 continuam em INF-01/02, sem acesso nesta sessão. Panthera-Onca deve permanecer fora de arn.local. Esta sessão não instalou o portal na ARN nem alterou servidores, AD ou DNS. Criou apenas o cliente OAuth local autorizado, sem criar contas Workspace ou alterar MFA.
