# F1-13: critérios de aceitação propostos

16 de outubro de 2026. Todos os cenários têm estado **Por executar**. Não existem código F1-13, activação ou resultados Playwright deste módulo. A [especificação](superpowers/specs/2026-10-16-tarefas-locais-design.md) fixa o elenco `f113.*`. IDs CA-F113 estáveis.

Na execução futura, preencher Passou/Falhou/Não executado, comando, SHA, contagens e evidência privada. Uma condição de segurança falhada ou não executada impede declarar o módulo aceite.

| ID | Contexto e acção | Resultado exigido | Prova planeada | Estado |
| --- | --- | --- | --- | --- |
| CA-F113-01 | Alfa consulta Minhas tarefas com uma tarefa open atribuída e outra de Beta. | Vê só a própria. A de Beta devolve 404 vazio private/no-store, sem título inferível. | Política pura, SQL e Playwright | Por executar |
| CA-F113-02 | Alfa actualiza a própria de open para in_progress com revisão exacta. | Uma revisão, um evento, 303. Responsável A vê o novo estado. Dados profissionais e concessões iguais. | SQL e formulário Playwright | Por executar |
| CA-F113-03 | Alfa tenta concluir a tarefa de Beta ou alterar título/unidade/autor. | Recusa sem escrita. IDs e relações vêm do servidor. | POST adulterado e snapshot | Por executar |
| CA-F113-04 | Responsável A cria e atribui a Alfa em LAB-A. | Uma tarefa, autor=gestor, responsável=Alfa. Beta não lê. Atribuição a LAB-B ou população recusa. | SQL transaccional e CMS | Por executar |
| CA-F113-05 | Responsável A acompanha a equipa A, filtra por Alfa e por estado. | Duas linhas elegíveis no preset; Beta ausente. Filtro inválido 404 antes da fonte. | Playwright e unitários de parse | Por executar |
| CA-F113-06 | Prazo 2030-01-15T10:00:00Z; relógio 10:00:01Z. | Atraso visível na equipa. Sem prazo não é atraso. GET não escreve. | Relógio injectado | Por executar |
| CA-F113-07 | Datas não UTC, 2030-02-29, overflow, CRLF isolado no título. | Recusa sem conversão silenciosa. 2032-02-29 aceite se usado. | Unitários parametrizados | Por executar |
| CA-F113-08 | Cancelar com motivo pelo gestor A. | Destinatário deixa de actualizar. Detalhe de equipa mostra cancelada sem motivo interno ao Alfa. | SQL e browser | Por executar |
| CA-F113-09 | Reabrir completed. | Só se a proposta institucional for aceite; senão recusa documentada. | Política e POST negativo | Por executar |
| CA-F113-10 | Reatribuir de Alfa para outro membro Active de LAB-A. | Alfa perde Minhas tarefas e detalhe. Novo responsável lê. Autor original imutável. Histórico conserva. | SQL antes/depois e Playwright | Por executar |
| CA-F113-11 | Unidade inactiva, conta suspensa ou concessão revogada/expirada. | Leitura e escrita negadas. Uma causa por cenário. Reactivação não recupera sessão antiga. | Serviço auditado e Playwright | Por executar |
| CA-F113-12 | lab_admin e editor de notícias autenticados nas duas páginas. | 404 vazio. Sem excepção manage_options. | REST users/me positivo + GET People-style | Por executar |
| CA-F113-13 | Duas páginas com a mesma revisão; uma grava. | Segunda recusa CAS e conserva campos próprios. | Playwright duas origens | Por executar |
| CA-F113-14 | Dois processos SQL no mesmo objecto. | Um commit, uma recusa, um evento, avisos únicos. Sobreposição real sob lock. | Barreira entre processos | Por executar |
| CA-F113-15 | Nonce inválido, extras, actor/unidade/estado adulterados, no-op. | Recusas sem escrita. No-op mantém revisão. GET/HEAD sem mutação. | HTTP real e hashes de tabela | Por executar |
| CA-F113-16 | Falha injectada na auditoria ou no último aviso. | Agregado, transição e avisos revertem. Sem sucesso parcial. | Integração SQL | Por executar |
| CA-F113-17 | Atribuir e reenviar o mesmo POST. | Uma tarefa e um aviso por chave. | Índice único e browser | Por executar |
| CA-F113-18 | Destinatário marca aviso como lido e repete. | Primeiro instante conservado. Lida ≠ aceitação da tarefa. Grants/sessão iguais. | SQL e Playwright | Por executar |
| CA-F113-19 | Destinatário perde atribuição; aviso antigo. | Lista/detalhe do aviso não revelam a tarefa inacessível. | SQL e URL directa | Por executar |
| CA-F113-20 | REST páginas, block-renderer, search, feed, oEmbed, revisões, meta. | Sem corpo de tarefas, com fixture autenticada e nonce REST válido. | HTTP WordPress real | Por executar |
| CA-F113-21 | 21 tarefas elegíveis; paginação 20+1. | Autorização antes de contar. Sem duplicados. Página excessiva vazia. | SQL e navegação | Por executar |
| CA-F113-22 | HTML literal, UTF-8 inválido, limites de título/descrição/comentário. | Recusa ou texto escapado. Sem script. | Rendering e Playwright | Por executar |
| CA-F113-23 | Comentário de texto na tarefa própria. | Append-only visível a Alfa e ao gestor A. Beta 404. Sem anexo binário nesta parcela. | SQL e browser | Por executar |
| CA-F113-24 | Upload ou URL estática de anexo. | Recusa. Lacuna US-ARN-08 registada, sem media pública. | POST negativo | Por executar |
| CA-F113-25 | Instalação CLI, repetição, parcial, colisão de página. | Só CLI local administradora. Repetição integral também após edição. Recusa parcial. | Prefixo `arn_tsk_` e limpeza | Por executar |
| CA-F113-26 | Flag ausente/false/0/'true'; schema incompatível; development. | Fecha antes da fonte. Restaurar configuração em finally. | Unitários e browser | Por executar |
| CA-F113-27 | Navegação: só ligações aplicáveis; destinos People e anteriores conservados. | Menu não substitui guarda. Back mantém filtros. | Playwright actores | Por executar |
| CA-F113-28 | 320/390/768/1280, teclado, labels, erros, 44 px, inputs 16 px. | Sem overflow, h1 único, marca/fontes locais. | Capturas privadas | Por executar |
| CA-F113-29 | Zoom nativo 200% separado de CSS raiz 200%. | Medir ambos. Não declarar equivalência. | Playwright + anotação Windows | Por executar |
| CA-F113-30 | Preservação de contas, concessões, sessões, People e opções. | Só deltas auditados previstos. Hashes por nome de opção. | Baselines imutáveis | Por executar |
| CA-F113-31 | 22 originais reais. | Se ausentes no PC, Não executada a comparação física, reservada a Atchutchi. | Inventário privado | Por executar |
| CA-F113-32 | CI no SHA entregue e revisão. | Não equivalem a PEN/RH, IIS/MySQL 8.4 ou produção. F1-13 institucional continua em curso. | Relatório por níveis de prova | Por executar |

## Preparação futura

Fixtures `f113.*` novas. Não usar as contas People `f107.*` nem a lista real. Relógio de teste 2030-01-15T10:00:00Z. LAB-A/B activas. Controlos positivos antes de cada recusa. Sem email ou Google. Sem implementar nesta PR documental.
