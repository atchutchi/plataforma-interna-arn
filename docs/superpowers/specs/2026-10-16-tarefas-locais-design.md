# F1-13: especificação de tarefas locais

19 de outubro de 2026. Estado: proposta técnica e funcional conciliada com os contratos F1-12 já integrados em main. Não é implementação, activação, aprovação institucional nem código. Não cria schema, contas, concessões, páginas, flags ou dados.

## Pedido, base e fronteira

Issue principal [#37](https://github.com/atchutchi/plataforma-interna-arn/issues/37) (ALB-F113-02). Issue relacionada [#29](https://github.com/atchutchi/plataforma-interna-arn/issues/29) (ALB-F1-13-SPEC). Branch desta conciliação: `docs/albertina-f1-13-conciliacao`, a partir de `origin/main` `2c77a46a3721b560e75696e036c051b546d49ba8`.

A primeira proposta (Issue #29, branch `docs/albertina-f1-13-tarefas`) partiu de main `fffef58323636d59985a073e6b03bfc8e3c40e69`. Nessa base histórica a F1-12 ainda não estava integrada: a PR #26 era só referência documental e as classes `Communications`, `Calendar` e `F112` não existiam. Essa afirmação deixa de ser verdadeira no main actual. A F1-12 está integrada; a proposta F1-13 reutiliza os contratos reais actuais desses pacotes e não inventa um padrão paralelo sem o declarar.

Fontes confirmadas: README US-F04, US-F05, US-M02, US-M03, US-S01, US-S03, US-S04, US-ARN-08 e US-ARN-10; AGENTS; DESIGN.md; PRODUCT.md; `Authorization\Action`, `AuthorizationPolicy`, `Role`; persistência F1-03; sessões; unidades F1-05; notificações F1-04; ficheiros privados F1-08; comunicados/calendário F1-12 (`F112`, `Communications`, `Calendar`) e o catálogo F1-04 (`Notifications\Notification::EVENTS`). Superpowers esteve indisponível na primeira passagem. Impeccable e UI UX Pro Max orientam a superfície existente, sem substituir tema, fontes ou marca. README e AGENTS não são editados nesta conciliação.

## Requisitos confirmados e propostas

| Origem | Confirmado no catálogo actual | Proposta desta parcela |
| --- | --- | --- |
| US-F04 | Consultar título, descrição, prioridade, prazo e estado; filtrar por estado; só tarefas acessíveis | Páginas Minhas tarefas e detalhe próprio |
| US-F05 | Destinatário actualiza estado; servidor valida relação e transição; histórico visível ao responsável autorizado | Operações fechadas `start` e `complete` com UpdateOwnTask/Own |
| US-M02 | Criar e atribuir no âmbito; notificar; ver no painel | Operação `create` com AssignUnitTask/Units; aviso interno, sem email |
| US-M03 | Acompanhar por funcionário e estado; prazos e atrasos; detalhe autorizado | Tarefas da equipa, LAB-A apenas no elenco |
| US-ARN-08 | Responsável principal, mudanças de prazo/atribuição, reatribuir após transferência/saída; comentários/anexos seguem a tarefa; grupos transversais F3 | Presets abaixo. Comentário de texto proposto na primeira parcela. Anexos binários adiados. Grupos F3 fora de âmbito |
| US-ARN-10 / US-S01 | Aviso só após sucesso; sem duplicados; lida ≠ aceitação | Avisos do módulo, no mesmo padrão F1-12, não uma extensão silenciosa do catálogo F1-04 |
| US-S03 | Sessão, revogação, revisão | Reutilizar WordPressLocalAccountSessions |
| US-S04 / US-ARN-11 | Adaptar ecrãs, teclado, rótulos | Mesma identidade visual; 320/390/768/1280 |
| AuthorizationPolicy | Employee: ReadOwnTask, UpdateOwnTask com `owner_id === actor`. UnitManager: AssignUnitTask, ReadUnitTask, sem herdar o pacote Employee | Não acrescentar acções nesta parcela. Qualquer acção nova é proposta explícita, nunca concessão automática |
| F1-04 | Só eventos de conta/concessão: `state_changed`, `grant_created`, `grant_replaced`, `grant_revoked` | Não projectar avisos de tarefa nesse catálogo. Ver secção de notificações |
| F1-12 | POST com operação fechada, nonce, CAS, 303 de sucesso, 404 indistinto, avisos na tabela do módulo, destinatários calculados na transacção | Reutilizar este contrato HTTP e transaccional |

Não inferir hierarquia, poderes ou pessoas da lista real do README. `manage_options` não concede competência de tarefas. TechnicalAdministrator tem catálogo vazio.

## Elenco fictício fechado

Contas novas, Subscriber marcadas, emails `@example.invalid`. Não reutilizar `f107.*`, `f112.*`, Ana Teste, contas editoriais ou a lista institucional.

| Login proposto | Nome apresentado | Unidade | Concessões propostas |
| --- | --- | --- | --- |
| `f113.alfa.teste` | Alfa Tarefa | LAB-A | Employee/Own |
| `f113.beta.teste` | Beta Tarefa | LAB-B | Employee/Own |
| `f113.responsavel.a.teste` | Responsável Tarefa A | LAB-A | Employee/Own e UnitManager/Units(LAB-A) |

O responsável recebe Employee em separado porque UnitManager não herda leitura/escrita próprias. Um controlo negativo técnico (`lab_admin`) e um editor de notícias sem estas concessões recusam todas as rotas. LAB-A e LAB-B activas. Não criar pessoas a partir de unidades.

## Campos e limites propostos

Cada tarefa tem ID positivo, unidade proprietária, autor imutável, responsável principal (destinatário actual), revisão positiva, `updated_at` Unix UTC e estado. Versões de conteúdo e comentários não se eliminam.

| Campo | Regra de laboratório |
| --- | --- |
| Título | 1 a 160 caracteres, uma linha, UTF-8, sem NUL/U+2028/U+2029 |
| Descrição | 0 a 8000, texto simples, CRLF→LF uma vez, recusar CR isolado e `<>` |
| Prioridade | enumeração fechada `low`, `normal`, `high` |
| Prazo | ausente ou instante canónico `YYYY-MM-DDTHH:MM:SSZ`. Sem offset local. Fuso institucional por confirmar; o exemplo usa UTC |
| Estado | `open`, `in_progress`, `completed`, `cancelled` |
| Unidade | LAB-A ou LAB-B, fixada no servidor na criação |
| Autor | `author_id` do actor autenticado que cria, imutável, nunca enviado pelo browser |
| Responsável principal | `assignee_id` de um membro Active da mesma unidade, com Employee/Own |

Contar caracteres com mbstring. Não interpretar HTML. Escapar na apresentação é operação distinta.

## Estados, transições e competência

| Transição | Operação HTTP | Actor proposto | Resultado |
| --- | --- | --- | --- |
| criar + atribuir | `create` | UnitManager com AssignUnitTask na unidade | Tarefa `open`, responsável = alvo, evento `created` |
| `open` → `in_progress` | `start` | Destinatário actual com UpdateOwnTask | Um evento |
| `in_progress` → `completed` | `complete` | Destinatário actual | Conclusão; sem reabrir automaticamente |
| `open`/`in_progress` → `cancelled` | `cancel` | Responsável de unidade com ReadUnitTask+AssignUnitTask, motivo obrigatório | Destinatário deixa de actualizar |
| alterar prazo | `change_due` | Mesmo responsável de unidade | Evento `due_changed`; destinatário notificado |
| reatribuir | `reassign` | Mesmo responsável de unidade, alvo Active na unidade | Ver secção seguinte |
| comentário de texto | `comment` | Quem já lê a tarefa na geração corrente | Append-only; incrementa a revisão do agregado |
| `completed` → `open` | `reopen` | **Proposta**, não regra confirmada | Se recusada institucionalmente, omitir na implementação |

Transições inválidas, actor errado ou CAS desactualizado recusam sem escrita. No-op (mesmos valores canónicos) não incrementa revisão nem avisos.

## Atribuição, leitura e perda de acesso

Proposta: a atribuição **transfere** ReadOwnTask/UpdateOwnTask para o novo responsável principal. O anterior perde consulta em Minhas tarefas e o detalhe próprio na geração corrente. O autor **não** recupera leitura só por ser autor. O responsável de unidade continua a ver o objecto via ReadUnitTask no âmbito, incluindo histórico e comentários. Não somar concessões parciais nem inferir chefia.

Reatribuição após conta suspensa, concessão revogada/expirada ou unidade inactiva: o destinatário actual deixa de ler/escrever na revalidação. Reactivar a conta não recupera a sessão antiga. Um novo login recupera só as permissões ainda existentes. Continuidade: o gestor da unidade reatribui a outro membro Active da mesma unidade. Transferência institucional e grupos entre unidades ficam em F3/RH, fora desta parcela.

## Persistência, CAS, auditoria e concorrência

Proposta de cinco tabelas próprias InnoDB, instalação CLI administradora explícita, IdentityWpdb dedicada, sem reconnect e sem `@@session.in_transaction` / `@@in_transaction` / PROCESS / Performance Schema.

| Tabela proposta | Função |
| --- | --- |
| `arn_tasks` | Agregado corrente: campos de negócio, `assignee_id`, `revision`, `updated_at`, `create_request_id` único |
| `arn_tasks_comments` | Comentários append-only, ligados à tarefa. Equivalente funcional de `versions` em F1-12, não um quarto agregado silencioso |
| `arn_tasks_audit` | Cadeia append-only da revisão do agregado |
| `arn_tasks_notifications` | Avisos e recibos do módulo, no mesmo espírito de `arn_comm_notifications` / `arn_calendar_notifications` |
| `arn_tasks_schema` | Marcador de schema/instalação, no mesmo espírito F1-12 |

**Diferença face a F1-12, não escondida:** `Communications\NoticeRepository` e `Calendar\EventRepository` ainda interrogam `@@session.in_transaction` antes de `START TRANSACTION`. Essa sonda não existe no MySQL 8.0.44. A implementação futura de F1-13 não copia essa sonda. Usa a API portátil da ligação dedicada (`inTransaction`, guarda por savepoint) depois da correcção transaccional integrada. Não declarar F1-13 segura com base num head ainda em revisão.

CAS pela revisão exacta (`expected_revision`). `PHP_INT_MAX` recusa. Dois processos reais com espera no lock, um commit e uma recusa. Idempotência do instalador e de reenvios POST. Prefixos de ensaio exclusivos, por exemplo `arn_tsk_`.

A identidade de um `create` **não** é `task_id`: o identificador ainda não existe no primeiro POST. O servidor emite `create_request_id` no GET autorizado do formulário (32 hex, 16 bytes criptográficos), liga-o ao nonce (actor/módulo/`create`/token) e grava-o com unicidade em `arn_tasks.create_request_id`. Reenvio do mesmo POST, com o mesmo token: 303 para a tarefa já criada, sem segunda linha, sem segundo aviso, sem nova revisão. Token em falta, repetido noutro actor ou adulterado: 404 vazio antes da fonte. Título, prazo ou alvo canónicos **não** são a chave: dois pedidos com tokens distintos criam duas tarefas se a autorização o permitir.

## Notificações e auditoria

Contrato proposto, alinhado com `NoticeRepository::persist` / `EventRepository::persist` e não com o catálogo F1-04:

1. O estado resultante é calculado **dentro** da transacção, depois de revalidar actor, sessão, concessões, unidade e `expected_revision`.
2. Os destinatários são calculados **a partir desse estado resultante**, ainda dentro da mesma transacção, com o elenco bloqueado (`locked()`), nunca a partir do browser e nunca só depois do `COMMIT`.
3. Auditoria e avisos são persistidos atomicamente com o agregado. Uma falha no último aviso provoca rollback integral: tarefa, comentário, revisão, auditoria e avisos revertem.
4. A apresentação ao utilizador ocorre **depois** do `COMMIT`. Apresentar depois não significa escolher destinatários depois.
5. Recibos ficam na mesma tabela de avisos, coluna `read_at` anulável, como em F1-12. O primeiro instante conserva-se; um `UPDATE` posterior não o substitui. Lida ≠ aceitação da tarefa e não altera a revisão. Não há tabela F1-04 nem `arn_tasks_receipts` separada.
6. Destinatário que perdeu a atribuição deixa de ver o aviso na lista/detalhe.
7. Unicidade de entrega, **depois** de existir `task_id`: `(task_id, revision, recipient_id, kind)`. Esta chave não identifica o `create`. Sem colisão entre tarefas distintas.

Campos propostos de `arn_tasks_notifications`: `id` positivo; `task_id`; `revision`; `recipient_id`; `kind`; `created_at` Unix UTC; `read_at` nulo ou o primeiro instante. PK `id`; único `delivery (task_id, revision, recipient_id, kind)`; leitura `(recipient_id, read_at, id)`.

Tipos internos propostos do módulo: `task_assigned`, `task_reassigned`, `task_state_changed`, `task_due_changed`, `task_commented`. Não entram em `Notifications\Notification::EVENTS`.

**Diferença face a F1-04, não escondida:** F1-04 projecta só eventos de acesso da conta destinatária. F1-12 guarda avisos nas tabelas do próprio módulo e revalida a audiência corrente. F1-13 segue F1-12. O sítio onde o colaborador **vê** os avisos de tarefa (lista F1-04, lista do módulo, ambas) permanece decisão institucional pendente, não regra.

Sem email, Calendar, Google, push ou canais externos.

## Comentários

Preferência técnica desta proposta: tabela separada append-only, ligada à tarefa, **não** um campo JSON no agregado e **não** um payload de log.

**Diferença face a F1-12, não escondida:** comunicados e eventos versionam conteúdo imutável (`versions`). Não têm fluxo de comentários. Tarefas precisam de um histórico de mensagens curto; por isso `arn_tasks_comments` substitui o papel de `versions` e não é um agregado extra escondido. Não guardar o texto só na auditoria.

| Aspecto | Contrato proposto |
| --- | --- |
| Persistência | `arn_tasks_comments`, InnoDB, append-only. Sem `UPDATE`/`DELETE` de linhas de comentário |
| Campos | `id` positivo; `task_id`; `author_id` (actor autenticado, servidor); `body` 1–2000, texto simples, CRLF→LF, recusar CR isolado, `<>`, NUL; `created_at` Unix UTC; `task_revision` = revisão do agregado **depois** deste comentário |
| Índices | PK `id`; único `(task_id, task_revision)`; leitura `(task_id, id)` |
| Ordenação | `id` crescente por tarefa, estável. Sem reordenar |
| Autor | Sempre o actor da sessão revalidada. O browser não envia `author_id` |
| Relação com a tarefa | FK lógica a `arn_tasks.id`. Comentário sem tarefa, ou tarefa inexistente, recusa |
| Relação com a revisão | A criação exige `expected_revision` da tarefa. Sucesso incrementa `arn_tasks.revision`, grava o comentário com essa revisão nova e cria auditoria `commented` |
| Autorização | A mesma da leitura da tarefa na geração corrente (destinatário Own ou gestor Units). Beta não lê. lab_admin sem concessão 404 |
| Auditoria | Um evento `commented` na cadeia da tarefa, mesma transacção |
| Notificação | Um aviso `task_commented` aos destinatários calculados do estado resultante (proposta: responsável principal se o autor for o gestor; gestor da unidade se o autor for o responsável). Sem escolher destinatários depois do commit |
| Append-only | Sem edição, sem apagar, sem substituir corpo. Reenvio com a mesma `expected_revision`: recusa CAS determinística (erro de formulário na página autorizada, sem escrita, sem linha nova). Não é no-op. O primeiro sucesso já consumiu essa revisão; o segundo pedido com o mesmo CAS é conflito, não igualdade canónica |
| Anexos | Fora desta parcela. Upload ou URL estática recusa. Lacuna US-ARN-08 |

A inclusão de comentários na primeira implementação continua **proposta**, não regra aprovada. Se a decisão institucional os adiar, a operação `comment` e a tabela ficam fora do plano executável, sem apagar esta definição.

## Operações HTTP

Páginas propostas `minhas-tarefas` e `tarefas-da-equipa`, bloco dinâmico, conteúdo guardado só o marcador. GET autorizado: `Cache-Control: private, no-store`. HEAD autorizado: 200 sem corpo. GET/HEAD não escrevem. Recusa, ausência, método errado, filtro inválido, ID não canónico: 404 vazio indistinto **antes** do login e **antes** de consultar dados protegidos. REST, search, feeds, oEmbed, revisões, meta e block-renderer não transportam corpo de tarefas.

POST alinhado com `F112\Input` / `F112\ManagementPage` / `F112\Controller`:

- nonce ligado a actor/módulo/operação/objecto; em `create`, também a `create_request_id`;
- `operation` fechada;
- `expected_revision` (CAS), excepto `create`;
- `create_request_id` só em `create`, eco do token emitido no GET autorizado, nunca inventado pelo browser;
- motivo privado quando a operação o exige (`cancel`, e as restantes com auditoria);
- validação de forma e nonce **antes** de ler o agregado.

Operações fechadas: `create`, `start`, `complete`, `cancel`, `reopen`, `reassign`, `change_due`, `comment`.

O browser **não** controla: `actor_id`, `unit_id`, `author_id`, `current_assignee_id` / `assignee_id` corrente, estado arbitrário fora da operação, destinatários das notificações, permissões. Campos extra recusam (404, como `Input::shape` em F1-12). Um estado alvo só existe como efeito da operação (`start` produz `in_progress`); não há editor livre de `state`.

Campos editáveis permitidos por operação:

| Operação | Campos de browser admitidos além de `operation`, nonce, `id`/`expected_revision` |
| --- | --- |
| `create` | título, descrição, prioridade, prazo, alvo de atribuição da mesma unidade, eco de `create_request_id` |
| `start` | motivo |
| `complete` | motivo |
| `cancel` | motivo obrigatório |
| `reopen` | motivo (só se a proposta institucional for aceite) |
| `reassign` | novo responsável da mesma unidade, motivo |
| `change_due` | novo prazo canónico ou ausência, motivo |
| `comment` | `body` |

### Respostas, alinhadas com F1-12

Não se inventa um código HTTP distinto sem conflito declarado. O CMS F1-12 usa 303 no sucesso, 404 vazio quando `decide()` devolve `null`, e permanece na página (sem 404) com `DomainException`/`InvalidArgumentException` para CAS e campos inválidos.

| Resultado | Contrato F1-13 proposto | Origem F1-12 |
| --- | --- | --- |
| Sucesso com escrita | 303 para o detalhe ou lista autorizada, `saved=1`, sem corpo da mutação | `ManagementPage` / `Controller` 303 |
| No-op (valores canónicos iguais, `expected_revision` exacta) | 303 igual ao sucesso; **não** incrementa revisão; **não** cria auditoria nem aviso; `persist` sai cedo como `$before === $after`. Não se aplica a `comment` | `NoticeRepository::persist` |
| Reenvio de `create` com o mesmo `create_request_id` | 303 para a tarefa já persistida; sem segunda linha nem segundo aviso | Identidade do pedido, não `task_id` |
| Reenvio de `comment` com a mesma `expected_revision` | Recusa CAS: erro de formulário, sem escrita, sem linha nova. Determinístico; nunca no-op | `DomainException` |
| CAS desactualizado | Não 404. A página autorizada reapresenta o objecto corrente com erro de formulário (`role=alert`): operação recusada ou registo mudou. Campos próprios permitidos podem regressar escapados. Sem escrita | `DomainException` em `ManagementPage::decide` |
| Operação inválida (transição ou actor competentemente autenticado mas operação fora da tabela) | O mesmo tratamento de CAS: erro de formulário na página já autorizada, sem escrita. Não revelar objectos de outra unidade | `DomainException` |
| Objecto não autorizado, inexistente, método errado, nonce inválido, forma inválida, filtro inválido | 404 vazio, `private, no-store`, sem `Location`, **antes** da fonte protegida | `Controller::refuse` |
| Campos de texto/data inválidos numa página já autorizada | Permanece na página com erros associados ao rótulo; campos permitidos recuperados e escapados | `InvalidArgumentException` |

Portal de consulta (Minhas tarefas / detalhe): não há formulário de correcção para leitura. Recusa de objecto = 404 vazio, igual a ausência.

## Superfície

Reutilizar DESIGN.md: fundo #f4f7f8, azul #0b3a5b, Manrope/Source Sans 3 locais, linhas e não cartões. Minhas tarefas e Tarefas da equipa com h1 único, estados vazios com acção de limpar filtros, erros `role=alert` associados ao rótulo, controlos ≥44 px, inputs 16 px, foco visível. UI UX Pro Max: não usar placeholder como único rótulo; manter foco não tapado. Ensaiar 320/390/768/1280, teclado, Escape no menu, zoom nativo **separado** de CSS a 200%. Sem novo template.

## Datas, filtros e contagens

Relógio injectável nos testes; exemplo `2030-01-15T10:00:00Z`. Atraso: `due_at` presente e `now > due_at`. Prazo em falta não conta como atraso. GET não escreve expiração. Filtros: estado, responsável (só na vista de equipa), `q` até 120 caracteres, `arn_tasks_page` canónico. Autorização **antes** de contar, ordenar (prazo nulo no fim, depois ID) e paginar de 20. Página vazia acima do total. Recusar arrays, overflow e HTML no filtro antes da fonte. Sem calendário de férias nem métricas de produtividade.

## Instalação e testes planeados

Flag proposta `ARN_ALLOW_LOCAL_TASKS`, ausente até revisão e baseline. Exige local e as flags de acesso fictício, autorização e unidades. Não reutilizar o nome de um módulo F1-12 para abrir tarefas. CLI administradora após F1-03/F1-05 íntegros e LAB-A/B activas. Recusar parcial. Repetir instalador inclusive após edição. Development: prova negativa. Positivo: prefixo exclusivo `arn_tsk_` e WordPress/Playwright real. Preservar contas, concessões, sessões, People, F1-12 e originais. Falha injectada na auditoria ou no último aviso reverte o agregado.

A implementação só começa depois de o responsável rever esta especificação conciliada e o plano futuro. Propor não equivale a aprovar.

## Decisões institucionais pendentes

Continuam propostas explícitas, sem valor de regra:

1. Reabertura de `completed` (`reopen`).
2. Poder de cancelamento: o gestor da unidade cancela tarefas de outro autor da mesma unidade?
3. Poder de reatribuição nas mesmas condições.
4. Fuso apresentado aos colaboradores (UTC, Europe/Lisbon ou outro), sem conversão silenciosa.
5. Local onde os avisos de tarefa aparecem (superfície F1-04, lista do módulo, ambas).
6. Inclusão de comentários na primeira implementação.
7. Política futura de anexos (PrivateFiles, antimalware, media nativa).
8. Quem designa o responsável principal além do preset LAB-A (PEN-07 / RH).

Nenhum valor de fixture se converte em aprovação institucional ou em concessão a pessoa real.

## Rastreabilidade proposta

| User story | Regra | Operação | Critério | Prova planeada |
| --- | --- | --- | --- | --- |
| US-F04 | Só o destinatário actual lê Minhas tarefas | GET detalhe | CA-F113-01, CA-F113-12, CA-F113-21 | Política, SQL, Playwright |
| US-F05 | Destinatário avança estado próprio | `start`, `complete` | CA-F113-02, CA-F113-03 | SQL e formulário |
| US-M02 | Gestor cria e atribui no âmbito | `create` | CA-F113-04, CA-F113-17, CA-F113-36 | SQL transaccional e CMS |
| US-M03 | Equipa, filtros, atraso | GET equipa | CA-F113-05, CA-F113-06, CA-F113-07 | Relógio injectado, parse |
| US-ARN-08 | Cancelar, reatribuir, prazo, comentário | `cancel`, `reassign`, `change_due`, `comment` | CA-F113-08, CA-F113-10, CA-F113-23, CA-F113-33 | SQL e browser |
| US-ARN-08 | Reabertura | `reopen` | CA-F113-09 | Política; POST negativo se recusada |
| US-S01 / US-ARN-10 | Destinatários na transacção; rollback do último aviso | todas as mutações | CA-F113-16, CA-F113-18, CA-F113-19, CA-F113-35, CA-F113-36 | Integração SQL |
| US-S03 | Sessão e suspensão | — | CA-F113-11 | Serviço auditado |
| CAS / HTTP F1-12 | 303, no-op, CAS, 404 | POST fechado | CA-F113-13, CA-F113-14, CA-F113-15, CA-F113-34 | HTTP real e duas origens |
| US-ARN-08 | Sem anexos públicos | POST negativo | CA-F113-24 | POST |
| Fundação | Instalação, flags, REST, a11y, preservação | CLI / GET | CA-F113-20, CA-F113-25 a CA-F113-32 | Prefixo exclusivo, Playwright, hashes |
