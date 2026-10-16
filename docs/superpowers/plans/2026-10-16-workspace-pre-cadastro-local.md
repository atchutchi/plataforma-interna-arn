# Plano de implementação do pré-cadastro Workspace local

> For agentic workers: REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Preparar 73 contas nominativas no laboratório, sem funções, com primeiro vínculo Google auditado e entrada posterior exclusivamente por issuer/sub.

**Architecture:** O módulo Identity/PreRegistration mantém o lote autorizado, os estados e a auditoria. A criação WordPress usa uma ligação dedicada e DML transaccional. O callback existente recebe um resolvedor adicional, depois da validação Facile. O primeiro vínculo e a passagem a Linked confirmam na mesma transacção das tabelas de identidade existentes.

**Tech Stack:** WordPress 7.1.3, PHP 8.4, MariaDB 11.8 do laboratório, Node 24.21.0, Facile 1.0.0 já fixado, PHPUnit, PHPCS e Playwright CLI. Sem novas dependências.

**Spec:** docs/superpowers/specs/2026-10-16-workspace-pre-cadastro-local-design.md, aprovada pelo responsável neste chat depois do commit c5a127b. Este plano aguarda revisão. Não há implementação ou aplicação do lote.

## Global Constraints

Só laboratório local, com HTTP/SQL em loopback. Nada em produção, AD, DNS ou MFA. As quatro contas genéricas continuam para revisão. Os 73 nomes apresentados e emails vêm exclusivamente da fonte privada seleccionada. Não usar a lista do README, aliases, cargos ou unidades inferidos.

Nenhuma role, capability individual, grant, perfil People, email, convite ou sessão durante a criação. Não marcar pessoas reais como fixtures. Não alterar contas, vínculos, ficheiros ou sessões anteriores. Os âmbitos Google continuam openid e email.

Prazo inicial: 86400 segundos desde observed_at_utc da fonte, com now < expires_at. A igualdade recusa. Fonte futura ou sem instante UTC canónico recusa. A repetição do lote não renova o prazo. Só uma revalidação CLI explícita de origem renova uma entrada pendente exacta e cria evento.

Conservar Facile, PKCE, state e nonce consumíveis, verificação de assinatura e claims e limites da sessão de uma hora e 30 minutos de inactividade. Não activar por GET ou callback. Tabelas incompatíveis ou preparação incompleta fecham as rotas.

A Issue #36 da Albertina trata a compatibilidade transaccional MySQL nos consumidores existentes. Esta implementação não altera IdentityWpdb nem esses consumidores e não incorpora o stash da colaboradora. O laboratório actual não homologa MySQL 8.4 ou IIS.

## Review Focus

1. Email com sub já ligado a outra conta WordPress: preservar o vínculo anterior e manter o novo pré-cadastro fechado. Tarefa 4.
2. Prazo expira enquanto decorre a autenticação: validar novamente dentro da transacção de associação. Tarefas 1 e 4.
3. Papel por defeito ou plugin tenta conceder capacidades ou enviar email: a criação não pode executar esse caminho e deve confirmar zero competências antes de abrir acesso. Tarefas 2 e 3.
4. Esquema desaparece, flag desliga ou marcador fica malformado depois da criação: não recuperar password, sessão nativa ou acesso como conta comum. Tarefa 3.
5. Um lote pára depois da primeira conta: repetição conserva a conta exacta e só cria as faltantes, sem duplicar auditoria ou aceitar dados diferentes. Tarefa 2.

## Estrutura e contratos

Novos ficheiros de produto em wp-content/plugins/arn-intranet-core/src/Identity/PreRegistration/. Novos testes em tests/phpunit/ e tests/integration/. Não alterar F1-05, F1-13, a matriz de concessões ou as fixtures dos módulos anteriores.

WorkspaceEntry tem display_name e primary_email. WorkspaceBatch tem entries, source_sha256, inventory_sha256, observed_at e expires_at. PreRegistration tem id opaco hexadecimal de 32 caracteres, user_id, display_name, primary_email, state, revision, authorized_by, source_sha256, inventory_sha256, observed_at, expires_at e disabled_from opcional. Estados: AwaitingGoogle, Linked e Disabled. disabled_from conserva o estado anterior sem apagar o vínculo.

Tabelas novas: arn_workspace_schema, arn_workspace_accounts e arn_workspace_audit. Versão 1. A conta tem índices únicos em id, user_id e email primário. Auditoria append-only com ID, registo, actor, acção, revisão, estado anterior/novo, hashes da origem, razão e instante UTC. O email é ASCII canónico do domínio configurado e o nome é UTF-8. Falha de UTF-8 recusa. A validade e os limites de tamanho são verificados antes de SQL. Não copiar os campos de utilização da exportação.

## Tarefa 1: fonte privada e política pura

Ficheiros a criar: Identity/PreRegistration/WorkspaceEntry.php, WorkspaceBatch.php, PreRegistrationState.php, FirstGoogleClaimPolicy.php e PrivateWorkspaceSource.php. Testes: WorkspaceBatchTest.php, FirstGoogleClaimPolicyTest.php e PrivateWorkspaceSourceTest.php. Duplo: tests/phpunit/doubles/WorkspaceInventoryFixture.php.

Interfaces: PrivateWorkspaceSource::load(string $inventory_path, string $source_path, string $inventory_sha256, string $source_sha256, string $domain, int $expected_count, int $now): WorkspaceBatch. FirstGoogleClaimPolicy::allows(array $validated_claims, string $primary_email, string $domain, int $expires_at, int $now): bool. O nome validated_claims significa saída do cliente mantido, nunca claims recebidas directamente do browser.

- [x] Escrever testes RED testSelectedPopulationAndExactNames e testClaimExpiresAtExactBoundary. O gerador fictício produz 77 registos, 73 seleccionados e quatro genéricos em example.invalid, com ambos os hashes correctos. Usar `self::assertCount(73, $batch->entries)` e `self::assertSame($batch->observed_at + 86400, $batch->expires_at)`. Aos expires_at - 1 aceita e aos expires_at recusa. Testar duplicado, alias não seleccionado, nome divergente, UTF-8 inválido, fonte futura, email_verified igual a 'true' e hd diferente.
- [x] Executar vendor/bin/phpunit -c phpunit.xml.dist --filter 'WorkspaceBatchTest|FirstGoogleClaimPolicyTest|PrivateWorkspaceSourceTest'. Esperado: falha por classes ausentes.
- [x] Implementar os valores e a leitura. Limite de cada ficheiro: 524288 bytes. Recusar symlinks/reparse points, ficheiros não regulares e qualquer ancestral com redireccionamento não validado. Exigir caminho sob ARN_WORKSPACE_PRIVATE_DIR, exterior aos checkouts Git e ao document root. Validar ACL local sem herança ampla antes de ler os dados reais. Comparar hashes dos bytes lidos, sem reler conteúdo mutável entre hash e parse. Não gravar payload ou erros identificáveis.
- [x] Repetir o comando focado e PHPCS dos novos ficheiros. Esperado: todos passam, sem acesso à base ou à fonte real.
- [x] Commit: feat: validar fonte privada do pré-cadastro Workspace.

## Tarefa 2: criação explícita, retoma e auditoria

Ficheiros a criar: PreRegistration.php, PreRegistrationSchema.php, WpdbPreRegistrationRepository.php e WorkspacePreRegistrationCommand.php. Testes: PreRegistrationRepositoryTest.php e tests/integration/workspace-provisioning.php. Modificar package.json apenas para acrescentar test:workspace-provisioning:integration com o padrão CLI já existente.

Interfaces: WpdbPreRegistrationRepository::__construct(IdentityWpdb $database), installSchema(): void, findForUser(int $user_id): ?PreRegistration, findForEmail(string $email): ?PreRegistration e provision(WorkspaceEntry $entry, WorkspaceBatch $batch, int $actor_id, int $now): PreRegistration. A validação de autorização CLI acontece antes de construir o adaptador de escrita. O comando usa o domínio de GoogleLoginController::configuration(), exige 73 no lote real e não aceita alvos arbitrários do browser.

- [x] Escrever RED SQL com prefixo exclusivo, incluindo testAuditFailureLeavesNoNativeUserOrRegistration. Criar duas entradas fictícias, repetir, interromper antes da segunda e retomar. Assert mesmo user_id/password/revisão/eventos para a primeira, criação só da segunda. Forçar falha da auditoria: `self::assertFalse(get_user_by('email', 'alfa@example.invalid'))` e `self::assertNull($repository->findForEmail('alfa@example.invalid'))`. Confirmar também ausência de meta órfã. Conflito com utilizador preexistente recusa e preserva-o. Todos os ensaios usam contas descartáveis e nunca o lote real.
- [x] Executar npm run test:workspace-provisioning:integration. Esperado: adaptador ainda ausente. Guardar o resultado RED privado.
- [x] Implementar DDL explícito e DML por conta. Verificar tabelas e índices, InnoDB, site único e esquema WordPress esperado. Usar uma IdentityWpdb dedicada. SET TRANSACTION ISOLATION LEVEL READ COMMITTED antes de START TRANSACTION recusa transacção já aberta. GET_LOCK por lote com timeout limitado e libertação em finally. Nenhuma atomicidade global é alegada.
- [x] Inserir directamente os campos nativos necessários de wp_users/wp_usermeta na mesma ligação da auditoria. Não chamar wp_insert_user, user_register ou notificações nativas neste adaptador. Login arn.pre. mais 24 hex aleatórios, nicename opaco, password de 24 bytes criptográficos/48 hex, hash nativo, chave de recuperação vazia, locale pt_PT, capabilities serializadas vazias e user_level zero. Inserir _arn_workspace_pre_registration com o ID opaco. Não guardar ou devolver a password. Invalidar caches nativas após commit e rollback.
- [x] Registar CLI arn workspace install-schema, preflight e provision. O último exige --apply e os dois caminhos/hashes privados. Exigir WP_CLI, local, manage_options, flag ARN_ALLOW_LOCAL_WORKSPACE_PREREGISTRATION estritamente true, esquema íntegro e marcador de preparação final. A pré-validação só devolve totais/códigos à consola. A aplicação permanece fechada até à tarefa 5 concluir a preparação.
- [x] Repetir SQL e PHPCS. Confirmar zero emails por interceptação de wp_mail, zero sessões e ausência de role mesmo com default_role=administrator numa instalação exclusiva. Commit: feat: persistir pré-cadastros locais com auditoria.

## Tarefa 3: guardas anteriores ao vínculo e estados

Ficheiros a criar: WordPressPreRegistrationAccess.php e PreRegistrationStateService.php. Modificar arn-intranet-core.php para registar sempre as guardas, antes do gate de dependências OIDC. Testes: WordPressPreRegistrationAccessTest.php e tests/integration/workspace-access.php. Acrescentar test:workspace-access:integration em package.json.

Interfaces: WordPressPreRegistrationAccess::register(): void e PreRegistrationStateService::setEnabled(string $id, int $expected_revision, bool $enabled, int $actor_id, string $reason, int $now): PreRegistration. Consultar marcador nativo e registo persistente sem atribuir autorização por nenhum deles isoladamente. Revisões positivas, CAS obrigatório e overflow recusado.

- [x] Escrever RED para password tecnicamente correcta de uma fixture AwaitingGoogle, recuperação nova/antiga, application passwords, cookie nativo anterior ao vínculo, marcador malformado, esquema removido e flag desligada. Assert recusa sem elevar outras contas ou mexer na conta administrativa anterior.
- [x] Executar PHPUnit --filter WordPressPreRegistrationAccessTest e npm run test:workspace-access:integration. Esperado: classes ausentes.
- [x] Implementar guardas authenticate, determine_current_user, allow_password_reset, validate_password_reset e wp_is_application_passwords_available_for_user com prioridade final. Flag ausente, texto, false, ambiente diferente, ausência de dependências e incoerência de marcador/registo não podem transformar uma conta pré-cadastrada numa conta nativa. Passwords continuam bloqueadas em todos os estados, incluindo Disabled.
- [x] Implementar disable/enable por CLI com revisão e razão. Escrita de estado e evento na mesma transacção. Desactivar destrói sessões depois de confirmar a mudança. A guarda compara arn_workspace_revision da sessão com a revisão corrente para cobrir falha entre commit e destruição. Reactivar não valida cookies antigos, não cria sessão e não muda issuer/sub. Nenhum estado concede funções.
- [x] Repetir testes e comparar os utilizadores anteriores. Commit: feat: bloquear vias nativas dos pré-cadastros Workspace.

## Tarefa 4: primeiro vínculo Google atómico

Ficheiros a criar: Identity/AuthorizedIdentityClaim.php e Identity/PreRegistration/PreRegistrationLoginResolver.php. Modificar Identity/WpdbIdentityLinkRepository.php, GoogleLoginController.php e WordPressFederatedSession.php. Testes: PreRegistrationLoginResolverTest.php e tests/integration/workspace-google-claim.php. Acrescentar test:workspace-claim:integration em package.json.

Interfaces: AuthorizedIdentityClaim contém LocalIdentityLink $link e int $authorizing_actor_id. WpdbIdentityLinkRepository::claimActive(callable $authorize): LocalIdentityLink abre a transacção já usada pelo repositório e chama $authorize(IdentityWpdb $same_connection): AuthorizedIdentityClaim dentro dela. PreRegistrationLoginResolver::resolve(array $validated_claims, OidcConfiguration $configuration, int $now): LocalIdentityLink faz a resolução. WordPressFederatedSession::issue(LocalIdentityLink $link, ?int $pre_registration_revision = null): string conserva a assinatura antiga compatível pelo argumento opcional.

- [x] Escrever RED para dois subs a disputar a mesma conta, um sub já ligado a outra conta, email não seleccionado, prazo expirado durante o fornecedor, conta com capability acrescentada, vínculo revogado e falha na última auditoria. No conflito com vínculo anterior, assert mesmo alvo/flags/sessões desse vínculo. Não se reassocia por email.
- [x] Executar PHPUnit --filter PreRegistrationLoginResolverTest e npm run test:workspace-claim:integration. Esperado: contrato novo ausente.
- [x] Implementar resolve primeiro pelo issuer/sub existente. Preservar o comportamento das contas anteriores. Para um vínculo de conta já pré-cadastrada, revalidar Linked, gates, marcador, revisão e zero capabilities antes de emitir sessão. Disabled ou vínculo revogado recusam. Uma colisão com um pré-cadastro pendente para o email não adopta esse registo. Guardar o conflito no relatório privado com deduplicação por par, sem claims/tokens nos logs. Só na ausência de vínculo chamar claimActive.
- [x] Dentro do callback transaccional, usar a ligação recebida para bloquear o pré-cadastro e confirmar AwaitingGoogle, revisão, validade temporal, marcador, existência da conta, emails exactos e capabilities vazias. Revalidar FirstGoogleClaimPolicy. Actualizar Linked e evento na mesma ligação e devolver AuthorizedIdentityClaim com o actor previamente guardado na decisão CLI, nunca actor recebido do browser.
- [x] claimActive valida a decisão, a conta e a unicidade e insere vínculo activo mais os eventos create/activate existentes antes do COMMIT. Uma falha reverte registo, vínculo e todas as auditorias. Não abrir outra ligação ou usar create/activate em duas transacções. Os métodos antigos conservam os seus testes e as suas competências.
- [x] Integrar resolve apenas depois de MaintainedGoogleClient::complete. Emitir sessão apenas após confirmação e releitura, com arn_workspace_revision. Bloquear startLink manual para alvos pré-cadastrados e excluí-los do selector administrativo para não contornar a lista fechada. Preservar a revogação explícita.
- [x] Provar concorrência com dois processos, barreira e observação de lock no laboratório exclusivo. Um vínculo, uma passagem a Linked e os eventos esperados. Repetir suites de identidade/sessão antigas e novas. Commit: feat: associar primeiro login Google dos pré-cadastros.

## Tarefa 5: portal sem competências e revalidação de origem

Ficheiros a criar: PreRegistrationPortal.php e LocalWorkspaceReadiness.php. Vista: wp-content/themes/arn-intranet/portal/pre-registration.php. Modificar o bootstrap do plugin e acrescentar testes PreRegistrationPortalTest.php e tests/integration/workspace-readiness.php. Criar docs/pre-cadastro-workspace-local.md e docs/qa-pre-cadastro-workspace-local.md.

Interfaces: LocalWorkspaceReadiness::complete(int $actor_id): void valida os esquemas e as guardas e escreve arn_workspace_prereg_ready=1 só por CLI. WpdbPreRegistrationRepository::renew(string $id, WorkspaceBatch $batch, int $expected_revision, int $actor_id, string $reason, int $now): PreRegistration só renova AwaitingGoogle exacto. Não muda nome, email, conta ou password.

- [x] Escrever RED com fixture Linked e sessão federada válida, mas sem competências. Assert só acesso à página pessoal de preparação e logout, sem corpo de módulos, ficheiros, nomes de colegas, contagens, CMS, REST, AJAX, feeds, XML-RPC ou pesquisa. Sem excepção administrativa. Testar GET/HEAD sem escrita e flag/schema incompatíveis.
- [x] Executar PHPUnit --filter PreRegistrationPortalTest e integração exclusiva workspace-readiness.php. Esperado: classes ausentes.
- [x] Implementar guarda central de rotas para contas pré-cadastradas. GET/HEAD da raiz apresenta a vista mínima com nome próprio escapado e texto «O teu acesso está em preparação. As permissões ainda não foram atribuídas.» Logout usa o mecanismo nativo. As outras superfícies recusam sem corpo de dados, antes das fontes e contagens. Não usar a navegação dos módulos ou as ferramentas fictícias nesta vista. Nome e sessão nunca entram em caches partilhadas.
- [x] Reutilizar marca, fontes e estilos existentes. Um h1, foco e ligações acessíveis. Não instalar tema novo. Não implementar perfil People. Documentar as recusas e cabeçalhos private/no-store, sem dados reais nos exemplos.
- [x] Implementar CLI renew com fonte/hashes novamente validados e uma revisão por renovação efectiva. Prazo não se renova por reaplicar o lote. Preparação final exige fonte/configuração protegidas, esquemas compatíveis, guardas carregadas e ausência de instalação parcial. Ausência do marcador final mantém provision e login de pré-cadastro fechados.
- [x] Repetir testes e idempotência depois de Linked/Disabled sem repor estados. Commit: feat: apresentar estado de acesso sem competências.

## Tarefa 6: ensaio completo fictício e revisão

Ficheiros: tests/integration/workspace-google-browser.php com fornecedor simulado em processo/instalação exclusivamente de teste, scripts e provas Playwright privados e documentação QA da tarefa 5. Acrescentar uma job exclusiva no workflow principal para as integrações novas, sem editar o workflow MySQL da Albertina.

- [ ] Preparar testes RED de browser para as rotas novas antes de as activar no laboratório normal. Gerar identidades, assinatura RSA e JWKS fictícios. Nenhum transporte simulado ou chave de ensaio entra no servidor HTTP normal.
- [ ] Executar com Playwright CLI o primeiro callback, repetição, sub adverso, prazo limite, falha de auditoria, sessão válida sem funções, logout, nova entrada, disable/enable, cookie antigo, recuperação e vias nativas. Obter nonces na própria sessão quando necessários. Esperado: percursos positivos completos e recusas pelos motivos isolados.
- [ ] Executar 375/768/1440 px, teclado, foco e zero erros JavaScript. Capturas e storageState ficam privados. CSS 200% é distinto de zoom nativo. Não usar contas reais para provar todas as variantes.
- [ ] Executar npm test, npm run lint, npm run build, PHPUnit completo, PHPCS e integrações novas mais identity/oidc/account-sessions. Windows sem Docker pode usar PHP portátil e dependências ignoradas existentes. A CI corre o runtime de contentores. Registar contagens efectivamente obtidas, sem fixar antecipadamente um número de testes aprovados.
- [ ] Rever o diff completo contra os 12 critérios da especificação, com atenção a atomicidade entre tabelas nativas e próprias, autoridade da primeira associação e perda de sessão. Corrigir achados e repetir só os testes afectados e a verificação final necessária. Commit das correcções e push. CI verde no SHA exacto antes de activar.

## Tarefa 7: aplicação local controlada e entrega

Ficheiros operacionais, dump, inventário, relatório, configuração e capturas ficam fora do GitHub. Actualizar apenas README, AGENTS e os dois documentos de operação/QA com resultados agregados e limitações.

- [ ] Confirmar que o inventário privado continua autorizado, íntegro e dentro da validade de observação. Se expirou, voltar a consultar/exportar a fonte autorizada e comparar a selecção. Não inventar sub ou mover a data observada. Qualquer alteração da população fica para revisão antes da aplicação.
- [ ] Guardar backups e snapshots novos e imutáveis da base, sessões, opções por nome/hash, configuração, 89 ficheiros e vínculos anteriores. Confirmar as duas junctions e os listeners antes de qualquer mudança. Não substituir provas F1-07/F1-12.
- [ ] Depois da revisão e CI, ligar explicitamente o código candidato ao laboratório sem mudar segredos ou expor portas. Configurar raiz privada e flag própria preservando os bytes anteriores. Instalar esquemas e concluir readiness por CLI. Executar preflight, guardar relatório privado e só depois provision --apply.
- [ ] Confirmar 73 registos/contas novas sem roles/capabilities, zero grants/perfis People/emails/sessões e quatro excluídos. Repetir o comando e provar igualdade integral. Um conflito ou falha deixa o lote fechado para retoma, sem apagar entradas ou adoptar contas antigas.
- [ ] Comparar todas as linhas anteriores, sessões, opções e ficheiros. Justificar cada delta nativo, sem excluir opções problemáticas. Não efectuar login nativo do administrador para obter prova. Não podar sessões antigas como preparação.
- [ ] Validar Google real apenas com o titular autorizado. Se issuer/sub já estiver ligado à conta administrativa anterior, preservar esse vínculo e registar o conflito com o novo pré-cadastro. A conta nova continua fechada e não se afirma que esse caso provou um primeiro vínculo novo. Os restantes percursos continuam provados com identidades fictícias.
- [ ] Verificar o diff acrescentado contra os nomes/emails/identificadores privados e padrões de segredos. Commit e push só de código, documentação e fixtures fictícias. Actualizar a PR com o estado final e evidência. Integração Git autorizada após revisão e CI exacta, sem deploy. Fases institucionais, RH e IIS/MySQL mantêm as suas pendências.

## Cobertura e execução

Critérios da especificação: 1 nas tarefas 1/2, 2 e 3 nas tarefas 2/7, 4 na 2/4, 5 e 6 na 4/6, 7 e 8 na 3/6, 9 na 5/6, 10 na 7, 11 na 6/7 e 12 na 6/7. Todos os cinco riscos de Review Focus têm testes nas tarefas indicadas.

Recomenda-se execução directa neste chat, tarefa a tarefa, com uma revisão independente do conjunto antes da activação. Os contratos de identidade, transacção e sessão estão estreitamente ligados. Não lançar agentes de implementação sem essa opção ser escolhida. O pedido actual aprova a especificação e a preparação deste plano. A revisão do plano e a escolha de execução precedem código de produto e criação das 73 contas.

## Registo de execução posterior à aprovação

O responsável aprovou a execução directa com revisão final. Tarefas 1 a 5 implementadas. A tarefa 6 tem ensaios locais concluídos e aguarda revisão independente e CI exacta antes da tarefa 7. As contas reais continuam por criar. A actualização de actividade da sessão federada existente é preservada em GET/HEAD, sem escrita funcional, de esquema, origem ou auditoria da página pessoal. Os resultados e decisões estão em docs/qa-pre-cadastro-workspace-local.md e no registo privado de execução.
