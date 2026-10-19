# Pesquisa integrada autorizada

Continuação de 19 de outubro de 2026 na branch `feat/pesquisa-integrada-autorizada`, baseada em `feat/painel-comunicados-eventos`, PR #47 no SHA `c9af88d`. Sem merge. As tarefas da Albertina, PR #41 e Issue #37, ficam fora do âmbito.

## Âmbito e critérios

A parcela implementa a pesquisa básica US-S02 dos módulos locais activos: funcionários, notícias aprovadas, documentos e regulamentos publicados, comunicados e eventos. O Início mantém o painel quando não há pedido de pesquisa e encaminha o formulário para a vista de resultados na própria página. O CMS permite também seleccionar a vista Pesquisa integrada no bloco existente, sem criar páginas automaticamente.

Os critérios são encontrar apenas conteúdo autorizado, identificar cada tipo de resultado, filtrar por tipo, paginar sem truncar silenciosamente cada módulo e conservar a autorização nos detalhes. Versões privadas, rascunhos, originais em revisão administrativa, excertos, audiências, contactos adicionais e contagens restritas não entram na resposta. Nenhuma pesquisa cria uma conta, concede competências, marca avisos como lidos ou escreve auditoria de negócio.

## Contratos

`Search/Query` valida os parâmetros antes de abrir fontes. Aceita uma expressão literal UTF-8 com até 100 caracteres e 160 bytes, o tipo fechado e paginação canónica até seis algarismos. Recusa parâmetros estruturados, controlos, HTML, filtros alheios e marcadores inválidos. Conserva `page_id` para ligações permanentes simples. Uma expressão vazia apresenta instruções sem consultar fontes.

`Search/WordPressSources` usa `LocalPeopleDirectory::contactList`, `NewsPublishedPortal::model`, `DocumentPortal::model` e as listagens publicadas F1-12. Não usa os fluxos de gestão, o manifesto administrativo, um índice de posts ou SQL próprio. Cada origem valida a sessão, o estado, as competências e a audiência pelos contratos existentes antes de filtrar e contar. Os destinos People/F1-12 vêm das guardas de navegação. Notícias e documentos exigem uma página publicada com o bloco e a vista correspondentes. Uma página substituída ou removida fecha essa fonte.

Os resultados projectam apenas `title`, `url` e `type`. Funcionários mantêm a pesquisa por nome do directório. Documentos pesquisam título, resumo e referência publicados. Notícias e comunicados usam os respectivos textos publicados. Eventos usam título, descrição e local publicados. O resultado não inclui esses textos. Os eventos próximos, passados e cancelados são pesquisados em três selecções disjuntas, com identificação explícita do estado na lista.

`Search/FederatedSearch` pagina dez resultados por página sobre as contagens já autorizadas. A ordem é funcionários, notícias, documentos, comunicados e os três grupos de eventos. Cada grupo conserva a ordenação nativa. Não se apresenta uma relevância calculada. O adaptador começa pela primeira página de cada origem elegível e só pede outras páginas para preencher a janela global. Não percorre todas as páginas ou guarda um índice entre pedidos.

Uma fonte nula ou defeituosa não divulga erros, linhas ou contagens e não impede as outras. Se uma página posterior falhar ou devolver uma contagem diferente, essa fonte é retirada e a janela é recalculada. Não se promete uma transacção global entre módulos. Cada consulta usa a autoridade corrente da origem e cada detalhe volta a verificá-la. Alterações simultâneas podem mudar a paginação entre pedidos.

A paginação documental passa do limite anterior de três algarismos para seis, compatível com os restantes filtros. O caso da página documental 1000 é validado sem criar dez mil documentos no laboratório. A pesquisa não é servida com dados de negócio em admin, REST, métodos de escrita, ambiente diferente de local ou sem actor. O portal emite `private/no-store` independentemente das flags editoriais.

## Apresentação

A vista conserva logótipo, fontes locais, cores, controlos e listas editoriais do tema. Impeccable e ui-ux-pro-max orientam a continuidade visual. Não se voltou a executar o contexto Impeccable já visto nesta conversa nem o helper Python ausente de ui-ux-pro-max. Há rótulos para expressão e tipo, recuperação para primeira página ou limpeza, estados inicial/inválido/vazio e mensagem genérica de falha parcial. Não há dependências ou pedidos JavaScript de pesquisa.

## Laboratório e validação

O primeiro preflight recusou uma conta fictícia `ana.teste` já existente antes de criar recursos. Essa conta foi preservada. A integração completa usa por isso outra instalação WordPress temporária em loopback, com prefixo, cookies, configuração e administrador próprios. Não reconfigura as instalações anteriores e não usa contas nominativas, nomes ou emails do Workspace. As fixtures têm apenas endereços `example.invalid` e competências fictícias auditadas pelo serviço existente.

O ensaio acrescenta sete contas às nove fixtures People/F1-12 do ensaio exclusivo de Serviços, e catorze tabelas às vinte anteriores. Recusa previamente essas contas, as 34 tabelas e páginas reservadas já existentes. O finally acompanha páginas, menus, contas e âncoras nativas do próprio ensaio. Os descritores de imagem/PDF são metadados sintéticos para testar pesquisa. Não provam admissão ou descarga de binários.

Os testes cobrem travessia de módulos e páginas nativas de 10/20, tipos, literais, parâmetros inválidos, projecção fechada, falha posterior e alteração de contagem. A integração cobre as fontes reais, versões publicadas durante revisão, audiências A/B, destinatários documentais, páginas adulteradas, sessão inválida, suspensão/reactivação e preservação de dados, notificações e auditoria durante a leitura. Playwright segue os detalhes nativos e verifica os formulários e a paginação no browser.

O fecho local passou 182 verificações WordPress/MariaDB e PHPCS nos 418 ficheiros. A execução PHP inicial passou 876 testes e 3913 asserções, com quatro omissões Windows já existentes. Depois da regressão da página documental 1000 e do fecho explícito das ligações, passaram os oito testes focados de pesquisa com 52 asserções e os 113 testes relacionados com pesquisa, notícias e People com 832 asserções. Os 55 testes Node, lint e build passaram. A CI do commit entregue fornece a execução integral final em Linux.

O primeiro ensaio SQL na instalação temporária encontrou esgotamento de ligações dedicadas durante a composição prolongada das fontes. Um ensaio mínimo demonstrou a acumulação de doze ligações mesmo depois da recolha de ciclos. `ReadConnections` passou a acompanhar e fechar exclusivamente as ligações que o adaptador abre, através de `finally`, e `Readers` injeta essas fábricas nos leitores nativos. `NewsConfiguration` fecha a própria ligação de metadados. A repetição das quatro fontes no ensaio final conserva o número de ligações. Não se alterou `IdentityWpdb`, a ligação global, transacções ou as tarefas da Albertina. Não se afirma ter corrigido o ciclo de vida de todos os adaptadores do projecto.

Playwright passou com oito perfis fictícios e larguras de 375 e 1440 px, mais 768 px no calendário. Confirmou pesquisa pelo formulário do Início, contagens autorizadas, terceira página com oito resultados, filtro de eventos próximos/passados/cancelados e detalhes reais dos cinco módulos. Confirmou recusas cruzadas de comunicados e notícias com 404 vazio e ausência do documento RH reservado na resposta ao destinatário alheio. Passaram parâmetros inválidos, estado vazio, limpeza, foco e activação por teclado, menu móvel e texto CSS a 200%. GET e HEAD devolveram 200, `private, no-store` e corpo HEAD vazio. Não houve erros JavaScript de página. A ampliação CSS não equivale a zoom nativo. Os metadados sintéticos não comprovam a transferência de binários.

As 34 tabelas de negócio preenchidas ficaram idênticas durante a leitura, incluindo auditoria e notificações. As contas e sessões nativas também ficaram idênticas durante o browser. No fecho foram removidas as 34 tabelas, dezasseis contas, páginas, menus e âncoras acompanhados pelo ensaio e a activação HTTP temporária. Das doze tabelas nativas iniciais, dez ficaram integralmente iguais e a configuração ficou byte a byte igual. Os deltas conservados são um `wp_navigation` publicado criado pelo WordPress e opções nativas: `fresh_site`, `theme_mods_arn-intranet`, o par de transientes dos padrões do tema e `wp_styles_for_blocks`. Não se apagaram estes deltas para forçar igualdade. A instalação fictícia anterior conservou as dezassete tabelas, com dezasseis integralmente iguais, configuração igual e delta apenas em opções. A conta Ana Teste anterior não foi alterada. A revisão estática independente não deixou achados P1/P2.

## Limites e continuidade

A parcela não conclui a F1-14 institucionalmente. Tarefas F1-13, módulos futuros, pesquisa de F2, escala real, índices externos, permissões institucionais, IIS/MySQL, segundo PC e aceitação global mantêm os seus pré-requisitos. Os resultados do laboratório não aprovam audiências, publicação ou competências institucionais. Provas completas, configurações, cookies e snapshots permanecem fora do GitHub.
