# Plano de capacidade de media em lotes F1-08

> Executar directamente com superpowers:executing-plans. O utilizador autorizou decisões técnicas locais e testes agrupados no fecho.

Objectivo: reduzir inicializações do scanner e aceitar envio de oito ficheiros em ciclos limitados.
Arquitectura: ClamAvScanner::scanMany(array, job, deadline), scanner opcional de derivados em MediaPipeline, composição local e progresso legível no portal.
Especificação: docs/capacidade-lotes-media-local-f1-08.md.

## Tarefa 1: implementação

- [x] Testes unitários para listas, resumo exacto, argumentos, ordem, alterações e recusa sem aprovação parcial.
- [x] Implementar análise conjunta de dois derivados conservando original primeiro, hashes, auditoria e limites.
- [x] Mostrar progresso agregado no portal sem alargar autorização.
- [x] Cobrir lote máximo 3/3/2 e retoma sem repetir entradas prontas.

## Tarefa 2: fecho

- [x] PHPUnit, PHPCS, Node/lint/build, SQL pertinente e Playwright real. Esperado: sem falhas, oito originais aceites apenas após worker, sem publicação automática ou alterações anteriores.
- [x] Revisão independente única. Foco: scanner parcial/erro, mutações entre verificação e análise, compatibilidade do diário/PreparedBatch, autorização em progresso/conclusão e limites agregados.
- [x] README e evidência local.
- [ ] Commit/push/PR, merge apenas com CI verde no SHA exacto e confirmação de main. O estado remoto comprova a etapa.
