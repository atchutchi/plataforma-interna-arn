# Tarefas pendentes no painel inicial

Entrega local de 20 de outubro de 2026, na branch feat/painel-tarefas-autorizadas, a partir de main 51ecf264f090732b36997746aa8f95d1a655e5de. Prossegue US-F02 e US-M02 da F1-14 depois da integração de tarefas pela PR #55. Não altera contas Workspace, produção, concessões ou a instalação dos módulos.

## Comportamento e autorização

O Início acrescenta Tarefas pendentes às secções de comunicados e eventos. Mostra até três tarefas atribuídas ao próprio utilizador, por iniciar ou em curso. Ordena por prazo crescente e depois por ID. Tarefas sem prazo ficam no fim. Exclui concluídas e canceladas antes da paginação. Ter competência de gestão de equipa não transforma esta secção numa lista de terceiros.

ServiceDirectory tem de oferecer tasks_own antes de HomeDashboard consultar a fonte. TaskPortal usa a mesma sessão, conta, concessões, unidade e projecção da lista pessoal. TaskSelection::pending é uma selecção interna fixa. Não acrescenta um valor aceite nos filtros externos nem usa parâmetros GET do Início. Uma única leitura do repositório decide a selecção dentro da transacção. O Portal temporário fecha a ligação dedicada.

A projecção contém apenas título, destino do detalhe próprio, estado, prioridade, prazo UTC e indicação de atraso. Não contém descrição, comentários, motivos de gestão, autor, responsável, revisão, tokens, contagens ou recibos. A consulta não marca avisos como lidos nem escreve auditoria ou tarefas. A ligação abre o detalhe já protegido pelo módulo.

Sem pendentes, a secção apresenta Não tens tarefas pendentes neste momento. Sem acesso ou com falha da fonte, apresenta o estado genérico de indisponibilidade e não oferece a ligação à lista. As outras secções mantêm as suas fontes independentes.

## Apresentação

Preserva logo, fontes, cores e componentes institucionais, segundo PRODUCT.md e DESIGN.md, Impeccable e UI UX Pro Max. As três secções usam linhas editoriais em três colunas no espaço amplo e uma coluna até 64rem. Estado, prioridade e atraso têm texto explícito. Os destinos mantêm área de interacção de 44px, escape e títulos acessíveis independentes.

## Critérios e evidência

| Critério | Evidência actual |
| --- | --- |
| Seleccionar pendentes antes da paginação, incluindo muitos terminais anteriores | Regressão pura com 25 tarefas e SQL com 21 terminais anteriores |
| Máximo de três, ordem por prazo e desempate por ID, sem campos privados | Testes puros, rendering e integração SQL |
| Só próprias, outra unidade isolada, reatribuição e suspensão revalidadas | Integração SQL no prefixo exclusivo, sem excepção administrativa |
| Leitura sem escrita em tarefas, comentários, auditoria ou recibos | Comparação das tabelas antes/depois em SQL |
| Vazio distinto de indisponível e fontes independentes | Testes puros e rendering |
| Composição WordPress, ligações, REST autenticado e adaptação visual | Playwright passou 23 verificações em 6144149, com quatro perfis Tasks/leitura e conservação da notícia |
| Commit, push, revisão e CI no head entregue antes de integrar | PR #57. Código 6144149 passou 20 checks. O head final exige CI própria antes do merge |

Os testes foram agrupados no fecho. A regressão executada contra os dois ficheiros originais produziu uma falta de método e cinco falhas de comportamento. Depois da implementação, a suite focal passou 16 testes e 118 asserções. A suite PHP completa passou 947 testes e 4638 asserções, com quatro omissões Windows anteriores. Node passou 55 testes, lint e construção. PHPCS passou os sete ficheiros PHP alterados ou novos. MariaDB passou 100 verificações no ensaio Tasks, incluindo dez do novo painel, e removeu as suas contas e tabelas exclusivas. As contas e sessões anteriores do WordPress de ensaio conservaram os hashes.

A revisão independente confirmou a fronteira pessoal, a exclusão de terminais antes da paginação, a projecção fechada e o fecho da ligação. Foi estática. Não substitui o percurso HTTP da composição real.

## Fecho da demonstração

Depois dos 20 checks aprovados em 6144149, o laboratório recebeu apenas os quatro ficheiros de execução alterados. Os 311 ficheiros WordPress versionados ficaram com hashes iguais aos do commit. Não houve instalação, criação de contas, alteração de concessões ou reposição dos exemplos.

Playwright passou 23 verificações. Alfa recebeu duas pendentes próprias. O gestor com uma tarefa concluída e Beta sem pendentes receberam o estado vazio. Um leitor F1-12 sem Tasks recebeu indisponibilidade, sem ligação pessoal. Parâmetros externos não alteraram a consulta fixa. A ligação abriu o detalhe próprio por teclado. REST confirmou primeiro a identidade autenticada e o ID real da página inicial, ambos com 200, e depois a ausência dos títulos das tarefas na projecção REST.

As larguras 320/390/768/1440 passaram sem transbordo, com H1 único e alvos das tarefas de pelo menos 44px. As capturas móvel e ampla foram inspeccionadas. Texto CSS a 200% também não produziu transbordo no computador. Isto não prova zoom nativo ou certificação de acessibilidade. A medição adicional encontrou 43,2px na ligação de cabeçalho à lista. O fecho acrescenta a altura mínima de 44px, a confirmar depois da nova CI e aplicação do CSS final.

As 39 tabelas de negócio da demonstração, incluindo tarefas, comentários, auditoria, notificações, autorização e os restantes módulos presentes, conservaram exactamente os hashes antes/depois de todo o percurso. As sessões nativas das contas fictícias tiveram os logins de QA previstos e não fazem parte dessa alegação de igualdade. A notícia do Fórum e as dez fotografias ficaram conservadas. Dados concretos, cookies, nonce, credenciais e capturas permanecem privados.

## Operação e limites

O laboratório estável só recebe o código após CI verde. Não repetir LocalInstaller, criar pessoas ou reconstituir as revisões das três tarefas de demonstração. O novo resumo usa os exemplos fictícios já existentes. O SHA entregue e a CI final são consultáveis na [PR #57](https://github.com/atchutchi/plataforma-interna-arn/pull/57).

Esta entrega conclui o resumo pessoal de tarefas no painel local. Não conclui F1-14 institucional, painel de notificações, parâmetros críticos do CMS, acessibilidade certificada, comparação física dos originais ou homologação IIS/MySQL/rede.
