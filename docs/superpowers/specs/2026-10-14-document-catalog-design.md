# Catálogo documental local F1-09

## Intenção e limites

Implementar US-F06, US-RH03 e o núcleo de US-ARN-09 no laboratório. Manter WordPress, o plugin ARN e o tema existentes. Os 22 documentos fornecidos continuam exclusivamente em EditorialPreview, acessíveis ao administrador para revisão. Nenhum deles será importado neste módulo. Comunicação sindical é a quinta categoria autorizada pelo utilizador.

O desenvolvimento autónomo e os ensaios com contas fictícias estão autorizados. Não se presume aprovação de classificação, destinatários ou competências institucionais. O módulo exige ambiente efectivo local e ARN_ALLOW_LOCAL_DOCUMENT_CATALOG booleano true, além das flags existentes de acesso fictício, autorização e ficheiros privados. F2 e produção ficam fora desta entrega.

## Modelo

Namespace Documents\Catalog. Metadata imutável contém title (1..200 caracteres), summary (até 4000), category (circular, nota-interna, despacho, regulamento, comunicacao-sindical), source_reference (1..200), owner_label (1..200), review_on (data ISO real), valid_from e valid_until opcionais e recipient_ids (1..100 inteiros positivos distintos). Datas de validade são informativas, sem retirar acesso automaticamente. Recusar controlos, HTML e texto vazio nos campos obrigatórios. A interface guarda texto simples e apresenta escapado.

Revision contém number positivo, Metadata, BlobDescriptor, actor_id positivo e created_at Unix positivo. Document contém id positivo, management_scope explícito, revision de concorrência positiva, versões contíguas começando em 1, published_version opcional e archived. Create produz revisão de concorrência 1 e versão 1. Revise acrescenta uma nova versão imutável, conserva a publicação existente e incrementa a revisão de concorrência. Publish aceita apenas a versão mais recente e incrementa a revisão. Publicar a mesma versão outra vez é recusado. Archive é terminal e incrementa a revisão. Todas as alterações exigem expected_revision exacta. Uma versão já existente nunca muda.

Cada versão tem um original privado próprio. Uma revisão sem novo upload duplica o binário anterior para novo ID opaco após verificação no mesmo handle. Não há remoção ou substituição de binários. Uma falha SQL pode deixar um binário privado órfão. Não pode deixar registos documentais ou eventos parciais. Reconciliação de órfãos fica identificada como pendência.

## Autorização

Acrescentar Action::ManageDocuments ao pacote HumanResources. Não herdar capacidades de funcionário ou administrador técnico. DocumentPolicy exige snapshot corrente, sessão válida e as concessões do mesmo utilizador. Gestão usa o management_scope persistido. Leitura por funcionário exige versão publicada corrente, documento não arquivado e presença do actor nos recipient_ids dessa versão. A autorização ReadContent usa Scope::own e owner_id igual ao destinatário, depois da verificação explícita da audiência. Isto representa uma entrega individual, não propriedade institucional.

Um gestor autorizado pode consultar versões anteriores e rascunhos do seu âmbito, incluindo documentos arquivados. O administrador técnico não tem excepção de negócio. Para escritas, bloquear as linhas das contas envolvidas em ordem crescente e revalidar estado, geração da sessão e concessões na mesma ligação e transacção da escrita. Um browser nunca fornece funções, âmbito, actor ou provas de sessão.

No laboratório criar apenas uma nova conta fictícia rh.documentos.teste, nativa Subscriber, marcada como fixture, com concessão HumanResources no âmbito unidade-ficticia-a. As concessões e o estado são criados pelo serviço de autorização auditado. Não modificar Ana Teste, lab_admin ou unidades existentes. A audiência inicial usa apenas Ana Teste e a nova conta fictícia. A palavra-passe aleatória fica fora do Git. Não gerar concessões a partir de nomes, cargos ou emails reais.

## Persistência

Uma ligação IdentityWpdb dedicada mantém CPT arn_documento privado, agregado, versões, registo de ficheiro e ambas as auditorias na mesma transacção. O título nativo do CPT é genérico, sem metadados restritos. CPT sem REST, pesquisa pública, arquivo, rewrite ou editor nativo. Os metadados reais vivem nas tabelas documentais. Instalação de esquema só por CLI explícita, nunca em GET ou activação automática. Validar InnoDB, esquema e índices, incluindo wp_posts, antes de operar. A instalação não altera tabelas alheias.

WpdbFileRegistry ganha registo dentro de transacção já aberta na mesma ligação, sem commit ou rollback internos. O método register existente conserva a semântica de transacção própria. Qualquer falha de escrita propaga erro estático e rollback integral. Testar conflito de revisão, falha de auditoria e revogação concorrente. Não repetir operações após perda de ligação.

## Interface e entrega

Gestão documental em página própria do CMS, disponível por competência de negócio. Formulários permitem criar rascunho com upload, acrescentar revisão, publicar versão identificada e arquivar com justificação. POST com nonce, revisão e verificação dentro da transacção. GET nunca escreve. Não aceitar caminho de servidor ou ID de binário escolhido pelo cliente. Upload local PDF, PNG ou JPEG até 1 MiB com fileinfo e assinatura, sem apresentar esta admissão como análise antimalware.

As páginas Documentos e Regulamentos existentes recebem o catálogo funcional para as contas fictícias. O administrador conserva a revisão dos anexos reais. Listas, filtros, contagens e paginação só usam documentos autorizados. Documentos apresenta todas as categorias, Regulamentos restringe regulamento. Preservar page_id em permalinks simples. Mostrar proprietário, referência de origem, versão corrente, revisão e validade. Estados vazios são claros. Gestão apresenta histórico e auditoria autorizados.

Download por arn_document_file com ID opaco. Resolver vínculo de objecto e versão, revalidar sessão e autorização antes de abrir o binário. Só GET. Recusa, ausência, versão antiga não autorizada, corrupção e ID inválido devolvem o mesmo 404 vazio. Sucesso usa attachment, MIME e comprimento verificados, no-store e nosniff. Nenhuma URL estática. O endpoint anterior arn_private_file continua a rejeitar object_type document.

Conservar DESIGN.md, logótipo, Manrope e Source Sans 3 locais. Aplicar Impeccable e UI UX Pro Max. Formulários com labels, mensagens junto dos campos, foco visível, alvos de 44 px e sem transbordo a 390, 768 e 1280 px. Não inventar indicadores ou ligações a módulos ainda ausentes.

## Verificação e aceitação local

Testes unitários para validação, imutabilidade, transições, concorrência e matriz de acesso. Integração com prefixo e contas exclusivos, limpeza só dos dados criados pelo ensaio. Cobrir rollback de documento, CPT, ficheiro e auditorias. WordPress development no CI permite ensaios isolados do núcleo mas não activa o adaptador local.

Playwright percorre criar rascunho, publicar, leitura por Ana, nova revisão sem retirar a publicação anterior, troca de versão corrente, recusa da versão antiga, arquivo, CSRF, destinatário indevido e download. Ensaios usam exclusivamente binários sintéticos. Comparar os dados preexistentes para detectar alterações indevidas. Correr PHP, PHPCS, Node, lint e build. Revisão independente e CI no SHA exacto antes de merge autorizado. Sem deploy.

F1-09 fica Em curso após esta parcela. Audiências institucionais, origem oficial por categoria, antimalware, media nativa e homologação IIS/MySQL continuam pendentes.
