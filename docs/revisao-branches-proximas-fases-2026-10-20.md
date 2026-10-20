# Revisão das branches e continuação

Inventário de 20 de outubro de 2026, antes da integração da PR #56. Main recebido: 3fd73027ae0358935c05ba33264f13a10b701640, com os dois workflows aprovados. Foram consultadas 59 referências remotas, excluindo origin/HEAD. Quarenta e sete eram ancestrais de main e doze não tinham ancestralidade directa. A falta de ancestralidade não significa que falte integrar o conteúdo: existem entregas recebidas por squash.

## Decisão por referência pendente

| Branch | Head observado | Situação e decisão |
| --- | --- | --- |
| docs/albertina-f1-13-tarefas | dd15b08 | PR #35 integrada. Preservar a branch, sem repetir a proposta histórica |
| docs/f1-12-spec-acceptance | d592403 | PR #26 fechada. A F1-12 já tem implementação, critérios e QA em main |
| feat/f1-10-news-submission | 39b7d0f | PR #22 integrada. Sem novo merge |
| feat/f1-12-comunicados-calendario | aa9645c | PR #32 integrada. Sem novo merge |
| fix/albertina-mysql-transaction-state | 12c94a0 | PR #39 em conflito com main. Proposta antiga, substituída pela continuação revista. Não integrar |
| fix/albertina-pr41-review-findings | c087aaa | Parcela histórica aproveitada selectivamente e corrigida na PR #51. Não promover a árvore antiga |
| fix/atchutchi-mysql-transaction-boundary-v2 | 45ae9c6 | Ramo experimental histórico sem PR aberta. Nenhuma integração nesta revisão |
| fix/atchutchi-mysql-transaction-guard-v2 | 28304c1 | PR #41 em conflito com main. Versão antiga da guarda, substituída pelo trabalho integrado. Não integrar |
| fix/atchutchi-mysql-transaction-state-v2 | cf33128 | Ramo experimental histórico sem PR aberta. Nenhuma integração nesta revisão |
| fix/sql-boundary-separated-system-prefix | aca747d, depois 071d0cb | PR #56 revista. Candidata à integração após composição com main, QA e CI exacta |
| fix/sql-boundary-set-scopes | b760935 | PR #53 fechada como substituída. A PR #54 contém a correcção revista |
| qa/albertina-f1-16-segundo-pc | 80c7633 | Relatório recebido pela PR #34. O ficheiro histórico já existe em main. Não repetir nem converter falhas antigas em resultados actuais |

As branches e PRs antigas ficam preservadas. Não foram apagadas, reescritas ou fechadas nesta revisão. Os conflitos e a evolução posterior impedem um merge automático das PRs #39/#41. A diferença de duas pontas entre uma branch antiga e main não é um patch para substituir o estado actual.

## PR #56

O diff de integração contém quatro linhas de produto em SqlBoundary.php e 23 linhas de testes. Remove o token @@ separado antes de verificar o âmbito GLOBAL/PERSIST/PERSIST_ONLY. Mantém os controlos positivos de SESSION, LOCAL, variáveis do utilizador e SET TRANSACTION sem âmbito. Nenhum dos três ficheiros da proposta tinha sido alterado pelas PRs #57/#58.

A revisão independente foi estática e não encontrou achados. A composição com main foi preparada na branch review/pr56-integracao-main-20261020 e publicada na branch da própria PR, sem force push. O head composto 071d0cb814397743aa3fec5836d7bc5c9a01616a recebeu 20 checks aprovados, com as três execuções de workflows efectivamente ligadas a esse SHA.

PHP passou 955 testes e 4795 asserções, com quatro omissões Windows anteriores. Node passou 55 testes, lint e construção. PHPCS passou os três ficheiros alterados. MariaDB 11.8.3 passou 509 verificações complementares, 80 da guarda, 292 da revisão e 10 People. As 40 tabelas de negócio da demonstração conservaram os hashes. Os ensaios usaram tabelas exclusivas e limparam os seus próprios prefixos. Não foi executada uma alteração global para demonstrar o achado. A proposta original documenta que a MariaDB rejeita a sintaxe separada.

O laboratório só recebe o ficheiro de produto após CI verde. O fecho HTTP, a CI do head final e o resultado de integração permanecem rastreáveis na [PR #56](https://github.com/atchutchi/plataforma-interna-arn/pull/56). Não constitui homologação institucional ou IIS/MySQL de produção.

Após os 20 checks aprovados em 071d0cb, foi aplicado apenas SqlBoundary.php à demonstração. Os 314 ficheiros WordPress versionados coincidiram com o candidato. Playwright passou 18 verificações com quatro perfis fictícios e os percursos de painel, detalhe pessoal de tarefa, comunicados, calendário, CMS editorial e administração técnica. O recibo fictício anterior permaneceu lido. As 40 tabelas de negócio conservaram os hashes antes/depois do circuito HTTP. Não houve instalação ou alteração de concessões.

## Próximas fases

A próxima implementação é F1-15, parâmetros autorizados e auditados do CMS. O [escopo e os critérios preparados](parametros-cms-local-f1-15.md) distinguem o mecanismo técnico da atribuição institucional de competências. F1-15 continua parcial até à implementação, testes e aceitação dessa parcela.

Depois seguem as lacunas comuns F1-08/09/10 de media e ficheiros, F2-01/02 pedidos e decisões, F2-03 férias, F2-04 suporte e F2-05/06 painel/pesquisa/notificações. A aceitação final F1-16, a infraestrutura representativa e F1-17/release ficam no fim, conforme a sequência obrigatória do README. Os ensaios de segurança por módulo continuam durante o desenvolvimento. Não se declara F1/F2 ou o site completo concluído com estas integrações.
