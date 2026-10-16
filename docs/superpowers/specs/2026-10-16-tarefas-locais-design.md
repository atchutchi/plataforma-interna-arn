# F1-13: especificação de tarefas locais

16 de outubro de 2026. Estado: proposta técnica e funcional para revisão. Não é implementação, activação, aprovação institucional nem código. Não cria schema, contas, concessões, páginas, flags ou dados.

## Pedido, base e fronteira

Issue [#29](https://github.com/atchutchi/plataforma-interna-arn/issues/29). Branch `docs/albertina-f1-13-tarefas`, a partir de main `fffef58323636d59985a073e6b03bfc8e3c40e69` (PR #30). A PR #25 já integrou F1-07. A [proposta F1-12](https://github.com/atchutchi/plataforma-interna-arn/pull/26) é só referência documental; as suas classes e tabelas não existem. Esta entrega não edita essa PR nem README/AGENTS.

Fontes confirmadas: README US-F04, US-F05, US-M02, US-M03, US-S01, US-S03, US-S04, US-ARN-08 e US-ARN-10; AGENTS; DESIGN.md; PRODUCT.md; `Authorization\Action`, `AuthorizationPolicy`, `Role`, persistência F1-03, sessões, unidades F1-05, notificações F1-04 e ficheiros privados F1-08. Superpowers esteve indisponível. Impeccable e UI UX Pro Max orientam a superfície existente, sem substituir tema, fontes ou marca.

## Requisitos confirmados e propostas

| Origem | Confirmado no catálogo actual | Proposta desta parcela |
| --- | --- | --- |
| US-F04 | Consultar título, descrição, prioridade, prazo e estado; filtrar por estado; só tarefas acessíveis | Páginas Minhas tarefas e detalhe próprio |
| US-F05 | Destinatário actualiza estado; servidor valida relação e transição; histórico visível ao responsável autorizado | UpdateOwnTask/Own; responsável lê via ReadUnitTask |
| US-M02 | Criar e atribuir no âmbito; notificar; ver no painel | AssignUnitTask/Units; aviso interno, sem email |
| US-M03 | Acompanhar por funcionário e estado; prazos e atrasos; detalhe autorizado | Tarefas da equipa, LAB-A apenas no elenco |
| US-ARN-08 | Responsável principal, mudanças de prazo/atribuição, reatribuir após transferência/saída; comentários/anexos seguem a tarefa; grupos transversais F3 | Presets abaixo. Anexos binários adiados. Grupos F3 fora de âmbito |
| US-ARN-10 / US-S01 | Aviso só após sucesso; sem duplicados; lida ≠ aceitação | Extensão explícita do catálogo F1-04, hoje limitado a eventos de acesso |
| US-S03 | Sessão, revogação, revisão | Reutilizar WordPressLocalAccountSessions |
| US-S04 / US-ARN-11 | Adaptar ecrãs, teclado, rótulos | Mesma identidade visual; 320/390/768/1280 |
| AuthorizationPolicy | Employee: ReadOwnTask, UpdateOwnTask com `owner_id === actor`. UnitManager: AssignUnitTask, ReadUnitTask, sem herdar o pacote Employee | Não acrescentar acções nesta parcela, salvo a extensão de notificações. Qualquer acção nova é proposta explícita, nunca concessão automática |
| F1-04 | Só eventos de conta/concessão para Ana Teste | Novos tipos de aviso de tarefa, destinatário = titular da tarefa no momento do commit |

Não inferir hierarquia, poderes ou pessoas da lista real do README. `manage_options` não concede competência de tarefas. TechnicalAdministrator tem catálogo vazio.

## Elenco fictício fechado

Contas novas, Subscriber marcadas, emails `@example.invalid`. Não reutilizar `f107.*`, Ana Teste, contas editoriais ou a lista institucional.

| Login proposto | Nome apresentado | Unidade | Concessões propostas |
| --- | --- | --- | --- |
| `f113.alfa.teste` | Alfa Tarefa | LAB-A | Employee/Own |
| `f113.beta.teste` | Beta Tarefa | LAB-B | Employee/Own |
| `f113.responsavel.a.teste` | Responsável Tarefa A | LAB-A | Employee/Own e UnitManager/Units(LAB-A) |

O responsável recebe Employee em separado porque UnitManager não herda leitura/escrita próprias. Um controlo negativo técnico (`lab_admin`) e um editor de notícias sem estas concessões recusam todas as rotas. LAB-A e LAB-B activas. Não criar pessoas a partir de unidades.

## Campos e limites propostos

Cada tarefa tem ID positivo, unidade proprietária, autor imutável, responsável principal (destinatário actual), revisão positiva, `updated_at` Unix UTC e estado. Versões de conteúdo não se eliminam.

| Campo | Regra de laboratório |
| --- | --- |
| Título | 1 a 160 caracteres, uma linha, UTF-8, sem NUL/U+2028/U+2029 |
| Descrição | 0 a 8000, texto simples, CRLF→LF uma vez, recusar CR isolado e `<>` |
| Prioridade | enumeração fechada `low`, `normal`, `high` |
| Prazo | ausente ou instante canónico `YYYY-MM-DDTHH:MM:SSZ`. Sem offset local. Fuso institucional por confirmar; o exemplo usa UTC |
| Estado | `open`, `in_progress`, `completed`, `cancelled` |
| Unidade | LAB-A ou LAB-B, fixada no servidor na criação |
| Autor | user_id do actor que cria, imutável |
| Responsável principal | user_id de um membro Active da mesma unidade, com Employee/Own |

Contar caracteres com mbstring. Não interpretar HTML. Escapar na apresentação é operação distinta.

## Estados, transições e competência

| Transição | Actor proposto | Resultado |
| --- | --- | --- |
| criar + atribuir | UnitManager com AssignUnitTask na unidade | Tarefa `open`, responsável = alvo, evento `created` |
| `open` → `in_progress` | Destinatário actual com UpdateOwnTask | Um evento |
| `in_progress` → `completed` | Destinatário actual | Conclusão; sem reabrir automaticamente |
| `open`/`in_progress` → `cancelled` | Responsável de unidade com ReadUnitTask+AssignUnitTask, motivo obrigatório | Destinatário deixa de actualizar |
| alterar prazo | Mesmo responsável de unidade | Evento `due_changed`; destinatário notificado |
| reatribuir | Mesmo responsável de unidade, alvo Active na unidade | Ver secção seguinte |
| `completed` → `open` (reabertura) | **Proposta**, não regra confirmada | Se recusada institucionalmente, omitir na implementação |

Transições inválidas, actor errado ou CAS desactualizado recusam sem escrita. No-op (mesmos valores) não incrementa revisão nem avisos.

## Atribuição, leitura e perda de acesso

Proposta: a atribuição **transfere** ReadOwnTask/UpdateOwnTask para o novo responsável principal. O anterior perde consulta em Minhas tarefas e o detalhe próprio na geração corrente. O autor **não** recupera leitura só por ser autor. O responsável de unidade continua a ver o objecto via ReadUnitTask no âmbito, incluindo histórico. Não somar concessões parciais nem inferir chefia.

Reatribuição após conta suspensa, concessão revogada/expirada ou unidade inactiva: o destinatário actual deixa de ler/escrever na revalidação. Reactivar a conta não recupera a sessão antiga. Um novo login recupera só as permissões ainda existentes. Continuidade: o gestor da unidade reatribui a outro membro Active da mesma unidade. Transferência institucional e grupos entre unidades ficam em F3/RH, fora desta parcela.

## Persistência, CAS, auditoria e concorrência

Proposta de três tabelas próprias InnoDB (`arn_tasks`, `arn_tasks_audit`, `arn_tasks_schema`), instalação CLI administradora explícita, IdentityWpdb dedicada, sem reconnect. Criação, transição, reatribuição, prazo, evento de auditoria e avisos na **mesma transacção**. Rollback se a auditoria ou o último aviso falhar. Auditoria append-only: actor, alvo, revisão, instante UTC, operação, campos alterados, hashes canónicos, sem payloads de descrição nos logs.

CAS pela revisão exacta. `PHP_INT_MAX` recusa. Dois processos reais com barreira e lock, um commit e uma recusa, não duas chamadas sequenciais. Idempotência do instalador e de reenvios POST. O motor SQL do segundo PC já mostrou que `@@session.in_transaction` não existe no MySQL 8.0; a implementação futura não deve depender de variáveis só da MariaDB.

## Notificações

F1-04 gera avisos só a partir da auditoria de acessos de Ana Teste. Esta parcela **propõe** tipos novos (`task_assigned`, `task_reassigned`, `task_state_changed`, `task_due_changed`), destinatário = responsável principal **depois** do commit, uma entrega por chave transição/destinatário/tipo. Recibo de leitura F1-04: não equivale a aceitação da tarefa nem altera revisão. Destinatário retirado deixa de ver o aviso. Sem email, Calendar, Google ou canais externos. Deduplicação não apaga avisos de uma nova transição legítima.

## Datas, filtros e contagens

Relógio injectável nos testes; exemplo `2030-01-15T10:00:00Z`. Atraso: `due_at` presente e `now > due_at`. Prazo em falta não conta como atraso. GET não escreve expiração. Filtros: estado, responsável (só na vista de equipa), `q` até 120 caracteres, `arn_tasks_page` canónico. Autorização **antes** de contar, ordenar (prazo nulo no fim, depois ID) e paginar de 20. Página vazia acima do total. Recusar arrays, overflow e HTML no filtro antes da fonte. Sem calendário de férias nem métricas de produtividade.

## Comentários e anexos (US-ARN-08)

Parcela mínima proposta: comentário de texto simples até 2000 caracteres, append-only, mesma autorização da tarefa, sem edição/apagar. Anexos binários **adiados**: dependem de PrivateFiles, admissão fileinfo, antimalware e media nativa ainda pendentes. Não inventar uploads públicos. A lacuna fica rastreada a US-ARN-08 até uma parcela posterior explícita.

## Transporte HTTP/REST

Páginas `minhas-tarefas` e `tarefas-da-equipa`, bloco dinâmico, conteúdo guardado só o marcador. GET autorizado: `Cache-Control: private, no-store`. Recusa, ausência, método errado, filtro inválido, ID não canónico: 404 vazio indistinto **antes** do login. POST: nonce, operação fechada, CAS, sem actor/unidade/estado/alvo adulterados do browser. Campos extra recusam. Sucesso 303. REST, search, feeds, oEmbed, revisões, meta e block-renderer não transportam corpo de tarefas, inclusive com fixture autenticada e nonce REST válido. Ficheiros privados, se existirem numa parcela futura, nunca por URL estática.

## Superfície

Reutilizar DESIGN.md: fundo #f4f7f8, azul #0b3a5b, Manrope/Source Sans 3 locais, linhas e não cartões. Minhas tarefas e Tarefas da equipa com h1 único, estados vazios com acção de limpar filtros, erros `role=alert` associados ao rótulo, controlos ≥44 px, inputs 16 px, foco visível. UI UX Pro Max: não usar placeholder como único rótulo; manter foco não tapado. Ensaiar 320/390/768/1280, teclado, Escape no menu, zoom nativo **separado** de CSS a 200%. Sem novo template.

## Instalação e testes planeados

Flag proposta `ARN_ALLOW_LOCAL_TASKS`, ausente até revisão e baseline. Exige local e as flags de acesso fictício, autorização e unidades. CLI administradora após F1-03/F1-05 íntegros e LAB-A/B activas. Recusar parcial. Repetir instalador inclusive após edição. Development: prova negativa. Positivo: prefixo exclusivo `arn_tsk_` e WordPress/Playwright real. Preservar contas, concessões, sessões, People e originais. Falha injectada na auditoria ou no último aviso reverte o agregado.

A implementação só começa depois de o responsável rever esta especificação e o plano futuro, e depois de F1-12 segundo o README. Propor não equivale a aprovar.
