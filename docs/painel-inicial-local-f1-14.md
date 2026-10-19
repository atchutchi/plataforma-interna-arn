# Painel inicial com comunicados e eventos

Entrega local de 19 de outubro de 2026 na branch `feat/painel-comunicados-eventos`, baseada em `feat/inicio-acessos-autorizados` no SHA `a18939c` da PR #46. Sem merge. Não modifica a PR #41, a Issue #37 ou o trabalho da Albertina.

## Comportamento

O Início reúne os acessos autorizados já existentes, até três comunicados recentes, até três eventos em curso ou futuros e as notícias editoriais. Os comunicados e os eventos usam as listagens publicadas da F1-12, com a sessão, o estado da conta, as competências, as audiências, as unidades e os intervalos temporais validados pelas fontes existentes. Os filtros externos da página inicial não alteram essas consultas.

Cada entrada mostra apenas título, data e ligação para o detalhe protegido. Não copia texto integral, descrição, audiência, autor, revisão ou contagens. A abertura do Início não marca avisos como lidos e não escreve auditoria de negócio. A versão publicada continua visível durante uma revisão privada.

Os eventos conservam a ordenação da agenda. Os horários estão identificados em UTC. Nos eventos de dia inteiro, a data final exclusiva da origem é apresentada como último dia incluído. Eventos passados, cancelados ou em rascunho não entram na selecção inicial.

As duas secções distinguem ausência de publicações autorizadas e indisponibilidade da fonte. Uma fonte indisponível não oculta a outra. Os atalhos completos de Comunicados e Calendário só aparecem se o respectivo destino continuar autorizado.

## Apresentação

O componente `portal/home-dashboard.php` usa o tema institucional existente e duas colunas no espaço disponível. Em ecrãs estreitos apresenta as secções em sequência. Conserva títulos acessíveis distintos, ligações com área mínima de 44 px e nomes completos que quebram linha. Não acrescenta dependências, fontes ou uma segunda paleta.

## Validação e limites

As fixtures usam exclusivamente contas `example.invalid` e unidades LAB-A/LAB-B num WordPress separado. O ensaio compara as projecções de dois leitores, a continuidade da publicação durante revisão, exclusões temporais e de estado, estados vazios e preservação das tabelas preenchidas. As âncoras nativas criadas pelo próprio ensaio são acompanhadas e removidas na limpeza, incluindo após falha.

A suite PHP passou 869 testes e 3864 asserções, com as quatro omissões Windows já existentes. Os seis testes novos somam 36 asserções. Node passou os 55 testes existentes, lint e construção. O PHPCS final passou os 409 ficheiros. Depois da correcção de ligações, a verificação focal de F1-12/comunicados/calendário passou 31 testes e 334 asserções. Estes resultados locais não substituem a CI do SHA entregue.

O primeiro ensaio temporal tentou criar conteúdo com um relógio anterior ao início da concessão e foi correctamente recusado. O ensaio foi corrigido para criar e publicar com o relógio actual e avançar apenas o relógio da consulta. A preparação extensa também revelou acumulação de ligações dedicadas das instâncias F1-12. O diagnóstico distinguiu a sessão válida na ligação existente e a falha ao abrir uma nova ligação, com numerosas ligações Sleep observadas. Cada Portal passa a fechar a sua própria ligação quando termina ou a preparação falha. A regressão mede as ligações antes e depois de doze leituras completas com instâncias terminadas. Não altera limites do servidor, transacções ou regras de acesso.

O Playwright passou os leitores A/B e um administrador nativo sem competências de negócio nas larguras 375, 768 e 1440. Confirmou os títulos exactos, um H1, ausência de transbordo horizontal, alvos de 44 px e texto CSS a 200%. Abriu por teclado o comunicado com revisão privada e o evento de dia inteiro. O leitor A recebeu 404 sem corpo nem redireccionamento ao tentar abrir os dois detalhes reservados de B. GET/HEAD do Início devolveram 200 com `private/no-store`, e HEAD sem corpo. O menu móvel fechou por Escape e não houve erros JavaScript. As capturas de computador e móvel foram inspeccionadas. Texto CSS a 200% não comprova zoom nativo nem certificação de acessibilidade.

A integração final WordPress 7.1.3/MariaDB 11.8.3 passou 105 verificações. O percurso com pausa para o browser passou as 92 verificações anteriores à inclusão das treze asserções de libertação de ligações. Os testes foram agrupados no fecho e só os ensaios com falhas ou alterações posteriores receberam nova execução focal.

A comparação antes/depois do percurso HTTP conservou exactamente os hashes das 37 tabelas presentes, incluindo as vinte tabelas temporárias de negócio. Após a limpeza, o conjunto regressou às 17 tabelas anteriores. Dezasseis conservaram integralmente os hashes, incluindo contas, metadados, pré-cadastros, vínculos e auditoria anteriores. A configuração ficou igual. Em options foram substituídos quatro hashes, correspondentes a dois transientes de padrões do tema e respectivos prazos. Não se apagaram ou repuseram estes deltas para obter igualdade artificial.

As vinte tabelas, nove contas, páginas, menus e âncoras criados pelo ensaio foram removidos. As âncoras órfãs dos primeiros ensaios falhados foram identificadas pelo prefixo e marcador exactos, ausência do autor temporário e exclusão dos hashes anteriores. A configuração temporária do browser foi retirada e a sessão Playwright foi fechada. O único token adicional da conta administrativa anterior foi destruído individualmente. O replay do cookie do leitor removido recebeu 302 para o login. Cookies, capturas, diagnósticos, snapshots e provas completas ficam fora do GitHub.

A revisão independente não encontrou P1/P2 no painel, no fecho de ligações ou no acompanhamento das âncoras. Foi uma revisão estática. O revisor não executou o SQL ou o Playwright.

Esta parcela inicia a agregação da F1-14 e não conclui a história. Pesquisa autorizada, outros módulos, homologação institucional, rede e produção conservam os seus pré-requisitos. As contas nominativas do Workspace e os originais institucionais não são usados nestes ensaios.
