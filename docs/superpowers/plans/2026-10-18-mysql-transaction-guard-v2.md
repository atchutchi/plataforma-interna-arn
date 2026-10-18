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
- Modificar: `.github/workflows/mysql-transactions.yml`

**Interfaces:**
- Consome: `IdentityWpdb::query()` e o futuro `IdentityWpdb::inTransaction()`.
- Produz: cenários RED determinísticos para a implementação da Tarefa 2.

- [ ] Criar teste PHPUnit que exige recusa sem ligação e ausência de reconexão.
- [ ] Criar integração MySQL com prefixo exclusivo e sentinelas transacionais.
- [ ] Demonstrar RED para `START TRANSACTION` repetido, DDL por `query()`, DDL pelo handle, `COMMIT AND CHAIN`, `CREATE TEMPORARY TABLE`, `SET autocommit=0` e ligação fechada.
- [ ] Ligar o teste à workflow MySQL isolada.
- [ ] Executar a workflow e confirmar falha pelos comportamentos ausentes, não por erro de sintaxe ou configuração.
- [ ] Commit: `test: reproduzir fronteiras transaccionais inseguras`.

### Tarefa 2: Implementar a guarda de savepoint

**Ficheiros:**
- Modificar: `wp-content/plugins/arn-intranet-core/src/Identity/IdentityWpdb.php`
- Modificar: `tests/phpunit/doubles/IdentityWpdbDouble.php`
- Testar: ficheiros da Tarefa 1.

**Interfaces:**
- Produz: `public function inTransaction(): bool`.
- Produz: tracking interno da guarda privada criada em `START TRANSACTION` e após `AND CHAIN`.

- [ ] Executar os testes da Tarefa 1 e registar RED.
- [ ] Implementar sonda de savepoint com nome privado seguro.
- [ ] Implementar criação, validação e remoção da guarda.
- [ ] Recusar `START TRANSACTION` aninhado antes da execução.
- [ ] Recusar dentro da transação controlada DDL e comandos conhecidos por commit implícito.
- [ ] Recusar alterações de `autocommit` nesta ligação dedicada.
- [ ] Suportar `COMMIT AND CHAIN` e `ROLLBACK AND CHAIN` com nova guarda.
- [ ] Tratar erro 1305 da sonda como ausência de savepoint apenas no contexto esperado.
- [ ] Executar testes focados e suite completa.
- [ ] Commit: `fix: controlar transaccoes por guarda de savepoint`.

### Tarefa 3: Migrar consumidores para a API única

**Ficheiros:**
- Modificar os consumidores atuais de `@@session.in_transaction` em Authorization, Calendar, Communications, Documents, PrivateFiles, F112, News e Organization.
- Modificar os testes de integração correspondentes.

**Interfaces:**
- Consome: `IdentityWpdb::inTransaction(): bool`.
- Produz: nenhuma consulta direta a variável específica de MariaDB.

- [ ] Substituir cada leitura SQL pelo método `inTransaction()`.
- [ ] Manter mensagens públicas constantes e sem dados SQL.
- [ ] Executar os testes de cada módulo afetado.
- [ ] Confirmar por pesquisa que não resta uso de `@@session.in_transaction` no produto.
- [ ] Commit: `refactor: centralizar estado transaccional na ligacao dedicada`.

### Tarefa 4: Verificação transversal e entrega

**Ficheiros:**
- Modificar: `docs/revisao-pr39-e-issues36-37-2026-10-16.md`, apenas com um aditamento da entrega V2.
- Modificar: `README.md`, apenas o estado da Issue #36 e próximo passo.

- [ ] Executar Node, lint, build, PHPUnit e PHPCS.
- [ ] Executar integrações MariaDB existentes e workflow MySQL 8.0.
- [ ] Confirmar que os seis contraexemplos da revisão anterior estão cobertos.
- [ ] Confirmar limpeza de tabelas e prefixos exclusivos.
- [ ] Abrir PR contra `main`, ligada à Issue #36.
- [ ] Exigir CI verde no SHA exato e revisão independente antes do merge.
- [ ] Commit: `docs: registar correcao transaccional v2`.
