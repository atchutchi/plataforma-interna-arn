# Conclusão da fronteira transaccional local

20 de outubro de 2026. Base origin/main 8641cfb. Branch própria fix/conclusao-fronteira-transaccional-local. O responsável pediu assumir a continuação sem novas tarefas para a colaboradora. Este registo acompanha uma correcção em desenvolvimento, sem declarar antecipadamente integração ou aceitação institucional.

Fecho posterior: a PR #51 foi integrada em main e081efb, depois de 21 checks aprovados no head final 00aef66. A CI de main 39384783433 passou os sete grupos e a matriz 39384783311 passou os três motores. A preparação da F1-13 prossegue numa branch própria. A demonstração permanece separada. Os parágrafos seguintes conservam a sequência de evidência, incluindo a falha inicial e os limites.

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

A PR #51 entrega o commit inicial 389a6e5. A execução de push 39382958401 passou os sete grupos de verificação, incluindo os quatro que falhavam no candidato. A matriz 39382958406 passou MySQL 8.0.44, MySQL 8.4 e MariaDB 11.8. A matriz da PR 39383117261 também passou. A execução WordPress da PR 39383117131 falhou na espera pelo lock de conta em news-submission.php, com worker vivo e observador sem erro SQL. Não foi feito merge ou simples rerun para esconder esta falha.

Esse teste ainda sondava a cache comum de metadados InnoDB a cada 50 ms. A continuação adopta os 150 ms já usados em news-editorial.php, conservando consulta, ID da ligação, asserções e prazo de 15 segundos. Notícias passou localmente 49 verificações. A calibração editorial-lock-observation.php reproduziu 16 leituras sem espera, intervalo máximo de 32 ms e escritor ainda bloqueado. Sem libertar o lock, o intervalo sem leitura revelou a espera. Só depois da libertação a escrita terminou. A revisão adicional não encontrou achados. Isto demonstra uma limitação da observação, sem atribuir uma causa única às falhas históricas da Issue #31. A CI do head com este ajuste ainda tem de passar.

A integração local de Serviços passou 210 verificações WordPress/MariaDB. Playwright validou nove perfis fictícios, dezoito verificações de disposição em 375/1440, oito recusas por URL directo, destinos de gestão, foco, texto CSS a 200% em dois perfis e largura 768 no administrador. Não houve excepções JavaScript. As capturas do gestor móvel e administrador foram inspeccionadas. Zoom nativo não foi executado nesta regressão. Os ensaios WordPress completos continuam em MariaDB. Não equivalem a homologação IIS/MySQL ou a uma segunda máquina.

O primeiro servidor de QA não tinha as flags dos módulos e recusou a sessão fictícia. As flags foram corrigidas só no encaminhador privado do laboratório temporário e foram emitidas novas sessões de teste. A repetição passou. Os logs das tentativas iniciais foram conservados. Depois da QA, as nove sessões adicionais foram destruídas pelos tokens exactos. O finally removeu os recursos do ensaio e foram confirmadas as doze tabelas nativas iniciais. Um pedido tardio criou a fixture automática Ana Teste. O servidor próprio foi terminado e essa conta foi removida apenas após confirmar ID novo, login/email constantes e marcador fictício, preservando a conta anterior.

A preservação comparou as 81 tabelas preexistentes da base partilhada. Oitenta ficaram integralmente iguais. Só mudaram duas opções transientes de padrões do tema no WordPress fictício temporário. Utilizadores, metadados, contas reais e restantes dados ficaram iguais. A configuração temporária ficou byte a byte igual e não restam sessões técnicas de QA. Não foram repostos valores antigos para ocultar os deltas nativos.

A demonstração localhost:8888 permanece na composição estável 8641cfb, com contas fictícias, enquanto o candidato é verificado. Os ensaios não activam o candidato nesse sítio. Configuração, credenciais, logs completos, inventários e capturas ficam exclusivamente no arquivo privado fora do Git. Nenhuma conta real ou produção faz parte desta parcela.

## Continuação

Depois dos checks e da revisão, actualizar o estado desta parcela, integrar apenas a entrega corrigida e preparar a implementação local da F1-13 a partir dos 36 critérios conciliados. Reabertura e outras decisões institucionais continuam propostas. Valores de fixtures não atribuem competências a funcionários. F1-13 e o site não ficam concluídos por esta correcção.
