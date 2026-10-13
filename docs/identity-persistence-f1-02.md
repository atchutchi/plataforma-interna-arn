# F1-02: fundação de associação persistente

13 de outubro de 2026. Este documento regista o marco histórico da fundação persistente, entregue sem login. A continuação actual acrescenta cliente homologado localmente, callback, formulário administrativo e sessão. Consultar [login Google local](google-login-local-f1-02.md) para o estado e os ensaios actuais. Os limites abaixo descrevem a entrega anterior.

## Contrato

IdentityLinkService exige o actor WordPress actual, conta local previamente existente e competência manage_options. Esta competência é consultada, nunca atribuída. A matriz definitiva de F1-03 permanece pendente. Não existe associação por email, criação automática de conta ou promoção de funções.

create reserva um par canónico Google issuer/sub, inicialmente sem autorização e inactivo. activate concede os dois estados após decisão explícita. revoke retira ambos, incluindo quando a conta local já foi eliminada. A consulta mantém estados para a regra GoogleAccountPolicy. Uma conta local e um par externo só podem ter um vínculo. Revogar não liberta o par para reassociação silenciosa.

WpdbIdentityLinkRepository usa duas tabelas próprias por site, InnoDB, IDs BIGINT unsigned, issuer ascii_bin e subject varbinary(255). Verifica tipos, nullability e índices únicos completos antes das operações. Um schema alterado ou incompleto falha fechado. installSchema é uma operação técnica explícita, sem hook e sem migração silenciosa. Não chamar num pedido de login ou durante uma transacção existente.

As alterações bloqueiam o vínculo com SELECT FOR UPDATE. A decisão e auditoria são confirmadas na mesma transacção. Falhar a auditoria reverte a decisão. SET TRANSACTION recusa transacções aninhadas antes de START TRANSACTION. IdentityWpdb usa uma ligação dedicada e recusa a reconexão automática do wpdb, que poderia repetir uma query fora da transacção perdida. A integração futura deve abrir uma ligação nova por unidade de trabalho, com configuração protegida e prefixo do site. Não reutilizar a ligação global ou a ligação para outros módulos.

A auditoria guarda IDs locais do actor e alvo, código de decisão, estados anterior/novo e instante UTC. Não guarda email, subject, tokens ou segredos. Repetir o mesmo estado é idempotente, sem nova linha. Esta tabela ainda não é um arquivo imutável perante um administrador da base de dados. Retenção, acesso operacional e registo de recusas pertencem à auditoria institucional seguinte.

As classes ainda não estão ligadas aos hooks. Nenhum endpoint as disponibiliza. Qualquer interface administrativa futura terá de verificar nonce/CSRF e capacidade antes de chamar o serviço. Não chamar create com um sub descodificado sem assinatura. A associação inicial precisa de prova da identidade Google e confirmação do responsável local. Revogar o vínculo ainda não destrói sessões WordPress existentes.

## Verificação

44 testes PHPUnit novos foram escritos e verificados em RED/GREEN. Suite total aprovada: 151 testes e 459 asserções com PHP 8.4.26 e PHPUnit 11.5.57. A revisão independente aprovou a versão final e repetiu a suite.

O ensaio tests/integration/identity-persistence.php executou no WordPress 7.1.3, PHP 8.4.26 NTS e MariaDB 11.8.3 nativos portáteis. Passaram 21 asserções: criação inactiva, unicidade por conta e par externo, comparação binária, actor sem competência, activação/revogação, idempotência, auditoria ordenada, rollback de criação e activação após falha real de auditoria por trigger e recusa de transacção aninhada. As funções locais não foram promovidas.

O ensaio usa prefixo aleatório próprio, contas fictícias example.test e limpeza em finally. Só aceita WP-CLI, ambiente local/development e administrador local. Não usar em produção ou numa instalação com dados reais. No wp-env de testes, executar:

```text
npm run env:start:tests
npm run env:check:tests
npm run test:identity:integration
npm run env:stop:tests
```

O workflow inclui agora este ensaio em WordPress/MariaDB, além de Node/PHP. O resultado final no SHA da PR deve passar antes da integração.

Neste computador, Docker Desktop falhou no arranque do serviço interno de inferência. Não houve reset de dados ou instalação no servidor. O laboratório portátil ficou apenas em .git/tools, com HTTP 127.0.0.1:8888 e MariaDB 127.0.0.1:33317. Não substitui wp-env nem a homologação IIS/MySQL 8.4. Os binários MariaDB e Node 24.21.0 tiveram SHA256 conferido com as distribuições oficiais. O logo e alterações do tema pertencem a uma entrega separada.

## Avaliação adicional do cliente

[Jumbojett OpenID-Connect-PHP](https://github.com/jumbojett/OpenID-Connect-PHP) tem release v1.0.2 de 13 de setembro de 2024. A leitura do código da branch master encontrou exp e nonce opcionais em verifyJWTClaims. Também existe aceitação de uma chave jwk fornecida no cabeçalho do token em verifyJWTSignature. Esta observação é limitada à versão examinada de master e não equivale a ensaio da release. Não foi instalado ou aprovado.

[Facile PHP OpenID Client 1.0.0](https://github.com/facile-it/php-openid-client/tree/1.0.0), publicado em 12 de junho de 2026, permanece em avaliação. Requer PHP 8.2 ou superior e delega verificação criptográfica no php-jose-verifier. A leitura de IdTokenVerifier exige iss/sub/aud/exp/iat. AbstractTokenVerifier adiciona a comparação do nonce quando fornecido, mas o nonce não é obrigatório na lista de claims. O futuro adaptador precisa de exigir presença e correspondência após verificação e de comprovar state, PKCE, utilização única e vínculo ao browser. Esta leitura não homologa a biblioteca. Falta instalar a versão bloqueada no ambiente de referência e ensaiar tokens assinados válidos e inválidos antes da escolha.

## Configuração Google e próximos passos

O responsável confirmou que não existe ambiente HTTPS de homologação. O primeiro ensaio Google será local. A [documentação Google para clientes Web](https://developers.google.com/identity/protocols/oauth2/web-server#uri-validation) admite a excepção de HTTP em localhost. A OidcConfiguration actual permanece restrita a HTTPS e não será enfraquecida globalmente. Uma excepção local futura deverá depender do ambiente local efectivo e de uma flag explícita, com testes de recusa em homologação/produção.

Serão necessários projecto Google institucional, consentimento interno quando elegível, cliente OAuth do tipo aplicação Web, Client ID e Client Secret guardados fora do repositório e callback local exacto fornecido pela integração implementada. Pedir apenas openid e email. Não pedir Gmail, Drive ou Admin Directory. O domínio Workspace não concede acesso ao portal. Não exportar as contas dos funcionários para Git.

F1-02 permanece Em curso. O passo seguinte é fechar a selecção e os ensaios do cliente, implementar o adaptador de protocolo e consumo do vínculo persistente, controlar vias alternativas e emitir/revogar sessões. F1-03 e F2 mantêm as dependências.
