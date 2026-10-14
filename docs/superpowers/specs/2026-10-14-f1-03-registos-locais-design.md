# F1-03: leitura de registos fictícios por âmbito

## Decisão e alcance

Continuação autónoma autorizada em 14 de outubro de 2026. Ligar as concessões persistentes ao motor de autorização na leitura de uma lista e de um detalhe por URL. Usar uma ferramenta WordPress em vez de iniciar um módulo institucional ou persistência de documentos. A ferramenta é um laboratório funcional de autorização por objecto, sem afirmar que US-A01/02 ou os módulos institucionais estão concluídos.

## Contrato

Ferramentas > Registos fictícios, slug arn-local-records, capability nativa read. Disponível apenas com ambiente efectivo local, ARN_ALLOW_FICTIONAL_LOCAL_ACCESS===true, ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE===true, utilizador corrente existente com login exacto LocalFixtureUser::LOGIN e meta LocalFixtureUser::META_KEY exactamente string1. lab_admin não recebe excepção. Revalidar também ao renderizar e antes da leitura SQL. Não criar contas, concessões ou tabelas em GET.

Uma leitura dedicada de AuthorizationSnapshot corrente e metadados da sessão WordPress corrente deve provar estado Active, titular e revisão/geração através de LocalAccountSessionPolicy::valid. Não basta is_user_logged_in ou read. Falha SQL, snapshot ausente, marca ou sessão inválida recusam de forma fechada. Não guardar decisões em cache. Factory interna injectável para testes é permitida, sem parâmetros de autoridade do browser.

Catálogo fechado de três registos fictícios em código: proprio, unidade-a, unidade-b. Títulos Registo fictício próprio, Registo fictício da unidade A, Registo fictício da unidade B. Conteúdo textual fixo e sintético. O próprio tem Scope::own e owner_id igual ao ID de Ana resolvido no servidor. As unidades têm Scope::units(['unidade-ficticia-a']) e equivalente b, owner_id0. Todos usam Action::ReadDirectory e provas de módulo, estado e audiência calculadas pelo catálogo local. Não usar dados institucionais ou pessoas reais.

AuthorizationPolicy avalia cada objecto com o snapshot, a prova de sessão e o relógio do servidor. Filtrar antes de devolver linhas, títulos ou contagens. A lista mostra apenas os objectos autorizados e a contagem filtrada. GET record aceita somente uma chave textual exacta do catálogo. Chave inválida, malformada, inexistente ou não autorizada devolve a mesma resposta404 genérica, sem títulos ou dados do alvo. A página deve definir nocache_headers. Não existe POST de alteração, exportação, busca, paginação, REST ou AJAX novos.

A interface usa componentes administrativos nativos acessíveis e português. Título Registos fictícios, aviso de laboratório, lista de links autorizados e detalhe com ligação de retorno. Não mostrar um inventário de registos recusados. Inputs de actor, owner, scope, role, revision ou session_valid são ignorados. O detalhe reavalia o mesmo motor que a lista. Um administrador nativo sem fixture não vê registos. Sem mudanças à semântica do motor, ao tema ou Google. Plugin0.6.0.

A auditoria continua nos eventos transaccionais de concessões. Leituras não criam eventos de mutação. O ensaio cria uma concessão unit-manager-a auditada, comprova acesso ao próprio e à unidade A após novo login, nega B e revoga a concessão de ensaio. A conta termina Active, subscriber e concessão própria original preservada, sem apagar o histórico. Não alterar F2, produção, delegações ou atribuições institucionais.

## Evidência exigida

TDD para catálogo, filtro e detalhe, gates, titular, sessão, expiração/revogação, falhas de persistência e resposta sem fuga. Integração WordPress/MariaDB exclusiva com contas/tabelas descartáveis, concessão e revogação auditadas e leitura autorizada usando snapshot real. Playwright no laboratório para lista, URL directa, tampering, sessão antiga, nova sessão, remoção após revogação, visitante e administrador sem fixture. Responsividade375/768/1440. Revisão independente, CI exactSHA, commits/push/PR e integração autorizada. Homologação institucional continua pendente.
