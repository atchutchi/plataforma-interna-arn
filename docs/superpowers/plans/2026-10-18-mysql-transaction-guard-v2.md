# Guarda Transacional MySQL V2, Plano de Implementação

> **Para agentes:** SUB-SKILL OBRIGATÓRIA: usar `superpowers:executing-plans` para executar este plano tarefa a tarefa. Todos os passos usam caixas de verificação para acompanhamento.

**Objetivo:** substituir a inferência transacional baseada em parsing e contadores por uma guarda de savepoint pertencente à ligação, capaz de falhar de forma segura em MySQL e MariaDB.

**Arquitetura:** `IdentityWpdb` continua a ser a ligação dedicada. Uma transação iniciada através de `IdentityWpdb::query()` recebe um savepoint privado e único. `inTransaction()` valida a existência dessa guarda através de `RELEASE SAVEPOINT` seguido de recriação. Se não existir guarda, uma sonda temporária distingue ausência de transação de uma transação externa não controlada. Comandos que fariam commit implícito dentro de uma transação pertencente à ligação são recusados antes de chegar ao servidor.

**Stack:** PHP 8.4, WordPress 7.1.3, `wpdb`, MySQL 8.0.44 na workflow isolada, MariaDB 11.8 no `wp-env`, PHPUnit 11.5, PHPCS, GitHub Actions.

**Especificação:** Issue #36 e `docs/revisao-pr39-e-issues36-37-2026-10-16.md`.

## Restrições globais

- Não usar `@@session.in_transaction`, PROCESS ou Performance Schema.
- Não reconectar automaticamente uma ligação perdida.
- Não tratar estado desconhecido como ausência segura de transação.
- Não alterar Google OIDC, pré-cadastro Workspace, F1-13 ou dados reais.
- Não integrar a PR #39 atual.
- Usar apenas bases, prefixos e identidades fictícias nos testes.
- Preservar PHP 8.4, WordPress 7.1.3 e a arquitetura WordPress existente.

## Foco da revisão

- `START TRANSACTION` repetido não pode confirmar trabalho anterior silenciosamente.
- DDL com commit implícito não pode executar dentro de transação controlada.
- DDL pelo handle `mysqli` deve invalidar a guarda e provocar recusa na verificação seguinte.
- `COMMIT AND CHAIN` e `ROLLBACK AND CHAIN` devem criar uma nova guarda para a nova transação.
- `autocommit=0`, ligação perdida ou sonda ambígua devem falhar fechados.

---

### Tarefa 1: Regressões RED das fronteiras transacionais

**Ficheiros:**
- Criar: `tests/integration/mysql-transaction-guard-v2.php`
- Criar: `tests/phpunit/IdentityWpdbTransactionGuardTest.php`
- Criar: `.github/workflows/mysql-transactions-v2.yml`

**Interfaces:**
- Consome: `IdentityWpdb::query()` e o futuro `IdentityWpdb::inTransaction()`.
- Produz: cenários RED determinísticos para a implementação da Tarefa 2.

- [x] Criar teste PHPUnit que exige recusa sem ligação e ausência de reconexão.
- [x] Criar integração MySQL com prefixo exclusivo e sentinelas transacionais.
- [x] Demonstrar RED para `START TRANSACTION` repetido, DDL por `query()`, DDL pelo handle, `COMMIT AND CHAIN`, `CREATE TEMPORARY TABLE`, `SET autocommit=0` e ligação fechada.
- [x] Ligar o teste à workflow MySQL isolada.
- [x] Executar a workflow e confirmar falha pelos comportamentos ausentes, não por erro de sintaxe ou configuração.
- [x] Commit RED publicado na branch.

### Tarefa 2: Implementar a guarda de savepoint

**Ficheiros:**
- Modificar: `wp-content/plugins/arn-intranet-core/src/Identity/IdentityWpdb.php`
- Modificar: `tests/phpunit/doubles/IdentityWpdbDouble.php`
- Testar: ficheiros da Tarefa 1.

**Interfaces:**
- Produz: `public function inTransaction(): bool`.
- Produz: tracking interno da guarda privada criada em `START TRANSACTION` e após `AND CHAIN`.

- [x] Executar os testes da Tarefa 1 e registar RED.
- [x] Implementar sonda de savepoint com nome privado seguro.
- [x] Implementar criação, validação e remoção da guarda.
- [x] Recusar `START TRANSACTION` aninhado antes da execução.
- [x] Recusar dentro da transação controlada DDL e comandos conhecidos por commit implícito.
- [x] Recusar alterações de `autocommit` nesta ligação dedicada.
- [x] Validar `completion_type` e recusar alterações pela API.
- [x] Suportar `COMMIT AND CHAIN` e `ROLLBACK AND CHAIN` com nova guarda.
- [x] Tratar erro 1305 da sonda como ausência de savepoint apenas no contexto esperado.
- [x] Normalizar perda de ligação e falhas mysqli para erro controlado.
- [x] Preservar a instrução original nos hooks `query` do WordPress.
- [x] Executar testes focados e suite completa.

### Tarefa 3: Migrar consumidores para a API única

**Ficheiros:**
- Modificar os consumidores atuais de `@@session.in_transaction` em Authorization, Calendar, Communications, Documents, PrivateFiles, F112, News e Organization.
- Modificar os testes de integração correspondentes.

**Interfaces:**
- Consome: `IdentityWpdb::inTransaction(): bool`.
- Produz: nenhuma consulta direta a variável específica de MariaDB.

- [x] Substituir cada leitura SQL pelo método `inTransaction()`.
- [x] Manter mensagens públicas constantes e sem dados SQL.
- [x] Executar os testes de cada módulo afetado.
- [x] Confirmar por pesquisa que não resta uso de `@@session.in_transaction` ou `@@in_transaction` no produto.
- [x] Commit de migração publicado na branch.

### Tarefa 4: Verificação transversal e entrega

**Ficheiros:**
- Criar: `docs/correcao-transaccional-v2-2026-10-18.md`.
- Atualizar `README.md` apenas depois da integração, para não apresentar o candidato como concluído antes da revisão.

- [x] Executar Node, lint, build, PHPUnit e PHPCS.
- [x] Executar integrações MariaDB existentes e workflow MySQL 8.0.
- [x] Confirmar que os seis contraexemplos da revisão anterior estão cobertos.
- [x] Confirmar limpeza de tabelas e prefixos exclusivos.
- [x] Registar evidência e limites no documento da entrega V2.
- [ ] Abrir PR contra `main`, ligada à Issue #36.
- [ ] Exigir CI verde no SHA exato da PR e revisão independente antes do merge.
- [ ] Atualizar o README depois da integração revista.
