# Redacção e submissão local de notícias F1-10

14 de outubro de 2026. Desenho técnico para US-NOT-01 a US-NOT-05 e a consulta de Rascunho/Pendente em US-NOT-06. O objectivo é permitir autoria e submissão reais no WordPress local com contas fictícias, imagens e anexos privados. O README mantém aprovação, rejeição, reenvio e publicação em F1-11. Esta parcela não substitui F0-05 ou decisões institucionais.

## Intenção e opções

A autorização existente distingue funcionário autor e equipa Editorial. O circuito EditorialPreview continua reservado ao administrador para rever os 22 anexos reais. Não se importam esses anexos e não se alteram Ana Teste, RH Documentos Teste ou lab_admin.

O editor nativo com REST e media pública introduziria uma segunda fronteira de autorização. Uma interface apenas em memória não satisfaria rascunhos duráveis, associação de anexos ou notificações confirmadas. A opção escolhida é um CMS específico no plugin, CPT privado como âncora, núcleo puro e persistência própria, seguindo o catálogo documental existente sem o reutilizar como notícia.

## Modelo

O namespace é Arn\Intranet\News. Asset é imutável e contém kind featured|gallery|attachment, BlobDescriptor e description. Imagens featured/gallery exigem PNG ou JPEG e texto alternativo de 1 a 300 caracteres. Attachment aceita PDF, PNG ou JPEG e uma designação de 1 a 160 caracteres. Não se guarda nome ou caminho do upload. Content contém título UTF-8 de 1 a 200 caracteres, corpo de 0 a 20000 caracteres em texto simples com parágrafos, categoria circular|nota-interna|despacho|comunicacao-sindical, featured opcional, galeria ordenada até 8 imagens e anexos ordenados até 5. Rejeita UTF-8 inválido e controlos, permitindo LF no corpo e normalizando CRLF para LF. Dados mantêm-se escapados na apresentação. Não permitir referências PHP externas nas listas.

Version identifica número positivo, Content, actor e instante UTC. Article contém ID, autor fixo, âmbito editorial fixo de uma fila, revisão concorrente, versões ascendentes e eventual versão/instante submetidos. Cada revisão tem novos IDs de binário para todos os elementos conservados, mesmo quando os bytes não mudam. Não repetir um ID no mesmo artigo. Criar guarda versão 1 em Rascunho. Rever acrescenta versão e exige CAS. Enviar exige corpo não vazio, destaque com alternativa e CAS, fixa a versão enviada e altera para Pendente. Pendente não admite edição ou nova submissão nesta fase. Não existem acções de aprovação ou publicação nesta entrega.

NewsPolicy exige sempre conta Active, geração actual de sessão e concessão persistente. CreateNewsDraft/SubmitNews usam o âmbito próprio e autor igual ao actor. O autor consulta as suas versões enquanto conserva a competência. Editorial com ReviewNews na fila consulta apenas a versão submetida de notícias Pendentes. Não consulta rascunhos alheios ou versões anteriores não submetidas. Administrador técnico não recebe excepção. Concessão revogada, suspensão ou sessão antiga recusam listas, detalhes, imagens, anexos e escritas.

## Persistência e notificações

NewsSchema instala explicitamente arn_news, arn_news_versions, arn_news_assets, arn_news_audit, arn_news_notifications e arn_news_schema. Exige InnoDB, colunas e índices compatíveis e texto documental utf8mb4_bin. O CPT arn_noticia usa título genérico e é privado, sem REST, editor, pesquisa pública, rewrite ou capacidades nativas. Nenhum pedido de leitura instala tabelas.

Uma IdentityWpdb dedicada reúne a âncora, artigo, versão, assets, file registry, file audit e news audit na mesma transacção. Cada asset tem ID próprio, artigo, versão, kind e posição. O FileRecord usa object_type news_asset, object_id do asset, version 1 e variant original. Isso conserva o catálogo fechado de variantes de ficheiro. O endpoint confirma todas as relações antes de abrir o binário.

A ordem é validar esquemas, START, bloquear contas e concessões por ID crescente e só depois a notícia. A autoridade corrente vem do servidor. Revalida geração da sessão e snapshot sob lock usando findInTransaction. O autor e a fila pré-lidos são comparados sob lock. Não há reconnect ou retry implícito. Falha SQL reverte as oito tabelas operacionais envolvidas. Binários copiados antes da transacção podem ficar órfãos, limite já documentado no armazenamento.

Enviar resolve no servidor os editores fictícios e confirma sob lock o seu estado e ReviewNews na fila. Sem editor elegível, conserva Rascunho e não grava submissão. A transacção cria o evento e uma notificação por destinatário com chave única artigo/versão/destinatário. A leitura só observa operações confirmadas. Repetições não duplicam eventos ou avisos. Recibos de leitura revalidam destinatário, sessão, concessão e notícia, são idempotentes e não alteram revisão nem representam decisão editorial. A fonte e tabela são específicas de notícias, sem misturar IDs com a auditoria de acessos existente. Não enviar emails.

## Adaptador local e ficheiros

Exigir local e as flags booleanas ARN_ALLOW_LOCAL_NEWSROOM, ARN_ALLOW_LOCAL_PRIVATE_FILES, ARN_ALLOW_FICTIONAL_LOCAL_ACCESS e ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE. CurrentNewsActor limita-se a autor.noticias.teste e editor.noticias.teste marcados. LocalNewsInstaller por CLI administradora cria estas duas contas Subscriber fictícias, estado Active e concessões auditadas. Autor tem Employee Own e editor Editorial Queues[editorial-ficticia]. A instalação normal é idempotente e recusa concessões revogadas, contas suspensas ou identidades inesperadas, sem restaurar acessos. Credenciais aleatórias ficam em autor-noticias-local.password e editor-noticias-local.password dentro da raiz privada validada, nunca em stdout ou Git. Sem associação Google ou concessões em contas reais.

MediaInput selecciona um upload temporário do servidor OU um ID conservado. Conservar só permite um asset da versão mais recente da própria notícia e do mesmo kind, com autorização antes da duplicação. Criar não aceita IDs conservados. Cada ficheiro tem limite 1 MiB, fileinfo/assinatura e política existente. Imagens exigem dimensões reais positivas, máximo 4096 por lado e 16000000 píxeis, verificadas no binário copiado. Não se acrescenta GD ou outra dependência. Esta admissão não é antimalware.

A rota GET arn_news_media usa ID opaco, autorização artigo/versão e integridade do handle. Imagens featured/gallery são inline com MIME verificado, CSP restritiva e nosniff. Anexos são attachment. No-store/private e comprimento verificado em ambos. Outros métodos, IDs malformados, ausência e recusa dão 404 vazio. Nenhum original vai para uploads, URL estática, REST ou endpoint documental anterior.

## Interface

O CMS Notícias locais apresenta As minhas notícias para autor, pendências autorizadas e notificações para editor. Criar e editar Rascunho, ver estado, guardar, pré-visualizar e Enviar para aprovação são operações separadas. A edição conserva, remove e reordena imagens/anexos com controlos explícitos por teclado, sem depender de arrastar. Novos uploads têm alternativas/designações. O editor consulta a versão pendente em leitura, sem botões de decisão F1-11. Formulários usam POST, nonce, justificação breve e CAS. Actor, papéis, fila e IDs de proprietário não são autoridade recebida do browser.

Erros ficam junto dos campos e conservam dados editoriais no mesmo POST escapado, sem payload em URL, transients ou opções. Uploads precisam de nova selecção após erro. Sucesso redirecciona para GET. Listas e filtros contam depois da autorização e têm paginação. A pré-visualização usa a página Notícias e o parâmetro arn_news_preview, com protecção antes de renderizar e modelo reduzido autorizado, sem agregar histórico. Recusa de preview dá 404 indistinto. O administrador conserva o circuito de anexos reais.

Impeccable e UI UX Pro Max orientam DESIGN.md, Manrope/Source Sans 3 locais, paleta ARN, controlos de 44 px, corpo de formulário 16 px, rótulos, erros e foco de teclado. A pré-visualização mostra destaque, parágrafos, galeria na ordem guardada e anexos autorizados. Aviso explícito de Rascunho/Pendente, sem aparência de publicação aprovada.

## Aceitação e limites

Testar imutabilidade, galeria/anexos completos, limites, CAS, bloqueio em Pendente, autorização de autor/editor/outro utilizador, geração antiga, concessão revogada e gates exactos. Integração WordPress/MariaDB com prefixo e contas exclusivos cobre rollback das oito tabelas, notificações sem duplicação, recibos, vínculos dos assets, schema incompatível e contenção de duas ligações. Limpar só fixtures próprias em finally.

O controlador instala as contas normais depois das revisões, ensaia Playwright com PNG/JPEG/PDF sintéticos, reordenação/remoção, pré-visualização, submissão, recibos, recusa de rascunhos alheios e acesso directo. Verifica 1280/768/390, teclado, texto ampliado e ausência de erros. Conserva hashes e linhas preexistentes, incluindo as 22 fontes reais. Commit/push em branch, revisão global, CI no SHA exacto e integração autorizada sem deploy. F1-10 fica Em curso até homologação institucional. F1-11 e restantes módulos não se declaram concluídos.
