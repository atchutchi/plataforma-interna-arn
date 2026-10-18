# Correção transacional MySQL V2

Data: 18 de outubro de 2026.

Branch: `fix/atchutchi-mysql-transaction-guard-v2`.

Base: `main` no commit `2c77a46a3721b560e75696e036c051b546d49ba8`.

Candidato verificado: `584f0b47ad7e8738d19f585dc60cc9b65690744a`.

Issue relacionada: #36.

Esta entrega substitui tecnicamente a proposta da PR #39. A PR #39 não foi integrada.

## Problema tratado

A implementação anterior inferia o estado transacional a partir de parsing, `autocommit` e contadores de sessão. A revisão independente demonstrou seis divergências entre esse estado calculado e o estado real do servidor. Em dois cenários, DDL confirmou linhas sentinela apesar de um `ROLLBACK` posterior.

A correção V2 deixa de depender de `@@session.in_transaction`, PROCESS ou Performance Schema. Cada transação iniciada pela ligação dedicada recebe um savepoint privado. A API `IdentityWpdb::inTransaction()` confirma a continuidade da transação libertando e recriando essa guarda. A ausência da guarda, uma transação externa, alteração de `autocommit`, `completion_type` incompatível, perda da ligação ou falha de introspeção são recusadas.

## Comportamento implementado

- Savepoint privado por transação pertencente à ligação.
- Recusa de `START TRANSACTION` aninhado antes de chegar ao servidor.
- Suporte explícito de `COMMIT AND CHAIN` e `ROLLBACK AND CHAIN`.
- Recusa de alterações de `autocommit` e `completion_type` pela ligação dedicada.
- Recusa de savepoints introduzidos pelo chamador.
- Recusa de DDL e outros comandos conhecidos por commit implícito dentro de transação controlada.
- Tratamento distinto de `CREATE TEMPORARY TABLE`, que não termina a transação.
- Deteção de DDL executado diretamente pelo handle `mysqli` através do desaparecimento da guarda.
- Deteção e recusa de transação aberta externamente.
- Falha fechada quando a ligação é perdida.
- Ausência de reconexão automática.
- Preservação do SQL original nos hooks `query` do WordPress.
- Migração dos consumidores de Authorization, Calendar, Communications, Documents, PrivateFiles, F112, News e Organization para a API única.

## Regressões cobertas

A workflow isolada MySQL 8.0.44 cobre:

1. Sessão inicial sem transação.
2. `START`, `COMMIT` e `ROLLBACK` normais.
3. `START TRANSACTION` repetido com sentinela reversível.
4. DDL recusado antes de commit implícito.
5. DDL em comentário executável MySQL.
6. Alterações de `autocommit`.
7. Alterações externas de `completion_type`.
8. Tabelas temporárias dentro da transação.
9. `COMMIT AND CHAIN`.
10. `ROLLBACK AND CHAIN`.
11. DDL pelo handle `mysqli`.
12. Transação externa não controlada.
13. Savepoints externos.
14. Perda real da ligação durante a transação.
15. Ausência de reconnect.
16. Persistência People, CAS, no-op, rollback por falha de auditoria e instalação idempotente em prefixo exclusivo.

## Evidência no SHA exato

### Workflow MySQL V2

Execução: `39118297914`.

- MySQL 8.0.44.
- Guarda transacional V2: 80 verificações aprovadas.
- People exclusivo: 10 verificações aprovadas.
- Prefixos exclusivos limpos no final.

### Workflow geral

Execução: `39118297940`.

- PHPUnit: 850 testes, 3.756 asserções, 4 omissões conhecidas do Windows, 0 falhas.
- PHPCS: 403 ficheiros, 0 erros e 0 avisos.
- Node: 55 testes, lint e build aprovados.
- Workspace: aprovado.
- Catálogo documental: aprovado.
- Comunicados e calendário: aprovado.
- Persistência WordPress e MariaDB: aprovado.
- Notícias e editorial: aprovado.

A pesquisa no repositório não encontrou usos restantes de `@@session.in_transaction` nem `@@in_transaction`.

## Limites

- Esta entrega não constitui homologação MySQL 8.4, IIS ou Windows Server 2019.
- Não houve deploy, alteração do servidor `.205`, DNS, AD, VPN ou Google Workspace.
- Não foram usados dados, emails, documentos ou credenciais reais.
- A Issue #31, relativa à intermitência histórica de concorrência editorial, continua separada.
- F1-13 continua sem implementação. O seu plano executável depende da conciliação documental da Issue #37 e da integração revista desta correção.

## Condição de integração

A branch está baseada no `main` atual e não está atrasada. A integração exige pull request, CI verde no SHA exato e revisão independente. Recomenda-se squash merge devido ao número de commits de diagnóstico e correção acumulados durante o ciclo RED/GREEN.
