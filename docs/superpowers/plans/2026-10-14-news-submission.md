# Redacção e submissão local de notícias Implementation Plan

> For agentic workers: REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox syntax for tracking.

Goal: criar, editar, pré-visualizar e submeter notícias fictícias duráveis, com galeria, anexos privados e notificação interna confirmada.

Architecture: modelo puro News, repositório transaccional em IdentityWpdb, adaptador local fechado e CMS/tema WordPress. FileRecord referencia assets imutáveis, sem alargar variantes ou reutilizar o catálogo documental como notícia.

Tech Stack: PHP 8.4, WordPress, PHPUnit/PHPCS, MariaDB local, Node24 e Playwright CLI. Sem dependências novas.

Spec: docs/superpowers/specs/2026-10-14-news-submission-design.md

## Global Constraints

Ler AGENTS.md e a spec. Não aceder a produção ou Google. Não alterar Ana Teste, RH Documentos Teste, lab_admin, documentos, unidades, concessões ou anexos reais preexistentes. Só contas/ficheiros/prefixos exclusivos em testes. Não instalar fixtures normais ou mudar configuração como worker. Não criar subagentes, não fazer push ou merge. Segredos, relatórios e QA fora do Git. Staging de ficheiros explícitos, nunca .git/.superpowers/output. Não executar instaladores em GET. Interfaces pt-PT, DESIGN.md e fontes locais. F1-11 conserva aprovação/rejeição/publicação.

## Review Focus

Sessão antiga, suspensão ou revogação não permite editar, pré-visualizar, descarregar ou ler notificações.
Retenção/reordenação com IDs alheios, duplicados ou de versão antiga não importa nem expõe assets fora da edição corrente.
Falha da auditoria ou das notificações reverte todas as oito tabelas, sem submissão ou avisos parciais.
Pendente bloqueia todas as alterações, inclusive galeria/anexos, e CAS impede sobrescrever outra revisão.
Erro de formulário, UTF-8 inválido, controlos ou upload adulterado não provoca fatal nem apaga silenciosamente o texto ou expõe dados no URL.

### Task 1: Modelo imutável e política editorial

Files: criar News/{Asset,Content,Version,Article,NewsPolicy}.php e tests/phpunit/NewsModelTest.php.

Interfaces readonly: Asset::__construct(string kind,BlobDescriptor blob,string description). Content::__construct(string title,string body,string category,?Asset featured,array gallery,array attachments). Version::__construct(int number,Content content,int actor_id,int created_at). Article::__construct(int id,int author_id,Scope review_scope,int revision,array versions,?int submitted_version,?int submitted_at). Article::create(int id,int author_id,Scope review_scope,Version initial):self; revise(Version next,int expected):self; submit(int expected,int at):self; version(int number):?Version; pending():bool. Versions lista zero-based ascendente. review_scope é Queues com uma fila. Todos os Version.actor_id igualam author_id. Revision equivale ao número de versões, acrescido de um depois de enviar. Cada Asset blobID é único em todo o artigo.

NewsPolicy métodos estáticos canCreate(AuthorizationSnapshot,array session,int now):bool, canEdit(Article,AuthorizationSnapshot,array,int):bool, canSubmit(Article,AuthorizationSnapshot,array,int):bool, canRead(Article,int version,AuthorizationSnapshot,array,int):bool e canReviewScope(Scope,AuthorizationSnapshot,array,int):bool. Reutilizar AuthorizationPolicy/Request/Action e LocalAccountSessionPolicy, sem modificar matriz para criar um bypass. canRead autor usa competência própria e pode histórico, editor apenas Pendente/versão submetida. canSubmit verifica conteúdo completo e Action::SubmitNews. Fornecer um método puro de autorização própria para a autoridade Task2 verificar CreateNewsDraft ou SubmitNews antes de obter o agregado sob lock, sem ignorar a distinção das acções.

- [ ] Escrever testes falhantes e observá-los para os limites, categorias, UTF-8/controlos, parágrafos CRLF, texto alternativo/designação, tipos MIME, listas ordenadas e referências externas PHP.
- [ ] Implementar sem WordPress. Galeria até8, anexos até5, título1..200, corpo0..20000, imagem alt1..300, anexo label1..160. Rascunho aceita corpo vazio/sem destaque, envio recusa ambos. Imagens PNG/JPEG. Attachment PDF/PNG/JPEG. Copiar listas sem referências externas. Metadados não podem ser alterados depois da construção.
- [ ] Testar revisão independente, CAS antigo, duplicação de IDs, bloqueio Pendente, duplicação de submissão e invariantes da hidratação. Testar autor/outro funcionário/editor na fila errada/admin técnico, grants expirados/revogados, estado Suspended e geração antiga.
- [ ] PHPUnit/PHPCS focados com PHP portátil. OPENSSL_CONF aponta .git/tools/oidc-homologation/openssl.cnf na suite completa. Commit explícito e relatório privado. Não persistir ou ligar hooks.

### Task 2: SQL atómico, autoridade e notificações

Files: criar News/{NewsSchema,NewsAuthority,WpdbNewsAuthority,WpdbNewsRepository,CurrentNewsActor,NewsConfiguration}.php, tests/integration/news-submission.php, testes unitários pertinentes, script npm e job CI separado como document-catalog-integration.

Contratos: NewsConfiguration::configured():bool e enabled(string environment,mixed newsroom,mixed files,mixed fictional,mixed authorization):bool. CurrentNewsActor segue as dependências Closure injectáveis e read/sessionFor de CurrentDocumentActor, mas só os dois logins/marks da spec. reviewerIds():array resolve editor.noticias.teste no servidor, injectável para fixtures exclusivas de integração. NewsAuthority::lockAndAssert(int author_id,Scope review_scope,string operation):array devolve apenas actor_id e reviewer_ids, sem receber provas HTTP. Operações create|revise|submit|read_notification. Bloquear actor+autor+editores necessários por ID crescente, revalidar snapshots, sessão e acção. Confirmar ReviewNews/estado dos destinatários da submissão sob locks. Sem editor válido recusar. Usar findInTransaction, sem transacções aninhadas.

WpdbNewsRepository(IdentityWpdb,NewsAuthority)::create(int author_id,Scope review_scope,Version initial,string reason):Article, revise(int id,Version next,int expected,string reason):Article, submit(int id,int expected,string reason):Article, find(int id):?Article, all():array, history(int id):array, asset(string blob_id):?array, notificationRows(int recipient_id):array, markRead(int notification_id,int at):void. Leituras internas não autorizam e não são endpoints. asset devolve asset_id,article_id,version,position,Asset. notificationRows devolve linhas do próprio envelope, sem payload editorial desnecessário. markRead revalida actor/destinatário e artigo Pendente na transacção, actualiza o primeiro read_at de forma idempotente, não a revisão editorial.

- [ ] Redigir testes falhantes antes do código. Instalação explícita e validação fechada dos seis esquemas, de FileSchema, AuthorizationSchema e wp_posts, sem alterar tabelas preexistentes. NewsSchema::table aceita news|versions|assets|audit|notifications|schema|posts. Texto utf8mb4_bin. Documentar colunas/índices finais no relatório consumido pelas tarefas seguintes.
- [ ] Persistir versões e assets imutáveis. Cada linha asset tem ID próprio e vínculo artigo/versão/kind/posição. FileRecord news_asset/assetID/1/original e ambas auditorias na ligação da transacção. Conteúdo e assets hidratam o agregado integral e recusam corrupção. Âncora arn_noticia private com título genérico, sem guardar texto editorial em posts.
- [ ] Implementar locks/CAS e comparação de autor/fila imutáveis sob lock. Transacção da submissão inclui auditoria e notificações únicas artigo/versão/destinatário. Perda de ligação recusa retry. Auditoria tem actor, operação, instante, revisão e motivo breve até500bytes. Expor apenas erros estáticos.
- [ ] Integração com prefixo/contas exclusivos verifica rascunho, revisão com múltiplos assets, submissão, bloqueio Pendente, CAS, revogação/sessão inválida, destinatário editorial inválido, recibos idempotentes e schema incompatível. Forçar falha de auditoria/notificação e comparar contagens das oito tabelas antes/depois. Duas ligações confirmam contenção de contas antes de notícia. Limpar só dados próprios em finally. Nenhuma instalação normal.
- [ ] Executar testes/PHPCS e integração local. Job CI isolado executa npm run test:news:integration. Commit explícito e relatório com assinaturas, schema, locks e limites, sem push.

### Task 3: Runtime local, retenção de assets e instalador

Files: criar News/{MediaInput,DraftInput,LocalNewsroom,LocalNewsInstaller,NewsFileController}.php e testes/integration pertinentes. Não alterar bootstrap até Task4.

MediaInput::__construct(?string existing_id,?string source,string description), exactamente um de existing_id ou source. DraftInput::__construct(string title,string body,string category,?MediaInput featured,array gallery,array attachments), com listas estritas copiadas. Inputs editoriais não são prova de acesso. LocalNewsroom(CurrentNewsActor?,Closure repository_factory?,Closure store_factory?,Closure gate?) disponibiliza create(DraftInput,string reason):Article, revise(int id,DraftInput,int expected,string reason):Article, submit(int id,int expected,string reason):Article, visible():?array, findVisible(int id):?Article, historyVisible(int id):?array, notifications():?array, markRead(int id):void e file(string blob_id):?array. configured delega em NewsConfiguration. Fábrica usa a mesma ligação dedicada para repository/registry. Leituras filtram antes de qualquer contagem/projecção. notifications reautoriza artigo e versão antes de devolver envelope.

- [ ] Testes falhantes para gates exactos, identidade marcada, retenção alheia/antiga/duplicada, categoria, sessão e imagem corrompida ou com dimensões excedidas. Criar não aceita existing_id. Rever só duplica assets da última versão da própria notícia e mesmo kind depois da autorização, podendo alterar a ordem/descrição. Duplicar todos os conservados para novos IDs. Remoção omite o item na versão nova, sem alterar histórico.
- [ ] Implementar admissão com LocalPrivateFiles::store, 1MiB e limite de dimensões da spec, confirmado no binário copiado. Sem dependências de imagem novas, sem confiar em MIME/nome do browser. Nunca tratar source como caminho HTTP. Runtime é chamado pelo adaptador com uploads PHP já validados.
- [ ] LocalNewsInstaller::install():array só CLI local manage_options +flags e raiz privada. Criar exclusivamente autor.noticias.teste/autor.noticias.teste@example.test e editor.noticias.teste/editor.noticias.teste@example.test, Subscriber marcados, Active e concessões da spec pelo serviço auditado. Novas passwords48caracteres em nomes fixos da spec, criação exclusiva fora do Git e das raízes públicas. Repetição verifica identidade/roles/grants exactos sem eventos novos. Recusar suspensos/revogação sem restaurar. Não devolver ou imprimir credenciais.
- [ ] NewsFileController regista template_redirect anterior aos endpoints existentes. GET arn_news_media confirma tuple do registry, asset, artigo, versão e política antes do handle. Inline só featured/gallery PNG/JPEG, anexo attachment. MIME/bytes verificados, no-store/private/nosniff/CSP, handle fechado. Todo o negativo404vazio, sem aceder aos dados antes dos gates. Endpoints documental e nativo não ganham acesso a news_asset.
- [ ] Testar runtime com fornecedores injectados e instalador com prefixo/contas exclusivos e limpeza finally. Testar repetição, recusa de concessão revogada e raiz pública. Executar PHPUnit/PHPCS/integração focados. Commit e relatório, sem normal install/config/push.

### Task 4: CMS, preview, inbox e evidência

Files: criar News/{NewsroomPage,NewsPreviewController,NewsPortal}.php e componentes de formulário/apresentação separados se preciso, assets/css/newsroom.css e assets/js/newsroom.js, tema portal/news-preview.php. Alterar bootstrap plugin, EditorialPreview/Portal.php, tema portal/view.php, README, AGENTS, docs/estado-funcional-f1.md e roteiro. Testes pertinentes.

NewsroomPage regista CPT arn_noticia estritamente privado e menu Notícias locais. Não usar editor nativo ou REST. CMS lista apenas own ou pendentes autorizados, filtra/conta/pagina depois de autorização. Autor cria/revê Rascunho, conserva/remove/reordena assets com controlos explícitos de posição e alternativas/designações, carrega ficheiros e envia por acção própria com CAS. Editor vê pendências e notificações, abre preview e marca recibo com nonce. Não publicar/aprovar/rejeitar. História/auditoria apresenta objectos reais do repositório de forma tipada e escapada.

- [ ] Ler skills Impeccable e UI UX Pro Max e DESIGN. Não repetir contexto Impeccable se já lido nesta conversa. Usar biblioteca/guidance instalada sem instalar substituto. Reutilizar tokens/fonts, controlos44px/font16 com selectores que superem WP apenas neste módulo e foco3px. Sem faixas laterais decorativas.
- [ ] Testes falhantes cobrem CSRF, forged actor/scope, rejeição de uploads não-PHP/tipos/limites, arrays e UTF-8/controlos. Justificação e campos inválidos re-renderizam POST escapado, erros próximos, conservando selecção e CAS antigo. Nunca payload URL/transient. Upload requer nova selecção após erro. Label acessível separado de ajuda com aria-describedby. Sucesso PRG.
- [ ] Implementar formas funcionais de criação/revisão, destaque, galeria até8 e anexos até5 com adição/remoção/ordem tanto no cliente como servidor. IDs conservados são revalidados no runtime, não aceites como autoridade. Pendente bloqueia todas as escritas. Não depender de drag-and-drop. Disponibilizar preview antes do envio.
- [ ] NewsPreviewController em template_redirect protege arn_news_preview no GET com 404 indistinto antes do tema. NewsPortal reduz apenas a versão autorizada e URLs privadas. EditorialPreview delega vista news com arn_news_preview para este modelo, sem substituir a colecção dos22originais para administrador. Portal/view encaminha news_preview. Preservar cabeçalho/menu/logo/fontes locais. Preview marcado Rascunho/Pendente e nunca como publicado, corpo em parágrafos, imagemalt/galeria ordenada e anexos autorizados. Cache privada em todas estas respostas.
- [ ] Testar uma linha real de auditoria, envelopes/links de notificação, contagens privadas e nativeCPT/REST sem bypass. Documentar instalador, flags, operação, casos QA e limites. Actualizar estado F1-10 Em curso nesta parcela, F1-11 Por iniciar.
- [ ] Executar PHPUnit/PHPCS/Node/lint/build e integração pertinente. Commit/relatório. Controlador instala fixtures normais, executa Playwright completo com PNG/JPEG/PDF sintéticos, regressões de baseline, revisão final, push/CI/merge sem deploy.
