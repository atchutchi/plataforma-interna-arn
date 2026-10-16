# F1-12: especificação de comunicados e calendário local

16 de outubro de 2026. Especificação de referência escolhida expressamente pelo responsável para implementar F1-12 no laboratório, a partir da proposta da PR #26. Os presets locais não aprovam regras editoriais institucionais. O estado de execução pertence ao plano e à QA, não aos cenários previstos neste documento.

## Pedido, base e fronteira de trabalho

O responsável autorizou preparar a especificação e os critérios de aceitação da F1-12 numa branch separada, com dados fictícios, actualizar o README e fazer commit e push. O objectivo é deixar a próxima fase preparada enquanto Albertina executa ALB-F1-07-QA. A preparação usa a branch `docs/f1-12-spec-acceptance`, baseada em main `aebf4ced459b4677a163a2d5f95eb3f5c916dbd7`. Não incorpora nem altera a branch F1-07 ou a branch da colaboradora.

Na consulta de 16 de outubro, a [PR #25](https://github.com/atchutchi/plataforma-interna-arn/pull/25) continuava aberta em rascunho no SHA `6f30d76022332c557826b4d4fbd304046df8383b`. A PR #24 já integra a parcela local F1-11. Estes estados são uma fotografia, não uma garantia sobre o momento em que outro agente iniciar trabalho.

Esta entrega contém apenas documentos. Não cria contas, concessões, CPTs, páginas, tabelas, flags ou notificações. Não inicia WordPress/SQL, instala dependências, usa Google, envia email, importa pessoas ou publica anexos. A implementação começa só depois de receber, rever e integrar F1-07 e rever esta especificação. O plano de implementação será preparado nessa altura, contra o main efectivo. Não é necessário alterar ALB-F1-07-QA para executar esta preparação.

## Requisitos confirmados e propostas

| Origem | Requisito ou limite |
| --- | --- |
| README, US-M05 | O responsável autorizado publica comunicados dentro do âmbito permitido. Identificar título, conteúdo e destinatários e notificar os destinatários. |
| README, US-F07 | Consultar eventos futuros com título, data, hora e local ou ligação. A privacidade depende da audiência. |
| README, secções 5.3 e 7 | Audiências explícitas, CPTs previstos arn_comunicado e arn_evento e criação por responsáveis designados. A administração técnica não atribui competência de negócio. |
| README, secção 11.2 | A publicação directa para a própria equipa e a revisão institucional são propostas a confirmar em PEN-07. Não são regras institucionais aprovadas. |
| F1-03/04/05/06 | Reutilizar estado/sessão, concessões, auditoria, unidades e tema. A unidade não concede acesso por si só. |
| Pedido actual | Preparação documental paralela. Só exemplos fictícios. Sem interferência com a colaboradora. |

Os circuitos, campos, limites e presets definidos abaixo são uma proposta fechada para um laboratório futuro. Uma aceitação técnica destes presets não resolve PEN-07 ou designa responsáveis institucionais. As restantes pendências estão na última secção.

## Abordagem e integração

Usar apenas posts e editor nativos simplificaria o formulário, mas abriria superfícies de leitura e escrita que precisam das mesmas guardas de objecto, audiência e versão. Transformar notícias em comunicados reduziria parte do código, mas confundiria a aprovação de notícias com a publicação operacional e a validade de comunicados. A proposta é manter agregados separados para comunicação e calendário, com CPTs privados como âncoras, armazenamento próprio e adaptadores WordPress.

Os destinos técnicos propostos são `src/Communications/`, `src/Calendar/` e vistas do tema `portal/`. Ainda não existem por esta especificação. Reutilizar padrões e contratos de autorização e ligação SQL, sem alterar `News\Article`, as tabelas de notícias ou introduzir um framework transversal. `News\Audience` demonstra as categorias all/units/people, mas não é uma API comum já aprovada. Antes de extrair código partilhado, provar equivalência por regressões. O laboratório F1-12 limita os presets a toda a população fictícia ou uma unidade fictícia, sem listas arbitrárias de pessoas.

`Authorization\Action::PublishTeamNotice` já existe. O catálogo actual não tem operações para eventos nem para decisão institucional de comunicados. Ter acesso editorial a notícias não autoriza estes novos actos. O plano futuro terá de acrescentar e rever operações explícitas, validar o codec e a persistência de concessões e manter as anteriores inalteradas. Não reutilizar PublishNews ou manage_options como atalho. Notificações F1-04 são hoje um catálogo de acessos e F1-11 tem avisos próprios de notícias. Não alegar que existe um barramento universal pronto a receber F1-12.

## Modelo e validação propostos

Cada agregado tem ID positivo, tipo, autor, unidade proprietária ou âmbito institucional explícito, revisão inteira positiva, estado, versões imutáveis e histórico de transições. Cada transição tem actor, operação, versão, revisão, instante UTC e justificação. A versão publicada é uma referência separada da versão em edição. Não eliminar versões ou auditoria ao retirar uma publicação.

| Entidade | Campos e regras de laboratório |
| --- | --- |
| Versão de comunicado | Título de 1 a 160 caracteres, corpo de 1 a 10000, audiência, circuito team-direct ou institutional-review e valid_until opcional. Sem início agendado nesta parcela. A publicação é imediata. |
| Versão de evento | Título de 1 a 160 caracteres, descrição de 1 a 10000, organizador identificado no servidor, audiência, modalidade presencial/online/híbrida, local até 240 caracteres, ligação até 2048 e intervalo temporal. |
| Validade | valid_until tem de ser posterior ao instante da publicação. O conteúdo está vigente em published_at <= agora < valid_until. Sem limite final, mantém-se até arquivo. Expiração é calculada na leitura e não escreve por GET. |
| Evento com hora | start_at e end_at em UTC, formato canónico YYYY-MM-DDTHH:MM:SSZ, com end_at > start_at. Mostrar sempre a indicação UTC no laboratório. Não aceitar offset ou hora local que seja convertida silenciosamente. |
| Evento de dia inteiro | all_day true, start_date e end_date_exclusive em YYYY-MM-DD. O fim é exclusivo e posterior ao início. A interface mostra a última data incluída com clareza. Não aceitar simultaneamente os campos do evento com hora. |
| Local e ligação | Presencial exige local. Online exige ligação HTTPS absoluta. Híbrida exige ambos. Recusar credenciais na URL, esquemas activos, controlos e URLs acima do limite. Não efectuar pedidos HTTP de pré-visualização à ligação. |
| Texto | UTF-8 válido, normalização de espaços exteriores e CRLF para LF no corpo, sem NUL ou CR isolado. Título/local numa linha, incluindo recusa de U+2028/U+2029. Contar caracteres com mbstring. Corpo é texto simples com parágrafos escapados, sem HTML activo. |

Não há anexos, fotografias, recorrências, convites externos, Google Calendar, ICS, confirmações de presença, reservas de salas, notificações antecipadas ou calendário de férias nesta primeira parcela proposta. Não converter os documentos reais existentes em comunicados ou eventos.

## Circuito de comunicados

No preset team-direct, o responsável com concessão explícita para LAB-A cria um rascunho próprio. Pode publicar, substituir a versão publicada e arquivar apenas comunicados da sua unidade e com audiência exactamente LAB-A. A autoria não lhe permite escrever na unidade B ou escolher toda a população. Uma versão nova em rascunho não substitui a publicação anterior até confirmar a publicação com CAS e audiência revalidada.

Neste preset, a competência de gestão cobre os comunicados da unidade concedida, inclusive de outro autor fictício da mesma unidade. O autor original mantém-se imutável e o novo interveniente fica na transição. A edição institucional continua reservada ao autor designado, com decisão pelo segundo actor competente. Não transportar a regra de substituição da equipa para a autoria institucional.

No preset institutional-review, o autor fictício expressamente designado cria e submete. Os estados editoriais são Draft, Pending, Rejected, Published e Archived. Submissão fixa uma versão e bloqueia a edição dessa versão. Um segundo actor com competência explícita para a fila e a audiência pode aprovar/publicar, rejeitar com motivo ou devolver para nova versão. A auto-aprovação é recusada neste preset. Uma devolução ou rejeição exige versão nova antes de voltar a submeter. Decidir não altera título, corpo, validade ou audiência.

Durante a revisão de uma publicação, a versão publicada anterior continua acessível à sua audiência enquanto estiver vigente. O leitor não vê rascunho, estado editorial, motivo privado ou contador de revisões posteriores. A aprovação troca a referência publicada atomicamente. A troca pode alterar a audiência e deve retirar o acesso dos leitores que deixaram de pertencer à versão corrente. Arquivo retira imediatamente a consulta de destinatários, sem apagar o histórico. Archived não admite novas escritas nesta parcela.

O autor consulta o próprio histórico com competência actual. O revisor consulta apenas as submissões da fila autorizada. Motivos de decisão pertencem a estas superfícies e não aparecem no conteúdo do destinatário. Nenhum autor ou revisor tem excepção para consultar a rota publicada fora da audiência corrente.

## Circuito de eventos e semântica do calendário

Estados propostos: Draft, Published, Cancelled e Archived. O gestor designado cria e edita no seu âmbito, publica uma versão, prepara alterações e confirma a substituição com CAS. A edição em rascunho não altera o calendário corrente. O evento de unidade só pode ter a audiência dessa unidade. O evento institucional exige concessão institucional própria. Esta designação e a publicação directa de eventos são presets de laboratório, sujeitos a validação antes do uso real.

Cancelar exige motivo auditado e só funciona numa publicação corrente. O evento sai da lista futura normal. O detalhe, para a audiência ainda autorizada da última publicação, mostra que foi cancelado e os dados públicos dessa versão, sem motivo interno. Arquivar retira o detalhe aos destinatários e conserva histórico para o gestor competente. Não há reactivação de Cancelled ou Archived nesta parcela. Um novo evento usa novo ID.

A página Calendário começa por uma agenda futura em lista. Inclui eventos Published com end_at > agora, incluindo os que já começaram. Ordena por início UTC e depois por ID. A vista por mês é uma lista agrupada por dia, sem exigir arrastar ou usar uma grelha. Para uma janela [início, fim), existe intersecção quando event_start < fim e event_end > início. Um evento que termina exactamente no início do mês não pertence ao mês. Um evento que atravessa dias aparece uma vez na agenda com o intervalo completo. Eventos de dia inteiro seguem o mesmo intervalo, usando meia-noite UTC apenas no preset local.

O filtro de histórico permite consultar publicações passadas autorizadas. Cancelados aparecem apenas no filtro explícito de cancelados ou no detalhe autorizado. Filtros inválidos, datas impossíveis, intervalos invertidos ou parâmetros estruturados não alargam a consulta. O relógio é injectável nos testes. Não usar a data real do PC como fonte instável de expectativas.

Gestão de eventos cobre os objectos do âmbito concedido, inclusive de outro organizador fictício desse âmbito. O organizador original não muda ao rever, cancelar ou arquivar. Novo interveniente e justificação ficam auditados. Não há herança de competência sobre unidades descendentes.

## Autorização, sessões e transporte

| Actor fictício | Leitura e operações previstas |
| --- | --- |
| Colaborador A ou B | Lê publicações e avisos vigentes da população fictícia ou da sua unidade, com conta Active, sessão da revisão corrente e concessão de leitura. Não escreve. |
| Responsável A | Concessão própria para PublishTeamNotice em LAB-A. Se também for gestor de eventos A, recebe a nova concessão específica. Não herda leitura de Employee sem atribuição separada. |
| Autor institucional | Cria/submete comunicados institucionais próprios por nova operação explícita. Não aprova a própria submissão. |
| Revisor institucional | Decide apenas a fila e as audiências explicitamente concedidas. Uma concessão editorial de notícias anterior não basta. |
| Gestor institucional de eventos | Gere apenas eventos institucionais por nova operação explícita. Não recebe permissões sobre notícias ou comunicados. |
| Administrador técnico, editor de notícias ou conta sem concessão | Sem excepção de negócio por papel nativo, endereço de email ou nome. manage_options serve apenas a instalação CLI. |

A pertença a LAB-A/B vem do snapshot fictício validado no servidor. Não vem de campos enviados, nomes reais ou do papel WordPress. Autorizar unidade proprietária, operação, estado e audiência completa. Concessões parciais não se somam para obter um âmbito maior. Uma pessoa em mais de uma unidade continua a precisar da concessão completa de gestão aplicável. Revalidar suspensão, revogação, validade temporal, unidade activa e geração da sessão antes da leitura ou escrita e sob os locks da mutação.

GET e HEAD autorizados devolvem a mesma projecção reduzida, HEAD sem corpo. GET/HEAD nunca escrevem. Escritas só em POST com allowlist, nonce ligado ao actor/operação/objecto e revisão CAS. Decisões usam versão submetida e token opaco que não exponha revisões privadas. Recusa ou ausência de detalhe, página de gestão ou operação usa 404 vazio com private/no-store antes de eventual redireccionamento para login. Erros de validação recuperáveis de um actor autorizado conservam apenas os campos próprios permitidos. Sucesso usa 303 para impedir reenvio acidental. O recibo de leitura é uma escrita separada e autorizada.

Os CPTs arn_comunicado/arn_evento, blocos e índices não expõem conteúdo em REST, pesquisa nativa, feeds, sitemap, oEmbed, editor, revisões, metadados ou block-renderer. Conteúdo nativo guarda só marcadores, não contactos, corpos ou ligações privadas. Não existem ficheiros públicos ou endpoints JSON paralelos nesta parcela. Contagens, filtros e paginação aplicam a autorização antes de contar ou limitar. Resultados vazios não revelam quantos conteúdos foram recusados.

## Persistência, auditoria e avisos

A proposta usa tabelas InnoDB separadas por módulo para agregados, versões, transições, destinatários de avisos e marcador de schema, sem actualizar a versão do schema News. O plano futuro deve fixar nomes/colunas/índices depois de rever esta especificação. A mutação operacional, versão, auditoria e avisos confirmam-se na mesma ligação IdentityWpdb dedicada, sem reconnect, ou são revertidos em conjunto. Locks têm ordem documentada e consistente: contas/concessões por ID, snapshot de unidades, agregado, avisos. O CAS é validado dentro da transacção e não só no formulário.

Um sucesso incrementa exactamente uma revisão e acrescenta a transição correspondente. Um no-op não escreve, não muda a revisão e não duplica avisos. Nonce errado, CAS antigo, audiência indevida ou falha tardia de auditoria/aviso não deixam alterações parciais. Dois escritores concorrentes com o mesmo CAS produzem um sucesso e uma recusa. O histórico inclui motivos sem os copiar para mensagens públicas ou logs de payloads.

Publicar comunicado ou evento e publicar uma nova versão gera um aviso por destinatário actualmente elegível, resolvido no servidor. No cancelamento do evento, usar a última audiência publicada e apenas destinatários ainda elegíveis. A chave única proposta é (tipo de objecto, ID, revisão da transição, destinatário, tipo de aviso). Reenvio do mesmo POST não gera nova transição. Entrar numa unidade depois da publicação pode dar leitura, mas não cria aviso retroactivo nesta parcela. Sem email, SMS, push ou tarefa de lembretes.

A lista e o recibo de avisos reavaliam o acesso actual à publicação correspondente. Avisos de versão substituída, comunicado expirado/arquivado, evento arquivado ou audiência perdida não revelam título, excerto ou ligação. Quando um evento é cancelado, o aviso de publicação anterior deixa de ser projectado e o novo aviso de cancelamento usa o detalhe cancelado permitido. Recibo repetido conserva o primeiro instante e não altera o agregado, a autorização ou a sessão. Ler não equivale a aceitar ou confirmar presença.

## Instalação futura, fixtures e isolamento

Propor duas flags booleanas independentes ARN_ALLOW_LOCAL_COMMUNICATIONS e ARN_ALLOW_LOCAL_CALENDAR, além de ambiente efectivo local, ARN_ALLOW_FICTIONAL_LOCAL_ACCESS, ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE e ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE. Ainda não estão implementadas. Módulo sem gate ou com schema incompleto recusa antes de consultar dados. Desligar uma flag não desliga os módulos anteriores nem o outro módulo F1-12.

A instalação futura é CLI administradora explícita. Não instalar por GET, activação de plugin, renderização ou chamada REST. Confirmar todas as dependências antes de criar recursos. Preservar concessões anteriores. Não reparar schemas parciais, reutilizar fixtures da F1-07 ou repor contas suspensas/revogadas para o instalador passar. Repetição exacta não altera tabelas, sessões, posts, opções, ficheiros ou edições legítimas. DDL não é apresentado como transaccional. Uma falha parcial mantém a rota fechada e exige diagnóstico explícito.

| Fixture proposta | Função no cenário, sem IDs reais |
| --- | --- |
| f112.colaborador.a e f112.colaborador.b | Leitores de LAB-A e LAB-B. |
| f112.responsavel.a | Publicador operacional e gestor de eventos A com duas concessões distintas e leitura separada. |
| f112.autor.institucional | Autor institucional com leitura atribuída separadamente. |
| f112.revisor.institucional | Revisor institucional diferente do autor, sem competência herdada de notícias. |
| f112.gestor.eventos | Gestor institucional de eventos, sem competência de comunicado. |

Emails exclusivamente @example.invalid, Subscriber e marca persistente de fixture. IDs são resolvidos pelo instalador, nunca codificados por posição da lista de pessoal. Passwords aleatórias e configuração ficam fora do Git e do document root. A raiz privada continua validada mesmo sem anexos. A unidade B tem de estar activa. Inspeccionar a revisão real do próprio PC, sem presumir a revisão 5 de Atchutchi. Reactivação necessária segue o serviço auditado existente depois de F1-07 integrado, sem SQL directo ou reinicialização.

As integrações positivas futuras usam prefixo exclusivo e nomes de contas/slugs derivados dele, limpando apenas os recursos próprios em finally. No ambiente development, um teste de recusa não conta como prova positiva. Baseline normal, contas/grants/sessões, posts/opções e ficheiros privados são preservados. Os 22 originais continuam só na revisão administrativa do PC que os possui. O laboratório da Albertina não é alterado nem copiado.

## Páginas, navegação e critérios de interface

Propor duas colecções nativas, Comunicados e Calendário, com detalhe autorizado e regresso que conserve os filtros. A gestão CMS apresenta apenas as operações concedidas, com estado, audiência, versão e confirmação da acção. O menu não substitui a guarda da rota. A navegação existente, incluindo os destinos People quando integrados, mantém-se. O painel F1-14 poderá consumir projecções autorizadas futuras, mas a sua implementação não pertence a esta parcela.

Comunicados usam linhas com título, âmbito, data e validade. Calendário começa por agenda e filtro de mês. Estados sem dados distinguem ausência de resultados de erro de validação, sem revelar conteúdos recusados. A origem fictícia está visível no laboratório. Nenhum botão representa uma função ainda inexistente. A gestão da audiência não apresenta pessoas reais.

As listas têm 20 resultados por página. Página é um inteiro positivo canónico, sem overflow. Pesquisa tem no máximo 100 caracteres, mês segue YYYY-MM e os filtros de unidade/estado pertencem a listas fechadas do servidor. Uma página além do total devolve zero itens sem inventar conteúdo ou ajustar silenciosamente o filtro. Não carregar toda a população ou todos os eventos no browser.

Preservar DESIGN.md e PRODUCT.md, Manrope/Source Sans 3 locais, paleta, logótipo e favicon. Os critérios de Impeccable e UI UX Pro Max são aplicados à clareza e consistência, sem gerar outro sistema visual ou trocar WordPress por outra stack. Um h1, labels persistentes, erros associados ao campo e resumo focável, teclado, Escape, foco visível e controlo de datas utilizável sem rato. Não impor calendário visual como única entrada. Alvos de 44 px, inputs de pelo menos 16 px, texto legível e sem dependência exclusiva da cor.

Aceitação visual futura em 320/390/768/1280 px, sem overflow, com nomes/títulos longos e ampliação real. Medir os valores efectivos de foco e controlos, distinguindo tokens nominais, quantização do browser e simulação CSS de zoom nativo. Registar consola, limitações de tecnologia assistiva e resultados reais. Não declarar certificação por unitários ou screenshots.

## Aceitação, pendências e próximo marco

A [matriz de aceitação F1-12](../../criterios-aceitacao-f1-12.md) identifica cenários, actores, resultados e provas. Todos estão por executar para F1-12. Esta especificação não transforma a evidência F1-11/F1-07 em evidência do novo módulo.

| Decisão por validar | Preset local proposto | Responsável institucional e bloqueio |
| --- | --- | --- |
| PEN-07, comunicados de equipa/institucionais | Equipa directa no próprio âmbito, institucional com segundo revisor e sem auto-aprovação. | Coordenação/editorial validam circuitos, substitutos e públicos antes da activação real. |
| F0-02/05 e PEN-01/05, eventos | Gestor de unidade e gestor institucional explicitamente designados, publicação directa. | Coordenação e donos do processo validam competências, organizadores e fonte das pertenças. |
| PEN-07/08, alteração, validade e conservação | Sem publicação agendada, validade opcional, cancelamento/arquivo sem eliminação. | Editorial/DCSI validam prazos, cancelamento, conservação e recuperação. |
| Configuração temporal | UTC e fim exclusivo para dia inteiro. | Responsável do produto valida fuso de apresentação e regras de eventos antes do uso institucional. |
| PEN-12, canais e lembretes | Só avisos internos imediatos, sem lembretes. | DCSI e donos do processo decidem canais adicionais. |

Continuidade actual: F1-07 já está integrada pelas PRs #27/#25 e o main recebido é fffef58. O responsável autorizou implementar esta proposta com fixtures, auditoria, Playwright, commits e push, preservando as tarefas #28/#29 da Albertina. O plano está em docs/superpowers/plans/2026-10-16-f112.md. Os parágrafos iniciais sobre a preparação paralela conservam a origem histórica da PR #26. Homologação institucional, IIS/MySQL, segurança/restauro e disponibilização continuam nos marcos próprios do README.
