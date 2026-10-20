# Conclusão da fronteira transaccional local

20 de outubro de 2026. Base origin/main 8641cfb. Branch própria fix/conclusao-fronteira-transaccional-local. O responsável pediu assumir a continuação sem novas tarefas para a colaboradora. Este registo acompanha uma correcção em desenvolvimento, sem declarar antecipadamente integração ou aceitação institucional.

## Entregas verificadas e composição

A branch fix/albertina-pr41-review-findings termina em c087aaa e a branch docs/albertina-f1-13-conciliacao em 8634ce6. Não havia novos commits publicados na consulta. A segunda já chegou a main pela PR #50. As PRs #39 e #41 não são integradas como entregues.

Foram aproveitados selectivamente o classificador SQL, a guarda privada por savepoint, testes e migrações de consumidores de c087aaa. A autoria dessa proposta é conservada neste registo. As correcções seguintes pertencem à continuação central. Não foram recuperadas versões antigas da interface, dos leitores documentais ou do workflow editorial.

Os consumidores Authorization, Organization, Documents, News, Communications, Calendar e F112 usam inTransaction na ligação dedicada que controla a escrita. A API não depende de uma variável de estado exclusiva da MariaDB. Não existe novo contador duplicado em cada repositório.

## Falhas reproduzidas e correcções

CASE e a palavra THEN em literais ou identificadores já não são confundidos com blocos de controlo. A criação de triggers de ensaio continua possível fora de uma transacção. DDL dentro da transacção continua recusado antes do motor. KILL volta a permitir que o ensaio de perda de ligação termine efectivamente a ligação de teste.

Interpretações de comentários condicionais têm de concordar quanto a COMMIT/ROLLBACK, CHAIN e RELEASE. Um filtro não pode acrescentar CHAIN à fronteira original. Os filtros query são aplicados uma vez. Um retorno não textual ou vazio recusa a consulta e limpa os resultados anteriores, sem executar a consulta original como alternativa.

As ligações dedicadas exigem autocommit activo, completion_type sem encadeamento, UTF-8 e modos lexicais sem NO_BACKSLASH_ESCAPES ou ANSI_QUOTES. A API recusa alterações a SQL_MODE, charsets lexicais, COLLATION_CONNECTION e SET CHARACTER SET/CHARSET. SET NAMES fica limitado a UTF-8. Uma alteração directa da sessão que viole o contrato fecha as operações seguintes. Não é normalizada silenciosamente.

A revisão independente identificou SET PASSWORD, que também pode terminar uma transacção. A instrução é recusada. O contra-exemplo foi reproduzido apenas no classificador, sem executar gestão de credenciais no motor. Três variantes têm regressão própria. Identificou ainda alterações indirectas do charset da ligação. Quatro variantes têm regressão pura e três recusas são exercitadas na integração antes de confirmar que o rollback continua possível.

Um COMMIT sintacticamente inválido chegou efectivamente ao motor. A consulta falha sem deixar escapar uma excepção mysqli para fora do contrato wpdb. A guarda continua válida, o ROLLBACK funciona e um observador externo confirma a ausência da sentinela. O modo global de reporte mysqli é restaurado. Esta prova não simula falhas de disco ou de confirmação distribuída.

## Evidência e limites correntes

Os ensaios MariaDB 11.8.3 locais usam tabelas aleatórias próprias e ligações dedicadas. Foram aprovadas 80 verificações da guarda, 292 da revisão, dez da persistência People, doze cenários adversariais e 37 verificações complementares após a extensão dos casos de charset. People passou novamente sem avisos de propriedades dinâmicas depois de corrigido o double WP_User. A regressão pura SqlBoundary tem nove testes e 40 asserções. A suite PHP final passou 895 testes e 3992 asserções, com quatro omissões Windows já existentes. Os 55 testes Node, lint e construção passaram. PHPCS examinou 430 ficheiros. Um erro e cinco avisos foram corrigidos e os quatro ficheiros finais alterados passaram nova verificação. A revisão independente final não deixou achados accionáveis.

A integração WordPress, Playwright e CI do SHA entregue serão acrescentados após conclusão efectiva. O workflow transaccional inclui MySQL 8.0.44, MySQL 8.4 e MariaDB 11.8. A existência dessa matriz não prova que as execuções passaram. Os ensaios WordPress completos continuam em MariaDB. Não equivalem a uma homologação IIS/MySQL ou a uma segunda máquina.

A demonstração localhost:8888 permanece na composição estável 8641cfb, com contas fictícias, enquanto o candidato é verificado. Os ensaios não activam o candidato nesse sítio. Configuração, credenciais, logs completos, inventários e capturas ficam exclusivamente no arquivo privado fora do Git. Nenhuma conta real ou produção faz parte desta parcela.

## Continuação

Depois dos checks e da revisão, actualizar o estado desta parcela, integrar apenas a entrega corrigida e preparar a implementação local da F1-13 a partir dos 36 critérios conciliados. Reabertura e outras decisões institucionais continuam propostas. Valores de fixtures não atribuem competências a funcionários. F1-13 e o site não ficam concluídos por esta correcção.
