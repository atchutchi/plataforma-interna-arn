# Estado funcional e sessões fictícias F1-03

O pedido de 14 de outubro autoriza ligar o estado persistente às sessões, auditar alterações e avançar autonomamente por fases locais. Esta especificação adopta a revisão da conta como geração de sessão. Não atribui permissões institucionais. O clone separado continua isolado numa branch da tarefa. A integração requer revisão e CI no SHA exacto.

## Comportamento

Só contas fictícias identificadas ficam sob esta guarda. A identificação inclui a marca persistente, o fallback legado de LocalFixtureAccess e uma sessão previamente marcada por este adaptador. Para autorizar exige sempre utilizador existente com marca META_KEY exactamente '1', ambiente efectivo local, ARN_ALLOW_FICTIONAL_LOCAL_ACCESS e ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE booleanos true, snapshot válido da mesma conta em Active. Pending, Suspended, ausência, erro de leitura e dados inválidos recusam. Contas legítimas sem marca ou sessão deste adaptador não consultam este repositório nem são alteradas. lab_admin mantém-se fora dos alvos.

A sessão guarda arn_authz_local=true, arn_authz_user_id e arn_authz_revision inteiros nativos. Cada pedido com cookie exige a mesma conta Active e revisão exacta. Uma alteração de concessão também invalida a geração, decisão conservadora explícita deste laboratório. Uma suspensão seguida de reactivação nunca recupera cookies anteriores, mesmo que não tenham sido usados durante a suspensão. Sessões sem marca anteriores à activação exigem novo login.

Na emissão, authenticate valida o estado, attach_session_information acrescenta os campos internos e send_auth_cookies revalida a geração final. Nenhum erro do repositório pode permitir emissão utilizável. Na resolução, determine_current_user revalida o estado actual sem cache. Recusa não activa, configuração inválida, marca removida ou falha de leitura destrói todas as sessões da fixture. Uma geração antiga com conta actualmente Active remove apenas esse token, preservando uma sessão nova válida. Reactivar exige novo login. Pedidos já em execução não são interrompidos retroactivamente.

Recuperação de palavra-passe, incluindo chaves já emitidas, recusa contas não activas. Application passwords ficam indisponíveis para estas fixtures, pois não transportam a geração da sessão. XML-RPC continua fechado e REST/AJAX usam a identidade revalidada e guardas existentes. Preservar decisões negativas e WP_Error anteriores. A limpeza de cookies com user_id=0 deve passar, sem recursão no filtro send_auth_cookies.

## Componentes

Namespace Arn\Intranet\Authorization\Session. LocalAccountSessionPolicy expõe active(?AuthorizationSnapshot,int):bool e valid(?AuthorizationSnapshot,int,array,bool):bool. WordPressLocalAccountSessions recebe ?callable lookup e ?callable enabled apenas para composição interna/testes, com defaults seguros. register() liga os filtros no bootstrap cedo, depois das guardas existentes. Callbacks públicos: filterAuthentication($user), filterCurrentUser($user_id), attachSessionInformation(array $session,int $user_id):array, filterSendAuthCookies(bool $send,int $expire,int $expiration,int $user_id,string $scheme,string $token):bool, filterPasswordReset($allow,int $user_id), validatePasswordReset(WP_Error $errors,$user):void, filterApplicationPasswords(bool $available,WP_User $user):bool.

A leitura default abre IdentityWpdb dedicada, com prefixo capturado do site e WpdbAuthorizationRepository::find. Só acontece depois de identificar a fixture e verificar as flags. Não usa AuthorizationStorageService de administração na autenticação, pois canManage depende do utilizador corrente e causaria recursão. O snapshot revalida destinatário e tipos. Sem criação de schema, novos campos SQL, alteração a Identity/OIDC ou segredos. Erros apresentados são estáticos.

## Auditoria e gestão local

As transições continuam através de AuthorizationStorageService e ChangeContext, com CAS e auditoria atómica já implementados. Não aumentar revisão ou inserir um evento por cada leitura/autenticação. A invalidação de sessões é efeito da transição auditada e não uma transacção distribuída entre tabelas de autorização e metadados WordPress.

Uma segunda fase acrescenta uma ferramenta Estado de contas local, slug arn-local-account-state, exclusivamente para Ana Teste marcada. A página de leitura persistente anterior mantém-se. O formulário novo exige manage_options, ambiente/flags efectivos, nonce, acção POST conhecida, revisão canónica e justificação validada. O destinatário é sempre resolvido no servidor e lab_admin não pode ser enviado como alvo. Usa apenas Active/Suspended como destinos. O actor é a conta corrente, referência de decisão fixa LAB-ACCOUNT-STATE e instante do servidor. Redirect 303 após sucesso, com código estático de mensagem, nunca texto arbitrário no URL. Falha de revisão informa para recarregar sem alterar dados. Nunca escreve por GET. Esta ferramenta não é gestão institucional de acessos.

## Verificação

TDD puro e dos hooks cobre estados, tipos, revisões, destinatário, erro de leitura, gate efectivo, identificação legada/marca removida, preservação de conta legítima, sessões antigas/nova, flag desligada e vias alternativas. Ensaio WordPress/MariaDB com prefixo e utilizadores exclusivos verifica criação real de tokens, cookies, autenticação, suspensão auditada, recusa, reactivação, não ressurreição e limpeza. Bootstrap real precisa ser exercitado além de doubles.

Playwright em sessões fictícias isoladas: login Active, página privada, suspensão via serviço ou ferramenta local auditada, cookie antigo recusado, login suspenso recusado, REST/AJAX fechados, reactivação e cookie antigo ainda recusado, novo login permitido. Nenhum cookie ou segredo entra no Git. Guardar capturas ignoradas. Repetir suites relevantes, revisão independente, commits/push por fases e PR após CI verde. F1-03 institucional, F0-02/06 completo, IIS/MySQL8.4, F2 e dados reais continuam condicionados.
