# Gestão local de concessões F1-03

14 de outubro de 2026. Próximo passo autorizado no laboratório: permitir gerir no painel as concessões já suportadas pelo serviço persistente. A matriz institucional mantém a validação pendente. Não há concessão de capacidades nativas WordPress nem novos módulos ou schema.

## Comportamento

Ferramentas, Concessões locais, slug arn-local-grants. Alvo sempre Ana Teste, resolvido no servidor por LocalFixtureUser::LOGIN e marca exactamente 1. Actor corrente com manage_options, ambiente efectivo local e ambas as flags ARN_ALLOW_FICTIONAL_LOCAL_ACCESS e ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE booleanas true. lab_admin nunca é alvo. Sem criação automática de utilizador, conta funcional ou schema. GET apenas lê. O serviço AuthorizationStorageService mantém a autoridade e a escrita auditada com CAS.

Catálogo fechado de dois presets: employee-own representa Role::Employee e Scope::own(). unit-manager-a representa Role::UnitManager e Scope::units(['unidade-ficticia-a']). São os cenários fictícios já exercitados. O browser só escolhe a chave do preset. IDs de utilizador, actor, role, scope, datas, flags ou referência enviados pelo browser não criam autoridade. Concessões novas usam user_id da fixture, starts_at do servidor, expires_at null e revoked false. Nada permite os pacotes administrativos.

Acções POST create, replace e revoke, dispatcher arn-local-grants. Nonce próprio da ferramenta, revisão esperada decimal positiva canónica sem overflow, justificação ChangeContext. replace e revoke exigem grant_id positivo canónico. Grant ID tem de pertencer ao alvo e ser validado no repositório na mesma transacção. create e replace exigem conta Active pelo serviço/DAO. revoke continua permitido em Suspended. A substituição mantém o registo anterior revogado e cria outro atomicamente. A revogação não elimina registos. Cada mudança efectiva aumenta a revisão e invalida a geração de sessão já existente.

Actor, referência LAB-GRANT-MANAGEMENT e instante da auditoria são resolvidos no servidor. Sucesso redirecciona 303 para URL fixo com código estático. Entradas inválidas recusam antes da ligação quando possível. Falha/CAS informa para recarregar sem expor erro SQL, pedido, nonce ou justificação no URL. Pedidos antigos ou repetidos não podem produzir alterações parciais.

## Interface e organização

Página nativa WordPress com texto pt-PT, nome/estado/revisão da fixture, indicação de que são concessões fictícias e não papéis WordPress, lista das concessões existentes com IDs e estado, formulário criar, substituir ou revogar e ligação à ferramenta de auditoria apenas leitura. Rótulos associados, ajuda curta e tabelas responsivas se necessárias. Não mostrar JSON ou detalhes de implementação como bytes UTF-8. Uma concessão revogada permanece visível no histórico e não é apresentada como activa. Preservar marca ARN existente.

Registar antes de admin_menu/admin_post via wp_loaded. Factory diferida de ligação dedicada com prefixo capturado antes da abertura. Não repetir blocos substanciais de validação, factory e autoridade da página de estado. Se necessário, extrair um colaborador comum pequeno com defaults seguros, mantendo o comportamento e testes da página de estado. Sem interfaces públicas de bypass de gates, sem novos hooks de concessão institucional e sem refactor alheio ao caso.

## Verificação

TDD dirigido para catálogo fechado, gates, identificação exacta, método/nonce, números canónicos e overflow, justificação, acção/preset inválidos, alvo fixo, conta legítima intocada, CREATE/REPLACE/REVOKE através do serviço e CAS. Testar comportamento real de escrita, histórico e revisão por integração WordPress/MariaDB com prefixo/contas exclusivos, sem afectar a fixture persistente. Reutilizar os ensaios existentes quando cobrem a transacção em vez de duplicar suites inteiras.

Playwright local: GET não altera estado, criar concessão de unidade, substituir/revogar com motivo, formulário antigo recusado, auditoria preservada, sessão anterior perde acesso após mudança de concessão e novo login funciona. Anónimo/subscriber recusados. Larguras 375/768/1440 e consola. No final, Ana permanece Active/subscriber e conserva pelo menos a concessão própria válida. O histórico nunca é apagado. Segredos/cookies fora do Git, sem exportar cookies, sem mudanças Google.

Só laboratório. Não implementar delegações institucionais, atribuições a pessoas reais, F2 ou deployment. Revisão, commits/push por fases, PR e CI verde no SHA exacto antes da integração autorizada.
