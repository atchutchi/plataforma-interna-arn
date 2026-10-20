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
| Composição WordPress, ligações, REST autenticado e adaptação visual | Pendente do percurso Playwright após CI verde |
| Commit, push, revisão e CI no head entregue antes de integrar | Revisão estática sem achados. CI própria exigida antes de merge |

Os testes foram agrupados no fecho. A regressão executada contra os dois ficheiros originais produziu uma falta de método e cinco falhas de comportamento. Depois da implementação, a suite focal passou 16 testes e 118 asserções. A suite PHP completa passou 947 testes e 4638 asserções, com quatro omissões Windows anteriores. Node passou 55 testes, lint e construção. PHPCS passou os sete ficheiros PHP alterados ou novos. MariaDB passou 100 verificações no ensaio Tasks, incluindo dez do novo painel, e removeu as suas contas e tabelas exclusivas. As contas e sessões anteriores do WordPress de ensaio conservaram os hashes.

A revisão independente confirmou a fronteira pessoal, a exclusão de terminais antes da paginação, a projecção fechada e o fecho da ligação. Foi estática. Não substitui o percurso HTTP da composição real.

## Operação e limites

O laboratório estável só recebe o código após CI verde. Não repetir LocalInstaller, criar pessoas ou reconstituir as revisões das três tarefas de demonstração. O novo resumo usa os exemplos fictícios já existentes. Configurações, credenciais, capturas e logs ficam fora do GitHub. A notícia real autorizada separadamente na PR #52 fica preservada.

Esta entrega conclui o resumo pessoal de tarefas no painel local. Não conclui F1-14 institucional, painel de notificações, parâmetros críticos do CMS, acessibilidade certificada, comparação física dos originais ou homologação IIS/MySQL/rede.
