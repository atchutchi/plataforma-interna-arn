# Integração das entregas locais, 19 de outubro de 2026

O responsável autorizou rever as branches e fazer merge das entregas adequadas. A integração parte de main em `2c77a46a3721b560e75696e036c051b546d49ba8` e conserva a autoria e os commits das branches originais. Não activa os novos ficheiros no checkout usado pelo laboratório habitual e não modifica dados, configuração Google ou produção.

## Composição revista

| Origem | Head revisto | Conteúdo |
| --- | --- | --- |
| PR #42 | `2a64585d4ff6b57ccd9ad08f257ea9a9f23ec810` | Relatório histórico e reprodutor transaccional de execução explícita. Não inclui a guarda candidata ou execução automática do reprodutor |
| PR #43 | `10512f969a813528077abc9fae8f672c5393d2d2` | Diagnóstico da concorrência editorial, preservando asserções e falhas anteriores |
| PR #44 | `1c71c51ae2ab562da6a806be01ac4317bbad8ac1` | Serviços com destinos autorizados |
| PR #45 | `8d613ea409fa5ae15e4ac6b22226544799b1dc70` | Projecção autorizada dos menus guardados |
| PR #46 | `a18939cf96ed124995b96ca0b932fdb17164ccd1` | Atalhos autorizados no Início |
| PR #47 | `c9af88dbfa595120565f73b0b55f3b9dcbab2c5b` | Painel inicial com comunicados e eventos autorizados |
| PR #48 | `75387417adafaafbb87b08be4f37fc30847681dc` | Pesquisa integrada sobre as fontes autorizadas |
| PR #49 | `ba3f00b96277df211ca1e8b76558a5eb3fdb912d` | Atalhos de gestão no painel WordPress. Contém a ancestralidade das PRs #43 a #48 |
| docs/albertina-f1-13-conciliacao | `8634ce6526a87679d24b423496e91393c61e381c` | Conciliação documental da Issue #37. Proposta com 36 critérios Por executar |

A composição resolve o conflito de introdução do README conservando a continuidade das parcelas e a versão documental 1.8. Os relatos anteriores passam a ser explicitamente históricos. Os três documentos da conciliação F1-13 são recebidos sem alterações adicionais.

## Entregas excluídas

As PRs #39 e #41 mantêm bloqueadores e não fazem parte desta integração. Também fica excluída a branch `fix/albertina-pr41-review-findings`, head `c087aaa8d882c917e667a6aa7f67b5ec9e2b2467`.

O workflow MySQL V2 `39337820924` desse candidato passou, mas o workflow geral `39337859907` falhou nos quatro grupos de integração de identidade, pré-cadastro Workspace, F1-12 e redacção editorial. A revisão independente executou o classificador PHP desse head e confirmou a recusa de SQL legítimo com CASE, literal THEN e identificador THEN. As divergências apontadas para modos de escape e AND CHAIN carecem de reprodução no motor. Não foram executadas neste processo de integração e não são apresentadas como perda de atomicidade demonstrada neste head.

Os contra-exemplos SQL anteriores conservam a identificação dos heads e motores efectivamente ensaiados nos respectivos relatórios. Não se substituem essas evidências por resultados de outra revisão. Não se alteram as branches da Albertina ou as atribuições das Issues.

## Revisão e validação

A revisão independente da composição de código não encontrou novos bloqueadores P1/P2 entre main e o head da PR #49 ou no instrumento histórico da PR #42. Os leitores conservam as guardas nativas, a autorização antecede contagens e resultados e o adaptador fecha apenas as ligações que criou. A revisão documental confirmou a conciliação dos contratos de notificação, idempotência do pedido e comentários concorrentes. Isto não substitui a aprovação das decisões funcionais da F1-13.

A QA local do head da PR #49 está descrita em `painel-gestao-autorizada-local-f1-15.md`, incluindo Playwright com nove perfis fictícios, o ensaio WordPress/MariaDB de 210 verificações e a limpeza do laboratório separado. A integração acrescenta documentação e o instrumento de revisão, sem alterar esse código de produto. Não é necessário recriar contas ou repetir os mesmos percursos visuais para receber estes ficheiros.

O fecho exige PHPUnit, PHPCS, testes Node, lint, construção e todos os grupos de integração WordPress/MariaDB verdes sobre a composição final. Os resultados e o SHA final ficam na PR de integração e nos checks do GitHub. Uma execução anterior verde de uma branch não autoriza integrar um head posterior por verificar. Nenhuma falha conhecida é ignorada ou convertida em sucesso por repetição sem diagnóstico.

## Continuidade

F1-13 continua sem módulo implementado e com os 36 critérios Por executar. A proposta recebida não autoriza contornar a fronteira transaccional ou resolver decisões de negócio por inferência. A Issue #31 conserva a pendência de atribuir a falha histórica editorial. F1-15 continua parcial, com parâmetros críticos pendentes. As homologações institucionais e de infraestrutura mantêm os seus requisitos.

O merge actualiza main no GitHub e conserva as branches originais. A activação no laboratório habitual, migrações, concessões de acesso e produção não fazem parte desta operação. O checkout principal fica preservado para evitar uma alteração involuntária do processo local que o utiliza.
