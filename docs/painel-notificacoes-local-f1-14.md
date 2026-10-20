# Notificações autorizadas no painel inicial

Parcela local F1-14, 20 de outubro de 2026. Branch feat/painel-notificacoes-autorizadas, a partir de main 9e1a9df. Continua o resumo de tarefas da PR #57. Não instala dados, cria contas, altera concessões ou activa serviços externos.

## Contrato

O Início acrescenta um resumo de até cinco avisos, considerando os primeiros vinte candidatos autorizados de cada fonte nativa: comunicados, calendário, tarefas próprias, notícias e alterações de acesso. Ordena esse conjunto por data UTC decrescente, módulo e ID decrescente para desempate. É um resumo de janelas limitadas dos módulos, não uma contagem ou pesquisa completa de todos os avisos.

HomeNotificationSources conserva as guardas locais, a sessão e os leitores nativos. F1-12 e Tasks exigem primeiro o destino autorizado em ServiceDirectory e revalidam os avisos. Notícias usa LocalNewsroom::notifications através de Readers, que conserva as políticas editoriais e de publicação. Acessos usa CurrentLocalAccount e WpdbNotificationRepository com a revisão corrente. Não existe excepção para administração técnica. As ligações dedicadas fecham em finally ou no destructor do Portal.

HomeNotifications projecta apenas título, módulo, tipo de aviso, data UTC, ligação e estado Lida/Por ler. Notícias usa a identificação neutra Notícia #ID, sem projectar títulos de versões privadas ou motivos editoriais. Publicações ligam ao artigo publicado. Avisos editoriais ligam ao percurso protegido de revisão. Acessos liga à página nativa de notificações locais. Não transfere destinatários, revisões, motivos, contagens, cookies ou tokens para o template.

Consultar o Início ou seguir uma ligação não marca avisos como lidos. O resumo não tem formulários nem cria auditoria de negócio. Os recibos permanecem nos percursos POST protegidos dos módulos. A leitura reflecte os recibos existentes. Não cria um novo mecanismo de notificações nem email, push ou permissões de produção.

Uma falha, linha inválida ou duplicado incompatível fecha apenas a respectiva fonte. Sem fontes disponíveis apresenta indisponibilidade. Com fontes autorizadas vazias apresenta ausência de notificações. Não mostra a excepção nem infere uma contagem de avisos inacessíveis.

## Interface e aceitação

Preserva PRODUCT.md, DESIGN.md, logótipo, fontes e cores institucionais. Impeccable e UI UX Pro Max orientam a tipografia, os estados explícitos, os destinos com altura mínima de 44px e a adaptação a uma coluna. O resumo ocupa uma secção própria depois dos três resumos existentes. Tem título acessível, escape de texto e URL e não acrescenta H1.

| Critério | Verificação |
| --- | --- |
| Cinco avisos no máximo, projecção fechada e ordem determinística | Testes puros |
| Janela de vinte por fonte e duplicados sem incompatibilidade | Testes puros |
| Vazio distinto de indisponível e falhas independentes | Testes puros e rendering |
| Recibos conservados em leitura e idempotentes no percurso nativo | Testes puros e integração SQL |
| Reatribuição, outra unidade e suspensão revalidadas | Integração SQL em prefixo exclusivo |
| Composição real das cinco fontes e respectivos destinos | Playwright pendente da CI inicial |
| REST autenticado sem projecção dos avisos e reflow/teclado | Playwright pendente da CI inicial |
| Preservação das tabelas de negócio durante GET | Comparação SQL no ensaio. Demonstração pendente |
| Revisão, commit, push e CI exacta antes do merge | Revisão estática sem achados. CI pendente |

## Testes agrupados e limites

No fecho, PHP passou 954 testes e 4675 asserções, com quatro omissões Windows anteriores. Node passou 55 testes, lint e construção. PHPCS passou os oito ficheiros PHP alterados ou novos. O ensaio Tasks passou 107 verificações, incluindo as sete adicionais do resumo de avisos, e removeu as suas tabelas e contas exclusivas. A revisão independente foi estática e não identificou achados. Pediu confirmação no runtime dos cinco adaptadores e preservação dos recibos durante GET.

O laboratório estável só recebe código após CI verde no head candidato. Não repetir instaladores ou fixtures. Os testes HTTP usam as contas fictícias existentes e conservam os originais de revisão e a notícia autorizada já presente. Credenciais, cookies, resultados detalhados e capturas ficam fora do Git. Esta parcela não conclui a aceitação institucional da F1-14, a homologação IIS/MySQL ou o site completo.
