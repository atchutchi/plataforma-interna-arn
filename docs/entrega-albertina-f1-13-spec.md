# Entrega ALB-F113-02 — conciliação F1-13

19 de outubro de 2026. Issue principal [#37](https://github.com/atchutchi/plataforma-interna-arn/issues/37). Issue relacionada [#29](https://github.com/atchutchi/plataforma-interna-arn/issues/29). Executante: Albertina4264.

## Base recebida

| Item | Valor |
| --- | --- |
| Primeira proposta (#29) | main histórico `fffef58323636d59985a073e6b03bfc8e3c40e69`; branch `docs/albertina-f1-13-tarefas` |
| origin/main desta conciliação | `2c77a46a3721b560e75696e036c051b546d49ba8` |
| PR #25 / F1-07 | Integrada, merge `a1a3fbb` |
| F1-12 | Integrada em main. Pacotes `F112`, `Communications`, `Calendar` existem e são a referência de contrato |
| PR #26 | Proposta histórica; não é o código corrente |
| Branch desta entrega | `docs/albertina-f1-13-conciliacao` |
| README/AGENTS | Não alterados |
| Código | Não alterado |

Na base histórica `fffef583`, F1-12 ainda não estava integrada: a PR #26 era só referência e as classes não existiam. Essa frase era verdadeira então e deixa de o ser no main actual. Esta passagem não apaga essa história; corrige o enquadramento e reutiliza os contratos reais.

A branch antiga `chore/albertina4264-F1-01-fundacao` e o stash local sobre `IdentityWpdb` / MySQL 8 foram preservados e não entram aqui. A validação do segundo PC continua na Issue #28, PR própria, bloqueada até a PR #41 estar em main.

## Ficheiros

1. `docs/superpowers/specs/2026-10-16-tarefas-locais-design.md` — proposta conciliada
2. `docs/criterios-aceitacao-f1-13.md` — CA-F113-01 a CA-F113-35, todos Por executar
3. este relatório

Não há schema, contas, concessões, páginas, flags, dependências nem código de tarefas. Nenhum critério passou: nenhum foi executado.

## Contratos reutilizados de F1-12

- Operação HTTP fechada, nonce por actor/módulo/operação/objecto, CAS por `expected_revision`.
- Sucesso e no-op: 303. No-op não incrementa revisão nem cria auditoria/avisos.
- CAS desactualizado e operação inválida numa página já autorizada: erro de formulário, sem 404, campos próprios recuperáveis.
- Objecto não autorizado, inexistente, método errado, nonce/forma inválidos: 404 vazio `private/no-store` antes da fonte.
- Avisos e recibos nas tabelas do módulo, destinatários calculados na transacção a partir do estado resultante, apresentação depois do commit.
- Instalação CLI explícita, flags booleanas próprias, sem abrir um módulo alheio.

## Diferenças declaradas, não escondidas

1. **F1-12 e `@@session.in_transaction`.** `NoticeRepository` e `EventRepository` ainda usam essa sonda. Não existe no MySQL 8.0.44. F1-13 não a copia. O adaptador futuro usa a API portátil da ligação dedicada.
2. **Avisos.** F1-04 (`Notification::EVENTS`) continua limitado a eventos de acesso. F1-13 não estende esse catálogo em silêncio. Segue as tabelas de aviso do módulo, como F1-12. O sítio de apresentação ao colaborador fica pendente.
3. **Comentários.** F1-12 versiona conteúdo; não tem comentários. F1-13 propõe `arn_tasks_comments` append-only, ligada à tarefa, com `expected_revision`, incremento da revisão do agregado, auditoria e aviso na mesma transacção.

## Decisões propostas (não aprovadas)

- Reutilizar só ReadOwnTask, UpdateOwnTask, AssignUnitTask e ReadUnitTask. UnitManager não herda Employee; o gestor de ensaio recebe Employee/Own em separado.
- Atribuição transfere a leitura/escrita própria ao novo responsável; o anterior perde Minhas tarefas; o autor não ganha leitura por autoria; o gestor conserva ReadUnitTask.
- Estados `open`, `in_progress`, `completed`, `cancelled`. Operações `create`, `start`, `complete`, `cancel`, `reopen`, `reassign`, `change_due`, `comment`.
- Reabertura de `completed` fica proposta explícita, não regra.
- Cancelamento e reatribuição pelo gestor da unidade, no mesmo espírito da gestão de comunicados de equipa, ficam propostas.
- Comentário de texto na primeira parcela; anexos binários adiados (PrivateFiles, antimalware, media nativa). Lacuna US-ARN-08.
- UTC no laboratório; prazo em falta permitido; atraso = `now > due_at`. Sem calendário de férias. Fuso apresentado por confirmar.
- Flag futura `ARN_ALLOW_LOCAL_TASKS` ausente até revisão e baseline. Instalação só CLI.
- Persistência sem `@@session.in_transaction`.

## Skills

- Superpowers: indisponível na primeira passagem; conciliação feita contra o código F1-12 em main
- Impeccable: DESIGN.md e PRODUCT.md
- UI UX Pro Max: rótulos associados, erros anunciáveis, foco visível, alvos 44 px
- Playwright: não executado para F1-13 (só documentação)

## Perguntas institucionais pendentes

1. A reabertura de tarefas concluídas é permitida no laboratório?
2. O gestor da unidade pode cancelar e reatribuir tarefas de outro autor da mesma unidade, como na gestão F1-12 de comunicados de equipa?
3. Quem designa o responsável principal além do preset LAB-A (PEN-07 / RH)?
4. O fuso a apresentar aos colaboradores é UTC, Europe/Lisbon ou outro, sem conversão silenciosa?
5. Comentários na primeira implementação ou só depois dos anexos PrivateFiles?
6. Os avisos de tarefa entram na superfície F1-04 existente, numa lista do módulo, ou em ambas?
7. Qual é a política futura de anexos, depois de antimalware e media nativa?

## Verificação desta passagem documental

- Só os três ficheiros desta entrega.
- IDs CA-F113-01 a 32 conservados, todos Por executar.
- CA-F113-33 a 35 acrescentados para comentário append-only, matriz HTTP F1-12 e destinatários intra-transacção.
- Sem afirmação de implementação, activação ou teste executado.
- Sem alteração a README.md, AGENTS.md ou código.

## Próximos passos

Atchutchi revê esta especificação conciliada e os critérios. Só depois se prepara o plano de implementação. Não fazer merge automático, deploy nem começar código F1-13 nesta atribuição. Não fechar a Issue #36 nem a PR #39 a partir desta branch.
