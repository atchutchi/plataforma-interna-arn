# Tarefas locais F1-13

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

Implementar Configuration, LocalRoster e CurrentActor próprios. Depois, cinco tabelas InnoDB, TaskRepository com CAS/idempotência e auditoria/avisos na mesma transacção. Seguem transporte privado, instalador explícito, vistas, navegação e aceitação completa. Revalidar destinatários a partir do estado resultante antes do COMMIT, fechar as ligações criadas e preservar as contas e dados anteriores. Não activar produção, renovar pré-cadastros reais ou publicar documentos administrativos.
