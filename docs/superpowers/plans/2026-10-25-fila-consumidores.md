# Ligação da fila aos consumidores

Base main 693591f. Laboratório 8892, dados fictícios, testes agrupados no fecho e revisão independente. A autorização para commit, push e merge com CI verde já existe.

## Contrato

Notícias (criação e revisão com media), documentos (criação e revisão) e anexos de tarefas enviam os bytes para a fila. O contexto imutável guarda o autor, operação, objecto, revisão esperada, texto, audiência e ordem dos ficheiros, sem cookies, nonces ou caminhos temporários. Os originais conservados de uma revisão também são copiados e analisados no worker.

O worker técnico só analisa. O autor regressa a uma página privada de envios, acompanha estados e confirma Concluir envio numa sessão válida. Essa confirmação revalida a competência e a revisão e chama o repositório nativo do módulo, que volta a validar a autorização e a política CMS na transacção. Não há impersonação do autor pelo worker, publicação automática ou atribuição de funções.

O resultado técnico vale até 24 horas para esta aceitação local. Resultados antigos exigem nova análise. Cada conclusão tem um marcador durável antes de invocar a operação de negócio e um recibo depois da confirmação. Uma repetição nunca invoca novamente uma operação já iniciada. Uma falha entre o marcador e o recibo deixa resultado indeterminado, com diagnóstico manual pela auditoria do módulo. Não inferir rollback nem repetir uma criação automaticamente. As cópias seguem ImportBatch e são conservadas quando o resultado SQL é incerto.

A interface mostra apenas os envios próprios cujo módulo/objecto continua autorizado. Um ID de outra pessoa, conta suspensa, sessão revogada ou perda de competência não permite ler textos nem concluir. A conta técnica continua sem competência para concluir os envios alheios.

## Tarefas

1. Contexto fechado e persistente, visibilidade por proprietário, conclusão uma vez e adaptador para bytes já analisados, com verificação de integridade.
2. Ligar os três formulários, página privada de acompanhamento/conclusão e acessos de retorno. Conservar regras de aprovação/publicação e todos os limites existentes.
3. Testes de domínio, autorização, concorrência, rollback e Playwright dos três circuitos no laboratório. Comparar preservação, actualizar README/evidência, revisão independente, commit/push e CI exacta antes do merge. Confirmar main.

## Decisões

A conclusão explícita pelo autor evita armazenar uma sessão para uso posterior e conserva a autoridade transaccional já implementada. É uma decisão de implementação, não uma nova política institucional. Pedidos sem ficheiros nas notícias continuam a guardar texto directamente. A recuperação automática de conclusões indeterminadas fica fora desta parcela, que bloqueia repetição e preserva a prova.

## Fecho da execução

Tarefas 1 e 2 executadas. Tarefa 3: PHP 1062/5351, Node 55, SQL 42+23, Playwright 47, PHPCS 18 e lint/build aprovados. Seis omissões Windows anteriores. Comparação privada de preservação aprovada. O contexto e os três consumidores foram revistos por um agente independente, sem achados bloqueantes ou menores pendentes. Os limites de evidência indicados pelo revisor foram resolvidos pela execução principal para HTTP, ClamAV e preservação. CI exacta continua a ser a condição de integração, verificável na PR. Não se afirmou prova de falha física de energia ou homologação institucional. Não houve decisões de negócio novas nem desvios ao âmbito aprovado. A escolha de conclusão explícita pelo autor e o tratamento conservador de resultados indeterminados estão descritos no contrato.
