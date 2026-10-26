# Supervisão local da fila F1-08

## Entrega

O comando `wp arn media supervise --cycles=3 --interval=30 --limit=1 --user=<operador-ficticio>` executa a fila existente numa sessão CLI finita. Aceita de um a dez ciclos, intervalos de dez a trezentos segundos após cada ciclo e de uma a três entradas por ciclo. Os valores do exemplo são os defaults. Não instala tarefa Windows, daemon ou WP-Cron. A janela/processo CLI tem de permanecer activo durante a sessão. A operação institucional persistente continua por definir.

O operador consulta o estado, contagens dos ciclos confirmados, próximo instante previsto e últimos 25 eventos em Ferramentas > Fila de media. A listagem completa fica no diário privado. Pedir paragem exige POST, nonce, revisão corrente e motivo de dez a trezentos caracteres. O ciclo em curso termina antes da paragem. Uma paragem válida concorrente prevalece na transição seguinte e fica registada como paragem, sem iniciar outro ciclo.

Uma falha de scanner retém a entrada e termina a supervisão, sem analisar as seguintes ou repetir a entrada. A consulta inicial recusa lotes indisponíveis e entradas que ficaram em análise. A supervisão não publica, não conclui envios em nome dos autores, não modifica permissões e não apaga bytes. A conclusão de notícias, documentos e tarefas mantém as condições da entrega anterior.

## Interrupções e auditoria

Um bloqueio de processo impede dois supervisores simultâneos. O bloqueio do worker existente continua a serializar cada ciclo com decisões técnicas e execuções manuais. O diário usa um bloqueio separado, com espera limitada a cinco segundos para tolerar consultas concorrentes. Não há repetição automática de escritas ou operações após falha. A autoridade é revalidada durante a espera entre ciclos e antes dos registos.

Cada evento tem sequência, actor, instante UTC e dados fechados, encadeados por hash. Um marcador exclusivo sincronizado precede a escrita do evento. Marcador sem evento, sequência incompleta, hash divergente, ligação simbólica, hard link ou ficheiro inesperado tornam o diário indisponível. A detecção não protege contra um administrador de ficheiros que reescreva integralmente a cadeia e os marcadores. Não há reparação automática. A capacidade local é de mil eventos e cada início reserva margem para terminar os dez ciclos.

Se o processo sair sem recibo final, o painel apresenta o último estado persistente e explica que não comprova actividade actual. Um novo arranque recusa essa execução. Reconhecer interrupção exige motivo, nonce, revisão e bloqueio de execução livre. Regista a decisão sem recuperar entradas da fila. O operador terá de diagnosticar separadamente qualquer entrada em análise. Uma nova execução é sempre explícita. Nenhum prazo permite roubar o bloqueio de um processo vivo.

## Activação

Além das condições da fila, exige `ARN_ALLOW_LOCAL_MEDIA_SUPERVISOR` exactamente true e `ARN_MEDIA_SUPERVISOR_DIR` com uma pasta privada existente. A pasta fica separada das raízes públicas, da fila, da quarentena e dos ficheiros de entrega. Só o operador fictício configurado pode consultar ou decidir. CLI também consulta a guarda de estado da conta. Não há sessão HTTP artificial ou concessão de competências. Configuração e provas ficam fora do Git.

## Aceitação

Aceitação local em 26/10/2026: PHP 1076 testes e 5416 asserções, com seis omissões anteriores específicas de Windows. Os testes focados da fila/supervisor passaram 36 testes e 159 asserções, com uma dessas omissões. Node 55, lint/build e PHPCS dos sete ficheiros PHP passaram. Playwright aprovou 27 verificações, incluindo envio fictício, ciclos reais, paragem, nonce inválido, campos adicionais, revisão antiga, reconhecimento recusado durante execução, saída sem recibo, novo arranque recusado, reconhecimento auditado, novo arranque explícito, anónimo, autor de notícias, teclado e reflow a 320/390/768/1440 px. As imagens de desktop e móvel foram inspeccionadas.

A revisão independente identificou contenção do diário e corrida entre paragem e avanço. Ambas foram corrigidas com regressões, incluindo dois processos PHP a disputar o bloqueio. A revisão das correcções não encontrou novos achados. A prova HTTP usa o laboratório 8892 e um PDF fictício analisado pelo ClamAV. A saída sem recibo é simulada por `exit` num processo CLI, sem corte físico de energia. Os ensaios injectados de scanner não constituem prova de detecção real de malware.

A comparação final passou 383 asserções. Preservadas 133 tabelas exteriores, 29 tabelas de negócio, 13 linhas de contas e 208 ficheiros anteriores, incluindo os lotes existentes. Criado um lote técnico fictício e dez ficheiros de recepção/processamento. O diário da supervisão é separado e conserva os quatro arranques de aceitação, as paragens, o reconhecimento e os ciclos. Não houve registo de ficheiros de negócio, criação de contas, concessões, alterações à demonstração 8888 ou deploy. As sessões nativas dos logins de teste são alterações esperadas.

Os problemas do guião foram corrigidos sem repetir decisões já confirmadas: contador capturado por valor no teste, NODE_PATH ausente na primeira suite Node, GD ausente na primeira suite PHP, nome da chave da conta técnica e selectores Playwright. Os logs iniciais foram preservados. Evidência privada em media-supervisor-20261026, incluindo snapshots SQL, hashes, logs, screenshots e auditoria. A CI exacta e a integração são rastreáveis na PR, com confirmação posterior de main.

Continuam pendentes previews PDF, manutenção operacional das assinaturas, capacidade/conservação institucionais, recuperação de conclusões de negócio indeterminadas e operação persistente no servidor. Esta parcela não constitui deploy ou homologação de IIS/MySQL.
