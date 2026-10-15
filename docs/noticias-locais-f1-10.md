# Redacção e submissão local de notícias F1-10

14 de outubro de 2026. Esta parcela implementa criação e revisão de Rascunho, destaque, galeria, anexos, pré-visualização, envio para aprovação, fila Pendente, aviso interno e recibo de leitura. Aplica-se apenas a contas fictícias instaladas localmente. F1-10 fica Em curso. Aprovação, rejeição, reenvio e publicação pertencem a F1-11.

Actualização de 15 de outubro: a evolução [F1-11](publicacao-editorial-local-f1-11.md) implementa o circuito local de decisão/publicação e substitui a instalação antiga, as quatro flags e as duas contas descritas historicamente abaixo. O runtime corrente exige cinco flags, esquema 2 e o elenco completo de cinco fixtures. LocalNewsInstaller recusa a entrada antiga. A revisão independente antecede a activação normal pelo controlador, seguida da aceitação integrada. A homologação institucional continua pendente.

## Instalação e acesso

O código regista o CPT `arn_noticia` como âncora privada sem editor nativo, REST, pesquisa pública ou capacidades WordPress de edição. O acesso exige ambiente efectivo `local` e as quatro constantes booleanas verdadeiras `ARN_ALLOW_LOCAL_NEWSROOM`, `ARN_ALLOW_LOCAL_PRIVATE_FILES`, `ARN_ALLOW_FICTIONAL_LOCAL_ACCESS` e `ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE`. A instalação das tabelas e das duas contas fictícias é explícita por CLI administradora local com `(new \Arn\Intranet\News\LocalNewsInstaller())->install()`. A operação não imprime passwords. Estas ficam exclusivamente na raiz privada validada. A instalação não acontece em GET, na activação do plugin nem no primeiro acesso ao menu.

Antes de activar a interface HTTP local, verificar `post_max_size >= 20M`, `upload_max_filesize >= 1M` e `max_file_uploads >= 14` no PHP que serve o WordPress. Cada ficheiro continua limitado a 1 MiB. Se o PHP rejeitar o corpo POST completo, os campos editoriais podem não chegar à aplicação e têm de ser preenchidos novamente. Não alterar configuração de produção para este ensaio.

O menu `Notícias locais` só aparece para o autor `autor.noticias.teste` com CreateNewsDraft ou o editor `editor.noticias.teste` com ReviewNews na fila `editorial-ficticia`, mediante sessão e geração válidas. O administrador técnico não ganha competência editorial. Nenhuma das três contas anteriores, concessões antigas ou 22 anexos reais é alterada pelo código do CMS.

## Operação

O autor usa `Notícias locais` no CMS. Pode criar um rascunho incompleto, guardar uma nova versão com justificação e revisão concorrente, conservar um asset da versão actual, retirá-lo ou mudar a sua posição com botões Subir e Descer. Pode adicionar um destaque, até oito imagens de galeria e até cinco anexos. Cada imagem precisa de texto alternativo e cada anexo de designação. Os ficheiros novos são seleccionados de novo após qualquer erro. O servidor confirma que os IDs conservados pertencem à versão actual, ao artigo, ao tipo e ao actor autorizados antes de os duplicar em novos IDs privados.

`Pré-visualizar` abre a página Notícias existente com `arn_news_preview=<ID>`. A página é resolvida pelo slug e permalink do WordPress. A vista mostra estado Rascunho ou Pendente e uma advertência de que a notícia não está publicada. O autor pode ver o próprio histórico e uma linha de auditoria real por revisão. O editor só vê a versão submetida de notícias Pendentes, mesmo que o repositório interno tenha versões anteriores. O editor consulta os avisos e marca a leitura. O recibo não decide nem publica a notícia.

Os formulários fazem POST com nonce específico da operação e ID, justificação e revisão CAS. Um erro de campo conserva apenas o texto recebido e os IDs indicados na mesma resposta POST, escapados para HTML. O servidor volta a validar esses IDs. Não há payload em URL, opção ou transient. Um sucesso usa redireccionamento para GET. Os resultados são autorizados antes de contar e paginar. As respostas privadas usam `no-store`.

Os originais só saem por `arn_news_media=<ID opaco>` após ligação ao FileRecord `news_asset`, versão e notícia autorizadas. A rota devolve 404 vazio para método, ID, ficheiro, versão ou acesso inválidos, antes do redireccionamento de login. As rotas editoriais e documentais antigas não adquirem acesso a `news_asset`; a REST e o CPT nativo não fornecem outra via de leitura. A admissão de ficheiros verifica MIME, assinatura e dimensões para imagens mas não constitui análise antimalware.

## Casos de QA

Num laboratório com prefixo exclusivo, testar rascunho sem imagem, guardar imagem de destaque, duas imagens de galeria e um PDF sintéticos, rever com conservação e novos IDs, reordenar e retirar, falha de ficheiro acima de 1 MiB, imagem disfarçada e dimensões excessivas, submissão completa e bloqueio posterior da edição. Confirmar que um POST inválido conserva título, corpo, categoria, justificação, ordem e CAS antigo e não conserva os bytes seleccionados. Confirmar 14 ficheiros válidos no mesmo POST com a configuração HTTP indicada.

Testar como editor a contagem autorizada, a versão submetida, o aviso e o recibo. Testar com autor de outra notícia, conta suspensa, concessão revogada, geração antiga e sessão anónima os 404 vazios de pré-visualização e `arn_news_media`. Repetir em `arn_document_file`, `arn_private_file` e REST com IDs de notícia. Verificar o CPT sem REST e sem edição nativa. Inspeccionar o CMS em 1280, 768 e 390 px, teclado, foco, escala de texto, ordem da galeria e anexos e texto alternativo. Conferir que os 22 originais de revisão administrativa mantêm os seus hashes e que as três contas antigas e respectivos grants ficam iguais.

Permanecem por validar classificação e autoridade institucionais, antimalware, media nativa, ambiente Windows/IIS/MySQL 8.4, política de armazenamento e aceitação editorial. Não executar o instalador sobre dados institucionais.
