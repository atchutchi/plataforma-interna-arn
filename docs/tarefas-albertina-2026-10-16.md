# Coordenação com Albertina4264, 16 de outubro de 2026

## Estado após revisão para integração, 19 de outubro

A conciliação publicada em docs/albertina-f1-13-conciliacao, head 8634ce6, foi revista como proposta e é incluída na composição de integração. Mantém 36 critérios Por executar. A recepção documental não aprova políticas ou implementação. Os três ficheiros da autora são conservados sem reescrita.

A correcção transaccional fix/albertina-pr41-review-findings avançou para c087aaa. O ensaio MySQL V2 está verde, mas a verificação geral 39337859907 falha nos quatro grupos de integração de identidade, pré-cadastro Workspace, F1-12 e redacção editorial. A revisão confirmou que SQL legítimo com CASE ou literal THEN é recusado. Outros riscos identificados exigem reprodução no motor e não são apresentados como falhas SQL demonstradas. Este candidato e as PRs #39/#41 ficam fora do merge. Não se alterou a branch da autora, o laboratório ou a atribuição das Issues.

O pedido actual autoriza integrar apenas as entregas validadas. Seguir docs/integracao-entregas-locais-2026-10-19.md. Os relatos seguintes conservam os SHAs e resultados anteriores e não substituem este estado.

## Revisão anterior de 19 de outubro

A PR41 em 28304c1 propõe a guarda V2 e a branch fix/albertina-pr41-review-findings em 9cd87c3 acrescenta as correcções 349d08e/9cd87c3. Os checks dessa branch passaram, mas a nova revisão reproduziu dez falhas em doze cenários MariaDB. Não integrar PR39, PR41 ou as variantes experimentais enquanto os bloqueadores persistirem. A conciliação da Issue37 foi entretanto publicada em 4b90341, com 35 critérios propostos Por executar e ainda sem PR. Preservar essa entrega e concluir a revisão dos contratos, sem a refazer. Seguir docs/revisao-branches-pr41-2026-10-19.md e docs/prompt-albertina-pr41-issue37-2026-10-19.txt. O prompt é entregue ao responsável, sem mensagem directa ou nova atribuição à colaboradora. Preservar os commits e entregar a correcção dirigida à base V2 e a conciliação documental em PRs separadas, sem merge.

## Actualização após recepção das entregas

O responsável pediu a revisão, o merge das entregas adequadas e novas tarefas. Foram recebidos o relatório parcial do segundo PC pela PR #34, merge b0a85aed946a47f1c433b286d9bf041291e2cd78, e a proposta F1-13 pela PR #35, merge 0e7ffcd2d485b3c142660a99c2e6d7dd023fa7a9. Os heads 80c7633579682cfe796b383a40bdfeba24598517 e dd15b085b64498f6b36f6c5e53bcde528f36f377 tiveram 12 checks aprovados cada antes do merge. As branches da autora foram conservadas.

O relatório F1-16 documenta falhas no MySQL 8.0.44 e limites de QA. Receber o relatório não significa aceitar todos os percursos nesse PC ou corrigir o produto. A proposta F1-13 tem 32 IDs distintos e todos Por executar. O texto sobre classes F1-12 inexistentes aplica-se à base histórica fffef583, anterior à PR #32. A próxima proposta deve conciliar o main efectivo. Reabertura, competências, fuso e notificações continuam propostas.

Foram criadas e atribuídas à Albertina4264 duas tarefas novas:

| Prioridade | Issue | Branch sugerida | Entrega |
| --- | --- | --- | --- |
| 1 | [#36, ALB-MYSQL-01](https://github.com/atchutchi/plataforma-interna-arn/issues/36) | fix/albertina-mysql-transaction-state | Correcção das fronteiras transaccionais, todos os consumidores afectados, regressão MySQL/MariaDB e PR própria |
| 2 | [#37, ALB-F113-02](https://github.com/atchutchi/plataforma-interna-arn/issues/37) | docs/albertina-f1-13-conciliacao | Conciliar os três documentos F1-13 com a F1-12 integrada, mantendo critérios Por executar e decisões pendentes |

Depois da correcção, retomar a #28 com os percursos bloqueados, REST com nonce da sessão real e zoom nativo separado de CSS. Não apagar falhas anteriores. Não alterar Identity/PreRegistration, GoogleLoginController, WpdbIdentityLinkRepository ou a branch feat/workspace-pre-cadastro-local. Esse trabalho fica com Atchutchi/Codex. Não editar README/AGENTS nas entregas da colaboradora.

O [prompt de continuação após recepção](prompt-albertina-apos-revisao-2026-10-16.txt) substitui o guião inicial para as novas tarefas. As secções seguintes registam a atribuição e o contexto anteriores e não significam ausência actual das entregas já integradas.

Atchutchi autorizou verificar as branches, atribuir novas tarefas à colaboradora e continuar o trabalho central. A consulta remota encontrou a branch chore/albertina4264-F1-01-fundacao em `16890af68736c39d8f6ee7612e4def9ca187f415`, de 9 de outubro. Tinha zero commits exclusivos e estava 116 commits atrás do main anterior `aebf4ced459b4677a163a2d5f95eb3f5c916dbd7`. Não apareceu nova branch ou PR de entrega da colaboradora no repositório consultado. Isto não verifica trabalho ainda não publicado no seu PC ou noutro repositório.

A conta canónica Albertina4264 foi confirmada na lista de pessoas atribuíveis do GitHub. As duas Issues abaixo foram criadas e atribuídas a essa conta, com instruções completas. Não foi enviado email ou mensagem por outro canal. A branch e o laboratório da colaboradora não foram alterados.

| Tarefa atribuída | Branch própria sugerida | Entrega |
| --- | --- | --- |
| [ALB-F1-16-01, Issue #28](https://github.com/atchutchi/plataforma-interna-arn/issues/28) | qa/albertina-f1-16-segundo-pc | docs/qa-segundo-pc-albertina-f1-16.md com ambiente, testes, matriz real, baselines/deltas e limites de zoom/acessibilidade |
| [ALB-F1-13-SPEC, Issue #29](https://github.com/atchutchi/plataforma-interna-arn/issues/29) | docs/albertina-f1-13-tarefas | Especificação proposta de tarefas, critérios CA-F113 por executar e relatório de entrega. Sem implementação |

## Ordem e separação

Começar por verificar se há commits ou alterações locais por publicar. Preservar tudo, sem reset, force push ou exclusão de ficheiros. Informar o responsável sobre trabalho que ainda não apareceu no remoto. Criar cada branch nova a partir do main actualizado depois de confirmar a integração da PR #25. Não é necessário reutilizar a branch antiga da fundação.

A primeira prioridade é a validação no segundo PC. Enquanto faltar runtime ou outro pré-requisito local legítimo, a proposta documental F1-13 pode avançar sem escrever produto. A validação não reinstala o laboratório de Atchutchi. A proposta F1-13 não implementa antes de F1-12 nem decide regras institucionais.

Na tarefa #28, provar os três logins fictícios e percursos People, preservação de contas/concessões/sessões, guardas HTTP/REST, repetição de instalação, loopback e apresentação. Medir zoom nativo separadamente de CSS a 200%. Guardar hashes por nome de opção sem valores, para diagnosticar deltas próprios em vez de presumir a causa do delta de outro PC. Os detalhes e critérios estão na Issue. Ausência dos 22 originais reais implica comparação física Não executada nesse PC, sem copiar ou inventar documentos.

Na tarefa #29, rastrear US-F04/05, US-M02/03, US-S01/03/04 e US-ARN-08/10. Definir a proposta de campos, estados, competência, atribuição/reatribuição, continuidade, prazos, auditoria, CAS, notificações, anexos e superfícies privadas. Reutilizar as acções ReadOwnTask, UpdateOwnTask, AssignUnitTask e ReadUnitTask existentes. Marcar extensões e decisões institucionais como propostas. A PR #26 é referência documental F1-12, sem classes de produto instaladas. Os critérios planeados não são testes executados.

Cada tarefa devolve uma PR contra main ligada à sua Issue. A revisão e integração são do responsável. Sem merge, deploy ou mudanças em README/AGENTS pela colaboradora nesta entrega. Os ficheiros previstos são distintos e não colidem com a continuação central de F1-12.

## Continuação central

Atchutchi/Codex integrou a entrega #27 na branch F1-07, confirmou revisão e os 11 checks no head exacto 7e8ff4a e integrou a PR #25 em main, merge a1a3fbb. A proposta F1-12 na PR #26 continua separada da implementação. A próxima fase central é rever e actualizar essa proposta contra o main efectivo, conciliando o README antes de preparar o plano de implementação. A preparação F1-13 pela colaboradora é paralela apenas em documentação.

Os pendentes locais de zoom nativo e acessibilidade não são homologação de IIS/MySQL, RH ou produção. Nenhuma tarefa autoriza .205/.151, AD, DNS, Google Workspace, emails ou importação de pessoas reais. Segredos, configuração, originais, baselines, dumps e capturas ficam fora do Git.

## Ocorrência CI durante a integração

No SHA `7e8ff4a3606f13b0fb5e4bd32e4b5edc3bfa1748`, a execução push [38886879047](https://github.com/atchutchi/plataforma-interna-arn/actions/runs/38886879047) passou. A primeira execução pull_request [38886885593](https://github.com/atchutchi/plataforma-interna-arn/actions/runs/38886885593) falhou apenas na job de notícias, em news-editorial.php:335, no primeiro processo do ensaio de duas decisões concorrentes. O observador não confirmou a espera real em INNODB_LOCK_WAITS dentro do prazo de 15 segundos. A mensagem foi Falhou: processo concorrente espera num lock real. O log não estabelece se houve recusa antecipada do worker, falha de observação ou outra causa.

O teste, o worker e o adaptador editorial não têm diferenças contra o main anterior. Foi pedida uma reexecução só da job falhada, sem mudar código, aumentar timeouts ou retirar a asserção de concorrência. A ocorrência anterior da PR #26 tinha outra mensagem, durante revogação, pelo que não se afirma uma causa comum. Conservar as falhas e o estado de cada tentativa. Diagnosticar a intermitência com instrumentação que não exponha segredos continua uma pendência técnica, mesmo que a reexecução passe.

A tentativa 2 passou sem alteração de código. Os 11 checks foram confirmados no head 7e8ff4a antes da integração da PR #25. A revisão da composição final também foi aprovada. Não se alega uma correcção da intermitência ou homologação institucional a partir dessa reexecução.

O [prompt de continuação](prompt-albertina-2026-10-16.txt) remete para as duas atribuições actuais. A passagem ALB-F1-07-QA de 15 de outubro permanece histórica e não deve ser executada em duplicado.
