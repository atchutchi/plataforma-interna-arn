# Estado transaccional portátil, versão 2

Data: 18 de outubro de 2026. Branch de trabalho: `fix/atchutchi-mysql-transaction-state-v2`. Issue relacionada: #36.

## Objetivo

Substituir as consultas a `@@session.in_transaction`, específicas da MariaDB, por um único contrato capaz de observar o estado real da ligação dedicada nos motores usados pelo projeto.

A alteração não integra nem reescreve a branch `fix/albertina-mysql-transaction-state`. A PR #39 permanece como entrega independente e como evidência da primeira abordagem.

## Problema reproduzido

A primeira proposta mantinha parte do estado em PHP e tentava corroborá-lo com contadores e `autocommit`. A revisão independente demonstrou divergências em fronteiras como DDL com commit implícito, `COMMIT AND CHAIN`, tabelas temporárias e comandos executados diretamente no handle `mysqli`.

A suite v2 começou vermelha em MySQL 8.0.44 e MariaDB 11.8 porque `main` ainda não possuía `IdentityWpdb::inTransaction()`. O arranque, a ligação SQL e as fixtures estavam funcionais. A falha inicial ocorreu exclusivamente pela ausência do contrato.

## Contrato implementado

`IdentityWpdb::inTransaction(): bool` usa uma segunda ligação `mysqli`, com as mesmas credenciais protegidas, para observar a sessão principal pelo seu `PROCESSLIST_ID`.

A consulta usa somente:

- `performance_schema.threads`;
- `performance_schema.setup_instruments`;
- `performance_schema.setup_consumers`;
- `performance_schema.events_transactions_current`.

O método:

- devolve `true` apenas quando existe um evento transaccional `ACTIVE` comprovado para a ligação principal;
- devolve `false` apenas quando a instrumentação está activa e não existe transacção corrente, ou quando o evento corrente terminou de forma comprovada;
- lança `RuntimeException` com mensagem estática quando a ligação, o observador, a instrumentação, o consumidor, o privilégio ou a resposta não permitem uma conclusão segura;
- não usa `PROCESS`;
- não reconecta automaticamente;
- não depende de análise textual da query ou de contadores PHP como fonte de verdade;
- observa operações executadas fora de `IdentityWpdb::query()`, incluindo comandos diretos no handle principal.

Uma falha do observador é terminal para a instância. A operação corrente falha fechada em vez de continuar fora de uma transacção comprovada.

## Preparação do ambiente local

Os scripts `npm run env:start` e `npm run env:start:tests` iniciam o wp-env e executam `scripts/prepare-transaction-observer.mjs`.

O script:

1. identifica o contentor MariaDB do ambiente selecionado;
2. confirma `Performance Schema`;
3. activa-o e reinicia apenas o contentor MariaDB quando necessário;
4. activa o instrumento `transaction`;
5. activa o consumidor `events_transactions_current`;
6. concede ao utilizador SQL da aplicação apenas as leituras necessárias quando esse utilizador não é `root`;
7. verifica as leituras com as credenciais efectivamente usadas pelo ambiente;
8. devolve erro estático sem apresentar passwords ou saída privada do cliente SQL.

No wp-env atual, WordPress usa a conta `root` definida pela ferramenta. Na workflow independente, os testes usam a conta limitada `arn_tx`, com acesso à base exclusiva e `SELECT` apenas nas quatro superfícies do `performance_schema` acima.

## Consumidores migrados

O contrato único passou a ser usado pelos módulos de:

- autorização;
- unidades;
- People;
- documentos;
- ficheiros privados;
- notícias;
- comunicados;
- calendário;
- contexto partilhado F1-12.

Os métodos `*InTransaction` continuam a exigir uma transacção exterior comprovada. Uma falha de observação não é convertida em ausência segura de transacção.

## Provas automatizadas

A workflow `Transaction state v2` executa em MySQL 8.0.44 e MariaDB 11.8.

Cenários positivos:

- sessão inicial sem transacção;
- `START TRANSACTION`, DML e `ROLLBACK`;
- `COMMIT`;
- `COMMIT AND CHAIN`;
- `ROLLBACK AND CHAIN`;
- `CREATE TEMPORARY TABLE` sem commit implícito;
- DDL com commit implícito e linha sentinela;
- DDL em comentário executável MySQL;
- `autocommit=0` antes e depois de DML;
- `START`, `ROLLBACK` e DDL executados diretamente no handle `mysqli`;
- ligação principal encerrada;
- ausência de reconnect;
- escrita People, CAS, no-op, rollback por falha de auditoria e instalador idempotente;
- regressões dos consumidores existentes na workflow principal.

Cenários de falha fechada:

- consumidor `events_transactions_current` desligado;
- instrumento `transaction` desligado;
- privilégio de leitura do evento transaccional revogado;
- ligação principal indisponível.

## Limites

Esta entrega prova o contrato em MySQL 8.0.44, MariaDB 11.8, WordPress 7.1.3 e PHP 8.4 nas workflows do projeto.

Não constitui:

- homologação de MySQL 8.4 no Windows Server;
- homologação IIS/FastCGI;
- configuração do Panthera-Onca;
- alteração de produção;
- aprovação da especificação F1-13;
- conclusão de F1-16 ou da infraestrutura INF.

A homologação futura deve confirmar que `performance_schema` está activo em MySQL 8.4, que o utilizador da aplicação possui apenas as leituras documentadas e que a indisponibilidade dessas superfícies impede a escrita com erro controlado.
