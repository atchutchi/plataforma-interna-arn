# Decisão editorial e publicação local Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar o circuito editorial completo e a leitura de versões publicadas por audiência no WordPress local fictício.

**Architecture:** Evoluir o agregado News e derivar o estado do histórico auditado. Esquema 2 com migração CLI explícita. Autoridade revalidada sob locks, serviço local, CMS e projecções separadas de publicação e pré-visualização.

**Tech Stack:** WordPress existente, PHP 8.4, MariaDB 11.8, PHPUnit, PHPCS, JavaScript/CSS locais e Playwright. Sem novas dependências.

**Spec:** docs/superpowers/specs/2026-10-14-news-editorial-design.md

## Global Constraints

- Exigir local e cinco flags booleanas true: ARN_ALLOW_LOCAL_NEWSROOM, ARN_ALLOW_LOCAL_EDITORIAL_WORKFLOW, ARN_ALLOW_LOCAL_PRIVATE_FILES, ARN_ALLOW_FICTIONAL_LOCAL_ACCESS e ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE.
- Não migrar por GET/activação/leitura, nem manter uma rota legada de escrita que contorne o estado editorial.
- Manter IdentityWpdb dedicada sem reconnect, locks de contas ordenados antes da notícia e auditoria/avisos na mesma transacção.
- Sem Administrator como competência editorial. Versões antigas sem audiência não podem ser aprovadas.
- Os 22 originais mantêm revisão administrativa exclusiva, fora do Git e document root. Não alterar contas reais, Google, AD, DNS, produção ou enviar emails.
- Novas passwords aleatórias de 48 caracteres ficam apenas na raiz privada validada. Ensaios positivos usam prefixo arn_ed_<10 hex>_ e nomes derivados, sem reutilizar contas normais.
- Preservar DESIGN.md, fontes e marca, controlos 44 px, formulário 16 px, foco 3 px e interfaces pt-PT.
- Não enfraquecer INNODB_LOCK_WAITS/FOR UPDATE NOWAIT, limites de binário, autorização de ficheiros ou testes para obter CI verde.

## Review Focus

1. Uma revisão muda a audiência e os ficheiros, mas leitores devem continuar a receber exactamente a versão aprovada anterior até nova decisão. Testes Task 1, 3 e 5.
2. Migração interrompida por DDL deixa o módulo fechado e pode ser retomada sem inventar audiência ou reescrever o histórico original. Integração Task 2.
3. Revogação ou suspensão entre pré-leitura e decisão deve ser observada sob lock, sem publicação parcial ou aviso sem decisão. Integração Task 2.
4. A fila, os avisos e uma pré-visualização do editor não podem mostrar título, contagens ou media da revisão ainda não submetida. Testes Task 3, 4 e 5.
5. Recibo antigo, duplo clique ou duas decisões concorrentes não podem duplicar decisão/avisos ou tornar acessível uma publicação substituída. Integração Task 2 e Playwright Task 5.

### Task 1: Agregado editorial, audiências e políticas puras

Files: criar News/Audience.php, NewsState.php e EditorialTransition.php. Alterar Article.php, Content.php, DraftInput.php e NewsPolicy.php. Testes em tests/phpunit/NewsEditorialModelTest.php e NewsEditorialPolicyTest.php, ajustando fixtures antigas apenas onde a nova submissão exige audiência.

Interfaces: Audience::all/units/people, includes(int,array):bool e permissionScope():Scope. EditorialTransition::__construct(int revision,string event,int version,int actor_id,int occurred_at,string reason). Article mantém os sete argumentos e acrescenta array $transitions = array(). Propriedades públicas calculadas transitions, state, published_version, published_at, approved_by. Article::approve(int expected,int version,int actor_id,int at,string reason), reject com mesma assinatura, returnForEditing com mesma assinatura e archive com mesma assinatura. revise/submit conservam assinatura, geram a transição e preservam publicação anterior. Content/DraftInput recebem ?Audience no último argumento. NewsPolicy::canReadPublished(Article,AuthorizationSnapshot,array session,int now,array unit_ids):bool e canDecide(Article,string operation,int version,AuthorizationSnapshot,array session,int now):bool. canRead recebe array unit_ids opcional, mas o preview continua distinguido pelo runtime posterior.

create/revise/submit acrescentam string reason = '' no último argumento, preservando chamadas legadas. Novos caminhos de repositório fornecem sempre o motivo. state e published_version resultam do histórico, não de um parâmetro livre.

- [ ] Ler o trecho Modelo/Audiência da especificação e as classes existentes. Escrever testes RED com Draft->Pending->Rejected->nova versão->Pending->Published, return, archive, CAS/versão errada e auto-decisão.
- [ ] Testar publicação v2 para A enquanto v3 com audiência B está Draft/Pending/Rejected, sem acesso de B ou aos ficheiros novos. Testar apenas v3 após aprovação. Testar listas com referências PHP, arrays inválidos, grants parciais e audiência desconhecida/legada.
- [ ] Implementar as interfaces. O histórico completo valida versões, actores, tempos e estado. Conservar o construtor legado só para created/revised/submitted. Pendente legado sem audiência pode ser devolvido, nunca aprovado.
- [ ] Executar testes focados, suite PHP e PHPCS pertinentes. Commit e relatório task-1-report.md. Não alterar runtime/configuração/dados.

### Task 2: Esquema 2, migração e persistência transaccional

Files: alterar NewsSchema.php, WpdbNewsRepository.php e WpdbNewsAuthority.php, criar EditorialAuthority.php e componentes de migração/hidratação separados se necessário. Criar tests/integration/news-editorial.php e fixtures históricas v1, actualizar a job de notícias e package.json sem substituir ensaios anteriores. Ajustar fixtures F1-10 para novas submissões com audiência explícita. Antecipar apenas a passagem de DraftInput::audience a Content em LocalNewsroom::validateText/content, com teste de roundtrip, para preservar a integração existente. O restante runtime mantém-se na Task 3.

Interfaces: EditorialAuthority::authorizeDecision(Article,int version,string operation,array locked):array{actor_id,recipient_kinds} e authorizeNotice(Article,object notice,array locked):bool. NewsSchema::upgrade(int actor_id,int at):array retorna from,to,articles,versions,audit_events,notifications,before_sha,after_sha. WpdbNewsRepository acrescenta decide(int id,string operation,int version,int expected,string reason):Article. Usa approve/reject/return/archive no agregado. create/revise/submit persistem transições e audiência. history e notifications incluem versão/revisão/tipo. Reads continuam internas e sem autorização automática.

- [ ] Escrever RED real MariaDB: v1 seed com Draft/Pending e ficheiros, migração conserva colunas originais/IDs/hashes, NULL audiência e bloqueio de aprovação. Testar DDL parcial reconhecido, falha e retoma, repetição e esquema inesperado recusado.
- [ ] Implementar versão 2 conforme especificação. A migração técnica tem histórico próprio. Não actualizar esquema por leitura nem supor rollback de DDL. Falhas mantêm marcador 1 e rotas fechadas.
- [ ] Estender lockAndAssert para operações editoriais/avisos e snapshots de participantes do servidor. authorizeDecision só depois de lockedArticle/CAS. Nova interface obrigatória para decidir, sem quebrar stubs NewsAuthority de operações antigas. A integração pode injectar providers de participantes exclusivos. Task 3 liga o roster normal.
- [ ] Acrescentar ?Closure participant_provider ao construtor de WpdbNewsAuthority, inicialmente vazio por defeito e fechado para decisões. Task 3 fornece CurrentNewsActor::participantUnits a partir do factory de LocalNewsroom. Não chamar métodos que ainda não existem nem criar um fallback permissivo.
- [ ] Testar aprovação, rejeição, devolução, arquivo e decisão de versão incorrecta. Injectar falha tardia de auditoria e notificação e comparar todas as tabelas operacionais e agregados anteriores. Forçar revogação sob lock e decisão concorrente, com um único resultado confirmado.
- [ ] Avisos transaccionais com tipo/revisão, autor único na aprovação, destinatários actuais, receipt idempotente e autorização actual. Não expor stderr, SQL ou segredos em diagnósticos.
- [ ] Executar integração exclusiva, PHP/PHPCS pertinentes. Commit e task-2-report.md com contratos de providers e migração. Não activar ou migrar o prefixo normal.

### Task 3: Roster, runtime e instalador fictício auditado

Files: criar LocalNewsRoster.php e LocalEditorialInstaller.php. Alterar CurrentNewsActor.php, LocalNewsroom.php, NewsConfiguration.php e LocalNewsInstaller.php quando necessário. Ligar participant_provider no factory de WpdbNewsAuthority. Criar integração de instalação/actores e testes focados.

Interfaces: CurrentNewsActor::participantUnits():array<int,array<string>> e unitsFor(int):?array. Roster normal das cinco contas/presets da especificação, com validação de marcas. LocalNewsroom::decide(int id,string operation,int version,int expected,string reason):Article, published():?array e findPublished(int):?Article. visible/findVisible continuam só para gestão autor/editor. Pré-visualização não ganha acesso de leitor. file passa unidades verificadas a NewsPolicy. LocalEditorialInstaller::install():array e constructor ?string prefix=null,?string private_root=null. Prefixo explícito só arn_ed_<10 hex>_, com logins derivados. Normal usa nomes fechados e migra esquema 1 explicitamente.

- [ ] RED: flags falsa/string/desconhecida, leitor fora da audiência, ficheiro de versão substituída, sessão antiga e actor não marcado. Publicação v2 continua durante revisão v3 para outra audiência e troca só após aprovação.
- [ ] Implementar roster apenas servidor, presets vazios por defeito e fecho quando faltar conta esperada. Os três Employee Own podem preparar notícias próprias. Dois editores partilham fila e audiências explícitas, sem herança técnica.
- [ ] Instalar/migrar só CLI local administradora com as cinco flags. Acrescentar grant de audiência ao editor fictício existente por serviço/CAS/auditoria. Exigir novo login pela geração. Não restaurar revogados/suspensos ou alterar três contas anteriores à redacção.
- [ ] Teste positivo usa nomes/prefixo/raiz exclusivos e finally ownIDs/passwords. Confirmar idempotência exacta e falha parcial. Development CI verifica recusa de instalação antes de SQL. Não executar o instalador positivo contra o elenco normal.
- [ ] Rever avisos/markRead para distinguir author decision, submitted reviewer e publication reader. Não devolver agregados para HTTP. Commit e task-3-report.md, sem activação normal.

### Task 4: Gestão CMS, decisão e consulta editorial

Files: evoluir NewsroomPage.php, NewsFormInput.php, NewsPortal.php e assets newsroom. Extrair NewsDecisionForm/NewsroomProjection/NewsroomFilters se necessário para evitar um monólito. Tests PHPUnit de validação, projecção e rendering reais.

Interfaces: formulário aceita preset audience (resolvido no servidor), operações approve/reject/return/archive com version e token CAS HMAC opaco nas decisões editoriais. A revisão inteira é resolvida pelo servidor após verificação do token, mantendo expected_revision inteiro no serviço. Nonce arn_news_<operation>_<id>, motivo obrigatório. Projecção de gestão distingue latest author e last-submitted editor, sem nova versão privada. Não criar listas de pessoas/unidades arbitrárias recebidas do browser.

Filtros GET CMS: arn_author, arn_from, arn_to, arn_state, arn_paged. NewsFormInput pode receber uma terceira callable de resolução de presets, exclusivamente do servidor, para testar os inputs sem WordPress. A implementação por defeito resolve LocalNewsRoster. Preset vazio mantém Draft sem audiência. Preset desconhecido é erro de campo.

- [ ] Ler Impeccable, UI UX Pro Max, PRODUCT/DESIGN. Contexto Impeccable já carregado nesta conversa, não repetir init/context/palette. Testes RED para nonce, CAS, versão trocada, audiência forjada, mensagens e recuperação de POST sem perder media/ordem/CAS.
- [ ] Mostrar audiência e versão antes da decisão. Autor consulta motivo, corrige e reenvia. Editor usa filtros author/from/to/state depois de autorização e projecção. Filtrar estado da última submissão autorizada e não revelar rascunho posterior.
- [ ] Integrar avisos tipados e recibos, histórico com versão/actor/data/acção. Ler só histórico autorizado do autor ou versões submetidas do editor. UI Pending/Rejected/Published/Archived coerente, sem mutações por GET e sem editor nativo/REST.
- [ ] Manter ajuda/media/accessibilidade e separar acções. Testes de rendering incluem conta real de auditoria/stdClass, estado com publicação anterior e link de retorno. Commit e task-4-report.md. Não activar dados normais.

### Task 5: Publicações no portal, documentação e QA integrada

Files: criar NewsPublishedPortal/NewsPublishedController e projecções de listagem/detalhe do tema. Alterar EditorialPreview/Portal.php, bootstrap, portal/view.php, home e CSS só onde necessário. README/AGENTS/estado/roteiro/docs operação e QA.

Interfaces: GET arn_news=<ID> na página Notícias, modelo exclusivamente de published_version com autor/data/audiência legível, sem versões privadas ou recipient IDs. Colecção com q/category/arn_from/arn_to/arn_page estritos e contagens depois de audiência. Home apresenta últimos publicados autorizados. Admin conserva manifesto real, sem privilégio editorial.

- [ ] RED para lista/pesquisa/count não revelar Draft alheio, revisão não aprovada ou dados fora da audiência. Preview restrito a autor/editor e detalhe publicado não usar submitted_version por engano. Validar arrays, datas/categorias inválidas, URL/método e cache privada.
- [ ] Implementar listagem e leitura responsivas com media privados e autor/data da versão aprovada. Mantém versão anterior durante revisão. Audiência de uma revisão não pode alterar visibilidade da publicação corrente.
- [ ] Actualizar estado F1-11 Em curso, operação/migração explícitas, limites de DDL, antimalware, media nativa, conservação e homologação pendentes. Não declarar produção pronta.
- [ ] Executar PHP/PHPCS/Node/lint/build/integrações pertinentes e commit. Root revê, guarda baseline privado e só então activa flag/migra/instala normal. Playwright integral conforme especificação, hashes/fontes reais preservados, dois editores, A/B, revisão de audiência, erro/CSRF/CAS, arquivo/URLs directas, 320/390/768/1280/teclado/ampliação. Revisão final, CI exactSHA e integração sem deploy.
