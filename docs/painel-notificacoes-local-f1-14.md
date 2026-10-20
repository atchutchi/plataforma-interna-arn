# Notificações autorizadas no painel inicial

Parcela local F1-14, 20 de outubro de 2026. Branch feat/painel-notificacoes-autorizadas, a partir de main 9e1a9df. Continua o resumo de tarefas da PR #57. A leitura não escreve dados de negócio nem activa serviços externos. Não cria ou altera contas e concessões. A preparação explícita da tabela nativa de recibos no laboratório está documentada abaixo.

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
| Composição real das cinco fontes e respectivos destinos | Playwright 53, incluindo notícias editoriais e acessos |
| REST autenticado sem projecção dos avisos e reflow/teclado | Controlos positivos REST, teclado, 320/390/768/1440 e alvos de 44px passaram |
| Preservação das tabelas de negócio durante GET | Igualdade das 40 tabelas da demonstração antes/depois de todos os percursos de leitura |
| Revisão, commit, push e CI exacta antes do merge | Revisão estática sem achados. Vinte checks passaram em 3836700. Exigir CI no head final da PR #58 |

## Testes agrupados e limites

No fecho, PHP passou 954 testes e 4675 asserções, com quatro omissões Windows anteriores. Node passou 55 testes, lint e construção. PHPCS passou os nove ficheiros PHP alterados ou novos, incluindo a regressão de Serviços acrescentada após a primeira CI. O ensaio Tasks passou 107 verificações, incluindo as sete adicionais do resumo de avisos, e removeu as suas tabelas e contas exclusivas. A revisão independente foi estática e não identificou achados. Pediu confirmação no runtime dos cinco adaptadores e preservação dos recibos durante GET, posteriormente verificados por Playwright.

O laboratório estável só recebe código após CI verde no head candidato. Não repetir instaladores ou fixtures. Os testes HTTP usam as contas fictícias existentes e conservam os originais de revisão e a notícia autorizada já presente. Credenciais, cookies, resultados detalhados e capturas ficam fora do Git. Esta parcela não conclui a aceitação institucional da F1-14, a homologação IIS/MySQL ou o site completo.

## Correcção identificada pela CI

O candidato c7250a9 falhou os dois ensaios de Serviços da CI durante a abertura de uma ligação do instalador F1-12. O adaptador de avisos consultava três vezes o ServiceDirectory completo por leitura do Início, incluindo People, que não é fonte de avisos. Essa composição abre ligações sem fecho explícito e as chamadas repetidas atingiram o limite de ligações no ensaio.

A nova regressão em service-navigation mede Threads_connected antes e depois dos três leitores de avisos ainda indisponíveis. Falhou localmente contra o candidato original. A correcção fornece uma fonte People vazia apenas neste adaptador. Mantém os leitores, destinos e guardas nativos de F1-12 e Tasks e elimina a consulta que não era necessária. Não aumenta limites SQL nem altera o comportamento do directório de funcionários. O ensaio completo de Serviços passou 211 verificações. Os 20 checks da CI passaram em 38367006a7cc884930e680ffe522bc7ca4176f15 antes da aplicação à demonstração.

## Preparação da demonstração

O laboratório recebeu os seis ficheiros de execução desta parcela após a CI verde. Os 314 ficheiros WordPress versionados ficaram com hashes iguais aos do candidato. Não foram repetidos instaladores de contas, tarefas, People, notícias ou F1-12.

Faltava apenas arn_notification_reads, o contrato nativo de recibos da F1-04. Uma operação CLI explícita de administrador local executou LocalNotificationInstaller::install sob guardas de raiz, URL, prefixo e vinte contas exclusivamente example.invalid. Criou apenas essa tabela vazia. As 39 tabelas de negócio anteriores, os utilizadores nativos e todos os metadados conservaram exactamente os hashes. A base dos ensaios HTTP passa assim a conter 40 tabelas de negócio. A consulta HTTP não instala ou repara esse contrato.

## Fecho HTTP

Playwright passou 53 verificações em nove perfis fictícios: três de Tasks, leitores F1-12 de duas unidades, autor de notícias, editor, Ana e administrador técnico. A projecção do Início coincidiu com os adaptadores reais e com os respectivos recibos. As colecções das cinco fontes abriram, incluindo a página nativa de acessos. O aviso editorial abriu o percurso protegido e a notícia autorizada anterior conservou as dez fotografias. O administrador técnico não recebeu uma excepção de negócio.

As larguras 320/390/768/1440 conservaram H1 único, ausência de transbordo e alvos de pelo menos 44px. As capturas ampla e móvel foram inspeccionadas. Texto CSS a 200% não produziu transbordo. Não equivale a certificação de acessibilidade ou prova de zoom nativo. A ligação do aviso abriu o detalhe próprio por teclado. Parâmetros externos não seleccionaram outro destinatário ou fonte. REST confirmou primeiro utilizador e página inicial com 200 e depois a ausência dos avisos na resposta.

Durante todos os GET, as 40 tabelas de negócio conservaram exactamente os hashes, incluindo recibos e auditoria. Depois dessa comparação, um POST explícito no percurso nativo Tasks marcou um aviso fictício como lido. Alterou apenas uma linha na tabela de notificações Tasks. O Início reflectiu Lida. Repetir o POST conservou o recibo e todas as restantes tabelas. As sessões nativas tiveram os logins fictícios previstos e não fazem parte da alegação de igualdade durante o QA.

Os ensaios não alteraram contas reais, Workspace ou produção. A revisão independente foi estática, complementada pelos testes da composição real. O contrato local desta parcela fica implementado e ensaiado. A [PR #58](https://github.com/atchutchi/plataforma-interna-arn/pull/58) conserva os commits e a CI final exigida antes de integrar. F1-14 institucional, homologação e restantes pré-requisitos mantêm-se por concluir.
