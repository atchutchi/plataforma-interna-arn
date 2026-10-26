# Estado operacional de media

Execução directa com superpowers:executing-plans, autorizada no laboratório fictício. Testes agrupados no fecho por pedido do responsável. Preservar a demonstração, produção, contas e ficheiros anteriores. A base será main após a integração da PR #67.

## Contrato e decisões

O operador técnico precisa de distinguir falta de capacidade, scanner indisponível e conversores indisponíveis antes de processar novos envios. Acrescentar Ferramentas > Estado de media e o comando explícito `arn media check`. Ambos usam a guarda fictícia existente e uma flag própria. GET só consulta recibos, sem iniciar processos, instalar esquemas ou gravar diagnósticos. POST exige nonce e identificador único. O relatório regista actor, instante de início/fim e resultados fechados, sem caminhos privados ou conteúdo de pessoas.

Cada execução cria um directório privado novo. O recibo inicial precede o diagnóstico. O recibo final tem marcador de hash exclusivo e nunca substitui um anterior. Repetir o mesmo pedido não executa novamente. Uma interrupção fica visível como incompleta. Capacidade máxima de cinquenta relatórios. Um bloqueio não bloqueante impede verificações simultâneas. Não reparar registos nem apagar bytes. GET não cria sequer o ficheiro de bloqueio.

O ensaio ClamAV usa texto fictício benigno e os mesmos argumentos de admissão que exigem assinaturas oficiais recentes. O resultado demonstra execução com essa política, não detecção de malware. O ensaio de imagem usa uma imagem sintética mínima no processo GD existente. O ensaio PDF usa um PDF mínimo gerado no servidor e o conversor já entregue. Não introduzir reportlab no runtime da aplicação. Cada resultado pode ser disponível, indisponível ou desactivado. Nenhum resultado concede acesso ou dispensa análise por ficheiro.

A ocupação da fila, quarentena e entrega é medida sem ler conteúdos, com travessia limitada, sem seguir links ou ficheiros especiais. Limitar cinco mil entradas e quatro níveis por raiz, com recusa de diagnóstico parcial. Mostrar número de ficheiros, bytes observados, espaço livre e lotes/directórios conservados. Os limites de admissão existentes continuam efectivos. Não escolher prazos de retenção institucional nem eliminar originais para libertar espaço.

Decisão: diagnóstico explícito e conservação integral são implementáveis sem decisões institucionais adicionais. Actualizar binários, trocar bases antimalware em utilização, definir conservação institucional e instalar serviços persistentes são entregas separadas. O relatório apresenta idade e não promete validade futura.

## Entregas e verificação

1. Criar InventorySnapshot para métricas limitadas e MediaHealth para os ensaios sintéticos. Reutilizar ClamAvScanner, LocalProcess e PdfRenderer. Separar leitura de capacidade de execução de processos.
2. Criar HealthReports para recibos duráveis e HealthAccess para rota, CLI e apresentação. Registar a entrada junto de QueuePage. Activação só por configuração externa ao Git.
3. Testar preservação, autorização antes/depois, idempotência, concorrência, interrupção, corrupção, limites de travessia e falhas de dependências. Ensaiar HTTP com operador fictício, outra conta, anónimo, nonce inválido, campos adicionais, teclado e reflow.
4. Agrupar PHP, Node, lint/build, PHPCS e Playwright. Comparar contas, tabelas e ficheiros anteriores. Rever a branch com contexto independente. Actualizar README, commit/push e integrar apenas com CI verde no SHA exacto, confirmando main.

Interfaces: InventorySnapshot e MediaHealth produzem apenas métricas e estados fechados. HealthReports valida esse contrato antes de persistir e depois de ler. HealthAccess nunca apresenta stdout, stderr, caminhos ou mensagens de excepções. O leitor considera recibos incompletos e corrupção como estados distintos de um diagnóstico aprovado.

Estado: implementação e aceitação local concluídas sobre main 47f4427. Dezasseis testes PHP focados, 31 verificações Playwright, diagnóstico CLI real, Node 55, lint/build e PHPCS aprovados. Suite PHP 1098/5510, sete omissões Windows incluindo o novo caso FIFO. Revisão independente: um achado de abertura anterior à validação corrigido com lstat e validação posterior do descritor. A regressão POSIX depende da CI Linux. Baseline preservada integralmente. Evidência e limites em docs/estado-operacional-media-local-f1-08.md. Commit/push e CI exacta precedem integração.

Decisão de execução: o responsável pediu testes agrupados no fecho. A implementação inicial antecedeu a execução dos testes. Não apresentar esta parcela como TDD integral. A expectativa inicial do guião de inventário foi corrigida de registos SQL para todos os ficheiros físicos existentes, sem esconder a evidência da primeira falha.
