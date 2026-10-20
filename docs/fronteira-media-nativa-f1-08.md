# Fronteira da media nativa, F1-08/F1-09/F1-10

## Entrega

O portal mantém os uploads nos módulos privados de notícias e documentos. A biblioteca nativa não conhece as competências, audiências e versões desses módulos. NativeMediaBoundary fecha essa alternativa pública enquanto o plugin ARN está activo, sem flag de excepção administrativa. Não migra, apaga, publica ou atribui permissões a ficheiros existentes.

O bloqueio cobre capacidades de upload, upload_dir, upload/sideload, wp_upload_bits, criação/actualização de attachments, eliminação nativa, rotas REST de media e tipo attachment, descoberta dessas rotas, biblioteca, carregamento assíncrono, acções AJAX de media e páginas de attachment. As consultas nativas excluem attachments antes da contagem e paginação. Consultas mistas e get_posts não podem omitir esse filtro. As projecções de URL, metadados, imagem e JS são fechadas. O editor de imagens nativo fica indisponível para impedir cópias e derivados públicos. O editor nativo apresenta uma indicação para usar os módulos privados.

O oEmbed também recusa attachments. Comentários desses attachments não entram nas listagens, contagens ou respostas REST singulares. O exportador nativo, incluindo a criação de arquivos de dados pessoais, fica fechado porque usa SQL directo e pode incluir attachments mesmo numa exportação de páginas. A guarda HTTP corre em init antes das capacidades do menu, para conservar o 404 vazio. O core pode criar uma pasta vazia ao consultar upload_dir, mas não recebe autorização para escrever os bytes do ficheiro.

O tema usa os recursos institucionais versionados no próprio tema. A edição textual das páginas e os CPT privados continuam disponíveis sob as suas guardas. O bloqueio não depende das flags locais de notícias/documentos. Desligar um módulo não reabre o circuito nativo.

## Limites explícitos

Um plugin PHP não intercepta ficheiros estáticos já existentes em uploads, aliases IIS ou cópias servidas directamente. A homologação exige inventário de URLs e remoção/migração autorizada dessas exposições. Esta entrega não afirma que uma biblioteca antiga ficou privada. Também não impede código privilegiado de escrever directamente no disco ou de retirar hooks.

Não é uma implementação de media nativa privada. O circuito nativo é recusado. A geração de derivados privados com identidade de versão, a reconciliação de órfãos, limites por envio, antimalware/quarentena, recuperação, ACL/reparse points e ensaios representativos IIS/MySQL continuam pendentes. Uma futura integração de editor de imagens tem de usar saída privada e conservar a autorização do objecto pai antes de reabrir qualquer capacidade.

As recusas não criam registos de negócio nem copiam payloads para auditoria. A auditoria de criação/versões/downloads continua nos módulos existentes. Não existe operação de eliminação ou reconciliação nesta entrega.

## Aceitação

| Critério | Verificação requerida | Estado |
| --- | --- | --- |
| MED-01 | Administrador, autor e visitante não conseguem usar uploads nativos | Aprovado local |
| MED-02 | Upload, sideload, bytes e inserção de attachment não criam ficheiros ou registos | Aprovado local |
| MED-03 | REST, AJAX, páginas e pesquisas não expõem attachments ou contagens | Aprovado local |
| MED-04 | Metadados, URLs e geração de miniaturas nativas ficam fechados | Aprovado local |
| MED-05 | Anexo anterior fictício conserva bytes e metadados sem alteração | Aprovado local |
| MED-06 | Páginas textuais, formulários e entrega privada de notícias/documentos continuam funcionais | Aprovado local |
| MED-07 | Playwright com sessão nativa e nonce válido, controlos positivos e visitante | Aprovado local |
| MED-08 | Demonstração 8888, contas reais e tabelas exteriores ao laboratório preservadas | Aprovado local |
| MED-09 | PHP/Node/lint/build/PHPCS, integração e revisão aprovados, CI verde no SHA final | Local aprovado. CI final e integração rastreáveis na PR #60 |

O ensaio tests/integration/native-media.php funciona apenas em CLI local/development com operador técnico. Cria um attachment sintético sem binário público e uma página temporária com marcador aleatório. Limpa exclusivamente os IDs do ensaio com marcador exacto. Não remove uma conta ou original existente. O controlo positivo REST confirma que o bloqueio não se deve a ausência de autenticação.

## Evidência do fecho local

PHP 8.4.26 passou 981 testes e 5028 asserções. As quatro omissões Windows de symlink já existiam e mantêm validação na CI Linux. Node passou 55 testes, lint e build. PHPCS passou 504 ficheiros, seguido da verificação dos três ficheiros alterados no fecho. O ensaio WordPress/MariaDB passou 48 verificações com o bootstrap efectivo do laboratório.

Playwright passou 37 verificações sobre HTTP em localhost:8892: 22 de fronteira, dez de audiências e entregas privadas, duas de upload privado e três de exportação de dados pessoais/reflow. A sessão técnica usou nonce REST real, com users/me como controlo positivo. Media, oEmbed, biblioteca, exportação, AJAX e páginas de attachment foram recusados. A pesquisa geral não encontrou a fixture. O core recusa subtype=attachment com 400, sem revelar a fixture. Contagens/comentários mistos e singulares têm também cobertura integrada no WordPress.

O formulário privado criou um único rascunho fictício com PNG e PDF. A auditoria registou a criação, versão e referências. A nova notícia ficou em rascunho, sem publicação. Os media publicados anteriores conservaram os hashes e só a audiência autorizada conseguiu recebê-los. O documento publicado continuou disponível à conta competente. Visitantes e outra audiência foram recusados.

A comparação conservou integralmente as linhas das 133 tabelas exteriores ao prefixo de laboratório e as dez contas fictícias em users. Todas as linhas anteriores das 21 tabelas de negócio e os 18 ficheiros anteriores foram conservados. Acrescentaram-se apenas dois binários privados e as linhas esperadas do rascunho: news 1, versions 1, assets 2, news_audit 1, private_files 2 e private_file_audit 2. Não houve novo binário em uploads. A fixture nativa temporária de HTTP foi removida pelo seu ID e marcador exactos. Sessões fictícias mudaram pelos logins, sem alegar igualdade de usermeta. Os 333 ficheiros WordPress aplicados coincidem com o código da branch. A demonstração 8888, contas reais e produção não foram alteradas.

Foram inspeccionadas as capturas do painel técnico e do formulário da notícia. O reflow do painel passou a 390 px. Não se repetiram zoom nativo, leitor de ecrã ou segundo PC. A consola regista pedidos bloqueados de Gravatar e do script injectado pelo antivírus, já presentes no laboratório. O bloqueio externo do browser foi conservado.

A revisão independente identificou oEmbed, consultas de tipo implícito, exportação e comentários. As duas primeiras falharam nos ensaios adicionados antes da correcção e passaram depois. Os restantes receberam cobertura integrada e HTTP pertinente. A última revisão não deixou achados funcionais. O primeiro ensaio HTTP detectou 403 do core antes de admin_init. Antecipar para init permitiu o contrato de 404 vazio. Foram corrigidas duas expectativas do harness: o core devolve false para URL omitida e 400 para o subtipo de pesquisa não suportado. O comparador privado foi alinhado com os nomes reais news/assets e conservou a exigência de igualdade das linhas anteriores. Não se alteraram dados para ocultar diferenças.

A CI do primeiro commit 6f2dd9a passou vinte controlos. O commit final tem de passar a sua própria CI antes de integrar. O resultado final, incluindo SHA e checks, fica na [PR #60](https://github.com/atchutchi/plataforma-interna-arn/pull/60). A verificação de main é uma condição adicional do fecho, sem deploy automático.

## README

O README passa de registo cronológico a painel de estado. As parcelas já integradas têm estado Concluído local. O backlog enumera apenas entregas ainda necessárias. Os requisitos e fontes foram conservados em requisitos-plataforma.md, sem replicar o inventário nominal ou endereços de contas. Esses dados documentais permanecem na versão anterior do README no histórico privado Git. Decisões pendentes e critérios de deploy não foram apagados.
