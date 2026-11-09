# Correcção da dependência HTTP para a entrega

Continuação autorizada pelo responsável em 9 de novembro, após identificar dois avisos de symfony/http-client na preparação da PR #80. Execução directa com testes agrupados no fecho e revisão independente apenas desta nova alteração. Não repetir a revisão da ferramenta de candidatos já corrigida.

Objectivo: actualizar a dependência afectada no ambiente de contentor, confirmar compatibilidade OIDC e impedir CI verde quando a auditoria das dependências de produção falhar.

Arquitectura: manter composer.json e resolver apenas symfony/http-client e dependências indispensáveis com --minimal-changes. Usar os wrappers wp-env existentes, somente em loopback. Instalar de novo pelo composer:install e verificar o lock. Não alterar vendor ou configuração de 8888/8892, Google real ou produção.

Decisão técnica: composer install preserva um lock existente e não selecciona a correcção. Acrescentar composer:update:http-client como operação dirigida dentro do mesmo contentor, seguida de composer:install. Esta é a clarificação operacional da regra do AGENTS.md, não uma resolução manual do lock ou fora do contentor.

## Etapas

- [x] Recuperar o motor Docker através de actualização oficial validada, preservando discos/configuração e sem reset ou aceitação automática de novos termos.
- [x] Confirmar os bindings loopback antes do arranque do ambiente wp-env exclusivo de testes.
- [x] Executar actualização dirigida e instalação no contentor. Rever o delta de composer.lock e rejeitar alterações não necessárias.
- [x] Acrescentar composer audit --locked --no-dev ao job PHP da CI.
- [x] Detectar e recusar descoberta incompleta de testes no mount Windows/WSL. Copiar fontes/vendor para filesystem Linux e verificar os hashes antes da suite. Desvio necessário descoberto na validação, sem alterar código de terceiros ou resolver o lock no host.
- [x] Agrupar PHPUnit, PHPCS, Node/lint/build, integração de identidade/OIDC e auditoria. Usar dados fictícios. Playwright verifica o acesso local sem usar Google real.
- [ ] Actualizar README e relatório. Revisão independente do delta desta tarefa, commit/push e CI exacta. Merge da PR #80 apenas após auditoria e checks aprovados. Confirmar main.

## Revisão

Verificar resolução compatível com PHP 8.4, actualização mínima, ausência de excepções na auditoria, preservação das versões directas Facile/Guzzle e nenhuma activação no servidor ou alteração de dados reais. A versão corrigida deve vir do resolver e dos avisos oficiais, nunca de edição manual de referências/hashes.
