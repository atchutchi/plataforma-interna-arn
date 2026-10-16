# QA do pré-cadastro Workspace

Estado em execução. A preparação do plano foi aprovada para execução directa com revisão independente final. As provas operacionais ficam privadas. Não contém uma lista de funcionários ou configuração OAuth.

## Verificações já executadas

Fonte privada e política pura: RED observado. Primeira suite completa com 810 testes, 3651 assertions e quatro skips existentes no Windows. Leitura limitada, hashes, selecção fechada, metadados temporais e recusas de caminhos/ACL têm cobertura.

Criação auditada: 25 verificações SQL em tabelas nativas exclusivas, com rollback de criação/auditoria, retoma, colisões e ausência de notificações, sessões ou roles. A role por defeito dessa instalação fictícia é administrator para provar que não é herdada.

Guardas e estado: 16 verificações SQL, incluindo wp_authenticate com password tecnicamente correcta, recuperação e application passwords nativas. CAS, rollback da auditoria e no-op do estado foram verificados. Suite completa nessa etapa: 819 testes, 3696 assertions e quatro skips existentes.

Primeiro vínculo: 22 verificações SQL. Incluem falha na última auditoria, rollback entre tabelas, par anterior preservado e revogação sem fallback para email. Dois processos em ligações diferentes ficaram simultaneamente bloqueados, com observação em INNODB_LOCK_WAITS antes da libertação da barreira. Resultado: um commit e um conflito confirmado, uma passagem a Linked e os dois eventos esperados da identidade. Regressão focada: 213 testes e 409 assertions. PHPCS dos 24 ficheiros dessa etapa sem erros ou avisos.

## Ensaios ainda por concluir

Preparação positiva e negativa da readiness no runtime completo, renovação com rollback, percursos HTTP do cliente mantido com fornecedor OIDC simulado, emissão e replay de cookies com revisão, logout e nova entrada. Ensaiar flags, esquema, sessão antiga, recuperação anterior e superfícies proibidas isoladamente.

Playwright em 375, 768 e 1440 px, teclado, foco, ausência de overflow e erros JavaScript. Texto a 200% é uma verificação distinta de zoom nativo do browser. Capturas, cookies e storageState ficam privados. A suite PHP completa, Node, lint, build, PHPCS, CI exacta e revisão independente continuam pendentes nesta altura.

## Preservação do laboratório normal

As integrações SQL usam prefixos novos e contas fictícias. Uma guarda recusa DML/DDL contra as tabelas normais durante o ensaio. O código HTTP normal ainda não foi ligado ao candidato.

Na comparação privada entre as duas primeiras provas de implementação, houve alteração de três transientes de temas e uma sessão federada nova da conta técnica anterior. Não se repôs nem podou essa sessão. A origem exacta da actividade HTTP paralela não ficou demonstrada. As restantes linhas anteriores, dados de negócio, configuração e 89 ficheiros privados mantiveram os hashes. Não se afirma igualdade integral do intervalo global. A aplicação final terá novos snapshots imutáveis e comparação por delta.

## Direcção visual

Mantém PRODUCT.md e DESIGN.md. Aplicam-se os princípios Impeccable do registo product e as orientações de acessibilidade de UI UX Pro Max. O auxiliar search.py desta instalação está indisponível: scripts/data são ficheiros com referências relativas para um destino ausente. Não se alega uma pesquisa de design executada. A vista será validada no browser com os recursos institucionais existentes.

Nenhuma destas verificações constitui homologação de produção, de IIS/MySQL ou aprovação institucional de permissões.
