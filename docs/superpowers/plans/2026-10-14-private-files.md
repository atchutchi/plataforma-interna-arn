# F1-08: implementação de ficheiros privados

> For agentic workers: REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Execute task-by-task with independent review.

Goal: construir armazenamento privado imutável, registo auditado e entrega autorizada com ensaio local sintético.

Architecture: núcleo Documents\PrivateFiles independente do tema. IdentityWpdb guarda referências e auditoria. O adaptador WordPress resolve a sessão e os objectos no servidor.

Tech Stack: PHP 8.4, WordPress, MariaDB local, PHPUnit, PHPCS, Playwright.

Spec: docs/superpowers/specs/2026-10-14-private-files-design.md

## Global Constraints

Só laboratório, sem produção, dados ou concessões reais. Sem alterações aos 22 anexos do portal administrativo. Sem dependências novas. PSR-4 e plugin existente. Interface pt-PT. Instalação explícita por CLI. Nenhuma escrita de schema ou concessões em GET. Sem política institucional de admissão implícita.

## Review Focus

Raiz pública alternativa ou caminhos com diferença de maiúsculas no Windows devem ser recusados.
Uma corrupção ou substituição posterior ao registo deve impedir a entrega, mesmo com autorização válida.
Duplicação e falha de auditoria não podem deixar metadados utilizáveis sem evento.
Revogação de sessão ou alteração de estado deve produzir recusa no pedido seguinte.
Variantes e versões antigas não podem herdar uma autorização calculada só para a primeira listagem.

## Task 1: núcleo imutável e registo auditado

Files: criar src/Documents/PrivateFiles/{BlobDescriptor,PrivateBlobStore,FileRecord,FileSchema,WpdbFileRegistry,PrivateFileDelivery}.php no plugin, tests/phpunit/PrivateFiles*.php e tests/integration/private-files.php. Modificar package.json e .github/workflows/verificacao.yml apenas para acrescentar test:private-files:integration.

Interfaces: usar exactamente as assinaturas da secção Fronteiras e interfaces da spec. O resolvedor find_record recebe string id e devolve ?FileRecord. authorize recebe FileRecord e devolve bool estrito true. PrivateFileDelivery::open devolve ['record'=>FileRecord,'handle'=>resource] ou null. FileSchema::table aceita apenas files/audit/schema. WpdbFileRegistry valida schema antes de cada operação e recusa transacção externa.

- [ ] Escrever primeiro testes negativos e verificar falha por classes ausentes. Incluir raiz dentro/fora/sobreposta, links, fontes que crescem, limite real, prefixo SQL malformado, rollback de auditoria, duplicados e adulteração.
- [ ] Implementar o núcleo e o registo, com validação estrita, excepções constantes e sem caminhos em erros. Preservar a ordenação de autorização antes de abertura.
- [ ] Executar PHPUnit dirigido, PHPCS e o ensaio real em prefixo/pasta aleatórios. O ensaio limpa só os seus recursos e não altera as tabelas existentes. Adicionar execução equivalente na CI.
- [ ] Rever o diff e criar commit de código/testes. Relatório identifica comandos, resultados e limites.

## Task 2: entrega WordPress e ferramenta local

Files: criar src/Documents/PrivateFiles/Local{FileCatalog,PrivateFiles,PrivateFilesPage,FileInstaller}.php, tests/phpunit/LocalPrivateFilesTest.php e tests/integration/local-private-files.php. Modificar arn-intranet-core.php para hooks e package.json/CI para teste integrado.

Interfaces: consumir núcleo da tarefa 1. Configuração ARN_LOCAL_PRIVATE_FILES_DIR aponta para pasta já existente, ARN_ALLOW_LOCAL_PRIVATE_FILES booleano true e ambiente local. LocalFileInstaller::install(int owner_id): array prepara fixtures fechadas, schema e registo, só com WP_CLI/local/manage_options, sem editar utilizadores. LocalPrivateFilesPage fica em Ferramentas, apenas Ana válida. LocalPrivateFiles resolve id de GET estrito e liga CurrentLocalAccount e AuthorizationPolicy ao catálogo do servidor. O instalador devolve IDs de ficheiros por objecto e variante para QA, nunca segredos.

- [ ] Escrever testes que recusem flags textuais, ambiente não local, administrador sem concessões, IDs malformados, scope de outra unidade, versão antiga e sessão inválida.
- [ ] Implementar adaptação sem alterar o tema ou a pré-visualização real. A fonte de metadados do catálogo é o registo, com object_type fechado local_document e object_id 1/2/3. Versão 2 corrente, versão 1 recusada. Scope próprio para objecto1, unidades A/B para objectos2/3. Autor é owner_id fixado no instalador e verificado no servidor, não no query string. Não aceitar outros object_types.
- [ ] Executar integração HTTP e Playwright com sessões fictícias e downloads reais sintéticos, incluindo variantes. Confirmar ausência de alteração aos snapshots existentes e limpar só os recursos de QA.
- [ ] Actualizar README, AGENTS e docs/ficheiros-privados-locais-f1-08.md com comandos, evidências e pendências. Correr suites completas, lint e build. Criar commit e obter revisão final independente antes do push/PR.

## Continuação do objectivo

Após integração aprovada desta fase, continuar F1-08/09 pelos requisitos ainda não cumpridos. Esta entrega não conclui o portal nem autoriza produção. Aprovações de classificação, audiências, RH/DCSI e testes de infraestrutura continuam por obter quando se tornarem dependências concretas.
