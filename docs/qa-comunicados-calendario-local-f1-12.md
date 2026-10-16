# F1-12: aceitação no laboratório local

16 de outubro de 2026. Implementação na branch `feat/f1-12-comunicados-calendario`, a partir de main `fffef58323636d59985a073e6b03bfc8e3c40e69` e da proposta da PR #26. Código PHP inicialmente revisto e corrigido no SHA `bc60cf5ec6a18df9a834ee7b951110c028cadec1`. A correcção visual descoberta na aceitação está no SHA `65a01e25ecbe12bda9824d691e3739f0af8224a2`. A correcção posterior de geração de segredos é descrita abaixo. O SHA final e a respectiva CI constam da PR #32. Este relatório distingue prova local de aprovação institucional.

## Ambiente e preparação

Windows, PHP portátil 8.4.26, Node.js 24.21.0, WordPress 7.1.3 e MariaDB 11.8.3. HTTP em 127.0.0.1:8888 e SQL em 127.0.0.1:33317. Ambiente efectivo `local`. Relógio UTC nos conteúdos e relógio injectado 2030-01-15T10:00:00Z nos cenários SQL. Não foram alterados o relógio do Windows, o fuso de outros módulos, servidores institucionais, IIS, AD ou DNS.

Foi guardado um baseline inicial imutável de 39 tabelas e 83 ficheiros privados. Antes da activação, foi guardado outro ponto de comparação, também imutável, com hashes por chave primária, opção, sessão bruta e ficheiro. Foram copiados a configuração e os destinos das duas junctions. Nenhum destes artefactos ou credenciais está no Git.

A activação ligou o plugin e o tema desta branch ao WordPress local e acrescentou apenas as duas flags booleanas `ARN_ALLOW_LOCAL_COMMUNICATIONS` e `ARN_ALLOW_LOCAL_CALENDAR`. A prova final conserva exactamente os bytes da configuração anterior depois de remover essas duas definições. A normalização de uma quebra de linha introduzida pelo WP-CLI foi identificada e eliminada com base no backup original.

O instalador explícito criou seis contas fictícias Subscriber, 13 concessões auditadas, duas páginas e dez tabelas de módulo. O marcador final só foi gravado depois de verificar todos os recursos. Não foram criadas contas reais. As passwords ficaram na raiz privada anterior, fora do Git e do document root.

## Provas executadas

Os identificadores seguintes correspondem a logs e resultados JSON privados do laboratório. Os ficheiros públicos contêm requisitos, código e resultados agregados. Não contêm cookies, nonces, passwords, dumps ou dados do inventário Workspace.

| Prova | Execução e resultado |
| --- | --- |
| P01 | Suite PHP completa: 800 testes, 3612 asserções e quatro omissões Windows preexistentes. PHPCS dos 16 ficheiros PHP da correcção sem erros ou avisos. Node: 55 testes, lint e build aprovados. |
| P02 | `test:communications:integration`: 70 verificações SQL exclusivas, comparação de todas as linhas normais e limpeza própria. |
| P03 | `test:calendar:integration`: 70 verificações SQL exclusivas, comparação de todas as linhas normais e limpeza própria. |
| P04 | `test:f112-installer:integration`: 58 verificações, incluindo seis segredos com roundtrip nativo exacto após forçar a regressão de espaços. `test:f112-readiness:integration`: 52 verificações. Colisões, repetição, suspensão/revogação, marcador ausente/inválido e falhas na primeira/segunda página. |
| P05 | Playwright `login-six`: seis logins reais por formulário WordPress e ambas as listas com 200 para cada conta. |
| P06 | `manager-flow-final`: data impossível recupera campos e foco, quatro pedidos adulterados recusados, criação/publicação com 303 e dois pedidos com o mesmo CAS dão um sucesso e um conflito com recuperação permitida. |
| P07 | `institutional-flow-native-url`: Pending oculto, ausência de auto-aprovação, devolução, rejeição, aprovação por segundo actor, fila 200 após 303, detalhe decidido 404, continuidade de v3 durante Draft/Pending de v4, troca de audiência e arquivo. |
| P08 | `published-reads`: A/B, versão publicada sem rascunho, HTML literal escapado, 21 resultados em 20/1 sem duplicados, página 3 vazia, filtros/regresso, segunda página de avisos, recibo próprio repetido e recusa de recibo alheio, agenda/histórico/cancelados/intersecção/dia inteiro e HEAD. |
| P09 | `calendar-management`: presencial, online e híbrido por formulário, seis recusas de âmbito/URL/datas, continuidade de v1 durante revisão, publicação de v2, cancelamento e arquivo. |
| P10 | `private-http-negative`: 53 recusas HTTP 404 vazias, private/no-store e sem Location. Inclui anónimo, métodos, IDs, arrays, excesso de pesquisa e filtros inválidos. Nonce REST e identidade da fixture A comprovados. |
| P11 | `native-channels-final`: dois controlos próprios, administrador e editor WordPress, autenticados por nonce REST e users/me. Por controlo: quatro recusas de negócio, quatro recusas de editor CPT, 11 pedidos REST e seis canais nativos sem conteúdo privado. Inclui pages, types, posts, revisions, search, renderer, feeds, sitemap e oEmbed. As duas contas de controlo foram removidas com verificação da propriedade. |
| P12 | `visual-touch-targets-green` e `cms-responsive-targets`: 16 combinações do front end e 12 do CMS, em 320/390/768/1280 px. Todos os alvos próprios medidos >=44 px, inputs >=16 px, h1 único e sem overflow ou erros de JavaScript. Fontes locais e logótipo carregados. Menu por teclado, Escape e devolução de foco aprovados. Texto CSS a 200% com 34 px efectivos e sem overflow. |
| P13 | `long-title-four-widths`: título de 160 caracteres criado/publicado pelo CMS e integralmente apresentado nas quatro larguras, sem overflow e com h1 único. |
| P14 | `account-suspended`, `account-reactivated-old-session` e `account-new-login`: suspensão auditada fecha listas/avisos, reactivação não recupera a sessão antiga e novo login recupera as duas listas. Só a nova fixture A foi alterada. |
| P15 | `gate-communications` e `gate-calendar`: desligar cada flag fecha apenas o seu módulo, preserva o outro, o início e os destinos anteriores. Configuração restaurada em finally com hash exacto. Quatro cenários `schema-*-missing/incompatible` provam o mesmo fecho e independência, com restauro em finally. `final-matrix-positive` confirmou depois leitura e gestão conforme os seis perfis. |
| P16 | `readonly-get-head`: 12 pedidos GET/HEAD reais. Comparação anterior/posterior de todas as linhas das 49 tabelas, incluindo opções e sessões, integralmente idêntica. |
| P17 | Repetição do instalador normal antes e depois das edições legítimas: tabelas, opções, sessões, configuração e ficheiros integralmente idênticos. Snapshot final preserva todas as linhas de negócio, contas e sessões anteriores. Os 83 ficheiros anteriores conservam hash, tamanho e mtime. |
| P18 | Auditoria normal: comunicados com 28 objectos/32 versões/93 revisões auditadas/150 entregas e calendário com 10 objectos/12 versões/26 revisões auditadas/58 entregas. Históricos dos agregados válidos, cardinalidades coerentes e zero chaves de entrega duplicadas. |

As integrações P02/P03/P04 foram executadas em série no PHP/WordPress/MariaDB locais com prefixos aleatórios próprios. A CI executa os mesmos scripts num ambiente efémero, primeiro com o gate negativo development e depois com local explícito. PHPUnit isolado ou development não foram apresentados como aceitação WordPress.

Os pares de cores do CSS foram calculados separadamente. Texto normal 14,61:1, metadados 5,75:1 no front end e 5,43:1 no CMS, ligações 11,02:1, botão 11,86:1, hover 6,44:1, erro 9,61:1 e foco 5,98:1. Todos superam os mínimos usados de 4,5:1 para texto e 3:1 para foco. Esta medição da paleta não é uma auditoria de todos os componentes WordPress anteriores.

## Matriz dos 32 critérios

O estado Passou refere-se ao âmbito local fictício. A distribuição entre política pura, SQL exclusivo e browser está explicitada. Não significa que todos os casos de fronteira tenham sido repetidos em cada camada.

| ID | Estado | Prova e resultado |
| --- | --- | --- |
| CA-F112-01 | Passou | P02/P05/P08/P10: A lê A e institucional, B não lê A e A não lê B. Recusa vazia sem título/contagem. |
| CA-F112-02 | Passou | P02/P06: publicação directa A auditada, 303, apenas audiência A. LAB-B e circuito institucional indevidos recusados. |
| CA-F112-03 | Passou | NoticePolicyTest e P02/P07/P11: fila e audiência completas, segundo actor, Pending oculto. Autoria, papel Editorial ou administração técnica não substituem competência. |
| CA-F112-04 | Passou | NoticeModelTest e P07: devolução/rejeição com motivo privado e nova versão antes de reenviar. O leitor não recebe a decisão privada. |
| CA-F112-05 | Passou | P02/P07: publicação anterior conserva acesso durante revisão. Aprovação substitui versão/audiência e retira acesso/aviso anterior. |
| CA-F112-06 | Passou | NoticeModelTest/NoticeSelectionTest e P02: validade exclusiva no relógio injectado, recusa de intervalo inválido e avisos expirados ausentes. P06/P08 mostram validade no CMS/lista/detalhe. P16 confirma leitura sem escrita. |
| CA-F112-07 | Passou | Modelos, P02/P07: arquivo fecha detalhe/avisos e conserva histórico. Transição posterior recusada. |
| CA-F112-08 | Passou | CalendarPolicyTest e P03/P09: três modalidades, gestão A separada da institucional, campos e âmbito verificados. |
| CA-F112-09 | Passou | CalendarModelTest/F112InputTest e P09: datas gregorianas, ano bissexto, UTC estrito, fim exclusivo e separação de horários/dias inteiros. POST negativo real. |
| CA-F112-10 | Passou | AgendaSelectionTest e P03/P08: passado só no histórico, em curso/futuro na agenda, ordem e limite temporal testados com relógio fixo. |
| CA-F112-11 | Passou | AgendaSelectionTest/CalendarModelTest e P08: intersecção mensal, exclusão do fim na fronteira e dia inteiro sem dia adicional. |
| CA-F112-12 | Passou | P03/P09: v1 mantém-se durante o rascunho, publicação troca para v2 e entrega auditada. |
| CA-F112-13 | Passou | P03/P08/P09: cancelamento refere a última publicação, oculta motivo/rascunho, sai da agenda normal e arquivo fecha detalhe. Reactivação recusada no modelo/SQL. |
| CA-F112-14 | Passou | P02/P03 revalidam suspensão/revogação sob lock. Políticas/contexto verificam expiração e unidade inactiva. P14 prova suspensão e geração de sessão no browser. Unidades normais anteriores não foram alteradas para repetir cenários já exclusivos. |
| CA-F112-15 | Passou | NoticePolicyTest/CalendarPolicyTest/CalendarModelTest e P02/P03/P06/P09/P10: relações fixadas no servidor, campos extra recusados e âmbito/autoria preservados. Outro actor competente da mesma unidade é coberto no modelo/política, sem atribuir novos grants normais para o ensaio. |
| CA-F112-16 | Passou | Modelos e P02/P03/P06: CAS anterior recusa, no-op não contorna a revisão, PHP_INT_MAX não transborda e browser conserva só campos permitidos. |
| CA-F112-17 | Passou | P02/P03: dois processos reais sobrepostos, duas esperas de lock observadas, um commit e um conflito. Sem substituir por chamadas sequenciais. |
| CA-F112-18 | Passou | F112HttpTest/F112InputTest e P06/P10/P16: nonce/actor/forma/método recusados, POST 303 e GET/HEAD sem escrita integral. No-op coberto em SQL. |
| CA-F112-19 | Passou | P02/P03: triggers injectam falha na auditoria e no último destinatário. Agregado, versões, auditoria e entregas revertem em conjunto. |
| CA-F112-20 | Passou | P02/P03/P06/P18: CAS/deduplicação, reenvio antigo recusado, entregas únicas e novas versões não suprimidas. |
| CA-F112-21 | Passou | P02/P03/P07/P08: perda de audiência, expiração, substituição e arquivo retiram os avisos inacessíveis. Elegibilidade e ausência de entrega retroactiva testadas no repositório. |
| CA-F112-22 | Passou | P02/P03/P08: recibo do titular, repetição idempotente conserva primeiro instante no SQL e POST real repetido 303. Recibo de outro actor 404. |
| CA-F112-23 | Passou | P10/P11: controlos autenticados positivos, nonces REST válidos e canais nativos sem conteúdo de negócio. As âncoras conservam apenas marcadores privados. |
| CA-F112-24 | Passou | Selecções/F112InputTest e P08/P10: 20/1 elegíveis, sem duplicados, página superior vazia e filtros inválidos sem alargamento da consulta. |
| CA-F112-25 | Passou | F112ValuesTest/F112RenderingTest, modelos e P06/P08/P13: limites, UTF-8/controlos/separadores/normalização e HTML literal. Título máximo real nas quatro larguras. |
| CA-F112-26 | Passou | CalendarModelTest/F112InputTest e P08/P09: apenas HTTPS admitido, userinfo/controlos/esquemas inválidos recusados e ligação literal com noopener/noreferrer. O endereço fictício de reunião não foi aberto. |
| CA-F112-27 | Passou | P04/P17: CLI/admin/local, preflight, colisões, recursos parciais, conclusão final e repetição antes/depois de edição. Sem reparação ou reposição de acessos. |
| CA-F112-28 | Passou | F112GateTest/P04/P15: booleanos estritos, development negativo, flags independentes e schemas ausentes/incompatíveis no browser. Restauro e positivos finais aprovados. |
| CA-F112-29 | Passou | P05/P08/P10/P15 e matriz final: destinos e guardas independentes, filtros/regresso conservados e navegação anterior preservada. Dados People anteriores intactos em P17. |
| CA-F112-30 | Passou no âmbito medido | P06/P12/P13: responsividade, rótulos, foco, menu, alvos, texto ampliado e marca. Zoom nativo do browser não foi executado e continua separado da prova CSS. O segundo PC permanece na Issue #28. |
| CA-F112-31 | Passou com deltas previstos reconciliados | P17 e reconciliação abaixo. Sem alterações a linhas anteriores de negócio, contas, sessões ou ficheiros. Configuração anterior byte a byte mais duas flags. Não se afirma identidade total após criar recursos novos. |
| CA-F112-32 | Passou quanto à separação de prova | Revisão independente executada, CI exacta deve acompanhar a PR e limites institucionais conservados. Não declara PEN-07, IIS/MySQL, F1/F2 ou produção concluídos. |

## Preservação e deltas reconciliados

Depois da aceitação existem 49 tabelas e 89 ficheiros privados. Aos 83 ficheiros anteriores juntaram-se apenas as seis passwords das novas fixtures. Na comparação por chave primária, nenhuma linha anterior foi alterada ou removida fora de wp_options. As alterações próprias correspondem às seis contas, 13 grants, 27 eventos de autorização, 40 posts nativos novos, metadados/sessões dessas contas e às tabelas F1-12. Os controlos temporários foram removidos.

wp_options tem uma opção nova de conclusão F1-12. Seis entradas de cache nativa de traduções/padrões de tema foram regeneradas, com novos IDs de linha, mantendo os nomes. O contador user_count corresponde exactamente às seis novas contas. Foi reconstruído o hash anterior desse contador com a contagem do baseline. O cron foi reconciliado por registos privados cujos hashes coincidem com os snapshots: os mesmos 12 hooks e apenas wp_privacy_delete_old_export_files avançou 3600 segundos, mantendo intervalo, frequência e zero argumentos. É a tarefa horária nativa de limpeza de exportações. Nenhuma opção desapareceu por nome. Entre o snapshot imediatamente após instalação e o final da aceitação, todas as opções são exactamente iguais.

O comparador bruto assinalou estes deltas em vez de os excluir ou substituir o baseline. Não foi apresentado como comparação idêntica. A reconciliação identificou os deltas previstos antes de aceitar a parcela. As diferenças de cron/cache observadas antes da primeira mutação SQL F1-12 permanecem no registo histórico, sem atribuição retroactiva baseada apenas em hashes. Não foram normalizadas ou apagadas.

As 12 leituras finais P16 passaram também uma comparação integral idêntica das 49 tabelas, incluindo opções e sessões. As duas repetições normais do instalador passaram a mesma exigência de ausência de alterações. Os 22 originais reais conservam o circuito administrativo anterior e os seus ficheiros/metadados. Nenhum destes originais foi usado como comunicado ou evento.

## Revisão, falhas retidas e limites

Um revisor independente leu o intervalo completo de 77 ficheiros, especificação e critérios. Não encontrou Critical. Foram corrigidos os três Important e as duas omissões reclassificadas Important, com regressões RED/GREEN. Não houve um segundo ciclo de revisão. A aceitação descobriu depois áreas de clique menores que o requisito, corrigidas no CSS e verificadas no browser.

A CI inicial falhou no estado fresh_site de um WordPress novo. O diagnóstico identificou apenas essa opção. As páginas marcadoras preservam-na através de filtro temporário exclusivo, removido em finally. A execução 38905186102 comprovou o cenário novo e passou. A execução 38907064548 passou todas as seis jobs no SHA bc60cf5 e a execução 38910761278 passou no SHA 65a01e2. A CI do SHA final entregue deve constar da PR.

No SHA 0531b54, a execução de push 38911711359 passou e a execução de PR 38911718646 encontrou uma falha real na verificação de password durante a repetição do instalador. O alfabeto extra de wp_generate_password inclui espaço. wp_hash_password remove espaços nas extremidades e wp_check_password compara o valor integral. O diagnóstico nativo de 48 caracteres reproduziu false para os bytes originais e true para o valor aparado. A regressão força esse caso apenas nas fixtures exclusivas e falhou antes da correcção. Novos segredos usam agora 24 bytes criptográficos em 48 caracteres hexadecimais. Não se alteraram passwords, ficheiros, concessões ou sessões das seis contas normais existentes. Não foi usada uma reexecução sem correcção para ocultar esta falha.

Foram retidas as falhas do próprio ensaio: medição de foco antes de terminar o carregamento, expectativa de 404 para uma recusa de negócio recuperável com 200 e expectativa de saved permanecer na URL depois da limpeza nativa do WordPress. O teste de pesquisa global também confundiu o termo devolvido no formulário com um título protegido. O diagnóstico confirmou ausência de títulos numerados e do corpo protegido. A expressão passou a procurar os conteúdos completos das fixtures. Nenhuma política foi alargada para fazer passar estes ensaios.

A auditoria e os presets são fictícios. Conservação, audiências e competências institucionais continuam dependentes de PEN-07 e decisões RH/DCSI. Não há homologação IIS/MySQL 8.4, ensaio de produção ou validação de escala com funcionários reais. Os locks sobre o elenco completo são aceites apenas para as seis fixtures. Sem email, push, ICS, Google Calendar, recorrência, anexos ou lembretes nesta parcela.

O inventário Workspace contém nomes apresentados e emails seleccionados apenas em armazenamento privado. Não corresponde a pré-cadastro ou atribuição de funções. As Issues #28/#29, branches e laboratório da Albertina permanecem separados. A integração Git exige CI verde no SHA entregue e não representa publicação ou activação institucional.
