# F1-02: homologação OIDC e login Google local

13 de outubro de 2026. Facile PHP OpenID Client 1.0.0 está homologado para o laboratório local. O callback Google real, o vínculo persistente auditado e a sessão WordPress foram executados com sucesso. Esta conclusão não homologa IIS, MySQL 8.4, LAN/VPN ou operação institucional.

## Dependências e protocolo

O plugin usa [Facile OpenID Client 1.0.0](https://github.com/facile-it/php-openid-client/tree/1.0.0), licença MIT, com php-jose-verifier 1.0.0 e Guzzle 7.15.5, licença MIT. As dependências estão fixadas em composer.lock, gerado por npm run composer:install no wp-env de testes Linux em CI, execução [38467039287](https://github.com/atchutchi/plataforma-interna-arn/actions/runs/38467039287). O workflow temporário foi retirado após obter o lockfile. A auditoria Composer não encontrou vulnerabilidades conhecidas ou pacotes abandonados na data do ensaio.

MaintainedGoogleClient delega a troca do código e a verificação JOSE na biblioteca. Fixa https://accounts.google.com, os endpoints Google e RS256. Exige Authorization Code, PKCE S256, state e nonce aleatórios. O nonce tem de estar presente e corresponder após a assinatura. A biblioteca compara o valor mas não exige a presença por defeito. O adaptador não aceita tokens no callback do browser nem guarda access tokens ou refresh tokens.

O ensaio Google identificou recusa intermitente de iat com tolerância temporal zero. O verificador recebe agora tolerância máxima de 30 segundos para diferença entre relógios. Uma emissão 60 segundos no futuro é recusada. A verificação adicional de exp recusa ID tokens já expirados, mesmo dentro da tolerância. A sincronização de hora continua necessária.

A [documentação Google](https://developers.google.com/identity/openid-connect/openid-connect) fundamenta o fluxo de servidor, callback exacto, openid/email e hd. O adaptador exige domínio Workspace configurado, email verificado e vínculo local activo por issuer/sub. Email e pertença ao domínio não atribuem permissões.

## Configuração protegida

O controlador só activa Google com WP_ENVIRONMENT_TYPE=local. HTTP exige ARN_OIDC_ALLOW_LOCAL_HTTP="1", host localhost, caminho /wp-login.php e query exacta action=arn_google_callback. Homologação e produção não herdam esta excepção.

Foi criado, após confirmação do responsável, um cliente Web dedicado chamado ARN Intranet local no projecto Google autorizado, com público interno. O cliente existente foi preservado. O pedido usa apenas openid e email, sem Gmail, Directory ou Drive. O callback registado é http://localhost:8888/wp-login.php?action=arn_google_callback.

ARN_OIDC_CONFIG_FILE na configuração WordPress aponta para JSON exterior ao Git e ao webroot. Pode ser também uma variável de ambiente. Este exemplo está desactivado e não contém credenciais:

```json
{
  "ARN_OIDC_ENABLED": "0",
  "ARN_OIDC_CLIENT_ID": "CONFIGURAR_FORA_DO_GIT",
  "ARN_OIDC_CLIENT_SECRET": "CONFIGURAR_FORA_DO_GIT",
  "ARN_OIDC_REDIRECT_URI": "http://localhost:8888/wp-login.php?action=arn_google_callback",
  "ARN_OIDC_WORKSPACE_DOMAIN": "arn.gw",
  "ARN_OIDC_ALLOW_LOCAL_HTTP": "1"
}
```

Só activar depois de configurar e restringir o ficheiro. Não copiar credenciais para commits, screenshots ou relatórios. Em Windows, o operador aplica ACL sem herança ampla, apenas para o utilizador que executa PHP e SYSTEM. Esta ACL foi confirmada no laboratório. Em POSIX, o ficheiro não pode conceder permissões de grupo ou outros. A pasta sessions fica no mesmo directório privado. As sessões PHP usam HttpOnly, SameSite=Lax, nome próprio, caminho /wp-login.php e Secure quando HTTPS. A tentativa dura cinco minutos, roda o ID no início e é removida sob lock antes da troca do código.

O servidor HTTP está limitado a 127.0.0.1. Guzzle valida TLS com o bundle de CA WordPress. O servidor não regista URLs de callback. IIS terá de aplicar política equivalente aos logs e erros antes de receber credenciais próprias. Não desactivar TLS para resolver falhas.

O Composer autoloader é carregado uma vez. Nos contentores, ARN_OIDC_VENDOR_AUTOLOAD aponta para /var/www/html/arn-repo/vendor/autoload.php. Sem dependências, os hooks Google não são disponibilizados. Os controlos de contas já vinculadas continuam registados.

## Associação e sessão

Em Ferramentas, Acesso Google local, um administrador com manage_options escolhe uma conta WordPress existente e confirma a activação. O POST verifica nonce e competência. A tentativa guarda alvo, actor e hash da sua sessão. O callback confirma actor, competência e sessão antes de criar e activar o vínculo auditado. Uma identidade já vinculada a outra conta não é reassociada. Não há cadastro automático ou promoção de papéis.

A associação invalida chaves pendentes de recuperação e destrói sessões anteriores do alvo. O login seguinte resolve issuer/sub na persistência, aplica a política e emite sessão WordPress com marca federada. A sessão local dura uma hora e expira após 30 minutos sem actividade. Estes limites não fecham PEN-10. Cada pedido reconsulta vínculo e configuração. Inactividade destrói apenas a sessão corrente. Revogação, configuração desactivada ou falha global recusam acesso e invalidam todas as sessões.

As guardas são registadas sempre. Descoberta segura distingue uma instalação nova de esquema parcial ou indisponível. A opção arn_oidc_schema_ready não pode desligar controlos sobre vínculos existentes. Registos malformados e respostas ambíguas falham fechados. Contas vinculadas, inclusive revogadas, não podem usar password, recuperação ou application passwords. validate_password_reset bloqueia chaves antigas.

A revogação administrativa exige nonce e competência e destrói sessões. No ensaio, retirou o acesso no browser e uma nova autenticação Google foi recusada. A reactivação explícita preservou o par e foi auditada.

## Evidência executada

PHP 8.4.26 NTS, WordPress 7.1.3, MariaDB 11.8.3 e Node 24.21.0 no laboratório portátil. Binários, instalação, base e artefactos de browser não são versionados.

| Verificação | Resultado |
| --- | --- |
| PHPUnit completo | 315 testes, 779 asserções. |
| Cliente mantido com RSA/JWKS de fixtures efémeras | 35 testes, 97 asserções, assinatura, algoritmo, chave adversa, iss/sub/aud/exp/iat, nonce, state, PKCE e falhas HTTP. |
| Sessões WordPress reais | 24 asserções, hooks reais, vias alternativas, cookie, inactividade, sessão paralela preservada e revogação global. |
| Persistência MariaDB | 21 asserções, unicidade, auditoria, rollback e competência. |
| Node, lint e construção | 54 testes e verificações de estrutura aprovados. |
| PHPCS | 63 ficheiros aprovados. |
| Google real com browser Codex e Playwright | Consentimento interno, callback de associação, login, portal autenticado, logout, nova entrada, revogação, recusa e reactivação. |
| Playwright CLI local | Botão Google, password vinculada recusada, callback inválido, portal privado, REST 401, ícone, telemóvel sem overflow e zero erros JavaScript. |
| Revisão independente | Quatro achados corrigidos e revistos. Sem novos P1/P2 no conjunto final. |

As fixtures usam assinaturas RSA reais e transporte simulado. O percurso Google usa endpoints e token assinados pelo fornecedor real. Os dois ensaios são distintos. A CI final da PR tem de passar no SHA exacto antes da integração.

Para repetir a integração isolada:

```text
npm run env:start:tests
npm run env:check:tests
npm run composer:install
npm run test:php
npm run lint:php
npm run test:identity:integration
npm run test:oidc:integration
npm run env:stop:tests
```

F1-02 continua Em curso no backlog institucional, com homologação local do cliente e do fluxo concluída. Falta HTTPS em IIS/PHP/MySQL 8.4, LAN/VPN, cliente e segredos próprios e política institucional de sessões, recuperação e operação. PEN-02/03/10 e F0-04 continuam relevantes. Não houve instalação no servidor ARN, alteração AD/DNS/MFA ou importação de funcionários. F1-03 e F2 mantêm pré-requisitos.
