# Acessos autorizados no Início

Continuação F1-06 de 19 de outubro de 2026. A branch `feat/inicio-acessos-autorizados` parte de `feat/navegacao-menus-autorizados` no commit `8d613ea`, PR #45. A nova PR depende dessa entrega. Não fazer merge. A PR #41 e a Issue #37 da Albertina ficam fora do âmbito.

## Parcela e limites

O Início tinha notícias e biblioteca documental, mas não apresentava os atalhos já disponíveis em Serviços e na navegação. O modelo passa a obter os mesmos destinos de `ServiceDirectory`, sem aceitar filtros do URL para alterar essa escolha. O componente `portal/access-links.php` é partilhado pelas duas páginas. Conserva os textos escapados, descrições, estado vazio e ordem fechada dos cinco módulos conhecidos. Cada instância recebe um identificador de título próprio por `wp_unique_id`, evitando colisões entre regiões.

Os atalhos aparecem depois da introdução e antes das comunicações recentes. O colaborador vê Funcionários e Meu perfil. O gestor fictício pode ter também A minha equipa. O leitor F1-12 vê Comunicados e Calendário. Um administrador nativo sem competências de negócio recebe o estado vazio. Estas diferenças resultam das fontes existentes, não de novas funções ou permissões.

Em preparação continua apenas em Serviços. Não se acrescentam operações de tarefas, pedidos, férias ou suporte ao Início. O código não instala recursos, escreve menus, cria contas, concede acessos ou gera eventos de leitura. A auditoria de contas e concessões mantém os contratos existentes. Os destinos revalidam o acesso quando são abertos.

Esta é uma parcela de navegação F1-06. Não é a conclusão do dashboard F1-14, da pesquisa integrada ou das notificações agregadas. Também não antecipa a implementação F1-13. O impedimento transaccional e a conciliação documental dessa fase mantêm-se separados. As pendências institucionais do README continuam válidas.

## Desenho e verificação

Conservam-se o logótipo oficial, fontes locais Manrope/Source Sans 3, cores, escala tipográfica e componentes do PRODUCT.md/DESIGN.md. Impeccable e ui-ux-pro-max orientam a continuidade visual e a acessibilidade. A única regra CSS nova dá espaçamento vertical ao componente no Início, usando o token existente. Não se executou o script Python ausente de ui-ux-pro-max.

A regressão do modelo falhou primeiro na MariaDB real porque o Início não tinha `services`. O teste puro falhou pela ausência do componente comum. Passaram depois da implementação. O novo teste puro tem 14 asserções sobre estado vazio, escape de texto/URL, ausência de campos privados e operações futuras, hierarquia e identificadores distintos. O teste anterior de Serviços continua a verificar a apresentação real através do componente partilhado. Os dois testes focais passam 25 asserções.

A suite PHP completa passou 863 testes e 3828 asserções, com as quatro omissões Windows já existentes. Node passou 55 testes. Lint e construção passaram. O PHPCS inicial encontrou dois incrementos sem parênteses nos doubles dos testes. A correcção manual conservou o resultado dos dois testes focais. O PHPCS final passou os 404 ficheiros. Consultar na PR a CI do SHA exacto entregue.

O ensaio `test:services:integration` passa de 49 a 73 verificações. Conserva a recusa de recursos existentes e as vinte tabelas temporárias. Acrescenta modelo e HTML reais do Início, flags desligadas, administrador sem autoridade, colaborador, gestor, sessão inválida, ausência de actor, página adulterada, F1-12 e exclusão admin/REST. A comparação ordenada dos hashes das dez tabelas preenchidas de People, autorização e organização confirma que a consulta conserva os dados e a auditoria. Não se depende da ordem indefinida de linhas SQL.

O Playwright abriu páginas WordPress temporárias em localhost:8889 com quatro sessões fictícias: colaborador, gestor, administrador nativo sem competências de negócio e leitor F1-12. Todos passaram nas larguras 375, 768 e 1440, com um H1, destinos esperados, texto CSS a 200% sem transbordo e ausência de erros JavaScript. As ligações têm alvos de 44 píxeis. A lista ocupa uma coluna em 375/768 e duas em 1440.

Os percursos abriram Meu perfil, A minha equipa, Comunicados e Calendário. O foco ficou visível e o menu móvel fechou por Escape. O ensaio F1-12 confirmou a mesma lista em Serviços, conservando Em preparação. GET e HEAD do Início devolveram 200 com `private/no-store`, e HEAD sem corpo. Os parâmetros estruturados de pesquisa/paginação do URL não alteraram os atalhos. As capturas de computador e móvel foram inspeccionadas. Texto CSS a 200% não constitui prova de zoom nativo, certificação de acessibilidade ou QA do segundo PC.

## Preservação e continuidade

As páginas, nove contas de ensaio, vinte tabelas de negócio e passwords temporárias foram removidas pelo finally. O único token criado para a conta administrativa anterior foi destruído individualmente, conservando as suas outras sessões. A configuração temporária do browser foi retirada e a sessão Playwright foi fechada. O replay do cookie da fixture removida foi recusado com 302 para login.

A comparação privada antes/depois conserva a configuração e o conjunto das 17 tabelas. Dezasseis mantêm exactamente todos os hashes, incluindo utilizadores, metadados, pré-cadastros, vínculos e auditoria anteriores. Em options mudaram apenas o transiente dos padrões do tema e o respectivo prazo, dois hashes substituídos. Não se apagaram ou repuseram estes deltas para obter igualdade artificial. Provas, snapshots, cookies e inventários ficam fora do GitHub. Não foram usados nomes, emails ou originais institucionais nesta parcela.

A revisão independente do diff não encontrou P1/P2. Foi uma inspecção de código e testes, sem execução SQL ou browser pelo revisor. Os resultados de execução pertencem ao laboratório deste PC. O checkout main conserva `2c77a46` e não recebeu esta branch.

Próximos passos: rever separadamente as PRs dependentes, conservando a proibição de merge. F1-13 continua dependente das correcções transaccionais e da conciliação revista da Albertina. Uma continuação independente pode preparar as projecções de leitura do painel com os contratos já existentes, sem inventar políticas ou concluir F1-14. A activação institucional, escala real, IIS/MySQL, segundo PC e zoom nativo continuam pendentes.
