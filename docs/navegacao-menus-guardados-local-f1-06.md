# Navegação guardada por acessos no laboratório

Continuação F1-06 de 19 de outubro de 2026. A branch `feat/navegacao-menus-autorizados` parte de `feat/portal-servicos-local`, commit `1c71c51`, entregue na PR #44. A nova PR deve ter essa branch como base. Não fazer merge nesta tarefa. A PR #41 e a Issue #37 da Albertina ficam fora do âmbito.

## Problema e comportamento

A entrega de Serviços retirava destinos recusados e duplicados da lista automática `core/page-list`. Um menu guardado no CMS podia ainda conter uma ligação a A minha equipa para um colaborador sem essa competência ou repetir Meu perfil dentro de um submenu. As guardas do destino recusavam a abertura, mas a navegação apresentava uma entrada inadequada.

`EditorialPreview\ModuleNavigation` projecta os blocos nativos de cada navegação de front end depois dos fornecedores People/F1-12. Consulta os mesmos destinos autorizados de `ServiceDirectory`. Conserva o primeiro rótulo permitido e retira duplicações do mesmo módulo nos submenus. Cada menu mantém a própria sequência. Não concede funções ou âmbitos e não substitui a autorização no destino.

`NavigationTargets` reconhece apenas as cinco páginas de módulo já conhecidas: Funcionários, Meu perfil, A minha equipa, Comunicados e Calendário. Admite os endereços nativos por ID, os slugs de raiz, subpastas do site, `index.php`, fragmentos e segmentos de caminho equivalentes. Endereços externos, páginas filhas homónimas e ligações comuns são conservados. Uma ligação reconhecida sem acesso é retirada. Um submenu cujo próprio destino foi recusado conserva os filhos permitidos como grupo sem ligação. Um grupo que fica vazio é retirado.

Os objectos originais são conservados. A projecção usa cópias dos blocos e mantém o contexto nativo. A edição administrativa e o REST recebem os blocos originais, deixando as respectivas guardas nativas actuar. Não há gravação de menus, páginas, permissões ou eventos por esta leitura. As alterações de contas e concessões do laboratório continuam auditadas pelos instaladores existentes.

Não há alterações de CSS, fontes, logótipo ou componentes visuais nesta parcela. Conservam-se PRODUCT.md, DESIGN.md e o menu nativo WordPress. Impeccable e ui-ux-pro-max orientam a continuidade e a verificação de teclado, foco e adaptação. Não se apresenta como executado o script Python ausente de ui-ux-pro-max.

## Regressões e revisão

A reprodução WordPress falhou primeiro com a ligação de equipa recusada e o perfil repetido. A revisão independente encontrou dois casos adicionais. Caminhos com segmentos `.` ou `..`, incluindo `%2e`, tinham de seguir a resolução do browser. A barra final também é necessária quando o último segmento é um ponto ou dois pontos numa instalação em subpasta com `?page_id`. Os contra-exemplos falharam antes da correcção e passaram depois.

Um pai que o WordPress não apresenta, por estar em rascunho ou não ter rótulo, também não pode consumir o primeiro destino permitido dos filhos. A regressão real falhou antes de se aplicar a validação nativa antes da deduplicação. Passou depois.

O ensaio `test:services:integration` conserva as 32 verificações anteriores e passa a 49. Acrescenta menus `wp_navigation` guardados, submenus, rótulos, fragmentos, caminho equivalente, sessão inválida, gestor com equipa, preservação de objectos/conteúdo, pais ocultos, F1-12 e exclusões admin/REST. A limpeza elimina apenas os dois menus próprios, além dos recursos fictícios já controlados pelo teste. Os pré-requisitos e a recusa de instalações ocupadas mantêm-se.

Três testes puros novos passam 29 asserções. A suite PHP local completa passa 862 testes e 3814 asserções com as quatro omissões Windows já existentes. PHPCS passa 402 ficheiros. Node passa 55 testes. Lint e construção passam.

## Browser e preservação

O Playwright usa apenas contas fictícias e sessões nativas no WordPress 7.1.3 separado em localhost:8889, com PHP 8.4.26 e MariaDB 11.8.3. Não há login Google real ou uso das contas institucionais nesta parcela. Menus e página Serviços do ensaio são temporários. As configurações, cookies, capturas e logs completos ficam fora do Git.

Os percursos People passaram a 375, 768 e 1440 píxeis, com perfil único e equipa recusada para o colaborador. O gestor abriu a equipa. O perfil abriu por teclado com foco visível. O menu móvel abriu e fechou por Escape. O texto CSS a 200% não causou transbordo. As capturas de computador e móvel foram inspeccionadas.

A primeira QA F1-12 conseguiu abrir Comunicados mas falhou na ligação de calendário dentro do submenu. Uma execução posterior passou e a repetição voltou a falhar ao procurar o botão do submenu. Os logs vermelhos são conservados. Numa comparação controlada, a sequência rápida passou três vezes e falhou uma vez com o ponteiro dentro do menu. O código nativo tem uma transição de opacidade de 100 ms e callbacks de foco. O teste passou a aguardar `aria-expanded=true`, submenu visível e opacidade final antes de avançar, sem alterar o JavaScript do produto ou introduzir pausas fixas. Quatro comparações seguintes passaram, duas com o ponteiro dentro e duas fora. Isto sustenta a condição de sincronização do ensaio. Não demonstra por si só a causa interna de cada falha anterior.

O percurso F1-12 final passou nas três larguras, sem duplicados ou destinos People, abriu Comunicados e Calendário, ampliou texto CSS a 200% sem transbordo e abriu/fechou o menu móvel. Não teve erros JavaScript. A captura do menu móvel foi repetida após a animação nativa para permitir inspecção visual, em vez de avaliar uma imagem capturada durante a transição. A primeira invocação de um script People por argumento PowerShell teve um SyntaxError. Foi substituída pelo parâmetro `--filename` da CLI e o percurso completo passou.

A comparação privada antes/depois conserva a configuração e as 17 tabelas iniciais. Dezasseis tabelas conservam exactamente todas as linhas, incluindo utilizadores, metadados, pré-cadastros, vínculos e auditoria anteriores. Só options tem dois hashes substituídos: o transiente de padrões do tema e o respectivo prazo. Não se apagaram ou repuseram esses deltas para fabricar igualdade. Os menus e páginas próprios, as nove contas de cada ensaio e as vinte tabelas temporárias foram removidos. A configuração temporária do browser foi retirada e a sessão Playwright própria foi fechada. O checkout main conserva `2c77a46` e a instalação normal não recebeu esta branch.

A revisão independente final não identificou P1/P2 concretos adicionais. Os resultados SQL e browser pertencem à execução local, não à revisão estática. Os logs e snapshots originais ficam preservados numa pasta privada desta tarefa.

Esta parcela não conclui F1-06, F1-13, F1-14, acessibilidade formal ou aceitação institucional. Zoom nativo, segundo PC e IIS/MySQL continuam por executar. F1-13 mantém os seus pré-requisitos e as tarefas da Albertina permanecem separadas. O próximo desenvolvimento independente deve consultar o README e os estados correntes das PRs, sem integrar alterações nesta tarefa.
