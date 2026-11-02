# Plano de recuperação de envios F1-08

> Execução directa com superpowers:executing-plans e revisão independente no fecho, conforme autorização existente. Testes agrupados no fecho por preferência expressa do utilizador.

Objectivo: confirmar envios cuja operação SQL terminou mas cuja confirmação da fila se perdeu, sem repetir escritas de negócio.

Arquitectura: recibo imutável opcional transmitido por PreparedBatch aos cinco repositórios e gravado dentro das transacções existentes. A fila reconcilia exclusivamente mediante prova e autorização actual. Não há transacção distribuída nem tentativa automática após ausência de recibo.

Tecnologias: PHP, WordPress, IdentityWpdb, MySQL/MariaDB, PHPUnit e Playwright CLI.

Especificação: docs/recuperacao-envios-local-f1-08.md.

Restrições: laboratório 8892, contas fictícias, originais preservados, nenhum segredo ou dado real no Git, merge apenas com CI verde no commit exacto.

Revisão: atomicidade do recibo, associação exacta lote/contexto/actor, recusa de autoridade expirada, manutenção de CAS e diário sem reparação destrutiva, compatibilidade dos consumidores sem fila.

## Tarefa 1: recibo e confirmação

- [x] Criar Media/ConsumptionReceipt.php e Media/ReceiptSchema.php com fingerprint exacto, INSERT exclusivo dentro da transacção e leitura validada sem DDL.
- [x] Transmitir o recibo opcional por Media/PreparedBatch.php, News/LocalNewsroom.php, Documents/Catalog/LocalDocumentCatalog.php e Tasks, Requests e Support/Portal.php.
- [x] Gravar o recibo em News/WpdbNewsRepository.php, Documents/Catalog/WpdbDocumentRepository.php, Tasks/TaskRepository.php e Requests e Support/Repository.php antes da última guarda e COMMIT.
- [x] Implementar MediaQueue::reconcile com CAS, prova, reautorização e evento reconciled. ConsumerJobs encaminha apenas applying para reconciliação e ConsumerPage apresenta Confirmar registo guardado.
- [x] Testar falha pós-commit, rollback, duplicação, revogação, nonce/CAS, corrupção e ausência de recibo. Não alterar o contrato dos envios sem recibo opcional.

## Tarefa 2: validação e entrega

- [x] Executar PHPUnit e verificações do repositório, SQL real em prefixo exclusivo e Playwright CLI no laboratório. Guardar relatórios e snapshots fora do Git.
- [x] Uma revisão independente do diff completo. Corrigir problemas confirmados e repetir os testes afectados.
- [x] Actualizar README com resultados reais e limites.
- [ ] Commit, push e PR. Integrar apenas com verificações verdes no head exacto e confirmar CI de main. O estado remoto é a evidência desta etapa.
