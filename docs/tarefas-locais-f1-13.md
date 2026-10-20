# Tarefas locais F1-13

Estado corrente: transporte, instalador explícito, vistas privadas, navegação e fonte de pesquisa implementados. Lista própria/equipa, atribuição, progresso, prazos, reatribuição, cancelamento, comentários, histórico e avisos foram exercitados no WordPress separado. A [QA local](qa-tarefas-locais-f1-13.md) distingue as provas efectivas, as falhas corrigidas e os limites. MariaDB passou 90 verificações de persistência e 31 do instalador com preservação de 127 tabelas. A CI da parcela SQL em db96050 passou. A composição final, a pesquisa no browser e a activação da demonstração aguardam o fecho da correcção da PR #53. Os registos seguintes descrevem as parcelas históricas.

20 de outubro de 2026. Branch feat/tarefas-locais-f1-13. Base main e081efb, com a correcção transaccional da PR #51 já integrada e CI aprovada. Execução directa do plano 2026-10-20-tarefas-locais-f1-13.md, sem novas atribuições à colaboradora.

## Parcela de domínio concluída

TaskContent valida título, descrição, prioridade e prazo UTC. Recusa HTML, Unicode inválido, controlos, datas impossíveis, offsets e representações persistidas não canónicas. Reutiliza os validadores existentes sem abrir módulos anteriores.

Task é imutável. Cria, inicia, conclui, cancela, reatribui, altera prazo e acrescenta comentários. Cada alteração exige revisão exacta e relógio não regressivo. No-op mantém revisão e não acrescenta evento. Não há reabertura nem edição livre de estado. Depois de concluir/cancelar só permanecem comentários autorizados. Atraso não altera a tarefa por leitura.

O histórico explica o estado corrente por reconstrução de todas as transições. Autor, unidade e identidade do pedido inicial não são alteráveis. A unidade e o token ficam vinculados aos eventos. Os comentários têm revisão, autor, instante e corpo canónico separados. Um hash na auditoria verifica a consistência do corpo com o evento da mesma revisão. Não é uma assinatura nem impede alteração coordenada da base de dados.

As comparações de reconstrução são estritas, incluindo tokens hexadecimais que parecem números em notação científica. A ordem de chaves de objectos não é relevante, mas tipos escalares e ordem de listas são preservados.

TaskPolicy usa ReadOwnTask/UpdateOwnTask para o destinatário corrente e ReadUnitTask/AssignUnitTask para o gestor no âmbito. UnitManager não herda Employee. Administrador técnico não recebe competência. A prova corrente conserva sessão, geração, estado da conta, concessões e unidade activa. A elegibilidade offline de um destinatário não serve para autenticar uma rota.

TaskProjection permite histórico de estado, destinatário e prazo, retirando motivos privados da vista do destinatário. Só a leitura de gestão os recebe. Token de criação, hash e payload bruto da auditoria nunca entram na projecção. O antigo destinatário perde leitura após reatribuição. A autoria por si só não recupera acesso.

## Escolhas exclusivas do laboratório

Reabertura recusada. Cancelamento, reatribuição e alteração do prazo exigem gestor competente e autor da tarefa. Outro gestor com leitura da unidade pode ler e comentar, mas não recebe esses poderes pendentes de decisão. Comentários são de texto, sem anexos. Avisos serão apresentados no módulo e datas em UTC. Estas escolhas não aprovam regras institucionais ou concedem acesso a uma pessoa real.

## Verificação da parcela

As regressões foram executadas antes da implementação. Um ensaio adicional reproduziu aceitação indevida de uma unidade/token inicial alterado e outro reproduziu aceitação de corpo de comentário adulterado. Ambos foram corrigidos. A suite focada final passou 17 testes e 114 asserções. PHPCS passou os cinco ficheiros de domínio e três ficheiros de testes. A revisão independente final não deixou achados accionáveis.

Não há SQL, tabelas, contas, páginas, rotas, menus ou instalação F1-13 nesta parcela. Por isso não são alegados testes WordPress ou Playwright de tarefas. A QA Playwright da PR #51 pertence à regressão dos módulos anteriores. Os 36 critérios F1-13 completos continuam Por executar. O código puro apenas fornece parte da prova futura.

## Próxima execução

Seguem transporte privado, selecção/paginação, instalador explícito, vistas, navegação e aceitação completa. Revalidar destinatários a partir do estado resultante antes do COMMIT, fechar as ligações criadas e preservar as contas e dados anteriores. Não activar produção, renovar pré-cadastros reais ou publicar documentos administrativos.

## Autoridade e persistência locais

Configuration exige flag Tasks própria estritamente booleana e ambiente efectivo local. LocalRoster fecha três contas marcadas, Subscriber e emails example.invalid. CurrentActor valida os quatro contratos de autorização antes dos snapshots bloqueados, lê a sessão nativa na mesma ligação e confirma geração, estado, concessões e unidades activas. O administrador técnico não integra o elenco. Não reutiliza uma autoridade F1-12 com nome de módulo inventado.

TaskSchema instala explicitamente cinco tabelas InnoDB, com colunas, índices, collations e versão exactos. Recusa fundação parcial ou incompatível, sem reparar por leitura. TaskRepository mantém tarefa, CAS, comentário, auditoria e aviso na mesma transacção. O token de criação é único e o seu reenvio não duplica linhas. As leituras SQL limitam o âmbito antes de carregar payload e histórico. A reconstrução verifica cabeçalho, cadeia e comentário. Recibos idempotentes conservam o primeiro instante e não mudam a revisão da tarefa.

O preset avisa apenas o destinatário corrente elegível. Há no máximo um destinatário por alteração nesta configuração. O ensaio de falha no último aviso prova rollback dessa entrega única e não uma sequência com vários destinatários. Reatribuir retira ao destinatário anterior tanto a leitura como os avisos antigos. Texto acentuado e caracteres UTF-8 de quatro bytes usam escrita WordPress com validação por coluna.

Validação: suite completa com 920 testes PHP, 4163 asserções e quatro omissões Windows preexistentes. PHPCS dos 19 ficheiros de domínio, persistência e ensaio sem erros ou avisos. MariaDB local passou 77 verificações, incluindo sessão nativa, suspensão, reactivação com sessão antiga recusada, esquema parcial de autorização, idempotência, CAS/no-op, reatribuição, rollback de comentário/auditoria/aviso e dois processos realmente bloqueados. Depois de libertados, um confirma e o outro recusa a revisão, sem evento ou aviso duplicado. Contas e usermeta anteriores ficaram iguais e o prefixo exclusivo foi removido. O snapshot deste ensaio não demonstra igualdade de todas as restantes tabelas anteriores. Essa prova global fica no fecho integrado.

A revisão independente encontrou a falta de validação do schema de autorização. A regressão reproduziu a leitura indevida sem marcador antes da correcção. A versão corrigida foi novamente revista sem achados pendentes. A CI acrescenta job própria para os gates negativos development e os ensaios foundation/storage em contentor local efémero. O resultado remoto dessa job permanece pendente até ao push desta parcela. Ainda não há rotas, instalação funcional ou QA Playwright F1-13.
