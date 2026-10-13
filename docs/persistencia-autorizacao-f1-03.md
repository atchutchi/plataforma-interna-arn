# Persistência e auditoria local de F1-03

13 de outubro de 2026. O responsável autorizou este modelo e a sua implementação apenas no laboratório. A especificação técnica está em docs/superpowers/specs/2026-10-13-f1-03-persistencia-local-design.md. A parcela de F0-06 relativa a estado, concessões e auditoria fica preparada neste marco. A validação institucional da matriz permanece pendente.

## Modelo

Usar quatro tabelas próprias InnoDB por prefixo de site: arn_authz_schema, arn_authz_accounts, arn_authz_grants e arn_authz_audit. O primeiro schema tem versão 1. A instalação é explícita e recusa estrutura ou versão incompatível. Não há migração silenciosa, hook de instalação automática ou alteração das tabelas de identidade Google.

A conta funcional começa Pending com revisão 1. Active e Suspended representam o estado armazenado neste modelo. Cada alteração efectiva aumenta a revisão uma vez. O servidor bloqueia a conta e compara a revisão esperada antes de gravar. Duas decisões sobre a mesma revisão não podem confirmar ambas.

As concessões guardam função, tipo de âmbito, lista canónica de alvos, início, fim opcional e revogação. As substituições conservam o registo anterior revogado e criam um novo na mesma transacção. A leitura devolve todos os registos validados ao motor, que decide sobre vigência e estado. Não há cache de autorização ou aceitação parcial de uma colecção malformada.

A auditoria guarda actor, conta, evento, revisão, snapshots anterior/novo, justificação, referência e instante UTC. Mudança e evento confirmam na mesma transacção. Se a auditoria falhar, a mudança e a revisão também revertem. O API acrescenta eventos e não oferece edição ou eliminação do histórico. Um administrador da base de dados mantém capacidade técnica para alterar dados, pelo que este registo não é apresentado como inviolável.

Não se criam FK para os utilizadores WordPress: o histórico tem de sobreviver à remoção de uma conta do CMS. A autoridade de laboratório exige previamente a existência de uma conta marcada como fictícia. As referências de unidades, filas, audiências e pessoas não confirmam o cadastro institucional. A validação desses objectos continua reservada aos módulos e adaptadores posteriores.

## Laboratório

O adaptador exige ambiente efectivo local, ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE booleano true e actor corrente com manage_options. Só admite alvos marcados LocalFixtureUser::META_KEY. Não converte o administrador técnico WordPress em administrador de acessos institucional e não infere competências por email.

Ferramentas, Persistência de acessos local, é uma interface de leitura de Ana Teste. A instalação e a preparação ocorrem previamente por CLI controlada. Abrir a página não cria tabelas, contas ou concessões. A página apresenta estado, revisão, concessões e auditoria com saída escapada e sem formulário de escrita. Fora dos gates ou sem preparação, recusa ou apresenta uma indicação controlada.

O registo parte de wp_loaded, antes da construção do menu administrativo. A entrada e a renderização revalidam os gates. As duas tabelas conservam legendas e cabeçalhos semânticos e estão em regiões identificadas com deslocação interna e foco por teclado. O CSS é externo, escopado e só carrega nesta ferramenta sob autorização efectiva. O Núcleo da intranet ARN 0.3.0 mantém-se activo no laboratório. O tema e o painel ARN existentes conservam a sua apresentação.

As integrações automáticas usam prefixo aleatório, utilizadores e tabelas exclusivos. A autoridade sintética só permite essas fixtures. A preparação persistente do portátil usa apenas a fixture Ana Teste já existente. Não altera lab_admin como alvo, roles WordPress, vínculo Google ou sessões.

A validação estrutural verifica também a aplicação efectiva da CHECK JSON_VALID quando a metadata do servidor expõe ENFORCED. Uma expressão correcta com ENFORCED=NO recusa. A cobertura dessa metadata usa uma simulação focada sobre o ensaio local MariaDB. Não constitui homologação num servidor MySQL.

## Verificação e limites

O ensaio integrado de autorização passou com 97 asserções e limpeza confirmada no WordPress 7.1.3, PHP 8.4.26 e MariaDB 11.8.3 do laboratório portátil. Inclui CAS com duas ligações, substituição histórica, no-ops, revogação, rollback de auditoria com trigger, perda deliberada da ligação, recusa de transacção aninhada, corrupção de linhas e esquema e limites inteiros. O CAS foi exercitado sequencialmente com duas ligações. Não comprova espera simultânea no bloqueio. As integrações de identidade e sessão federada passaram respectivamente com 21 e 24 asserções.

TDD dos valores e serviço começou com classes ausentes e passou com 12 testes e 168 asserções. A integração SQL começou com DAO ausente. A correcção da CHECK reproduziu a aceitação indevida de ENFORCED=NO antes de a recusar. A autoridade e a ferramenta começaram com classes ausentes. Os seus testes finais passaram com 14 testes e 82 asserções, cobrindo gates, marca obrigatória, fábrica diferida, alvo fixo, saída escapada, mensagens controladas, momento do hook e CSS restrito.

A suite final passou com 441 testes PHP e 1594 asserções. PHPCS verificou 99 ficheiros sem erros ou warnings. Passaram 54 testes Node, lint e build. OPENSSL_CONF referenciou apenas a configuração local das fixtures. Não foram acrescentadas dependências ou alterado composer.lock.

A preparação explícita pelo serviço deixou Ana Teste, ID fictício 2, Active com revisão 8. Guardou três concessões, duas revogadas e uma Employee Own activa e oito eventos. Incluiu criação pendente, activação, criação e substituição de concessão, revogação, suspensão funcional e reactivação. O papel nativo subscriber ficou preservado. Uma segunda execução confirmou o mesmo estado sem repetir eventos. Não se criou conta funcional para lab_admin.

Playwright no browser do Codex, com sessão administrativa Google existente, mostrou as três concessões e os oito eventos. Um URL com user_id=1 continuou a mostrar exclusivamente a fixture 2. Não houve formulários ou escrita por leitura. A página coube em 375, 768 e 1440 px. Em 375 px as regiões tiveram 338 px de largura e deslocação interna, com foco visível e deslocação de 40 px pela seta direita. A viewport temporária foi reposta. O painel conservou logótipo, as duas famílias locais carregadas e seis atalhos. O fluxo administrativo teve zero erros ou warnings de consola.

Playwright CLI em sessão isolada confirmou encaminhamento do anónimo para o login, ausência do menu para subscriber e HTTP 403 na tentativa directa, sem tabela, nome da fixture ou referência de auditoria no corpo. Conservou o painel ARN e teve zero erros JavaScript. Os erros de recurso HTTP 403 foram deliberados nesse teste. A sessão CLI foi fechada. As capturas ficam ignoradas em output/playwright/persistencia-acessos-local.png, persistencia-acessos-mobile.png e persistencia-acessos-recusada.png. Scripts de preparação/QA, dados e configuração locais ficam fora do Git.

As revisões independentes corrigiram a CHECK não aplicada e o uso tardio de admin_init. A QA real identificou excesso de largura em 375 px e motivou as regiões com scroll interno. As re-revisões aprovaram as correcções. A revisão global e a CI no SHA exacto são condições da integração e constarão do registo da PR.

A persistência está separada do controlo efectivo do login e dos módulos. Um estado Suspended nesta demonstração ainda não revoga sessões WordPress reais. Delegações persistentes e autorização institucional por objecto ficam para os marcos seguintes. A instalação portátil e a CI MariaDB não homologam IIS ou MySQL 8.4. F1-03 permanece Em curso e F2 conserva os seus pré-requisitos.
