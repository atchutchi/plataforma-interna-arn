# Plano de conclusão da fronteira transaccional local

Execução directa neste chat com Superpowers executing-plans e revisão independente da composição no fecho. O responsável pediu a continuação autónoma do site sem novas dependências da Albertina. A tarefa técnica é corrigir os bloqueadores publicados, sem aprovar decisões institucionais, activar produção ou alterar contas reais.

Objectivo: fornecer a API inTransaction portável e uma fronteira que recuse instruções capazes de terminar ou substituir uma transacção sem autorização, preservando os circuitos já integrados.

Arquitectura: aproveitar os ficheiros da proposta c087aaa como material de partida, com atribuição explícita a essa entrega e sem integrar os seus commits falhados. A ligação dedicada conserva um savepoint privado validado por RELEASE/SAVEPOINT, sem rollback das escritas. O classificador distingue palavras, identificadores e literais. Interpretações condicionais têm de concordar quanto à fronteira resultante. Os filtros são aplicados uma vez antes de decidir.

Tecnologias: WordPress 7.1.3, PHP 8.4, MySQL 8.0.44/MySQL 8.4 e MariaDB 11.8 em ensaios isolados. A aprovação destes contratos SQL não equivale a homologação representativa IIS/MySQL ou aceitação institucional.

Fontes: docs/revisao-branches-pr41-2026-10-19.md, docs/integracao-entregas-locais-2026-10-19.md e contratos de persistência existentes. O laboratório demonstrativo de 8641cfb permanece activo e separado.

## Restrições e decisões

Os nomes de pessoas, credenciais, configurações, cookies, originais e provas completas ficam fora do Git. Só usar tabelas aleatórias próprias e contas fictícias. Não enfraquecer as asserções de atomicidade, concorrência ou rollback. Preservar os diagnósticos editoriais acrescentados pelas PRs já integradas.

Ruling: SQL composto usado para criar triggers de ensaio fora de uma transacção tem de continuar a funcionar. Dentro de uma transacção, DDL continua recusado antes de execução. Uma palavra THEN num literal ou numa expressão CASE não identifica por si uma instrução composta.

Ruling: alterações SQL_MODE não pertencem à API das ligações dedicadas. Recusar a sua alteração, NO_BACKSLASH_ESCAPES/ANSI_QUOTES e charsets lexicais fora de UTF-8. Recusar também COLLATION_CONNECTION e SET CHARACTER SET/CHARSET, que podem mudar implicitamente o charset efectivo. SET PASSWORD fica fora da API. Não prometer suporte arbitrário a dialectos ou modos não ensaiados.

Ruling: os consumidores usam inTransaction na ligação que controla a escrita. Não duplicar contadores em cada repositório ou usar uma variável exclusiva da MariaDB.

## Tarefa 1: classificação e estado da sessão

Ficheiros: Identity/IdentityWpdb.php, Identity/SqlBoundary.php e testes PHPUnit respectivos.

- [x] Escrever regressões para CASE, THEN literal/identificador, triggers de ensaio, comentários condicionais com CHAIN divergente e alteração de SQL_MODE.
- [x] Confirmar as falhas no material c087aaa antes de corrigir.
- [x] Corrigir a classificação com tokens e os defaults da sessão. Comparar também CHAIN após filtros. Recusas limpam resultados anteriores.
- [x] Executar os testes focados e os contra-exemplos reais em MariaDB local, sem activar a proposta na demonstração.

## Tarefa 2: compatibilidade dos consumidores e regressões reais

Ficheiros: repositórios Authorization, Organization, Documents, News, Communications, Calendar e F112, testes de integração e workflow transaccional.

- [x] Migrar as sondas de estado para inTransaction, preservando os leitores, menus e diagnósticos integrados.
- [x] Reproduzir as quatro falhas CI anteriores e resolver a causa. Conservar a capacidade de injectar falhas de auditoria/último destinatário e de perder realmente a ligação.
- [x] Testar rollback externo por sentinela, COMMIT que falha no motor, libertação da ligação, resultado antigo get_var/get_row e alteração de filtros.
- [x] Executar MySQL e MariaDB na CI do primeiro commit entregue, com ensaios sintéticos e limpeza própria. Nunca substituir a prova de um motor pela do outro. Nova CI exigida após ajuste do observador de Notícias.

## Tarefa 3: fecho da parcela e continuação

- [x] Agrupar a suite PHP, PHPCS, Node, lint, construção e integração WordPress no fecho. Executar Playwright da regressão local relevante.
- [x] Obter revisão independente do conjunto final, corrigir bloqueadores e actualizar README/AGENTS com factos e pendências.
- [x] Fazer commit e push na branch própria e abrir PR com os checks do SHA final. PR #51 integrada em e081efb depois de 21 checks aprovados em 00aef66. CI de main também aprovada.
- [x] Prosseguir para o plano executável F1-13 depois de demonstrar a fronteira corrigida e rever as decisões da proposta recebida. Plano 2026-10-20-tarefas-locais-f1-13.md em execução. O site não fica concluído por terminar esta parcela.
