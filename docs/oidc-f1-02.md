# F1-02: preparação técnica e barreiras à activação

Data: 13 de outubro de 2026. Executante: Codex a pedido de Atchutchi. Branch: feature/atchutchi-F1-02-oidc. F1-02 está Em curso apenas no marco de preparação. F1-01/F0-04 continuam a condicionar homologação e activação institucional.

## Código entregue

OidcConfiguration lê cinco nomes através de um callable externo. ARN_OIDC_ENABLED tem de devolver exactamente o texto 1. Qualquer outro valor devolve null sem ler segredos. Com a flag activa, CLIENT_ID, CLIENT_SECRET, REDIRECT_URI e WORKSPACE_DOMAIN são obrigatórios. Os nomes completos têm prefixo ARN_OIDC_. Sem configuração completa, lança uma excepção que não inclui valores.

O callback exige HTTPS, nome DNS, ausência de credenciais embutidas e fragmento. Não admite IP, localhost ou .local. Conserva query estática e caminho exactos, porque alguns clientes WordPress usam admin-ajax.php. Esta validação sintáctica não comprova sufixo público elegível, domínio controlado, resolução, certificado ou registo no Google. Tudo isso exige homologação. Não foi inventado callback institucional.

O segredo é privado, fica em memória, é omitido no JSON e substituído por [oculto] no debug normal. Serialização é recusada. O adaptador futuro deve consumir o valor apenas na troca segura de código e não o deve colocar em logs ou opções WordPress. Estes mecanismos não isolam o segredo de código PHP privilegiado. O leitor pode consultar getenv ou configuração protegida fora da raiz pública. Não há leitura automática, exemplo com credenciais reais ou alteração a Google Admin.

LocalIdentityLink conserva o par emissor e sub, ID WordPress, autorização explícita e estado activo. Os emissores Google documentados accounts.google.com e https://accounts.google.com são canonizados para o mesmo fornecedor. O sub é opaco e não é transformado. Não existem persistência, importação ou operação administrativa de associação nesta entrega.

GoogleAccountPolicy resolve apenas o ID do único vínculo correspondente, activo e autorizado, com hd exacto e email_verified booleano verdadeiro. Um email igual com sub diferente não entra. Mudança de email com o mesmo par emissor/sub não cria funcionário. Vínculos duplicados ou ambíguos falham, mesmo se um estiver desactivado. Nenhum email ou domínio concede funções.

O argumento validated_claims tem uma pré-condição de segurança: um cliente mantido tem de validar assinatura/JWKS, emissor, audiência, expiração, state e nonce antes de o fornecer. A política não verifica tokens brutos, não é um validador JWT e não é suficiente para login. Nenhum hook, endpoint ou callback a chama nesta entrega. As classes não emitem sessão, não criam contas e não atribuem capabilities. Os testes usam claims fictícias para verificar apenas a regra local, sem afirmar validação criptográfica.

## Auditoria do candidato

Foi examinado o código primário de OpenID Connect Generic Client 3.11.3, tag no commit 0ef442c0f61bda837bddacec37b96035176bee27. O candidato continua sem aprovação ou instalação. Fontes: [cliente](https://github.com/oidc-wp/openid-connect-generic/blob/3.11.3/includes/openid-connect-generic-client.php), [wrapper](https://github.com/oidc-wp/openid-connect-generic/blob/3.11.3/includes/openid-connect-generic-client-wrapper.php) e [validador](https://github.com/oidc-wp/openid-connect-generic/blob/3.11.3/includes/openid-connect-generic-jwt-validator.php).

O método get_id_token_claim verifica assinatura quando endpoint_jwks está configurado, mas tem uma via de descodificação sem verificação se não estiver. A futura integração tem de recusar configuração sem JWKS, sem aceitar apenas um aviso. get_user_by_identity procura por sub e devolve o primeiro resultado, sem chave composta com emissor. A política ARN tem de recusar vínculos ambíguos e preservar a chave composta. link_existing_users associa por email quando activo. Essa opção e create_if_does_not_exist têm de ficar desactivadas.

Não foi encontrada validação de nonce nos três ficheiros examinados. Isto é uma constatação limitada a esse código e não uma alegação sobre todas as extensões possíveis. Antes de adoptar o candidato, comprovar protecção de state/nonce contra replay, vinculação ao navegador e utilização única, além da assinatura e claims. Não criar um protocolo próprio para compensar estes pontos. Avaliar um adaptador ou biblioteca mantida se o candidato não passar.

Requisitos de identidade e claims conferidos na [documentação Google OIDC](https://developers.google.com/identity/openid-connect/openid-connect). A pertença ao Workspace vem de hd, sem substituição pelo sufixo de email. O vínculo estável usa sub.

## O que depende da instituição

| Dependência | Informação ou ensaio necessário |
| --- | --- |
| F1-01, F0-03/04 e INF-06 | Homologação Windows/IIS/PHP 8.4/MySQL 8.4 representativa de servidor fora do domínio |
| Cliente Google | Client ID e Client Secret institucionais, fornecidos e guardados fora do Git |
| Callback | Nome HTTPS aprovado, callback real do cliente seleccionado e registo exacto no Google |
| Identidade | Vínculo explícito das contas administrativas e funcionários locais previamente autorizados |
| Cliente mantido | Assinatura/JWKS obrigatória, claims, state/nonce, replay e Google real testados |
| F1-03/04/05 | Matriz funcional, estado persistente, revogação de sessões, cadastro e auditoria |
| Vias alternativas | Bloquear password e recuperação locais de contas federadas e application passwords. Recuperação técnica só após PEN-10 |
| LAN e VPN | Fluxo real completo, restrição de rede e ausência de exposição pública |

## Verificação e continuação

RED: os três ficheiros de teste foram criados e executados antes das classes. Os 42 cenários falharam por classes ausentes. GREEN inicial: suite completa com PHP 8.4.26, PHPUnit 11.5.57, 105 testes e 353 asserções aprovados. PHPCS apontou apenas anotação da serialização e uso deliberado de funções PHP nos testes. Os apontamentos foram corrigidos sem alterar o ruleset. A evidência final de testes e CI fica no README e na PR.

Revisão independente aprovada no commit 70a6bf283b7fc3422e1f98e7c49553552a819842, com PHPUnit repetido pelo revisor. Dois testes adicionais confirmam que identidades diferentes mantêm vínculos independentes e que uma colecção mal tipada falha fechada. Resultado final local: 107 testes, 357 asserções e PHPCS 34/34. Classes sem alterações após a revisão. Entrega e verificações finais na [PR #5](https://github.com/atchutchi/plataforma-interna-arn/pull/5). Esta entrega é preparação isolada, sem homologação ou activação.

O daemon Docker não está disponível nesta sessão. PHP 8.4.26 NTS portátil e Composer, ambos com checksum conferido, foram guardados apenas em .git/tools. Dependências foram instaladas a partir do composer.lock em vendor não versionado. Não houve regeneração do lockfile, instalação no sistema ou alteração de servidores. Estes testes verificam código, sem WordPress integrado ou IIS.

Próxima tarefa: seleccionar e homologar o cliente mantido e implementar o adaptador de associação persistente, os bloqueios de vias alternativas e os ensaios com fornecedor simulado. Configuração real, competências administrativas e sessão de recuperação permanecem pendentes. F1-03 não fica concluída pela existência desta política. F2 não começa nesta entrega.
