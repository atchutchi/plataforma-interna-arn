# Plano de implementação das tarefas locais F1-13

Para execução directa com superpowers:executing-plans. Revisão independente no fecho. O responsável pediu desenvolver autonomamente o que falta, sem nova dependência da colaboradora. A execução fica exclusivamente no laboratório e não aprova políticas institucionais.

Objectivo: permitir atribuir, consultar, acompanhar e actualizar tarefas com autoridade corrente, histórico, comentários e avisos atómicos.

Arquitectura: pacote Tasks com domínio imutável, política explícita, autoridade própria por ligação, cinco tabelas InnoDB e transporte WordPress privado. Reutilizar F112/ActorContext como valor de prova de sessão e F112/PlainText/Input::utc como validadores. Não instanciar F112/CurrentActor com um módulo fictício. Preservar o tema e os restantes módulos.

Tecnologias: PHP 8.4, WordPress 7.1.3, IdentityWpdb corrigida na PR #51, MariaDB 11.8 local e matriz SQL MySQL 8.0.44/8.4. Branch feat/tarefas-locais-f1-13, inicialmente dependente da PR #51 em 00aef66. A implementação não activa código na demonstração enquanto a base ou a parcela tiver falhas.

Especificação: docs/superpowers/specs/2026-10-16-tarefas-locais-design.md. Critérios: docs/criterios-aceitacao-f1-13.md, CA-F113-01 a 36. A conciliação já foi revista como proposta. As escolhas seguintes são configurações do laboratório autorizadas pela continuação autónoma, sem se converterem em regras institucionais.

## Regras da primeira parcela

Elenco fechado f113.alfa.teste, f113.beta.teste e f113.responsavel.a.teste, emails example.invalid e marcador de fixture. LAB-A/B apenas. Administrador técnico e editor sem concessões recusados. Nenhuma conta Workspace entra neste elenco.

Estados open, in_progress, completed e cancelled. Operações create, start, complete, cancel, reassign, change_due e comment. Reopen fica recusada. Na configuração conservadora, cancelamento, reatribuição e alteração de prazo exigem gestor competente da unidade e autoria da tarefa. Comentários de texto disponíveis a quem lê a tarefa corrente. Anexos binários recusados. Avisos só na superfície de tarefas. Datas apresentadas com indicação UTC. Estas escolhas mantêm explícitas as decisões institucionais pendentes da especificação.

Título 1 a 160 caracteres, descrição 0 a 8000, comentário 1 a 2000, motivo até 1000. Texto simples UTF-8 sem HTML. CRLF normalizado apenas em campos multilinha. Datas canónicas UTC, sem normalizar datas impossíveis. Prioridades low/normal/high. Revisão positiva exacta, sem overflow. Atraso só quando now > due_at e a tarefa ainda está aberta ou em curso.

## Ficheiros e responsabilidades

Tasks/TaskContent.php valida título, descrição, prioridade e prazo. TaskState.php fecha estados. Task.php representa o agregado e as transições. TaskPolicy.php decide leitura, operações e elegibilidade do destinatário com as acções existentes. TaskProjection.php separa a vista do destinatário dos motivos privados da auditoria.

Tasks/Configuration.php, LocalRoster.php e CurrentActor.php validam ambiente, flag própria, elenco, sessão nativa, concessões e unidades bloqueadas por ordem. TaskSchema.php instala explicitamente arn_tasks, arn_tasks_comments, arn_tasks_audit, arn_tasks_notifications e arn_tasks_schema. TaskRepository.php controla a transacção, CAS, idempotência, comentários, auditoria, destinatários e recibos. A ligação é fechada pelo adaptador que a criou.

Tasks/Input.php, Controller.php, Pages.php, Portal.php e ManagementPage.php implementam GET/HEAD privados, POST fechado, nonce, 303, erro de formulário e 404 indistinto. LocalInstaller.php e InstallationState.php fazem instalação CLI explícita e repetível. Navigation.php e Bootstrap.php ligam as páginas ao plugin, Serviços, Início e pesquisa através das guardas reais, sem atalhos de autorização. Vistas em tema/portal e CSS existente ou extensão focada.

## Foco da revisão

Autor que já não é destinatário não recupera leitura própria. Reatribuição e suspensão retiram também avisos antigos.

Reenvio de create usa create_request_id emitido pelo servidor e nonce ligado ao actor. Tokens diferentes criam pedidos distintos. Token de outro actor não reutiliza a tarefa.

Falha no último aviso ou na auditoria reverte tarefa, comentário e revisão. Não decidir destinatários depois do COMMIT.

CAS desactualizado não se transforma em 404 de um objecto já autorizado. No-op conserva revisão e não produz aviso.

Uma página, filtro, REST nonce válido, contagem ou resultado de pesquisa não pode revelar uma tarefa de outro âmbito. GET/HEAD não escrevem dados de negócio.

## Tarefa 1: domínio, política e projecção

- [x] Criar regressões inicialmente falhadas para valores, UTC, transições, CAS/no-op, reatribuição, história e motivo privado.
- [x] Implementar os cinco ficheiros de domínio e política, sem WordPress, SQL ou novas permissões.
- [x] Exercitar dono actual, autor antigo, gestor sem Employee, outra unidade, suspensão, revogação, expiração e unidade inactiva.
- [x] Confirmar 17 testes/114 asserções, PHPCS e revisão independente desta parcela. Entregar em commit sem activar rotas.

## Tarefa 2: autoridade e persistência

- [x] Testar configuração booleana, elenco fechado, sessão nativa e esquema parcial antes da implementação.
- [x] Implementar autoridade e cinco tabelas, com versão e índices exactos, sem migração em leitura ou activação.
- [x] Implementar create idempotente, CAS e mutações com destinatários do estado resultante dentro da transacção.
- [x] Provar rollback de auditoria/último aviso, reenvio de create, recibo idempotente e concorrência real entre processos. Preset de destinatário único, sem alegar entrega parcial de vários avisos.
- [ ] Confirmar igualdade das tabelas anteriores e remover só os recursos exclusivos do ensaio.

## Tarefa 3: percursos completos WordPress

- [ ] Acrescentar testes de forma, nonce, autorizações, filtros e matriz HTTP antes dos handlers.
- [ ] Criar lista própria, equipa, detalhe, criação, actualizações, comentários e avisos, com erros acessíveis.
- [ ] Instalar explicitamente as três fixtures e páginas num prefixo novo de laboratório. Não reutilizar contas existentes.
- [ ] Ligar menus, Serviços e pesquisa às autoridades do módulo. Conservar os percursos People e F1-12.
- [ ] Verificar REST, feed, search, oEmbed, meta e block-renderer com autenticação positiva antes das recusas.

## Tarefa 4: apresentação, aceitação e entrega

- [ ] Usar Impeccable e UI UX Pro Max na extensão do tema existente, preservando logo e fontes locais.
- [ ] Executar Playwright com o elenco próprio, 320/390/768/1280, teclado, filtros, formulários, duas origens CAS e estado vazio. Distinguir zoom nativo de CSS a 200%.
- [ ] Agrupar PHP, PHPCS, Node, construção e integrações no fecho. Preencher os 36 critérios com prova efectiva ou limite explícito.
- [ ] Obter revisão independente final e corrigir bloqueadores. Guardar configuração, cookies, capturas, dados e logs fora do Git.
- [ ] Actualizar README/AGENTS, fazer push e abrir PR. Só integrar com CI verde no head final.
- [ ] Actualizar a demonstração fictícia apenas depois da revisão e continuar as lacunas F1-14/15/16/17 possíveis localmente.
