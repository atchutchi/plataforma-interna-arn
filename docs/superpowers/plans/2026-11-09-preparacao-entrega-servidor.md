# Preparação verificável da entrega F1-17

> Execução directa com superpowers:executing-plans. Autorização do utilizador para avançar localmente, agrupar testes no fecho, commit/push e merge apenas com CI verde. Revisão independente no fim.

Objectivo: organizar todas as frentes de entrada em serviço e produzir um candidato de preparação verificável, sem activar módulos ou copiar dados do laboratório.

Arquitectura: reutilizar o inventário de blobs Git. Preparar uma directoria nova fora de qualquer checkout. Composer instala apenas dependências de produção do lock. Um comando de selagem confirma fontes e metadados de dependências e inventaria os ficheiros. A verificação posterior compara o inventário e o commit confiado. deployReady é sempre false nesta fase.

Stack: Node 24, Git, PHP 8.4 e Composer existentes. Sem novas dependências npm.

Especificação: docs/entrega/plano-entrada-servico.md e docs/entrega/instalacao-recuperacao.md.

## Restrições

Só preparação local. Sem DNS, servidor, dados reais, fixtures, migrações, notificações externas ou alterações ao laboratório 8892/8888. Segredos e candidatos fora do Git. Não sobrescrever destinos existentes nem apagar originais. Não actualizar composer.lock ou dependências. Testes agrupados no fecho conforme pedido do utilizador.

## Revisão e falhas a cobrir

Destinos existentes, dentro do Git ou através de symlinks devem ser recusados. Caminhos Git incompatíveis com Windows não podem escapar ao destino ou colidir. Fontes locais modificadas não entram no candidato. Vendor sem lock correspondente, com dependências de desenvolvimento ou symlinks deve falhar. Ficheiros extra, ausentes ou alterados após a selagem devem falhar, sem transformar integridade em aprovação institucional.

## Tarefa 1: candidato e verificação

- [ ] Criar scripts/release-candidate.mjs com stageCandidate(root, commit, destination), sealCandidate(root, commit, destination) e verifyCandidate(root, commit, destination). CLI stage/seal/verify, saída sem caminhos privados ou bytes.
- [ ] Criar tests/node/release-candidate.test.mjs com repositórios fictícios. Cobrir fontes imutáveis, destino, paths Windows, requisitos de produção e adulteração.
- [ ] Documentar comandos, catálogo de dependências locais ainda por adaptar, sequência, responsáveis e critérios de fecho. Actualizar README.
- [ ] No fecho, executar suite Node, lint/build, instalação Composer isolada e verificação de candidato real. Playwright apenas leitura do login local, sem alterar contas, se HTTP estiver disponível.
- [ ] Revisão independente da branch. Corrigir achados relevantes com regressão.
- [ ] Commit/push/PR, exigir todos os checks do SHA final antes do merge e confirmar main. Gerar candidato final de fontes integradas fora do Git. Guardar resultados privados.

Resultado esperado: ferramentas e organização concluídas, candidato íntegro e explicitamente não pronto para deploy. As adaptações institucionais e operações da sequência continuam pendentes até terem implementação e prova próprias.
