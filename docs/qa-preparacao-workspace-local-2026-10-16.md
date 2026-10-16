# Preparação do pré-cadastro Workspace

16 de outubro de 2026. Base Git: main, 365346a752c2a1b9082c686f6b1020fea59002a4. Branch documental: feat/workspace-pre-cadastro-local.

## Alcance executado

Consulta da arquitectura e do contrato OIDC, validação privada do inventário e preparação da proposta. Não foi escrito código de produto ou criado qualquer pré-cadastro. A especificação está em docs/superpowers/specs/2026-10-16-workspace-pre-cadastro-local-design.md e necessita de revisão antes do plano e da implementação.

A exportação privada tem 77 contas activas. As 73 nominativas seleccionadas passaram 594 verificações de estrutura, correspondência exacta dos nomes/emails com a origem, integridade SHA-256, classificação, estado, unicidade e ausência de conta WordPress com o mesmo email. As quatro contas genéricas continuam excluídas. A validação não determina se uma identidade Google já está vinculada a uma conta WordPress com outro email. O vínculo administrativo anterior permanece intacto.

O relatório detalhado, a exportação, o inventário, os scripts operacionais e os snapshots ficam no armazenamento privado local ou nas ferramentas ignoradas do operador. Não foram incluídos dados pessoais novos, identificadores Google, credenciais ou URLs de sessão nesta branch.

## Verificações correntes

Os 55 testes Node, lint e build passaram. A primeira execução no worktree novo encontrou a dependência de desenvolvimento @wordpress/env ausente. A repetição usou NODE_PATH para as dependências locais já existentes e passou integralmente. Não houve mudança de package.json ou lockfiles. Não se voltou a executar a suite PHP/SQL porque a entrega só modifica documentação.

Playwright CLI, em browser isolado sem sessão, passou dez verificações no laboratório existente: botão Google visível e destino correcto, ausência de overflow em 375 px, encaminhamento da página inicial para login, REST de utilizadores com 401 e código controlado, callback inválido com 403 e mensagem genérica, ausência de cookie autenticado e zero erros JavaScript. Não foi introduzido um nome, email ou password de funcionário no browser. As capturas ficam privadas e o browser de ensaio foi fechado. As tabs Google do responsável foram preservadas.

Foram comparados snapshots privados antes e depois destas verificações de browser. As 49 tabelas conservaram todas as linhas e hashes. As sessões, a configuração WordPress e os 89 ficheiros privados conservaram os respectivos hashes e metadados. O primeiro comparador PowerShell não tratou tabelas vazias correctamente. Esse resultado não foi usado como prova. O comparador PHP subsequente cobriu também tabelas vazias e confirmou a igualdade integral, sem repetir ou substituir snapshots.

## O que continua por executar

Rever a política de associação inicial, preparar o plano e implementar a instalação CLI, persistência auditada, bloqueio nativo anterior ao vínculo e associação concorrente no callback. Depois, executar ensaios fictícios PHP/SQL/Playwright, rever o código, fazer backup e aplicar o lote privado. A existência da especificação não significa que as 73 contas já estejam criadas ou aptas a entrar. Os ensaios actuais não comprovam o novo fluxo nem 73 autenticações Google reais.

Não houve alterações de papéis, concessões, contas, vínculos, sessões, configuração, Workspace, infraestrutura ou tarefas da Albertina.

## Continuação após aprovação da especificação

O responsável aprovou a especificação do commit c5a127b e pediu o plano. Foram preparadas sete tarefas e 41 passos verificáveis em docs/superpowers/plans/2026-10-16-workspace-pre-cadastro-local.md, ainda para revisão e escolha da execução. Voltaram a passar 55 testes Node, lint/build e git diff --check nesta continuação documental. Não se repetiram testes PHP/SQL ou Playwright sem alteração de produto.

Separadamente, o responsável pediu a revisão e integração das entregas da Albertina e novas atribuições. As PRs #34 e #35 foram revistas e integradas com 12 checks aprovados nos respectivos heads. A CI main posterior também passou nos merges b0a85ae e 0e7ffcd. As Issues #36 e #37 foram criadas e a atribuição a Albertina4264 foi confirmada. A primeira criação devolveu um erro GraphQL após criar a #36 sem assignee. A consulta identificou esse resultado parcial e a atribuição foi concluída na mesma Issue, sem duplicar a tarefa. As falhas de QA anteriores permanecem registadas e a proposta F1-13 continua sem aprovação institucional.
