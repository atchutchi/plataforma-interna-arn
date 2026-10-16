# Entrega ALB-F1-13-SPEC

16 de outubro de 2026. Issue [#29](https://github.com/atchutchi/plataforma-interna-arn/issues/29). Executante: Albertina4264.

## Base recebida

| Item | Valor |
| --- | --- |
| origin/main | `fffef58323636d59985a073e6b03bfc8e3c40e69` (PR #30) |
| PR #25 | Integrada, merge `a1a3fbb` |
| Branch desta entrega | `docs/albertina-f1-13-tarefas` |
| PR #26 F1-12 | Lida como referência; não editada; classes inexistentes |
| README/AGENTS | Não alterados |

A branch antiga `chore/albertina4264-F1-01-fundacao` e o stash local sobre `IdentityWpdb` / MySQL 8 foram preservados e não entram aqui. A validação do segundo PC está na Issue #28, PR própria.

## Ficheiros

1. `docs/superpowers/specs/2026-10-16-tarefas-locais-design.md` — proposta para revisão
2. `docs/criterios-aceitacao-f1-13.md` — 32 critérios CA-F113, todos Por executar
3. este relatório

Não há schema, contas, concessões, páginas, flags, dependências nem código de tarefas.

## Decisões propostas (não aprovadas)

- Reutilizar só ReadOwnTask, UpdateOwnTask, AssignUnitTask e ReadUnitTask. UnitManager não herda Employee; o gestor de ensaio recebe Employee/Own em separado.
- Atribuição transfere a leitura/escrita própria ao novo responsável; o anterior perde Minhas tarefas; o autor não ganha leitura por autoria; o gestor conserva ReadUnitTask.
- Estados `open`, `in_progress`, `completed`, `cancelled`. Reabertura de `completed` fica proposta explícita, não regra.
- Notificações: extensão do catálogo F1-04, hoje só eventos de acesso. Sem email.
- Comentário de texto na primeira parcela; anexos binários adiados (PrivateFiles, antimalware, media nativa). Lacuna US-ARN-08.
- UTC no laboratório; prazo em falta permitido; atraso = `now > due_at`. Sem calendário de férias.
- Flag futura `ARN_ALLOW_LOCAL_TASKS` ausente até revisão e baseline. Instalação só CLI.
- Persistência sem `@@session.in_transaction` (falhou no MySQL 8.0.44 do segundo PC).

## Skills

- Superpowers: indisponível
- Impeccable: DESIGN.md e PRODUCT.md
- UI UX Pro Max: rótulos associados, erros anunciáveis, foco visível, alvos 44 px
- Playwright: não executado para F1-13 (só documentação)

## Perguntas institucionais pendentes

1. A reabertura de tarefas concluídas é permitida no laboratório?
2. O gestor da unidade pode cancelar e reatribuir tarefas de outro autor da mesma unidade, como na proposta F1-12 para comunicados de equipa?
3. Quem designa o responsável principal além do preset LAB-A (PEN-07 / RH)?
4. O fuso a apresentar aos colaboradores é UTC, Europe/Lisbon ou outro, sem conversão silenciosa?
5. Comentários na primeira implementação ou só depois dos anexos PrivateFiles?
6. Os avisos de tarefa entram na superfície F1-04 existente ou numa lista própria?

## Próximos passos

Atchutchi revê esta especificação e os 32 critérios. Só depois prepara o plano de implementação, contra o main efectivo e depois de F1-12 conforme o README. Não fazer merge automático, deploy nem começar código F1-13 nesta atribuição.
