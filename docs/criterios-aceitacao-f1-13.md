# F1-13: critérios de aceitação propostos

19 de outubro de 2026. Todos os cenários têm estado **Por executar**. Não existem código F1-13, activação ou resultados Playwright deste módulo. A [especificação conciliada](superpowers/specs/2026-10-16-tarefas-locais-design.md) fixa o elenco `f113.*` e as operações fechadas. IDs CA-F113 estáveis. CA-F113-01 a CA-F113-32 conservam o identificador da primeira proposta. CA-F113-33 em diante cobrem contratos tornados explícitos na conciliação com F1-12.

Na execução futura, preencher Passou/Falhou/Não executado, comando, SHA, contagens e evidência privada. Uma condição de segurança falhada ou não executada impede declarar o módulo aceite. Estes critérios não são evidência de testes.

| ID | Contexto e acção | Resultado exigido | Prova planeada | Estado |
| --- | --- | --- | --- | --- |
| CA-F113-01 | Alfa consulta Minhas tarefas com uma tarefa `open` atribuída e outra de Beta. | Vê só a própria. A de Beta devolve 404 vazio private/no-store, sem título inferível, antes da fonte protegida. | Política pura, SQL e Playwright | Por executar |
| CA-F113-02 | Alfa executa `start` na própria, de `open` para `in_progress`, com `expected_revision` exacta. | Uma revisão, um evento, 303. Responsável A vê o novo estado. Dados profissionais e concessões iguais. | SQL e formulário Playwright | Por executar |
| CA-F113-03 | Alfa tenta `complete` ou `start` na tarefa de Beta, ou POST com título/unidade/`author_id`/`assignee_id` adulterados. | Recusa sem escrita. IDs e relações vêm do servidor. Forma inválida ou objecto alheio = 404 vazio. | POST adulterado e snapshot | Por executar |
| CA-F113-04 | Responsável A executa `create` e atribui a Alfa em LAB-A. | Uma tarefa, `author_id`=gestor, responsável=Alfa. Beta não lê. Atribuição a LAB-B ou população recusa. Destinatários calculados na transacção a partir do estado `open`. | SQL transaccional e CMS | Por executar |
| CA-F113-05 | Responsável A acompanha a equipa A, filtra por Alfa e por estado. | Duas linhas elegíveis no preset; Beta ausente. Filtro inválido 404 antes da fonte. | Playwright e unitários de parse | Por executar |
| CA-F113-06 | Prazo 2030-01-15T10:00:00Z; relógio 10:00:01Z. | Atraso visível na equipa. Sem prazo não é atraso. GET não escreve. | Relógio injectado | Por executar |
| CA-F113-07 | Datas não UTC, 2030-02-29, overflow, CRLF isolado no título. | Recusa sem conversão silenciosa. 2032-02-29 aceite se usado. | Unitários parametrizados | Por executar |
| CA-F113-08 | `cancel` com motivo pelo gestor A. | Destinatário deixa de actualizar. Detalhe de equipa mostra cancelada sem motivo interno ao Alfa. | SQL e browser | Por executar |
| CA-F113-09 | `reopen` sobre `completed`. | Só se a proposta institucional for aceite; senão recusa documentada, sem escrita. | Política e POST negativo | Por executar |
| CA-F113-10 | `reassign` de Alfa para outro membro Active de LAB-A. | Alfa perde Minhas tarefas e detalhe. Novo responsável lê. Autor original imutável. Histórico conserva. Avisos do estado resultante, não do browser. | SQL antes/depois e Playwright | Por executar |
| CA-F113-11 | Unidade inactiva, conta suspensa ou concessão revogada/expirada. | Leitura e escrita negadas. Uma causa por cenário. Reactivação não recupera sessão antiga. | Serviço auditado e Playwright | Por executar |
| CA-F113-12 | lab_admin e editor de notícias autenticados nas duas páginas. | 404 vazio. Sem excepção `manage_options`. | REST users/me positivo + GET People-style | Por executar |
| CA-F113-13 | Duas páginas com a mesma revisão; uma grava. | Segunda recusa CAS: permanece na página autorizada com erro de formulário, conserva campos próprios, sem 404 do objecto já visível. | Playwright duas origens | Por executar |
| CA-F113-14 | Dois processos SQL no mesmo objecto. | Um commit, uma recusa, um evento, avisos únicos. Sobreposição real sob lock, não só barreira anunciada. | Barreira entre processos | Por executar |
| CA-F113-15 | Nonce inválido, extras, actor/unidade/estado adulterados, no-op. | Nonce/extras/adulteração: 404 vazio sem escrita. No-op com CAS exacto: 303, revisão/auditoria/avisos inalterados. GET/HEAD sem mutação. | HTTP real e hashes de tabela | Por executar |
| CA-F113-16 | Falha injectada na auditoria ou no último aviso. | Agregado, transição, comentário e avisos revertem. Sem sucesso parcial. Destinatários não são escolhidos após o commit. | Integração SQL | Por executar |
| CA-F113-17 | `create` e reenviar o mesmo POST com o mesmo `create_request_id` emitido no GET. | Uma tarefa. 303 para o mesmo objecto. Sem segunda linha, sem segundo aviso, sem nova revisão. A identidade não é `task_id`. Token em falta ou adulterado: 404. | Índice único `create_request_id` e browser | Por executar |
| CA-F113-18 | Destinatário marca aviso como lido e repete. | Recibo em `arn_tasks_notifications.read_at`. Primeiro instante conservado. Lida ≠ aceitação da tarefa. Grants/sessão iguais. Sem tabela F1-04. | SQL e Playwright | Por executar |
| CA-F113-19 | Destinatário perde atribuição; aviso antigo. | Lista/detalhe do aviso não revelam a tarefa inacessível. | SQL e URL directa | Por executar |
| CA-F113-20 | REST páginas, block-renderer, search, feed, oEmbed, revisões, meta. | Sem corpo de tarefas, com fixture autenticada e nonce REST válido. | HTTP WordPress real | Por executar |
| CA-F113-21 | 21 tarefas elegíveis; paginação 20+1. | Autorização antes de contar. Sem duplicados. Página excessiva vazia. | SQL e navegação | Por executar |
| CA-F113-22 | HTML literal, UTF-8 inválido, limites de título/descrição/comentário. | Recusa ou texto escapado. Sem script. | Rendering e Playwright | Por executar |
| CA-F113-23 | `comment` de texto na tarefa própria, com `expected_revision`. | Linha append-only em tabela própria. Visível a Alfa e ao gestor A. Beta 404. Sem anexo binário. Revisão do agregado incrementa. Auditoria e aviso na mesma transacção. | SQL e browser | Por executar |
| CA-F113-24 | Upload ou URL estática de anexo. | Recusa. Lacuna US-ARN-08 registada, sem media pública. | POST negativo | Por executar |
| CA-F113-25 | Instalação CLI, repetição, parcial, colisão de página. | Só CLI local administradora. Repetição integral também após edição. Recusa parcial. | Prefixo `arn_tsk_` e limpeza | Por executar |
| CA-F113-26 | Flag ausente/false/0/'true'; schema incompatível; development. | Fecha antes da fonte. Restaurar configuração em finally. Não reutiliza flags F1-12 para abrir tarefas. | Unitários e browser | Por executar |
| CA-F113-27 | Navegação: só ligações aplicáveis; destinos People e F1-12 conservados. | Menu não substitui guarda. Back mantém filtros. | Playwright actores | Por executar |
| CA-F113-28 | 320/390/768/1280, teclado, labels, erros, 44 px, inputs 16 px. | Sem overflow, h1 único, marca/fontes locais. | Capturas privadas | Por executar |
| CA-F113-29 | Zoom nativo 200% separado de CSS raiz 200%. | Medir ambos. Não declarar equivalência. | Playwright + anotação Windows | Por executar |
| CA-F113-30 | Preservação de contas, concessões, sessões, People, F1-12 e opções. | Só deltas auditados previstos. Hashes por nome de opção. | Baselines imutáveis | Por executar |
| CA-F113-31 | 22 originais reais. | Se ausentes no PC, Não executada a comparação física, reservada a Atchutchi. | Inventário privado | Por executar |
| CA-F113-32 | CI no SHA entregue e revisão. | Não equivalem a PEN/RH, IIS/MySQL 8.4 ou produção. F1-13 institucional continua em curso. | Relatório por níveis de prova | Por executar |
| CA-F113-33 | `comment` com a mesma `expected_revision` após sucesso, ou com CAS já desactualizado; tentativa de editar ou apagar comentário existente. | Recusa CAS determinística: erro de formulário, sem escrita, sem linha nova. Não é no-op. Edição/apagamento: recusa, tabela append-only intacta. Ordenação por `id` crescente. | SQL e POST negativo | Por executar |
| CA-F113-34 | Matriz HTTP: sucesso, no-op, CAS, operação inválida, objecto não autorizado. | Sucesso e no-op = 303. CAS e operação inválida na página já autorizada, sem 404. Objecto não autorizado/inexistente/método errado = 404 vazio private/no-store antes da fonte. Igual a F1-12, sem código HTTP inventado. | HTTP real | Por executar |
| CA-F113-35 | Mutação com destinatários derivados; injecção de falha no último aviso. | Destinatários calculados do estado resultante dentro da transacção. Apresentação só após commit. Rollback integral se o último aviso falhar. Nenhum aviso F1-04 `Notification::EVENTS`. | Integração SQL | Por executar |
| CA-F113-36 | Duas mutações que geram o mesmo aviso; leitura do esquema de avisos. | Uma linha em `arn_tasks_notifications` por `(task_id, revision, recipient_id, kind)`. Recibo só em `read_at`. Sem `arn_tasks_receipts` e sem catálogo F1-04. | Índice único e SQL | Por executar |

## Rastreabilidade resumida

A tabela completa user story → regra → operação → critério → prova está na especificação. IDs novos não substituem CA-F113-01 a 32.

## Preparação futura

Fixtures `f113.*` novas. Não usar as contas People `f107.*`, as fixtures F1-12 `f112.*` nem a lista real. Relógio de teste 2030-01-15T10:00:00Z. LAB-A/B activas. Controlos positivos antes de cada recusa. Sem email ou Google. Sem implementar nesta PR documental. Sem `@@session.in_transaction` no adaptador futuro.
