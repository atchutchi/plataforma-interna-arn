# Revisão das branches e da fronteira transaccional, 19 de outubro de 2026

Base normal: main 2c77a46a3721b560e75696e036c051b546d49ba8, limpa e sem actualização remota desde a PR40. O utilizador determinou expressamente que não se fizesse merge enquanto houvesse falhas. Nenhuma branch desta revisão foi integrada ou activada no WordPress normal. Não foram enviados comentários, mensagens ou novas atribuições à colaboradora. O prompt é entregue ao responsável para encaminhar.

## Branches relevantes observadas

| Branch remota | Head | Estado em relação a main | Decisão |
| --- | --- | --- | --- |
| fix/albertina-mysql-transaction-state | 12c94a0 | 15 commits só em main e 1 só na branch, PR39 | Revisão anterior recusada. Não retomar o contador como solução aprovada |
| fix/atchutchi-mysql-transaction-guard-v2 | 28304c1 | 26 commits exclusivos, PR41 | Checks MySQL/MariaDB falhados neste head. O corpo da PR ainda descreve resultados de 7da232c |
| fix/albertina-pr41-review-findings | 9cd87c3 | 28 commits exclusivos. Acrescenta 349d08e/9cd87c3 à PR41 | CI verde neste head, mas revisão adicional e ensaios reais falham |
| fix/atchutchi-mysql-transaction-state-v2 | cf33128 | 15 commits exclusivos | Variante não integrada. Não seleccionar pela quantidade de commits |
| fix/atchutchi-mysql-transaction-boundary-v2 | 45ae9c6 | 61 commits exclusivos | Outra variante não integrada. Não foi homologada por esta revisão |
| docs/albertina-f1-13-conciliacao | 4b90341 | Um commit exclusivo, só os três documentos atribuídos | Publicada durante a revisão, CI verde, sem PR aberta. Propõe 35 critérios Por executar |

Os ramos históricos integrados e os worktrees anteriores foram preservados. A composição antiga da PR39 em review/albertina-mysql-39 continua separada. A verificação final encontrou a conciliação da Issue37 publicada em 4b90341, durante esta continuação. A Issue permanece aberta e sem PR formal. Foram confirmados 35 IDs distintos e todos Por executar. A publicação não equivale à revisão ou aprovação da especificação.

## Revisão independente e prova real

Foi revisto o diff integral dos 24 ficheiros entre 2c77a46 e 9cd87c3. O revisor fez 16 ensaios SQL do classificador e três cenários de controlo PHP com doubles mínimos. Não usou base de dados. O controlador extraiu o candidato exacto para uma pasta ignorada, sem mudar os ficheiros usados pelo WordPress normal, e fez as reproduções seguintes contra class-wpdb real e MariaDB 11.8.3 em 127.0.0.1:33317.

Primeira ronda: nove cenários negativos, seis sentinelas confirmadas antes da recusa posterior e limpeza com zero tabelas restantes. Segunda ronda com o reprodutor entregue: doze cenários, dois controlos passaram e dez falharam, incluindo a devolução de um resultado antigo por get_var. Cada cenário recebeu uma ligação dedicada nova. Usaram-se apenas tabelas de prefixo aleatório exclusivo e números fictícios.

| Cenário no candidato 9cd87c3 | Resultado MariaDB observado |
| --- | --- |
| DDL simples e SET autocommit simples, controlos | Recusa anterior à execução, ROLLBACK funcional e sentinela revertida |
| START TRANSACTION# inline | Executado, sentinela anterior confirmada e ROLLBACK pela API recusado |
| ANALYZE# inline seguido de TABLE | Executado e sentinela confirmada |
| /*!99999 SELECT */ CREATE TABLE | Parte condicionada ignorada pelo motor, DDL executado e sentinela confirmada |
| SET com autocommit entre crases | autocommit mudou para 0 e a API recusou ROLLBACK com a transacção activa |
| SET com delimitadores de comentário dentro de literais | Atribuição proibida executada, autocommit 0 e ROLLBACK recusado |
| SET STATEMENT ... FOR CREATE TABLE, só MariaDB | DDL executado e sentinela confirmada |
| IF 1 THEN CREATE TABLE ... END IF, só MariaDB | DDL executado e sentinela confirmada |
| Filtro query transforma SELECT 1 em COMMIT | COMMIT executado e sentinela confirmada |
| Falha injectada apenas em COMMIT | Operação falha mas a transacção fica activa e a API recusa ROLLBACK |
| get_var depois de SAVEPOINT recusado | Devolveu o resultado da leitura anterior |

As variantes BEGIN com comentário, outras formas SET citadas e um filtro que injecte DDL foram confirmadas apenas no classificador ou propostas para testes adicionais. Não são apresentadas como ensaios SQL reais desta entrega. Não se executou MySQL local, MySQL8.4, IIS ou produção.

A primeira ronda usou a variável de estado específica da MariaDB como oráculo privado. O reprodutor entregue evita essa variável e usa SET TRANSACTION READ ONLY directo numa ligação descartável. Sucesso significa ausência e erro 1568 significa transacção activa. Outro erro é desconhecido e falha o cenário. A sonda tem de ser validada no MySQL antes de alegar cobertura nos dois motores. O ROLLBACK directo usado em finally é só limpeza e nunca transforma uma recusa da API num teste aprovado.

## Correcções necessárias na PR41

Os seis grupos P1 identificados são comentários inline, interpretação de comentários condicionais, distinção entre literais/identificadores/comentários em SET, SQL composto de MariaDB, SQL transformado pelos hooks depois da classificação e perda da guarda após COMMIT falhado. O problema de resultados anteriores em get_var/get_row é P2. Referências de código pertencem ao head 9cd87c3: normalizeStatement perto de 405, query perto de 129/130, refuseTransactionCommand perto de 208, atribuições perto de 462 e causas de commit perto de 495. Não aplicar esses números ao main anterior.

Uma nova expressão regular para cada exemplo não demonstra um contrato seguro. Fixar o subconjunto suportado e a validação do SQL efectivamente executado, incluindo o resultado dos filtros. Conservar uma saída de limpeza comprovada após COMMIT falhado e invalidar resultados antigos quando uma operação é recusada. Não conceder PROCESS ou reconectar para ocultar a perda da transacção.

O reprodutor está em tests/integration/transaction-boundary-adversarial-review.php. Exige CLI, ARN_TX_ADVERSARIAL_REVIEW=1, host loopback e as variáveis privadas ARN_TX_TEST_*. Recebe class-wpdb.php no primeiro argumento e a raiz do candidato no segundo. Não depende do código da branch de revisão estar activado no WordPress. Em main ainda não existe mysql-transaction-bootstrap.php, pelo que o candidato tem de ser indicado explicitamente. O código de saída actual é 1, a prova RED de defeitos ainda existentes. O alvo após correcção é 0, mantendo todos os cenários e a limpeza.

O reprodutor é um instrumento de revisão opt-in. Não foi ligado à CI normal para evitar executar SQL com um candidato implícito ou confundir a CI do código de main com a aprovação da PR41. A Albertina deve integrá-lo na sua workflow isolada, com o candidato corrigido e os motores próprios.

A revisão do próprio reprodutor levou a três reforços antes da entrega: raiz candidata obrigatória, leituras observadoras directas por mysqli com resultado/erro/formato validados e confirmação de recusa efectiva e continuidade da guarda no caso get_var. Uma leitura desconhecida nunca significa ausência de sentinela, DDL ou tabelas por limpar. A versão reforçada voltou a produzir 12 cenários, dez falhas e limpeza 0, com stderr vazio. Isto melhora a prova de revisão, sem corrigir a guarda candidata.

## Continuidade e limites

A conciliação F1-13 em 4b90341 foi lida sem mudar os seus três ficheiros. Conserva CA-F113-01 a 32 e acrescenta 33 a 35, todos Por executar. Identificaram-se três lacunas documentais: as quatro tabelas enumeradas não incluem avisos/recibos apesar do contrato prometido, o reenvio de create em CA-F113-17 não tem identidade estável do pedido e a chave com task_id não impede uma tarefa duplicada, e o reenvio de comentário admite «no-op ou CAS» sem resposta determinística. A revisão independente confirmou os três pontos. O prompt pede contratos coerentes e PR formal, preservando o trabalho já publicado. Não se escolheu uma política institucional ou se escreveu schema para resolver a documentação.

A suite PHP do candidato correu com PHP 8.4.26 e OPENSSL_CONF da fixture RSA. Resultado: 849 testes, 3742 asserções e quatro omissões Windows, sem falhas. Isto não cobre os contra-exemplos novos e não é uma aprovação da guarda. Os checks verdes observados em 9cd87c3 são as execuções 39280823095, Transacções MySQL V2, e 39280823147, Verificação. Os resultados históricos do corpo da PR41 referem-se a outro SHA.

O diagnóstico editorial anunciado antes de surgir esta nova entrega ficou adiado. Nenhum ficheiro desse circuito foi alterado. A prioridade actual foi reproduzir os bloqueadores da PR41 e preparar uma entrega executável para a colaboradora. Não se corrigiu produto nem se declarou F1-13 implementada. Os 32 critérios existentes em main continuam Por executar. A conciliação publicada na Issue37 propõe mais três, num total de 35, também Por executar.

Passaram 55 testes Node, lint, build, sintaxe PHP e PHPCS do novo reprodutor. No WordPress fictício separado em 8889, Playwright confirmou login com fornecedor simulado, layouts 375/768/1440, teclado/foco, fontes locais, ausência de overflow a CSS 200%, 16 superfícies protegidas com 403, HEAD sem corpo e cabeçalhos pessoais. Logout e replay da sessão anterior deram o resultado esperado, com 302 para login. Zero erros JavaScript durante essa regressão. Capturas 375/1440 inspeccionadas. Isso verifica o portal já integrado em main, não a guarda candidata ou F1-13. CSS 200% não é zoom nativo e a injecção do antivírus Kaspersky foi observada nas origens de recursos.

O primeiro login fictício falhou porque o processo PHP recém-lançado não herdara OPENSSL_CONF. Um ensaio isolado confirmou geração RSA indisponível sem essa configuração e disponível com ela. Reiniciou-se apenas o processo HTTP criado nesta tarefa, com a configuração de ensaio já existente. Não houve alteração de código, política, prazo de contas ou acesso Google. O erro inicial e as falhas de transporte do executor foram conservados nas provas privadas.

A comparação antes/depois de SQL e browser confirmou 52 tabelas normais sem linhas alteradas, removidas ou acrescentadas, sem novas tabelas e com opções, sessões e configuração iguais. Os 89 originais privados ficaram intactos. As tabelas exclusivas dos ensaios foram eliminadas depois de comprovar as sentinelas. Os commits indevidos dessas sentinelas são falhas reais e não são apagados do resultado por a limpeza ter passado. main permanece 2c77a46 e limpa. Não houve merge ou push em main.

O prompt actualizado está em prompt-albertina-pr41-issue37-2026-10-19.txt. Não autoriza merge, produção, Google, alterações de permissões ou renovação do prazo dos 73 pré-cadastros reais, entretanto expirado. Logs, configuração, cookies e capturas permanecem privados. O estado Git não transporta os dados ou serviços de outro PC.
