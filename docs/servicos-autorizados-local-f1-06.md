# Serviços autorizados no portal local

Continuação de 19 de outubro de 2026, branch `feat/portal-servicos-local`, baseada em `test/editorial-contention-diagnostics` no commit `10512f9`. A entrega de interface fica separada da PR #43 e não faz merge. PR #41 e Issue #37 da Albertina permanecem fora do âmbito.

## Comportamento

A página Serviços deixa de apresentar directório e perfil como funcionalidades futuras. Mostra os destinos disponíveis para a conta corrente, pela mesma autoridade que já acrescenta os links à navegação WordPress. A extracção de `PeopleNavigation::destinations()` e `F112\Navigation::destinations()` conserva as guardas, as páginas reconhecidas e os atributos dos links nativos.

`EditorialPreview\ServiceDirectory` projecta apenas identificador conhecido, título e descrição fixos e URL autorizado. O catálogo admite Funcionários, Meu perfil, A minha equipa, Comunicados e Calendário. Não recebe nomes, emails, perfis ou conteúdo privado dos módulos. Uma fonte indisponível não revela a excepção e não cria acessos. Os destinos mantêm as próprias guardas quando são abertos.

Uma conta sem destinos recebe um estado vazio. Tarefas de equipa, pedidos/férias e suporte aparecem em Em preparação, sem botões ou links que simulem operações. Não foram implementados esses módulos nem concluída F1-14. O bloco não instala schemas, cria páginas, grava menus, atribui permissões ou regista eventos por GET. As alterações administrativas dos módulos continuam sujeitas à auditoria existente.

O tema conserva o logótipo oficial, Manrope e Source Sans 3 locais, cores institucionais e componentes do PRODUCT.md/DESIGN.md. O fragmento usa títulos subordinados ao H1 da página, texto escapado, linhas de acesso e foco nativo. A apresentação segue Impeccable e as orientações disponíveis de ui-ux-pro-max. O script de pesquisa Python desse último skill não está presente nesta instalação. Não se apresenta uma execução desse script como prova de design.

## Verificação

Os testes puros novos falharam antes da implementação das classes/vista e passaram depois: cinco testes e 23 asserções. Cobrem projecção fechada, URL inválido, fonte recusada, estado vazio, escape HTML e ausência de acções fictícias. A suite completa passou 859 testes e 3785 asserções, com as quatro omissões anteriores de symlink Windows. Node passou 55 testes. Lint e construção passaram. O PHPCS final passou nos 399 ficheiros, depois de corrigidos os problemas de formatação do novo ensaio SQL.

`npm run test:services:integration` exige CLI administradora e ambiente local ou development. Em development só verifica a recusa, sem criar recursos. Em local recusa antes de qualquer instalação se encontrar uma das vinte tabelas de negócio, os logins/emails das nove fixtures People/F1-12, o marcador de conclusão F1-12 ou páginas reservadas. Não o executar numa instalação com módulos já activos. A job F1-12 da CI executa o negativo antes de activar local e o positivo num contentor efémero. Não altera a configuração de desenvolvimento do utilizador.

No WordPress 7.1.3, PHP 8.4.26 e MariaDB 11.8.3 separados em localhost:8889 passaram 32 verificações: flags desligadas, administrador nativo sem autoridade People, colaborador sem equipa, gestor com equipa, igualdade entre menu e modelo de Serviços, ausência de duplicados, preservação da navegação guardada, página adulterada/não publicada, geração de sessão inválida, ausência de actor, administração e REST. O instalador real prepara nove contas fictícias, unidades e concessões auditadas temporárias. O finally elimina exclusivamente essas contas, páginas, passwords, marcador F1-12 e vinte tabelas inicialmente ausentes. As 73 entradas fictícias de pré-cadastro não recebem funções ou perfis.

A primeira execução do novo ensaio recusou a instalação por faltar a preparação dos schemas de autorização/unidades no próprio teste. Foi corrigida a preparação explícita, sem alterar o instalador ou relaxar validações. A primeira execução Node desta continuação falhou por faltar NODE_PATH para as dependências existentes do checkout principal. A execução com o contexto correcto passou os 55 testes.

O Playwright abriu uma página WordPress temporária com uma sessão administrativa fictícia, sem autoridade de negócio. Verificou o estado vazio a 375, 768 e 1440 píxeis, texto a 200%, um H1, foco no salto para o conteúdo, logótipo/fontes carregados, ausência de acções fictícias, GET 200 e HEAD 200 sem corpo com cache private/no-store. Não houve erros JavaScript. As capturas ficaram privadas e a móvel foi inspeccionada. A página e o token próprios foram removidos e o cookie anterior foi recusado por redireccionamento para login.

A regressão OIDC simulada manteve o portal de preparação, as três larguras, texto a 200%, teclado, 16 superfícies recusadas e logout com replay recusado por 302. Não houve login Google real nesta parcela.

## Preservação e limites

A comparação privada antes/depois da instalação temporária e da QA administrativa manteve a configuração e o conjunto de 17 tabelas existentes. Quinze ficaram exactamente iguais. Em posts, as três linhas anteriores conservaram os hashes e surgiu uma linha wp_navigation criada pelo fallback nativo do WordPress na primeira visita. Em options houve quatro hashes removidos e cinco acrescentados. As opções actuais diferentes são fresh_site, theme_mods_arn-intranet, os dois transientes de padrões do tema e _transient_wp_styles_for_blocks. Não se apagaram ou repuseram estas diferenças para obter igualdade artificial. A verificação específica da projecção e dos links nativos provou separadamente que estes não alteram a navegação guardada.

Os eventos de login simulado e as sessões da regressão posterior são efeitos esperados de autenticação. Provas, cookies, inventários e snapshots ficam privados. O checkout main e a instalação normal não receberam código desta branch. A revisão independente do produto e do ensaio não identificou P1/P2.

O percurso positivo F1-12 foi entretanto acrescentado com o instalador real, sessão nativa e comparação entre os seus dois destinos e o menu. O teste confirma que uma página F1-12 adulterada é excluída sem ocultar a outra. A suite também cobre a lista automática de páginas dentro e fora da navegação e restauração do filtro após excepção. Não confundir estes ensaios com zoom nativo, segundo PC, IIS/MySQL 8.4 ou validação institucional. Consultar a CI no SHA entregue e na PR desta branch. Manter a proibição de merge.

Próximos passos: rever a CI do SHA entregue e continuar os módulos cujo contrato já esteja aprovado. A aceitação institucional e a compatibilidade com a infraestrutura alvo continuam por executar. F1-13 continua dependente da conciliação da Issue #37 e da correcção transaccional revista. As decisões institucionais de audiências, funções e processos continuam por validar.

A QA positiva no browser encontrou duplicação e uma entrada de equipa recusada no fallback core/page-list. Uma regressão real reproduziu a falha. NavigationPageList envolve o callback nativo apenas dentro da navegação de front end e retira temporariamente as cinco páginas reservadas de topo da consulta get_pages. People/F1-12 acrescentam os seus destinos autorizados. O finally retira o filtro. Não se reescreve HTML ou navegação guardada. Uma página filha homónima, como departamento/calendario, é conservada. A revisão identificou este caso e o teste falhou antes do ajuste de post_parent e passou depois.

A repetição Playwright positiva com um colaborador People confirmou os dois acessos, menu sem duplicação ou equipa recusada, uma coluna em 375/768 e duas em 1440, alvos de 44 px, foco visível, texto a 200% sem transbordo e abertura efectiva de Meu perfil. O laboratório usou configuração temporária restrita à instalação fictícia para as quatro flags People. O ficheiro de ensaio e os recursos temporários foram retirados. As capturas de computador e móvel foram inspeccionadas. Este percurso não usa dados ou permissões das contas Workspace reais.
