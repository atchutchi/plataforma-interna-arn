# Diagnóstico da concorrência editorial, 19 de outubro de 2026

## Âmbito e estado

Continuação da [Issue #31](https://github.com/atchutchi/plataforma-interna-arn/issues/31), por pedido do responsável, na branch `test/editorial-contention-diagnostics`, a partir de `main` em `2c77a46a3721b560e75696e036c051b546d49ba8`. Apenas laboratório local e fixtures fictícias. Não há alterações ao plugin, tema, esquema, contas institucionais ou configuração Google. A PR #41 e a Issue #37 continuam fora desta tarefa. Não fazer merge desta entrega.

Foi reproduzido um mecanismo de observação desactualizada da cache InnoDB e preparado um ajuste causal da frequência de sondagem. A atribuição de cada ocorrência histórica continua por demonstrar, sobretudo a recusa genérica durante revogação. Uma passagem verde ou um erro SQL deliberado não constituem reprodução dessas ocorrências. A Issue #31 permanece aberta.

## Evidência anterior

As ocorrências preservadas na Issue #31 têm sintomas diferentes. Não se concluiu que partilhem uma causa.

| Origem | Evidência | Limite |
| --- | --- | --- |
| PR #26, `d592403` | Run `38873149969`, tentativa 1, recusa durante revogação concorrente com mensagem genérica de persistência | O erro original não foi conservado pelo worker |
| PR #25, `7e8ff4a` | Run `38886885593`, tentativa 1, primeiro worker sem lock observado em 15 segundos | Não há prova de processo vivo, fase ou erro da consulta |
| Main, `a1a3fbb` | Run `38887746270`, tentativa 1, mesma asserção de espera | A tentativa seguinte verde não identifica a causa |
| Revisão posterior, PR #40 | Run de push `38976288219` falhou na asserção de espera, enquanto outra execução do mesmo SHA passou | Não atribuir ao runner sem evidência |

O teste original de `main` passou as 59 verificações na execução de referência deste laboratório. A primeira execução instrumentada também passou. Não foi observada uma nova falha espontânea nestas execuções.

## Observação acrescentada

`EditorialContentionTrace` guarda apenas fases de um catálogo fechado, tempo monotónico e factos técnicos tipados: connection ID inteiro positivo, errno SQL inteiro e indicador booleano de erro SQL. Recusa fases, campos e tipos desconhecidos. Os ficheiros temporários têm nome aleatório próprio e leitura/escrita protegidas por lock. A leitura é limitada a 64 KiB. Registos inválidos ou parciais não são publicados e tornam a prova incompleta.

O worker regista arranque, ligação, snapshot, preparação, libertação pelo pai, início da decisão, entrada e saída da autoridade, autorização e resultado. Um observador do hook `query` guarda os factos técnicos antes do ROLLBACK poder limpar o erro do driver. Devolve a consulta sem a modificar. Nunca guarda a consulta, texto de excepção, credenciais, cookies, argumentos de processo, ambiente ou conteúdo livre de stdout/stderr.

O pai publica eventos JSON `Editorial contention` e `Editorial completion`, com fases, estado vivo/terminado, exit code conhecido ou desconhecido, marcadores, connection ID, observações e estado transaccional permitido. O tempo monotónico ordena fases e mede intervalos. Não representa uma hora civil de auditoria institucional.

A ligação original `$reader` continua a executar a consulta `INNODB_LOCK_WAITS` que decide a asserção. Uma segunda ligação dedicada faz a mesma observação e consulta o estado transaccional para comparação. Não substitui a prova original nem converte uma observação desconhecida em zero. As ligações são separadas, mas partilham a cache de metadados do servidor. Uma discordância ou concordância não prova que o snapshot esteja actualizado.

| Classificação | Significado limitado |
| --- | --- |
| `wait-observed` | A consulta original confirmou uma espera |
| `worker-before-marker` | O pai não obteve um connection ID válido do marcador |
| `observer-query-failed` | A consulta original não devolveu uma contagem válida, por erro ou resultado desconhecido |
| `observers-disagree` | Só a observação adicional confirmou uma espera |
| `worker-exited-before-lock` | Não foi vista espera e o worker já terminou |
| `wait-not-observed` | Não foi vista espera e os dados anteriores não classificam a situação |

Estas classificações são observações e não causas finais. Em particular, ausência de marcador não prova por si só recusa ou falha SQL. Os registos de fases e estado do processo permitem distinguir esses casos.

## Propriedades conservadas

Mantêm-se as 59 verificações originais, os dois processos independentes, os bloqueios reais e a janela de 15 segundos da observação. A prova de duas decisões exige exactamente uma confirmação e uma recusa, com uma única transição. A revogação confirmada sob lock continua a impedir a decisão sem efeitos nas oito tabelas de negócio observadas. Não foram acrescentados retries, skips, aumentos do timeout ou uma decisão editorial sequencial.

Uma consulta adicional, escrita de trace ou leitura de estado pode alterar a temporização do ensaio. Uma execução verde com instrumentação não demonstra desaparecimento da intermitência.

## Mecanismo reproduzido e ajuste da sondagem

O [código da MariaDB 11.8.3, trx0i_s.cc](https://raw.githubusercontent.com/MariaDB/server/mariadb-11.8.3/storage/innobase/trx/trx0i_s.cc), nas linhas 900 a 915 e 1093 a 1098, mantém uma cache comum às três tabelas InnoDB. Cada leitura renova o instante da última leitura. A actualização exige mais de 100 ms sem leitura. Consultas repetidas a cada 50 ms podem conservar um snapshot anterior ao bloqueio.

Um ensaio privado isolado manteve uma transacção detentora de uma linha e iniciou uma escrita assíncrona noutra ligação. Após preparar um snapshot sem espera, 16 leituras rápidas devolveram zero, com intervalo máximo medido no cliente de 47 ms. O escritor continuava pendente. Sem libertar a linha, 160 ms sem consultar essa cache permitiram observar a espera. Só o rollback do detentor permitiu concluir a escrita. A medição do cliente não certifica o intervalo interno do servidor, mas a observação antes/depois manteve o mesmo bloqueio real.

O observador editorial passa a deixar 150 ms entre rondas de leitura, conservando a consulta e a asserção originais e o prazo total de 15 segundos. A mudança respeita a janela de actualização identificada, sem aumentar o timeout ou aceitar uma segunda consulta como prova alternativa.

`npm run test:editorial-observation:integration` mantém esta experiência em SQL real, numa tabela exclusiva. A CI exige o escritor pendente, a espera observada após o intervalo e a conclusão apenas depois da libertação. Os resultados da sondagem rápida são diagnósticos. Não se exige que o escalonador do runner cumpra uma latência máxima ou que a cache fique obrigatoriamente desactualizada. Isto evita introduzir outra falha dependente da carga da máquina. A reprodução negativa observada no laboratório fica preservada separadamente.

## Calibração reproduzível

O comando `npm run test:editorial-diagnostics:integration` executa uma vez cada um dos quatro cenários abaixo, através de subprocessos reais e tabelas/contas exclusivas. Só funciona por WP-CLI em ambiente efectivo local ou development, com `manage_options`. Usa ficheiros temporários para stdout/stderr, evitando o bloqueio de pipes por saídas de erro extensas. Não publica o seu conteúdo livre.

| Cenário | Resultado exigido ao teste editorial original | Prova adicional |
| --- | --- | --- |
| `none` | Exit 0 e 59 verificações | Três esperas originais observadas, uma confirmação/uma recusa e revogação recusada |
| `early-worker` | Exit não zero na asserção original de lock | `worker-before-marker`, processo terminado, fase `refused`, sem entrada na autoridade |
| `observer-query` | Exit não zero na mesma asserção original | `observer-query-failed`, errno 1054 deliberado, observador independente confirma espera e transacção em `LOCK WAIT` |
| `revocation-observer-query` | Exit não zero com a mensagem genérica de persistência | Duas decisões iniciais preservadas, falha 1054 apenas no observador da revogação, fases do hook conservadas e igualdade das quatro tabelas de autorização após rollback |

Os três cenários negativos são falhas injectadas explicitamente através de `ARN_EDITORIAL_DIAGNOSTIC_FAULT`, apenas nos testes. Não são defeitos espontâneos nem resultados editoriais aprovados. Um valor desconhecido é recusado antes de criar fixtures. O caso normal não herda um modo negativo do chamador.

O controlador exige a preservação exacta dos utilizadores e metadados nativos e da lista de tabelas entre casos. Recusa tabelas editoriais preexistentes e exige a sua remoção no final. Cada teste original continua responsável pela limpeza exclusiva dos seus próprios recursos.

A job de notícias mantém o teste editorial original e acrescenta a calibração num passo separado. Uma falha espontânea no passo original continua a reprovar a job. Não há reexecução automática do teste falhado para produzir verde.

Para executar manualmente só um cenário, num laboratório preparado e fictício:

```powershell
$env:ARN_EDITORIAL_DIAGNOSTIC_FAULT = 'early-worker'
npm run test:news-editorial:integration
Remove-Item Env:ARN_EDITORIAL_DIAGNOSTIC_FAULT
```

O comando individual deve falhar. A calibração completa só passa se confirmar essa falha e a prova prevista, além do sucesso do caso normal.

## Continuação: recusa genérica na revogação

Foi consultada a tentativa 1 original de `38873149969`, sem a reexecutar. O stack mostra `revokeGrant` através de `operation` e `silently`, mas não conserva a excepção interior. A leitura do código confirma que uma excepção do hook de COMMIT do teste também atravessa esses wrappers e termina em «Persistência de autorização indisponível.». A mensagem isolada não permite concluir que houve erro SQL na escrita da revogação.

O cenário `revocation-observer-query` injecta a consulta inválida apenas na última observação. As duas primeiras decisões continuam realmente concorrentes e confirmam uma transição. O erro de observação faz falhar a asserção do teste dentro do hook. A operação reverte a revogação e devolve a mensagem genérica. A reprodução controlada demonstra este percurso possível. Não estabelece retrospectivamente a causa da tentativa histórica.

O trace do pai acrescenta um catálogo fechado de fases de revogação. Regista o errno do observador antes da ocultação, sem publicar SQL ou excepções livres. A comparação depois da recusa exige igualdade das quatro tabelas de autorização, incluindo concessões, revisão e auditoria. As fases e os factos booleanos são publicados pelo controlador antes das suas próprias asserções, para não perder novamente a prova em caso de falha na CI. O trace normal do pai acompanha a conclusão da revogação. Os temporários próprios são removidos.

Depois de uma revogação falhada, a concessão anterior continua válida. O worker pode concluir legitimamente uma decisão quando o rollback liberta o lock. Por isso, `news_snapshot_equal_after_refusal` é somente uma observação e não um requisito de igualdade de negócio neste cenário negativo. Mantém-se integralmente a asserção original das 59 verificações: uma revogação confirmada tem de impedir a decisão sem efeitos nas oito tabelas de negócio.

A selecção manual `ARN_EDITORIAL_DIAGNOSTIC_ONLY=revocation-observer-query` permite executar apenas esta calibração durante o diagnóstico. Valores desconhecidos são recusados. A CI não define este selector e executa os quatro casos. A primeira execução do novo cenário falhou por ausência do modo e a nova fase do teste unitário foi recusada antes da implementação. Depois da implementação, o cenário passou e os sete testes de trace passaram com 24 asserções. Isto é uma calibração nova, não uma reexecução da falha histórica.

A execução local dos quatro cenários passou. Depois da correcção do relatório anterior às asserções, o cenário novo foi executado novamente e confirmou erro 1054, espera secundária, trace completo e autorização restaurada. Node manteve os 55 testes aprovados e lint/build passaram. PHPCS passou nos quatro ficheiros PHP alterados. A revisão independente ficou sem achados por corrigir. As 17 tabelas fictícias e a configuração ficaram exactamente iguais, sem contas, tabelas ou ficheiros temporários editoriais restantes. No laboratório normal, mantiveram-se configuração, sessões e os 89 ficheiros privados. Só os dois transientes de padrões do tema foram removidos/reinseridos em `wp_options`, sem ocultar o delta.

## Validação desta entrega

Laboratório portátil Windows, WordPress 7.1.3, PHP 8.4.26 e MariaDB 11.8.3. O ensaio SQL usou um WordPress fictício separado, em loopback `localhost:8889`, com prefixos aleatórios exclusivos e bloqueio de correio/cron na configuração CLI herdada pelos subprocessos. Não foi executado sobre as contas Workspace reais. Este ambiente não comprova IIS ou MySQL 8.4.

| Verificação | Resultado observado |
| --- | --- |
| Teste editorial original, antes da instrumentação | 59 verificações, exit 0 |
| Teste editorial instrumentado | 59 verificações, três esperas reais observadas, uma confirmação/uma recusa e revogação recusada |
| Recusa precoce isolada | Exit 1, `worker-before-marker`, worker terminado e trace completo com `refused`, sem entrada na autoridade |
| Consulta observadora inválida isolada | Exit 1, `observer-query-failed`, errno 1054, observador independente em espera e estado `LOCK WAIT` |
| Controlador completo após formatação final | Exit 0, caso normal aprovado e dois casos negativos reprovados com a prova exigida, preservação nativa confirmada entre casos |
| Ajuste de sondagem para 150 ms | Novo controlador completo aprovado. Experiência SQL pública com 16 amostras rápidas a zero, escritor pendente, espera observada após intervalo e conclusão apenas após libertação. A comparação final conservou exactamente as 17 tabelas fictícias e a configuração, sem contas/tabelas editoriais temporárias ou correio bloqueado registado |
| Regressão unitária do trace | RED inicial com seis falhas por ausência da implementação, depois seis testes e 20 asserções aprovados |
| Suite PHP completa | 853 testes, 3758 asserções e quatro omissões existentes por symlinks indisponíveis no Windows |
| Node, lint e build | 55 testes Node aprovados, lint e build aprovados |
| PHPCS | 392 ficheiros na primeira entrega, exit 0. Os três ficheiros PHP alterados/acrescentados no ajuste passaram novamente |
| Revisão independente | Sem achados concretos por corrigir, com a causa histórica explicitamente fora do que os dados provam |
| Playwright em `localhost:8889` | Login OIDC simulado, portal sem competências, três larguras 375/768/1440, texto a 200%, foco por teclado, 16 superfícies recusadas, cabeçalhos privados, logout e cookie anterior recusado por redireccionamento 302. Sem erros JavaScript na vista testada |

As capturas de browser ficaram privadas e foi inspeccionada visualmente a vista móvel. Este percurso valida uma regressão do portal fictício existente. Não executa os processos concorrentes SQL dentro do browser e não comprova autenticação Google real ou publicação institucional.

Antes do browser, a comparação das 17 tabelas da instalação fictícia conservou todas excepto `options`, onde apareceram dois transientes nativos de cache dos padrões do tema. A configuração ficou igual. Não restaram contas ou tabelas editoriais. O controlador confirmou separadamente a igualdade exacta de utilizadores, usermeta e lista de tabelas após cada cenário. Não se apagaram transientes para fabricar igualdade.

A comparação do laboratório normal preservou a configuração, sessões e os 89 ficheiros privados. Nas 52 tabelas não houve novas tabelas ou alterações a linhas anteriores existentes. Foram removidas e reinseridas duas linhas de `wp_options`, correspondentes à renovação dos dois transientes de cache de padrões do tema. As restantes opções ficaram iguais. Esta diferença é conservada na prova privada e não é descrita como preservação integral das 52 tabelas. A primeira tentativa desta captura foi recusada pela guarda da instalação fictícia aplicada por engano ao comando normal. A captura final usou o contexto normal correcto. Não se repuseram linhas, revisões ou sessões para esconder diferenças.

Os logs completos ficam fora do Git, numa pasta privada desta tarefa. Não copiar dados identificáveis, configurações ou snapshots para a PR. A CI Linux no SHA entregue é uma verificação separada e o seu estado pertence à PR. Mesmo com CI verde, o pedido actual continua a proibir merge e a causa histórica mantém-se pendente.

Primeira CI, SHA `c892b0a`: a execução de push [39312608811](https://github.com/atchutchi/plataforma-interna-arn/actions/runs/39312608811) passou. Na execução da PR [39312638769](https://github.com/atchutchi/plataforma-interna-arn/actions/runs/39312638769), o teste editorial original passou as 59 verificações. A nova calibração passou `none` e `early-worker`, mas falhou `observer-query` na condição conjunta que exige espera secundária, estado `LOCK WAIT` e worker vivo. O controlador ainda não publicava esses factos antes da asserção, pelo que não é possível determinar retrospectivamente qual dos três faltou. Não se atribui essa tentativa à cache como facto provado e não se reexecutou a tentativa para a substituir por verde.

O ajuste seguinte acrescenta factos booleanos fechados antes dessa asserção, a experiência SQL e o intervalo de 150 ms. A revisão independente identificou uma exigência inicial inadequada de intervalo máximo inferior a 100 ms na experiência. Essa exigência foi retirada antes da entrega do ajuste. O controlo positivo do bloqueio e da libertação mantém-se obrigatório. Os resultados posteriores são novos ensaios de código alterado, preservando a tentativa vermelha anterior.

## Próximo passo

Conservar esta instrumentação na branch e PR de diagnóstico, sem merge nesta sessão. Rever a CI no SHA entregue. Na próxima falha espontânea, guardar a tentativa original e os eventos dos três cenários de concorrência antes de qualquer reexecução manual. Correlacionar o connection ID do marcador com a fase, errno anterior ao rollback, estado do worker e ambas as observações.

O mecanismo de cache já tem um contra-exemplo controlado e um ajuste no teste. Falta correlacionar uma falha espontânea com a prova completa, distinguir eventuais outras causas e investigar a recusa histórica durante revogação. Manter a Issue #31 aberta. A correcção transaccional da PR #41 e a conciliação F1-13 da Issue #37 continuam nas entregas da Albertina, sem antecipar alterações de produto nesta branch.
