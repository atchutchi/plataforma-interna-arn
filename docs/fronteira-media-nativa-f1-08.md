# Fronteira da media nativa, F1-08/F1-09/F1-10

## Entrega

O portal mantém os uploads nos módulos privados de notícias e documentos. A biblioteca nativa não conhece as competências, audiências e versões desses módulos. NativeMediaBoundary fecha essa alternativa pública enquanto o plugin ARN está activo, sem flag de excepção administrativa. Não migra, apaga, publica ou atribui permissões a ficheiros existentes.

O bloqueio cobre capacidades de upload, upload_dir, upload/sideload, wp_upload_bits, criação/actualização de attachments, eliminação nativa, rotas REST de media e tipo attachment, descoberta dessas rotas, biblioteca, carregamento assíncrono, acções AJAX de media e páginas de attachment. As consultas nativas excluem attachments antes da contagem e paginação. Consultas mistas e get_posts não podem omitir esse filtro. As projecções de URL, metadados, imagem e JS são fechadas. O editor de imagens nativo fica indisponível para impedir cópias e derivados públicos. O editor nativo apresenta uma indicação para usar os módulos privados.

O oEmbed também recusa attachments. Comentários desses attachments não entram nas listagens, contagens ou respostas REST singulares. O exportador nativo fica fechado porque usa SQL directo e pode incluir attachments mesmo numa exportação de páginas. A guarda HTTP corre em init antes das capacidades do menu, para conservar o 404 vazio. O core pode criar uma pasta vazia ao consultar upload_dir, mas não recebe autorização para escrever os bytes do ficheiro.

O tema usa os recursos institucionais versionados no próprio tema. A edição textual das páginas e os CPT privados continuam disponíveis sob as suas guardas. O bloqueio não depende das flags locais de notícias/documentos. Desligar um módulo não reabre o circuito nativo.

## Limites explícitos

Um plugin PHP não intercepta ficheiros estáticos já existentes em uploads, aliases IIS ou cópias servidas directamente. A homologação exige inventário de URLs e remoção/migração autorizada dessas exposições. Esta entrega não afirma que uma biblioteca antiga ficou privada. Também não impede código privilegiado de escrever directamente no disco ou de retirar hooks.

Não é uma implementação de media nativa privada. O circuito nativo é recusado. A geração de derivados privados com identidade de versão, a reconciliação de órfãos, limites por envio, antimalware/quarentena, recuperação, ACL/reparse points e ensaios representativos IIS/MySQL continuam pendentes. Uma futura integração de editor de imagens tem de usar saída privada e conservar a autorização do objecto pai antes de reabrir qualquer capacidade.

As recusas não criam registos de negócio nem copiam payloads para auditoria. A auditoria de criação/versões/downloads continua nos módulos existentes. Não existe operação de eliminação ou reconciliação nesta entrega.

## Aceitação

| Critério | Verificação requerida | Estado |
| --- | --- | --- |
| MED-01 | Administrador, autor e visitante não conseguem usar uploads nativos | Por executar |
| MED-02 | Upload, sideload, bytes e inserção de attachment não criam ficheiros ou registos | Por executar |
| MED-03 | REST, AJAX, páginas e pesquisas não expõem attachments ou contagens | Por executar |
| MED-04 | Metadados, URLs e geração de miniaturas nativas ficam fechados | Por executar |
| MED-05 | Anexo anterior fictício conserva bytes e metadados sem alteração | Por executar |
| MED-06 | Páginas textuais, formulários e entrega privada de notícias/documentos continuam funcionais | Por executar |
| MED-07 | Playwright com sessão nativa e nonce válido, controlos positivos e visitante | Por executar |
| MED-08 | Demonstração 8888, contas reais e tabelas exteriores ao laboratório preservadas | Por executar |
| MED-09 | PHP/Node/lint/build/PHPCS, integração e revisão aprovados, CI verde no SHA final | Por executar |

O ensaio tests/integration/native-media.php funciona apenas em CLI local/development com operador técnico. Cria um attachment sintético sem binário público e uma página temporária com marcador aleatório. Limpa exclusivamente os IDs do ensaio com marcador exacto. Não remove uma conta ou original existente. O controlo positivo REST confirma que o bloqueio não se deve a ausência de autenticação.

## README

O README passa de registo cronológico a painel de estado. As parcelas já integradas têm estado Concluído local. O backlog enumera apenas entregas ainda necessárias. Os requisitos e fontes foram conservados em requisitos-plataforma.md, sem replicar o inventário nominal ou endereços de contas. Esses dados documentais permanecem na versão anterior do README no histórico privado Git. Decisões pendentes e critérios de deploy não foram apagados.
