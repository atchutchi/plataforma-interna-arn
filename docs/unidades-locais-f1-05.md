# Unidades persistentes locais F1-05

14 de outubro de 2026. Parcela técnica de US-A03 e US-ARN-01. O catálogo documental de 37 unidades permanece separado e não é importado para este ensaio. F1-05 institucional continua dependente de F0-01 e dos contratos de RH.

## Utilização e fronteira

A ferramenta Unidades locais permite gerir apenas LAB-ROOT, LAB-A e LAB-B. Estas unidades não representam serviços da ARN, não contêm pessoas e não atribuem competências. O acesso exige ambiente efectivo local, a flag ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE com valor booleano true e manage_options. A instalação é explícita por WP-CLI com a mesma guarda. GET e activação do plugin não criam tabelas.

Os cenários fechados permitem criar a árvore inicial, renomear A, mover B para A, devolver B à raiz e desactivar B. IDs e relações mantêm-se no histórico. Não existe eliminação. O formulário exige nonce, revisão e justificação. A revisão global impede que dois pedidos validem alterações sobre árvores diferentes. Cada alteração conserva os estados anterior e posterior, actor, motivo e instante na mesma transacção dos dados.

O schema recusa incompatibilidades e instalações parciais. A revisão zero exige árvore vazia e uma revisão positiva exige as três unidades sintéticas. Falhas de escrita, auditoria ou validação revertem a operação. Pedidos com revisão antiga exigem recarregar. Não há sincronização com autorização, sessões, Google ou empregados. A pertença a estas unidades não concede acesso.

## Verificação

A suite PHP passou com 607 testes e 2057 asserções. Depois da correcção de supressão de erros SQL, os 26 testes focados passaram com 30 asserções e a integração real passou 28 verificações. A integração usa prefixo aleatório e contas exclusivas, com limpeza no final. Verificou idempotência, roundtrip, revisão antiga, rollback integral após falha na auditoria, bloqueio por outra ligação, incompatibilidade de schema, recusa de instalação parcial e incoerência entre revisão e árvore. O novo teste reproduziu saída SQL antes da correcção e confirmou saída vazia e reposição do estado da ligação depois.

Passaram os 54 testes Node, lint, build e PHPCS dos 138 ficheiros. A revisão independente identificou a questão da saída SQL em modo de depuração. A correcção foi novamente revista e aprovada. A CI no commit final verifica também as integrações já existentes de identidade, autorização, sessões e notificações.

O Playwright confirmou que a flag ausente recusa o administrador com 403. Com a flag local activa e sem schema, GET apresentou erro estático sem formulário e a base continuou sem tabelas de unidades. A instalação explícita por `LocalUnitInstaller::install()` criou revisão zero, árvore vazia e zero eventos. Não houve instalação por acesso HTTP.

No painel foram executados os cinco cenários, com persistência após recarregar. Os cinco eventos ficaram na revisão global 5. A foi renomeada conservando LAB-A. B regressou a LAB-ROOT e ficou inactiva. Um pedido com campos arbitrários de actor, nome, ID e superior só aplicou o cenário autorizado e registou o actor corrente do servidor. Nonce inválido, cenário desconhecido, revisão não canónica, overflow e motivo vazio foram recusados. A revisão antiga e a operação sem alteração devolveram 409 sem acrescentar eventos.

As larguras de 375, 768 e 1440 píxeis passaram sem transbordo horizontal. Não houve erros JavaScript nos percursos positivos. As respostas privadas usaram no-store. Retirar manage_options à conta temporária bloqueou de imediato GET e POST sem precisar de novo login. O visitante anónimo foi encaminhado para login e o seu POST foi recusado. A conta temporária e respectivas sessões foram removidas no final. Os IDs e motivos dos seus cinco eventos fictícios permaneceram na auditoria.

A comparação dos dados das quatro tabelas de autorização e das sessões de Ana Teste confirmou ausência de alterações. Ana permaneceu Active na revisão 22 com o papel subscriber e a concessão própria original. Capturas, helpers, configuração local e dados do ensaio ficam fora do Git.

## Operação local

A instalação exige WP-CLI booleano true e a mesma guarda da ferramenta. A API operacional é `Arn\Intranet\Organization\Storage\LocalUnitInstaller::install()`. A página fica em Ferramentas > Unidades locais. O schema só aceita o conjunto completo compatível ou as três tabelas ausentes. Uma instalação parcial ou estrutura incompatível precisa de diagnóstico explícito, sem reparação automática. A desactivação de B neste laboratório não valida a política institucional de unidades com pessoas ou tarefas activas.

## Passo seguinte

O contrato de preparação do cadastro e da importação está em contratos-cadastro-importacao-f1-05.md. Nesta parcela não existe upload CSV, execução de importações de pessoas ou criação automática de utilizadores.
