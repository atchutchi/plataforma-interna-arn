# Fila, lotes e gestão da quarentena F1-08

Base: main 0a50f3e. Execução directa autorizada pelo responsável, com testes agrupados no fecho e revisão independente final.

## Contrato

Laboratório local 8892 e administrador fictício explicitamente configurado. Uma fila técnica privada recebe lotes de até oito originais (1 MiB cada), devolve imediatamente a referência e conserva as cópias. Um worker CLI explícito processa até três entradas por execução, usando uma instância da admissão existente por ficheiro. Não executa scanners no pedido HTTP. Não usa cron, daemon, email ou rede externa.

Os estados são em fila, em análise, analisado, retido e cancelado. Analisado significa apenas preparação técnica, nunca publicação, registo de negócio ou autorização de leitura. A ligação assíncrona aos formulários editoriais/documentais/tarefas é uma parcela posterior, que terá de revalidar a versão e a autoridade do autor. Os percursos síncronos existentes continuam disponíveis.

Cada transição persiste actor, instante UTC, revisão e motivo quando é uma decisão manual. O diário tem sequência e encadeamento de hashes e falha fechado perante truncagem ou alteração. Os bloqueios nativos impedem workers simultâneos. Um processo interrompido conserva o estado em análise até recuperação manual auditada, apenas quando o bloqueio do worker está livre. Não há recuperação automática por prazo.

Repetir exige motivo e nova análise completa, até três tentativas. Reter, cancelar e arquivar são decisões lógicas. Não libertam binários, não apagam originais ou quarentena e não ignoram antimalware. Erros não expõem caminhos. A capacidade é limitada a 50 lotes conservados, com pelo menos 64 MiB livres. A manutenção física exige política de conservação posterior.

## Execução

1. Implementar domínio, diário privado, lotes e worker com bloqueios, validação estrita e revalidação da autoridade.
2. Ligar painel técnico WordPress e comando CLI com opt-in local, conta fictícia explícita, sessão/nonce e acções fechadas. Instalar apenas no laboratório isolado.
3. Agrupar testes de estados, corrupção, concorrência, limites, recusas HTTP e worker real. Verificar preservação SQL/binários, actualizar documentação e README, revisão independente, commit/push e PR. Integrar apenas com CI verde no SHA exacto e confirmar main.

## Decisões de implementação

O diário privado é a fonte da fila técnica. Não é necessário criar tabelas de negócio para este estágio sem publicação. Um resultado sem confirmação durável fica indeterminado e requer recuperação. A estrutura não promete resistência a um administrador do sistema de ficheiros que reescreva toda a cadeia.

PDF continua sem preview. Manutenção automática de assinaturas, integração assíncrona nos consumidores e homologação de capacidade permanecem identificadas no backlog.

## Registo de execução

Domínio, painel e comando implementados. Por instrução explícita do responsável, os testes foram agrupados no fecho. A revisão independente foi executada após a implementação. Corrigidos truncagem integral (marcadores prévios ao append), repetição manual sem limite (três por entrada), contagem de caracteres UTF-8 e orçamento do diário (1 MiB). A regressão máxima tem 114 eventos. Não houve alteração do contrato de publicação nem necessidade de autorização adicional.

Tarefas 1 e 2 concluídas. Tarefa 3: PHP 1049/5292/seis omissões Windows, Node 55, SQL 47, Playwright 26, PHPCS 7, lint/build e preservação aprovados. Revisão independente final sem achados. Falta apenas a confirmação da CI do commit entregue e da integração.
