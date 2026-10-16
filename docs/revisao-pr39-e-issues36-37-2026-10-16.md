# Revisão da PR39 e estado das Issues36/37

16 de outubro de 2026. Responsável pela revisão: Codex/Atchutchi. Base normal 8b1bf8ddd6d67b00cc652b247248ef86d003c9d4. Candidato da Albertina: PR39, head 12c94a0b6b86db65a0e846871fc6767420ee4f61, branch fix/albertina-mysql-transaction-state. Merge-base 0e7ffcd. A composição foi preparada num worktree de revisão, sem mudar main ou a branch remota da colaboradora.

## Estado observado no GitHub

A Issue36 permanece aberta e tem a PR39. Os checks observados estavam aprovados, incluindo o workflow MySQL8.0.44 e as integrações MariaDB. Isso não basta para aceitar a garantia transaccional.

A Issue37 permanece aberta, sem comentários ou PR publicada na consulta desta entrega. Não existe entrega remota da branch docs/albertina-f1-13-conciliacao. A proposta F1-13 recebida na PR35 continua histórica e não conciliada. Ausência de publicação não demonstra ausência de trabalho no computador da colaboradora. Não se alterou esse computador, stash ou branch.

## Veredicto

Não integrar a PR39 neste head. A API única é uma boa direcção e a ligação continua sem reconexão automática. Contudo, o booleano calculado pode divergir do estado efectivo do servidor. Os consumidores usam esse booleano para decidir se podem começar uma transacção ou juntar gravações à existente. Um falso positivo pode separar referência e auditoria em commits diferentes. Um falso negativo pode permitir um START que confirma trabalho exterior.

A revisão independente classificou quatro grupos de defeitos de produto como Important/P1 e lacunas de cobertura como Important/P2. A concorrência também precisa de reforço: duas escritas atómicas sequenciais produziriam o mesmo resultado final do ensaio actual. Não há aprovação da PR ou activação do candidato neste laboratório.

## Reprodução em servidor real

Carregou-se IdentityWpdb do candidato, com class-wpdb do WordPress7.1.3 e ligação dedicada à MariaDB11.8.3 em loopback. O oráculo SELECT @@session.in_transaction foi usado apenas no ensaio MariaDB para comparar o estado real. Não é uma proposta de implementação portável.

Cada cenário teve uma ligação nova e uma transacção explícita. Só se criaram tabelas de prefixo exclusivo arn_pr39 com sufixo aleatório. Os identificadores e valores eram fictícios. O cenário de DDL inseriu uma linha sentinela antes de provocar o commit implícito. Verificou-se a sua presença depois do ROLLBACK. Não houve escrita nas tabelas normais de contas, Workspace ou negócio. A limpeza confirmou zero tabelas do prefixo.

| Cenário depois de START | API do candidato | Servidor | Resultado |
| --- | --- | --- | --- |
| SET autocommit=1 quando já era1 | false | transacção activa | Divergência |
| COMMIT AND CHAIN | false | transacção activa | Divergência |
| CREATE TEMPORARY TABLE | false | transacção activa | Divergência |
| DDL em comentário executável MySQL | true | sem transacção | Linha sentinela permaneceu após ROLLBACK |
| DDL directo pelo handle mysqli | true | sem transacção | Segunda linha sentinela permaneceu após ROLLBACK |
| ROLLBACK externo seguido de SET autocommit=0 pela API | true | sem transacção | A resincronização absorveu a alteração externa |

A prova é MariaDB real, não MySQL8.4 ou IIS. O revisor também executou oito reproduções do algoritmo com doubles, incluindo ROLLBACK WORK TO SAVEPOINT e rollback por deadlock. Estes dois últimos não foram reproduzidos contra servidor nesta revisão. Não confundir as duas camadas de evidência.

## Pontos a corrigir no candidato

As referências seguintes pertencem ao head 12c94a0, não ao ficheiro de main anterior.

1. IdentityWpdb::inTransaction, linhas 153-168, e observe, perto de 209: autocommit e contagens Com_* não demonstram que a transacção ainda existe. DDL externo e rollback integral por deadlock precisam de recusa ou prova efectiva do estado.
2. resyncSessionCounters, perto de 256: adopta todas as contagens sem confirmar o incremento esperado. Não apagar evidência de comandos externos ao aceitar um novo snapshot.
3. observe e classify: SET autocommit=1 redundante, tabelas temporárias, CHAIN, completion_type e ROLLBACK WORK TO SAVEPOINT não têm a semântica actualmente assumida. Preservar o estado efectivo ou recusar antes de anunciar um resultado seguro.
4. classify, perto de 370: remover comentários executáveis e tratar instruções administrativas como other pode esconder um commit. A classe diz que recusa o desconhecido, mas other conserva state_known.
5. mysql-transaction-state: o caso nested não tem escrita sentinela. Dois START podem confirmar a primeira transacção e o booleano continuar true. Provar reversibilidade dos dados, COMMIT falhado e perda real de ligação dentro da transacção, não apenas close.
6. mysql-transaction-worker e controlador: o marcador waiter antecede UPDATE e o holder é logo libertado. Provar disputa efectiva pelo lock antes de libertar a barreira, sem confundir dois processos com concorrência observada.

Não basta ampliar expressões regulares ou adicionar outro contador PHP. A solução tem de comprovar o estado da ligação ou recusar aquilo que não consegue garantir, preservando dados e auditoria. Não conceder PROCESS, introduzir reconexão ou relaxar testes para resolver o problema.

## Regressões e limites

A composição do candidato com main passou 849 testes PHP e 3742 asserções, com quatro omissões Windows anteriores. OPENSSL_CONF foi configurado para a fixture RSA. Esses testes não cobriam os seis contra-exemplos SQL. Portanto, não se escreve que todos os critérios da Issue36 passaram.

Os ensaios Playwright desta entrega cobrem apenas o portal de preparação já integrado, com fornecedor simulado e conta fictícia na instalação separada localhost:8889. Passaram os layouts 375/768/1440, teclado com foco visível, fontes locais, ausência de overflow a CSS 200%, 16 superfícies protegidas com 403, HEAD sem corpo, cabeçalhos pessoais private/no-store e zero erros JavaScript. O logout terminou a sessão e o replay do cookie anterior devolveu 302 para login. As capturas foram inspeccionadas. CSS 200% não é zoom nativo. As únicas origens de recursos observadas foram localhost e a injecção do antivírus Kaspersky. Não se activou ou aprovou a PR39 e não se testou um módulo F1-13 inexistente. Provas, cookies e capturas ficam privados.

A navegação inicial ultrapassou os cinco segundos do comando click, mas o snapshot seguinte confirmou a chegada ao portal autenticado. A chamada run-code com código como argumento sofreu um erro de transporte/quoting do PowerShell. A execução por ficheiro passou. Os 55 testes Node, lint e build passaram depois de configurar NODE_PATH para as dependências já instaladas. Não houve alteração de produto para corrigir estes problemas do executor.

A comparação antes/depois da regressão do browser confirmou 52 tabelas normais, zero linhas alteradas, removidas ou acrescentadas, zero tabelas novas, configuração e sessões iguais, opções iguais e os 89 originais privados intactos. Esta comparação não pretende provar a reversibilidade dos commits implícitos nas tabelas exclusivas do ensaio SQL, que foram demonstrados e limpos separadamente.

A disponibilidade remota da Issue37 foi consultada. Não se inspeccionaram dados locais da Albertina. Reconexão explícita pedida pelo chamador ficou fora do requisito de ausência de reconexão automática. Não se alteraram permissões institucionais, Google, os 73 pré-cadastros reais ou os 89 originais.

## Passagem para continuação

A Issue36 continua a ser a atribuição de correcção transaccional da Albertina. A Issue37 continua a ser a sua conciliação documental. Esta revisão não assume que estão concluídas nem substitui a aprovação futura da especificação F1-13. O próximo trabalho independente foi preparado em preparacao-tecnica-f1-13-2026-10-16.md, sem editar os três documentos reservados à colaboradora.
