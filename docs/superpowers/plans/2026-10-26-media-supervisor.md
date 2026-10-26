# Supervisão local da fila

Execução directa com superpowers:executing-plans. Base a454fbc. O pedido de continuar autoriza a próxima parcela do README e conserva laboratório fictício, testes agrupados no fecho e merge apenas com CI verde.

Objectivo: executar ciclos espaçados do worker existente, com exclusão entre supervisores, auditoria privada, estado visível ao operador e paragem cooperativa. Não publicar conteúdo, recuperar lotes, apagar originais ou instalar serviços persistentes do sistema.

Arquitectura: supervisor PHP invocado por WP-CLI, entre 1 e 10 ciclos, entre 10 e 300 segundos de espera depois de cada ciclo e entre 1 e 3 entradas por ciclo. Flags locais explícitas e pasta privada independente. Diário limitado a 1000 eventos com marcadores prévios e cadeia de hashes. Falha interrompe o supervisor. Uma execução abandonada exige reconhecimento explícito com motivo e bloqueio livre, sem recuperar entradas da fila. O estado técnico não é aprovação de negócio.

Alternativas: WP-Cron depende de pedidos HTTP e mistura a execução com o portal. Uma tarefa Windows persistente exige política de operação institucional. Nesta parcela usa-se sessão CLI finita e supervisionada, compatível com o laboratório e sem essas dependências.

## Tarefas

- [x] Implementar estado, diário e supervisor, com revalidação de autoridade por ciclo e durante a espera.
- [x] Integrar comando CLI e painel existente com paragem e reconhecimento auditados, nonce e revisão.
- [x] Agrupar testes PHP, regressões Node/lint/build, Playwright e preservação de dados no laboratório 8892.
- [ ] Revisão independente, documentação factual, commit/push, CI exacta, merge e confirmação de main.

## Foco de revisão

Concorrência entre dois supervisores e paragem durante análise. Truncagem, escrita parcial ou substituição dos ficheiros de auditoria. Revogação da conta técnica durante espera. Falha de scanner sem repetição automática. Processo interrompido sem recibo final e nenhuma retoma silenciosa. Estados HTTP recusados para contas não autorizadas. A pasta de supervisão não ocupa a capacidade de lotes.

## Decisões de execução

Os testes ficam no fecho por instrução do responsável. A autorização anterior cobre a aplicação no laboratório fictício, commit, push e merge condicionado. Não há nova decisão institucional nesta parcela. As evidências e configurações ficam fora do Git. O plano conserva o registo de entrega.

Fecho local: PHP 1076/5416/seis omissões anteriores, Node 55, Playwright 27, PHPCS sete e lint/build aprovados. A revisão independente identificou duas corridas corrigidas com espera limitada do diário e avanço atómico perante paragem. Regressões focais 36/159/uma omissão passaram e a revisão das correcções ficou sem achados. Preservação passou 383 asserções. A prova de interrupção usa saída do processo e não simula corte físico de energia. O relatório de aceitação é a fonte dos limites. CI e integração ficam registadas na PR.
