# Fundação local de autorização F1-03

13 de outubro de 2026. A continuação pedida pelo responsável após a proposta da PR #9 permite implementar a fundação local com fixtures. Não aprova atribuições institucionais ou os procedimentos pendentes MP-01 a MP-10. A referência funcional é docs/matriz-permissoes-f1-03-proposta.md.

Objectivo: entregar um motor puro que decide por conta, função, âmbito, registo, vigência e estado, acompanhado de uma página de ensaios apenas local. Esta entrega não tem persistência de funções, atribuição de capacidades WordPress ou integração com os módulos institucionais. O login e a sessão Google existentes mantêm o comportamento homologado.

## Contrato

Namespace Arn\Intranet\Authorization, classes PSR-4 próprias, PHP 8.4, sem novas dependências. Inputs imutáveis. Account representa ID positivo, AccountState Pending/Active/Suspended e validade da sessão fornecida pelo chamador. Nenhum input contém email, senha, token ou cargo nominal.

Role tem Employee, UnitManager, HumanResources, Editorial, AccessAdministrator, TechnicalAdministrator, Leadership e Support. Action tem ReadContent, ReadDirectory, UpdateOwnProfile, ReadOwnTask, UpdateOwnTask, AssignUnitTask, ReadUnitTask, PublishTeamNotice, ReadHrRecord, WriteHrRecord, CreateNewsDraft, SubmitNews, ReviewNews, ApproveNews, PublishNews, ManageAccount e ManageGrants.

Scope tem fábricas own(), units(array), people(array), queues(array), audiences(array) e institutional(). IDs de pessoas são inteiros positivos. IDs de unidades, filas e audiências são strings não vazias restritas a letras ASCII, números, hífen e underscore, até 64 caracteres. Listas são não vazias, sem duplicados e com tipos homogéneos. own/institutional não aceitam alvos. Comparações são exactas e sensíveis a maiúsculas. Não inferir descendentes, nomes ou pertença institucional.

Account::__construct(int user_id, AccountState state, bool session_valid). Grant::__construct(int user_id, Role role, Scope scope, int starts_at, ?int expires_at, bool revoked=false). Instantes Unix não negativos. Fim, se presente, tem de ser posterior ao início. Vigência inclui início e exclui fim. Objectos inválidos lançam InvalidArgumentException constante, sem dados externos.

Request::__construct(Action action, Scope scope, int owner_id, int author_id, bool module_ready, bool state_allowed, bool audience_allowed, bool version_current). IDs de contexto são zero quando desconhecidos ou positivos. Estes dados e provas booleanas são calculados no servidor. Não aceitar provas de autorização do browser. Nenhum endpoint desta entrega aceita estes inputs de clientes.

AuthorizationPolicy::allows(Account account, Request request, array grants, int now): bool. Conta diferente de Active, sessão inválida, módulo indisponível, estado inadmissível ou relógio inválido recusam. Uma colecção mal tipada ou que contenha concessão de outra conta recusa por inteiro. Grant revogado, ainda não iniciado ou expirado não concede acesso. Uma concessão válida precisa de permitir a acção e cobrir o âmbito completo do registo. Não combinar duas concessões parciais para autorizar uma operação sobre vários alvos. A agregação futura tem de decidir por registo.

Scopes de tipos distintos não se cobrem, excepto Institutional concedido expressamente, que cobre o âmbito requerido. own exige owner_id conhecido e igual à conta. Scopes de lista exigem que todos os alvos requeridos pertençam à mesma concessão. Institutional requerido exige concessão Institutional.

Catálogo fechado: Employee permite ReadContent, ReadDirectory, UpdateOwnProfile, ReadOwnTask, UpdateOwnTask, CreateNewsDraft e SubmitNews. UnitManager permite ReadDirectory, AssignUnitTask, ReadUnitTask e PublishTeamNotice. HumanResources permite ReadHrRecord e WriteHrRecord. Editorial permite ReviewNews, ApproveNews e PublishNews. AccessAdministrator permite ManageAccount e ManageGrants. TechnicalAdministrator, Leadership e Support não têm acções funcionais por defeito. Acumulação exige concessões explícitas, sem herança de Employee.

UpdateOwnProfile, ReadOwnTask, UpdateOwnTask, CreateNewsDraft e SubmitNews exigem owner_id igual à conta, mesmo com Institutional. ReadContent e ReadDirectory exigem audience_allowed. ApproveNews e PublishNews exigem version_current e author_id conhecido e diferente do actor. Esta recusa de publicação própria é conservadora no laboratório e mantém PEN-07 pendente. ManageAccount e ManageGrants exigem alvo conhecido, diferente do actor. A auto-suspensão e a recuperação permanecem fora deste motor de gestão.

Delegation representa actor_id, manageable_user_ids, allowed_roles, allowed_scope, início, fim opcional e revogação. As listas são explícitas, válidas e não vazias. DelegationPolicy::canGrant(Account actor, array grants, array delegations, int target_user_id, Role requested_role, Scope requested_scope, int now, bool module_ready): bool. Exige primeiro autorização ManageGrants sobre a conta alvo e depois uma delegação válida do mesmo actor que abranja o alvo, o pacote e todo o âmbito pedido. Dados mal tipados ou actor de outra conta recusam. Recusa actor=alvo, AccessAdministrator e TechnicalAdministrator, que precisam de procedimento distinto. allowed_scope own limita o âmbito próprio da conta destinatária, não o do administrador.

## Ensaio WordPress

AuthorizationFixtures::results(int now): array devolve cenários predefinidos, com ID estável, texto pt-PT, decisão esperada e decisão obtida. Usar apenas IDs e unidades fictícias. Cobrir funcionário próprio, colega recusado, unidade autorizada/externa, RH limitado, delegação autorizada/ampliada, auto-promoção, concessão expirada/revogada, conta suspensa, módulo indisponível, notícia própria e versão alterada.

AuthorizationProbe é um adaptador de leitura. Regista Ferramentas, Ensaios de acesso local, slug arn-authorization-probe, apenas com WP_ENVIRONMENT_TYPE=local e ARN_ALLOW_LOCAL_AUTHORIZATION_PROBE booleano true. Página também exige manage_options no momento de renderizar. Mostrar todos os cenários numa tabela nativa acessível, com permitido/recusado e coincide/diverge em texto. Explicar que são dados fictícios e que não alteram acessos. Não criar endpoints REST, POST, formulários de concessão, contas ou dados persistentes. Não expor esta página fora de local ou sem a flag. A flag vai na configuração local e na .wp-env.json de desenvolvimento, nunca em homologação/produção.

## Limites e prova

Testar RED/GREEN, invariantes, relógio nas fronteiras, colecções mistas, âmbito parcial, concessão estrangeira, privilégios próprios e acções fora do pacote. Verificar a página com sessão administrativa local e a recusa sem sessão ou como fixture subscriber. Não alterar contas Google, roles WordPress, vínculo ou sessão de quem usa o portal. Os futuros adaptadores de persistência e dos módulos não podem usar o motor com dados desactualizados ou provas vindas do cliente.

Este motor não suspende sessões WordPress reais, não filtra objectos dos módulos ainda ausentes e não conclui US-A01/02 ou F1-03. Demonstra a decisão com snapshots fictícios. O próximo marco será especificar persistência, auditoria e integração com o estado local, após validar o modelo F0-06. F2 continua condicionado.
