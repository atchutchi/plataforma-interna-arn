# Callback Google e fonte institucional

Entrega de 12 de novembro de 2026 sobre 70cf00d, continuação das PR #81 e #82. Código e ensaios locais, sem implantação no .205, alteração do Wamp ou cópia de dados.

## Composição

O opt-in privado `ARN_INSTITUTIONAL_IDENTITY_ENABLED`, com valor booleano true, selecciona o circuito institucional. Ausente ou false conserva o circuito anterior. Não é uma opção editável no painel nem um parâmetro HTTP. Não abre os módulos de negócio.

A configuração continua no JSON externo apontado por `ARN_OIDC_CONFIG_FILE`, fora do webroot e do repositório. Usa os campos OIDC existentes. No modo institucional, a validação exige HTTPS mesmo num ambiente local e o callback tem de coincidir exactamente com o `site_url` da instalação. O registo Google do destino e a validação com uma conta institucional continuam pendentes. Não colocar segredos no README ou na PR.

As tentativas institucionais usam `sessions-institutional`, separada da pasta de tentativas locais. O armazenamento consome a tentativa antes de validar a resposta. O cliente mantido conserva state, nonce, PKCE e validação criptográfica. `CallbackLogin` só admite a finalidade login, sem alvo ou actor administrativo, e resolve exclusivamente um issuer/sub persistido, autorizado, activo e com geração positiva. Não cria vínculos por email, não reactiva contas e não concede funções.

O resultado segue para `SessionIssuer` e para a mesma `PrincipalSource` usada na guarda nativa. O emissor detecta um veto de `send_auth_cookies`, recusa a entrada e elimina apenas o token novo. Falha ao construir a fonte devolve ausência de fonte sem interromper o bootstrap. Enquanto o modo institucional estiver seleccionado, essa ausência nunca activa o circuito local.

As rotas locais de associação e revogação administrativa ficam fechadas neste modo. Contas com pré-cadastro local são expressamente recusadas. A migração desse estado não pertence a esta entrega e não se retiram as suas guardas para permitir uma entrada aparente. Os 73 registos e os seus prazos não são copiados, renovados ou alterados.

## Validação

O ensaio integrado de identidade passa a 64 verificações. Usa RSA efémera, transporte fictício, WordPress e SQL reais do laboratório. Cobre entrada válida, email alterado com identidade estável, issuer/sub desconhecido, state, nonce, audiência, expiração, domínio, email não verificado, finalidade errada, HTTP recusado, cookie vetado, conservação de outro token e ausência de novas concessões ou eventos de vínculo durante o login.

Também verifica configuração privada, destino exacto, desactivação e recusa das rotas administrativas locais. A suite portátil descobriu 149 ficheiros e passou 1230 testes com 6082 asserções e sete ignorados. Node passou 72 testes, lint e build. A CI deve confirmar o commit final, incluindo regressões de pré-cadastro e de sessões locais.

Playwright exercitou as rotas reais de início e callback em localhost:8890, com transporte OIDC substituído por respostas assinadas fictícias e configuração HTTPS simulada no servidor do ensaio. O browser recebeu o cookie e a fonte confirmou a sessão no pedido seguinte. Uma chamada sem tentativa válida devolveu 403. O redireccionamento para HTTPS não pôde ser concluído neste servidor HTTP, pelo que o pedido seguinte foi aberto no endereço local. Este ensaio não prova TLS, aceitação Google nem configuração institucional. O preparador privado e os cookies ficam fora do Git.

Uma revisão independente detectou o veto silencioso de cookies para pré-cadastros e a excepção durante a composição da fonte. Ambos foram corrigidos. O veto foi reproduzido num teste vermelho antes da correcção. A revisão das correcções não identificou outro bloqueante funcional. Os pré-cadastros permanecem fora deste circuito até uma migração própria.

## Ainda necessário

Comando operacional de migração com manutenção e recibo, instalação vazia representativa, origem institucional dos actores e unidades e composição individual de cada módulo. Preparar a vista sem competências antes de abrir o portal. Homologar o cliente Google, cookies e revogação no HTTPS real, ensaiar restauro e concluir a revisão da base das PR anteriores antes de integrar. O código não instala nem activa esta configuração no servidor.
