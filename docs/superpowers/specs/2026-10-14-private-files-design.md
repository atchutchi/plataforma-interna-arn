# F1-08: núcleo de ficheiros privados

O objectivo é permitir que documentos e notícias futuros usem ficheiros imutáveis sem URLs públicas. Esta entrega mantém o laboratório e as contas fictícias. Os 22 anexos reais continuam exclusivamente no portal administrativo de revisão. Não existe autorização para publicação ou entrada em produção.

## Arquitectura

O plugin guarda binários num directório configurado fora de todos os document roots conhecidos. Um identificador aleatório de 32 caracteres hexadecimais substitui caminhos e nomes fornecidos pelo utilizador. O armazenamento valida os caminhos em cada operação, recusa links e não substitui ficheiros existentes. Cada leitura abre um handle, confirma tamanho, assinatura, MIME e SHA-256 nesse handle e entrega o mesmo handle. A ACL da pasta tem de impedir alterações por processos não autorizados. A homologação de reparse points e ACL em IIS continua pendente.

Os metadados ligam um binário a object_type, object_id, version e variant. São imutáveis. O registo e o evento file_registered usam a mesma transacção numa ligação IdentityWpdb dedicada. O índice único object_type/object_id/version/variant impede substituir o anexo de uma versão aprovada. Uma falha SQL pode deixar um binário órfão privado, nunca uma referência publicada sem auditoria. Não há eliminação automática nesta entrega. O schema é instalado explicitamente por CLI e verificado antes de uso, nunca por GET.

O núcleo não decide quem pode publicar. O serviço de entrega recebe um resolvedor de autorização do objecto no servidor, que é chamado a cada pedido antes de abrir o binário. Estado da conta, geração da sessão, âmbito, audiência, estado editorial e versão pertencem a esse resolvedor. Um callback que recusa ou falha impede a leitura. Originais, miniaturas, pré-visualizações e versões antigas usam exactamente esse circuito.

## Fronteiras e interfaces

Namespace Documents\PrivateFiles. BlobDescriptor readonly: id, sha256, size, mime. PrivateBlobStore::__construct(string root, array public_roots, array allowed_mimes, int max_bytes), importFile(string source): BlobDescriptor, open(BlobDescriptor): resource. A raiz já deve existir e não pode coincidir, conter ou estar dentro de uma raiz pública. A política de admissão é explícita e fixada no servidor. O MIME nunca é inferido do nome do ficheiro. Nesta fase o laboratório usa apenas PDF/JPEG/PNG sintéticos. Isto não aprova a política institucional de tipos nem substitui análise antimalware.

FileRecord readonly: blob, object_type, object_id, version, variant, actor_id, created_at. Os identificadores são estritos, object_id/version/actor_id positivos, created_at UTC positivo e variant em original/thumbnail/preview. FileSchema::__construct(IdentityWpdb), install(): void, assertReady(): void, table(string): string. WpdbFileRegistry::__construct(IdentityWpdb), register(FileRecord): void, find(string): ?FileRecord. Sem update, delete, listagens livres ou payloads pessoais nos eventos. SQL parametrizado, engine transaccional e transacções aninhadas recusadas.

PrivateFileDelivery::__construct(PrivateBlobStore, Closure find_record, Closure authorize), open(mixed id): ?array. Sucesso devolve record e handle verificado. Recusa, ausência, corrupção e excepção devolvem null sem caminho nem causa para o browser. O resolvedor é código do servidor, nunca inputs de query.

O registo disponibiliza também findForObject(string object_type, int object_id, int version, string variant): ?FileRecord. É uma consulta singular parametrizada para o objecto conhecido pelo servidor. Evita duplicar um manifesto com IDs e não cria um endpoint de listagem.

## Adaptador local

Uma ferramenta Ferramentas/Ficheiros privados fictícios fica disponível apenas com ambiente local e ARN_ALLOW_LOCAL_PRIVATE_FILES booleano true. O download usa apenas arn_private_file=id, sem caminhos, MIME, versão, audiência ou âmbito fornecidos pelo browser. O handler corre antes do redireccionamento do portal e devolve 404 vazio para ausência e recusa. Sucesso usa attachment, application/pdf ou image/png/jpeg verificado, nosniff, no-store e Content-Length correcto. HEAD e métodos não GET não entregam bytes. Sem ranges ou cache nesta entrega.

A conta Ana Teste continua com as permissões existentes. CurrentLocalAccount valida snapshot e sessão. Um catálogo fechado do servidor resolve três objectos sintéticos próprios/unidade A/unidade B, com três variantes e uma versão antiga por objecto. O download aplica AuthorizationPolicy a ReadContent, com Scope e audiência calculados no servidor. Não há excepção para lab_admin. O administrador instala e prepara as fixtures por CLI. Não cria ou altera concessões, contas ou estados durante pedidos HTTP. Os ficheiros sintéticos e respectivo registo são separados dos anexos reais e o directório fica fora de ABSPATH e do Git. Só os objectos permitidos entram na lista ou nas contagens.

O instalador local é uma instância: LocalFileInstaller::__construct(?string prefix = null, ?string root = null), install(int owner_id): array. As dependências alternativas só são chamadas por CLI para ensaios exclusivos. A composição HTTP não as aceita do browser. O instalador positivo é ensaiado no laboratório local. O wp-env de CI conserva development e verifica a recusa do gate real nesse contexto, exercendo o núcleo e a entrega com dependências internas exclusivas.

## Evidência de aceitação

Testes puros cobrem path traversal, raízes públicas e links, MIME/assinatura/tamanho/hash, ficheiros alterados, identidade inválida, falha do resolvedor e nenhuma abertura antes da autorização. A integração WordPress usa tabelas e pasta exclusivas e confirma registo/auditoria atómicos, duplicação recusada, rollback e schema incompatível recusado. O ensaio HTTP/Playwright testa visitante, administrador sem concessão de negócio, conta fictícia, objecto permitido, IDOR por objecto/variante/versão, ausência indistinta e navegação. Não altera os registos existentes de autorização ou unidades. A CI executa os ensaios compatíveis com wp-env. F1-08 continua Em curso enquanto faltarem política de admissão institucional, media WordPress, ligação aos módulos documentais e homologação IIS/MySQL.
