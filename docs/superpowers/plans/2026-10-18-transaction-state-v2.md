# Transaction State V2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar uma leitura transaccional portável e fail-closed para a ligação `IdentityWpdb`, validada em MySQL e MariaDB, e substituir as consultas específicas de `@@session.in_transaction` sem integrar a PR #39.

**Architecture:** `IdentityWpdb::inTransaction(): bool` observa a sessão principal a partir de uma segunda ligação `mysqli` à mesma instância. A ligação observadora consulta `performance_schema.threads`, `setup_instruments`, `setup_consumers` e `events_transactions_current`, usando o `PROCESSLIST_ID` da ligação principal. Configuração, privilégio ou estado ambíguo geram `RuntimeException`, nunca `false` permissivo. A ligação principal continua sem reconnect automático.

**Tech Stack:** WordPress 7.1.3, PHP 8.4, `wpdb`/`mysqli`, MySQL 8.0.44, MariaDB 11.8, PHPUnit 11, PHPCS, GitHub Actions.

**Spec:** `docs/revisao-pr39-e-issues36-37-2026-10-16.md`, Issue #36 e contratos transaccionais já existentes nos repositórios WordPress.

## Global Constraints

- Não alterar, forçar, fazer merge ou reescrever `fix/albertina-mysql-transaction-state`.
- Não usar `@@session.in_transaction`, `PROCESS`, reconnect automático ou contadores PHP como fonte de verdade.
- Uma ligação perdida, observador indisponível, privilégios insuficientes, instrumentação desligada ou resposta ambígua deve falhar fechada.
- A ligação observadora nunca executa dados de negócio, DDL, commit ou rollback.
- O utilizador SQL da aplicação recebe apenas os privilégios mínimos de leitura necessários no `performance_schema`, além dos privilégios da base da aplicação.
- Nenhum teste usa pessoas, emails, credenciais ou dados institucionais reais.
- O código deve manter PHP 8.4, WordPress Coding Standards e os contratos públicos existentes.
- Toda alteração de produto segue RED, GREEN e regressão integral.

## Review Focus

- DDL e comentários executáveis que provocam commit implícito devem mudar imediatamente o resultado observado.
- `COMMIT AND CHAIN`, `ROLLBACK AND CHAIN` e `SET autocommit` devem refletir o estado real do servidor.
- Uma operação executada diretamente no handle principal deve ser observada sem confiar no método `IdentityWpdb::query()`.
- Falha, perda ou substituição da ligação principal ou observadora deve recusar, sem reconnect.
- Instrumentação, consumidor ou privilégio `performance_schema` ausente deve produzir erro estático, sem detalhes SQL ou credenciais.

---

### Task 1: Prova RED multi-motor do contrato transaccional

**Files:**
- Create: `tests/integration/transaction-state-v2-bootstrap.php`
- Create: `tests/integration/transaction-state-v2.php`
- Create: `.github/workflows/transaction-state-v2.yml`

**Interfaces:**
- Consumes: `Arn\Intranet\Identity\IdentityWpdb` de `main`.
- Produces: uma suite de integração que chama `IdentityWpdb::inTransaction(): bool` e prova as fronteiras em MySQL e MariaDB.

- [ ] **Step 1: Escrever o teste de integração antes da implementação**

O teste deve criar tabelas exclusivas e verificar: sessão inicial, START/DML/ROLLBACK, COMMIT, `COMMIT AND CHAIN`, `ROLLBACK AND CHAIN`, DDL implícito com sentinela, `CREATE TEMPORARY TABLE`, comentário executável MySQL, `SET autocommit`, comando pelo handle `mysqli`, ligação fechada e ausência de reconnect.

- [ ] **Step 2: Publicar o teste e confirmar RED**

Run: workflow `Transaction state v2` em MySQL 8.0.44 e MariaDB 11.8.
Expected: FAIL porque `IdentityWpdb::inTransaction()` ainda não existe em `main`.

- [ ] **Step 3: Registar a causa observada**

Documentar no output que a suite falha na ausência do contrato e não por configuração, sintaxe, conexão ou fixture.

### Task 2: Implementar observação fail-closed da sessão

**Files:**
- Modify: `wp-content/plugins/arn-intranet-core/src/Identity/IdentityWpdb.php`
- Create: `tests/phpunit/IdentityWpdbTransactionTest.php`
- Modify: `tests/phpunit/doubles/IdentityWpdbDouble.php`

**Interfaces:**
- Consumes: credenciais e host protegidos já mantidos por `wpdb`; `parse_db_host()`; thread ID do handle principal.
- Produces: `public function inTransaction(): bool` e encerramento seguro da ligação observadora.

- [ ] **Step 1: Escrever testes unitários falhados**

Cobrir conexão principal ausente, observador indisponível, thread principal ausente, instrumentação/consumer desligado, resposta duplicada, estado desconhecido e ausência de reconnect.

- [ ] **Step 2: Executar os testes focados e confirmar RED**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/phpunit/IdentityWpdbTransactionTest.php`
Expected: FAIL pela ausência do método e do adaptador observador.

- [ ] **Step 3: Implementar o mínimo para GREEN**

Adicionar `IdentityWpdb::inTransaction(): bool`, ligação observadora lazy e consulta preparada limitada ao `PROCESSLIST_ID` da ligação principal. Aceitar apenas um thread instrumentado, instrumento `transaction` e consumer `events_transactions_current` activos. Devolver `true` apenas para uma transacção `ACTIVE` sem `END_EVENT_ID`; devolver `false` apenas para ausência comprovada de linha activa; recusar os restantes estados.

- [ ] **Step 4: Executar unitários e integração multi-motor**

Run: PHPUnit focado e workflow `Transaction state v2`.
Expected: PASS em MySQL 8.0.44 e MariaDB 11.8.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/arn-intranet-core/src/Identity/IdentityWpdb.php tests/phpunit/IdentityWpdbTransactionTest.php tests/phpunit/doubles/IdentityWpdbDouble.php tests/integration/transaction-state-v2-bootstrap.php tests/integration/transaction-state-v2.php .github/workflows/transaction-state-v2.yml
git commit -m "fix: observar estado transaccional no servidor"
```

### Task 3: Migrar consumidores do contrato transaccional

**Files:**
- Modify: `wp-content/plugins/arn-intranet-core/src/Authorization/Storage/WpdbAuthorizationRepository.php`
- Modify: `wp-content/plugins/arn-intranet-core/src/Calendar/EventRepository.php`
- Modify: `wp-content/plugins/arn-intranet-core/src/Communications/NoticeRepository.php`
- Modify: `wp-content/plugins/arn-intranet-core/src/Documents/Catalog/WpdbDocumentAuthority.php`
- Modify: `wp-content/plugins/arn-intranet-core/src/Documents/Catalog/WpdbDocumentRepository.php`
- Modify: `wp-content/plugins/arn-intranet-core/src/Documents/PrivateFiles/WpdbFileRegistry.php`
- Modify: `wp-content/plugins/arn-intranet-core/src/F112/CurrentActor.php`
- Modify: `wp-content/plugins/arn-intranet-core/src/News/NewsMigration.php`
- Modify: `wp-content/plugins/arn-intranet-core/src/News/WpdbNewsAuthority.php`
- Modify: `wp-content/plugins/arn-intranet-core/src/News/WpdbNewsRepository.php`
- Modify: `wp-content/plugins/arn-intranet-core/src/Organization/Storage/WpdbLocalUnitRepository.php`
- Modify: affected integration tests.

**Interfaces:**
- Consumes: `IdentityWpdb::inTransaction(): bool` from Task 2.
- Produces: one portable transaction-state contract across all modules.

- [ ] **Step 1: Acrescentar regressões focadas para cada consumidor**

Cada método `*InTransaction` deve recusar fora de transacção, aceitar dentro da transacção exacta e preservar rollback exterior.

- [ ] **Step 2: Confirmar RED contra os consumidores atuais**

Run: the affected integration scripts.
Expected: MySQL fails where `@@session.in_transaction` is unavailable.

- [ ] **Step 3: Substituir as consultas específicas**

Usar exclusivamente `$database->inTransaction()`. Não capturar a falha como ausência segura. Não alterar mensagens públicas sem necessidade.

- [ ] **Step 4: Executar integração MariaDB e MySQL**

Run: workflow principal e `Transaction state v2`.
Expected: consumidores e regressões verdes nos dois motores.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/arn-intranet-core/src tests/integration tests/phpunit
 git commit -m "fix: unificar contrato transaccional dos módulos"
```

### Task 4: Documentação operacional e privilégios mínimos

**Files:**
- Create: `docs/transaction-state-v2.md`
- Modify: `AGENTS.md`
- Modify: `README.md` apenas nas secções de estado, F1-13 e homologação MySQL.

**Interfaces:**
- Consumes: comportamento verificado nas Tasks 1 a 3.
- Produces: instruções para desenvolvimento, homologação e concessão SQL mínima.

- [ ] **Step 1: Documentar a fronteira e os limites**

Registar tabelas `performance_schema` consultadas, privilégio mínimo, falha fechada, ausência de PROCESS/reconnect, diferenças entre prova MySQL 8.0/MariaDB 11.8 e homologação futura MySQL 8.4/IIS.

- [ ] **Step 2: Atualizar o estado do backlog sem declarar produção pronta**

Marcar a correção local como integrada na branch/PR, mantendo F1-13, F1-16 e INF pendentes até merge, homologação e aceitação.

- [ ] **Step 3: Verificar documentação e commit**

Run: `npm run lint`, `npm run build`, `git diff --check`.
Expected: PASS.

### Task 5: Verificação integral e entrega para revisão

**Files:**
- No new production files expected.

**Interfaces:**
- Consumes: all prior tasks.
- Produces: pull request reviewable against `main` and evidence linked to Issue #36.

- [ ] **Step 1: Executar toda a CI no SHA exacto**

Expected: Node, lint, build, PHPUnit, PHPCS, WordPress/MariaDB integrations and both transaction-engine jobs pass.

- [ ] **Step 2: Rever o diff completo**

Confirmar ausência de segredos, dados reais, permissões institucionais, alterações de produção ou mudanças fora do âmbito.

- [ ] **Step 3: Abrir PR contra `main`**

A PR deve referenciar a Issue #36, explicar por que substitui o candidato da PR #39, listar RED/GREEN, motores e limites. Não fazer merge automático.
