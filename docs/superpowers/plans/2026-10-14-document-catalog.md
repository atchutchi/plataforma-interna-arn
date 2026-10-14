# Catálogo documental local Implementation Plan

> For agentic workers: REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox syntax for tracking.

Goal: catálogo documental local com originais privados, versões imutáveis, gestão autorizada, audiência individual e auditoria.

Architecture: regras em Documents/Catalog no plugin, apresentação no tema. Uma ligação IdentityWpdb dedicada assegura a unidade transaccional documental e de ficheiros. Adaptador estritamente local usa contas fictícias.

Tech Stack: WordPress, PHP 8.4, PHPUnit, PHPCS, MariaDB local, Node 24 e Playwright CLI.

Spec: docs/superpowers/specs/2026-10-14-document-catalog-design.md

## Global Constraints

Ler AGENTS.md e a spec antes de trabalhar. Não aceder a produção. Não alterar Ana Teste, lab_admin, unidades ou anexos reais. Segredos e QA fora do Git. Nunca activar em development/production. Commit apenas ficheiros explícitos da tarefa, nunca .superpowers, .git ou output. Não fazer push ou merge como worker. Não criar subagentes. PSR-4 existente. Respostas e interfaces pt-PT.

## Review Focus

Sessão antiga ou conta suspensa perde leitura e escrita sem excepção administrativa.
Rascunhos, destinatários recusados, versões antigas e contagens não revelam metadados.
Falha SQL mantém CPT, agregado, versões, referências e auditorias sem escrita parcial.
Revisão concorrente e revogação serializam com a escrita documental.
Uploads, nomes e IDs não permitem caminhos arbitrários, URLs públicas ou execução.

### Task 1: Modelo imutável e autorização documental

Files: criar src/Documents/Catalog/{Metadata,Revision,Document,DocumentPolicy}.php no plugin e tests/phpunit/DocumentCatalogTest.php. Alterar Authorization/Action.php e AuthorizationPolicy.php e testes da matriz se necessário.

Interfaces: Metadata::__construct(string title,string summary,string category,string source_reference,string owner_label,string review_on,?string valid_from,?string valid_until,array recipient_ids), propriedades públicas readonly. Revision::__construct(int number,Metadata metadata,BlobDescriptor blob,int actor_id,int created_at), propriedades readonly. Document::__construct(int id,Scope management_scope,int revision,array versions,?int published_version,bool archived), propriedades readonly e validação integral. Document::create(int id,Scope scope,Revision initial):self. Document::revise(Revision next,int expected_revision):self. Document::publish(int number,int expected_revision):self. Document::archive(int expected_revision):self. Document::version(int number):?Revision.

DocumentPolicy::canManageScope(Scope scope,AuthorizationSnapshot snapshot,array session,int now):bool, canManage(Document doc,AuthorizationSnapshot snapshot,array session,int now):bool e canRead(Document doc,int number,AuthorizationSnapshot snapshot,array session,int now):bool. canManage delega em canManageScope, também usado na criação anterior ao agregado. A sessão usa LocalAccountSessionPolicy::valid(snapshot,snapshot->user_id,session,true). Gestão por Action::ManageDocuments e âmbito persistido. Leitura corrente por ReadContent próprio e destinatário explícito. Gestão também autoriza histórico/arquivo. Desconhecidos e snapshots alheios recusados.

- [ ] Escrever testes que falham para categorias, datas reais, limites e destinatários estritos, alterações imutáveis, revisão concorrente, publicação anterior conservada e arquivo terminal.
- [ ] Executar testes e observar falha por classes ausentes.
- [ ] Implementar os contratos exactos e ManageDocuments apenas em HumanResources.
- [ ] Testar grants revogados, estado Suspended, geração antiga, Employee destinatário corrente, destinatário excluído, gestor de outro âmbito e TechnicalAdministrator sem excepção. Executar PHPUnit e PHPCS focados. Usar .git/tools/php/php.exe e vendor/bin/phpunit; OPENSSL_CONF aponta .git/tools/oidc-homologation/openssl.cnf.
- [ ] Commit explícito e relatório privado com comandos e resultados. Não preparar persistência ou interface nesta tarefa.

### Task 2: Persistência atómica, autoridade e armazenamento

Files: criar Documents/Catalog/{CatalogSchema,WpdbDocumentRepository,DocumentAuthority,WpdbDocumentAuthority,CurrentDocumentActor,LocalDocumentCatalog,LocalDocumentInstaller}.php e testes de integração tests/integration/document-catalog.php. Alterar PrivateBlobStore, WpdbFileRegistry e respectivos testes. Adicionar script npm e etapa CI própria seguindo verificacao.yml.

Interfaces: consumir todas as classes da Task 1. PrivateBlobStore::duplicate(BlobDescriptor source):BlobDescriptor verifica e duplica para ID novo. WpdbFileRegistry::registerInTransaction(FileRecord record):void exige transacção activa na sua própria ligação e não finaliza a transacção. CatalogSchema(IdentityWpdb)::install():void, assertReady():void, table(string suffix):string com catálogo fechado. WpdbDocumentRepository(IdentityWpdb,DocumentAuthority)::create(Scope scope,Revision initial,string reason):Document, revise(int id,Revision next,int expected,string reason):Document, publish(int id,int version,int expected,string reason):Document, archive(int id,int expected,string reason):Document, find(int id):?Document, all():array, history(int id):array. Métodos de leitura não autorizam sozinhos e são internos, nunca endpoints. DocumentAuthority::lockAndAssert(Scope scope,array recipient_ids):int devolve actor depois de bloquear contas e verificar sessão/grants. Não aceitar actor ou provas HTTP como autoridade. Repository chama autoridade dentro da transacção, usa actor devolvido e verifica que Revision actor_id corresponde. A ordem de locks é contas por ID e depois documento. Ler âmbito imutável para preflight e confirmar sob lock antes de alterar.

CurrentDocumentActor::read():?array devolve [AuthorizationSnapshot,user_id,session] só para Ana marcada e rh.documentos.teste marcada, ambiente e flags exactos. LocalDocumentCatalog::configured():bool valida todos os gates. Métodos de runtime podem ser acrescentados conforme necessário para servir Task 3, documentar assinaturas no relatório. LocalDocumentInstaller::install():array instala esquemas e cria só rh.documentos.teste Subscriber fictícia, estado Active e grant RH auditados via serviço existente. Invocação CLI local por manage_options. Password aleatória em ficheiro ignorado configurado, nunca stdout ou Git. Idempotência verifica identidade exacta e grant sem duplicar eventos. Não cria rascunhos nem importa anexos.

- [ ] Escrever testes falhantes e executar antes da implementação. Ler padrões FileSchema e AuthorizationSchema. Validar schema InnoDB/wp_posts e índices antes de transacções. CPT privado sem REST/editor público será registado na Task 3.
- [ ] Implementar tabelas arn_documents, arn_document_versions, arn_document_audit e arn_document_schema. WPposts âncora privada genérica e escrita atómica na mesma ligação. Usar inserts preparados e falhas estáticas. Schema instalação sem GET. Nova revisão original própria e referências imutáveis. Registo file e fileaudit na mesma transacção documental.
- [ ] Implementar autoridade dentro da transacção com revalidação da geração e snapshot depois de locks. Repositório de autorização existente e nomes de tabela através dos respectivos schemas. Não alargar autoridade administrativa de negócio.
- [ ] Acrescentar WpdbAuthorizationRepository::findInTransaction(int user_id):?AuthorizationSnapshot para composição interna. Exigir transacção activa, reutilizar a hidratação e bloqueios existentes, sem START/COMMIT/ROLLBACK ou mudança de isolamento. find continua a abrir a sua própria transacção. A autoridade documental usa exclusivamente esta nova leitura na mesma ligação, depois de validar o esquema antes de START. Cobrir a recusa fora de transacção e preservação da transacção externa.
- [ ] Integração com prefixo/contas exclusivos testa criar/revisar/publicar/arquivar, rollback por falha de auditoria, CAS, grants e sessões inválidas, schema incompatível e ficheiro registado atomicamente. Development permite apenas núcleo isolado e nega runtime. Limpeza só de fixtures próprias em finally. Testar duplicate sem caminhos do browser e sem alterar blob original.
- [ ] Executar PHPUnit, PHPCS e integração local, sem instalar fixture normal ou alterar wp-config. Commit explícito. Relatório com assinaturas consumíveis pela Task 3, tabela de locks e limites. Não fazer push.

### Task 3: Gestão CMS, páginas e entrega privada

Files: criar Documents/Catalog/{DocumentCatalogPage,DocumentFileController,DocumentPortal}.php e apresentação plugin/admin ou tema/portal/document-catalog.php, CSS específico. Alterar bootstrap plugin, EditorialPreview/Portal.php, navegação necessária, README F1-09 e documentação de estado. Testes unitários/adaptador e integração pertinente.

Interfaces: consumir Task 1 e Task 2, ler relatório Task 2 para assinaturas finais. Adaptador realiza upload privado com LocalPrivateFiles::store(), constrói Metadata/Revision e usa o repository. O actor/âmbito/proprietário são do servidor, nunca campos livres de autoridade. Dados podem ser fornecidos livremente nos campos editoriais definidos, destinatários limitados ao roster fictício. Criação, revisão, publicação e arquivo via POST nonce, revisão concorrente e razão. Revisão sem ficheiro duplica original verificado. Não confiar em $_FILES type/name e só aceitar upload PHP válido.

- [ ] Ler skills Impeccable e UI UX Pro Max, executar contexto Impeccable se ainda não visto nesta conversa e ler DESIGN.md e CSS existente. Preservar tokens/fontes locais, 44 px, labels e foco.
- [ ] Adicionar testes falhantes para gates, filtros antes de contagem, destinatários, CSRF, versão antiga e endpoints alternativos.
- [ ] Implementar CMS funcional para gestor de âmbito fictício e páginas Documentos/Regulamentos com filtros e paginação preservando page_id. Administrador conserva EditorialPreview dos 22 anexos reais. Registar CPT estritamente privado sem bypass REST/native editor. Não instalar schemas em request.
- [ ] Implementar GET arn_document_file com autorização objecto/versão antes de open, 404 vazio uniforme e headers verificados. Fechar handle. arn_private_file não aceita document. Nenhuma URL estática.
- [ ] Documentar instalação/flags/QA, limites e procedimentos. F1-09 Em curso, não concluída institucionalmente. Actualizar AGENTS/estado/roteiro de forma coerente.
- [ ] Executar testes focados, PHPCS, Node, lint e build. Commit explícito e relatório. O controlador fará instalação normal autorizada, QA Playwright, revisão final, CI, push e integração. Não fazer push ou alterar configuração/contas normais como worker.
