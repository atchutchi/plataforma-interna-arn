# Adaptação institucional antes da instalação

Levantamento de 9 de novembro de 2026 sobre a base integrada 79afcaf. Não é autorização para retirar guardas. A troca de `WP_ENVIRONMENT_TYPE` ou a activação de `ARN_ALLOW_FICTIONAL_LOCAL_ACCESS` no servidor não transforma os módulos locais em módulos institucionais.

## Ordem técnica

| Dependência | Pontos de entrada no plugin, sob src/ | Alteração ainda necessária | Aceitação |
| --- | --- | --- | --- |
| Identidade e sessão | Identity/GoogleLoginController.php, Identity/ProtectedOidcConfiguration.php, Authorization/Records/CurrentLocalAccount.php, Authorization/Session/WordPressLocalAccountSessions.php | Composição institucional de issuer/sub e sessão revogável, configuração HTTPS aprovada e sem identidade de fixture | Conta sem associação recusada, suspensão revoga sessão, reactivação explícita, alteração de email não reassocia identidade |
| Pessoas e unidades | Organization/Storage/LocalUnitInstaller.php, Organization/Register/LocalAccess.php, Organization/Lifecycle/LocalAccess.php, People/PeopleConfiguration.php | Fonte autorizada por ambiente, migrações sem dados iniciais fictícios e principal real sem login fixo | Instalação vazia não concede direitos, importação auditada e sem criação de contas por CSV, isolamento de unidades |
| Concessões e delegações | Authorization/Storage/LocalAuthorizationStorageAuthority.php, Authorization/Delegations/LocalAccess.php | Gestor designado explicitamente e resolução institucional de actor/âmbito | Limites de âmbito, prazo, revogação e concorrência, sem autoelevação por papel WordPress |
| CMS, documentos e notícias | Cms/Configuration.php, Documents/Catalog/LocalDocumentCatalog.php, News/NewsConfiguration.php e respectivos instaladores | Composição institucional dos actores, parâmetros e ficheiros, migrations separadas de LocalInstaller | Autoria, decisão, publicação e download conservam audiência e versão, administrador técnico sem acesso implícito |
| Comunicados, calendário e tarefas | F112/Configuration.php, F112/LocalInstaller.php, Tasks/Configuration.php, Tasks/LocalInstaller.php | Substituir elencos de teste por participantes persistentes e fontes aprovadas | Pessoas/unidades revogadas deixam de participar, concorrência e notificações por destinatário conservadas |
| Pedidos, tickets e férias | Requests/Configuration.php, Support/Configuration.php, Leave/Configuration.php e respectivos instaladores | Actores institucionais, migrações próprias, configuração aprovada de tipos, calendários e saldos | Sem importação dos saldos/contas de teste, recusa de autoaprovação, estados e ficheiros íntegros |
| Media e operações | Media/QueueAccess.php, ConsumerJobs.php, SupervisorAccess.php, HealthAccess.php, LocalMediaProcessing.php, PdfRenderer.php | Operadores explicitamente designados, configuração privada e serviço operacional compatível | Sem acesso técnico a conteúdos alheios, scanner indisponível fecha admissão, retomada preserva recibos e hashes |
| Portal e composição | Adaptadores de painel, pesquisa, notificações e navegação dos módulos acima | Composição institucional só de fontes instaladas e autorizadas | GET sem mutação, nenhuma referência a objectos recusados, desempenho e acessibilidade aceites |

Os caminhos indicam pontos concretos, não uma lista exaustiva de todos os consumidores. Antes de cada alteração, pesquisar as chamadas e as factories do módulo. Conservar os testes de ambiente local e acrescentar um segundo circuito isolado com identidades fictícias e configuração institucional simulada.

## Contrato de migração

Cada migração declara módulo, versão anterior admitida, versão seguinte e pré-condições. Planeia antes de escrever. Não executa em activação, GET ou navegação. Recusa schema desconhecido, dependência ausente e instalação parcial não reconhecida. Obtém exclusão de manutenção e regista intenção, resultado e falhas sem segredos.

Separar DDL e validação de dados da criação de páginas/configuração. DDL MySQL não é tratado como reversível por transacção. Uma interrupção deixa o módulo fechado até retoma explícita e verificação do schema efectivo. Não copiar `LocalInstaller`, chamar instaladores de fixtures, repor revisões ou conceder papéis automaticamente.

Ensaiar instalação vazia, reexecução idempotente, upgrade da versão suportada, interrupção antes/depois de cada etapa, schema incompatível e rollback/restauro. A base, os ficheiros privados, os recibos e o histórico têm de permanecer coerentes. Nenhum ensaio usa dados reais.

## Próxima entrega de desenvolvimento

Começar pela composição de identidade/sessão e fonte institucional de actores. Os módulos de negócio dependem dessa fronteira. Não substituir todos os testes de ambiente por uma opção global de produção. Só abrir cada módulo quando o respectivo schema, fonte, autorização e configuração estiverem prontos e ensaiados. O candidato F1-17 permanece bloqueado enquanto esta adaptação não estiver concluída.
