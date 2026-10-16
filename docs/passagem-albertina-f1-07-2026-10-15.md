# Passagem a Albertina4264: ALB-F1-07-QA

Actualização de 16 de outubro de 2026: este guião é histórico. O responsável pediu que Codex/Atchutchi assumisse a tarefa neste PC. A execução e os limites estão na [entrega F1-07](entrega-f1-07-2026-10-16.md) e na [matriz QA actual](qa-directorio-perfil-local-f1-07.md). Não reenviar ou executar esta tarefa em duplicado nem presumir que Albertina produziu a entrega. A sua branch e laboratório não foram alterados. Os parágrafos seguintes conservam o estado recebido em 15 de outubro.

15 de outubro de 2026. Pedido de Atchutchi: actualizar o estado e preparar uma tarefa para Albertina continuar na sua branch e devolver o trabalho para revisão. Este guião e o prompt associado constituem a passagem. Não foi criada uma Issue nem enviada uma mensagem à colaboradora.

## Objectivo e limite

Fechar a aceitação local de US-F03, US-F10 e US-M01 da F1-07, corrigir apenas falhas verificadas e entregar uma PR a Atchutchi. O modelo, persistência, instalador, páginas e navegação já existem. Não repetir a fundação, reimplementar o módulo ou começar F1-12/F2 nesta tarefa. Não fazer merge ou deploy.

Ler README.md, AGENTS.md, DESIGN.md, PRODUCT.md, a especificação `docs/superpowers/specs/2026-10-15-people-design.md`, o plano `docs/superpowers/plans/2026-10-15-people.md`, a operação `docs/directorio-perfil-local-f1-07.md` e o registo `docs/qa-directorio-perfil-local-f1-07.md`. Os três primeiros blocos de implementação do plano já estão executados. O trabalho que falta é a aceitação do controlador e a devolução.

## Estado recebido

| Referência | Estado observado |
| --- | --- |
| main | PR #24 integrada, merge aebf4ced459b4677a163a2d5f95eb3f5c916dbd7. F1-11 local com decisão, publicação, avisos e arquivo. |
| F1-07 | Branch feat/f1-07-directory-profile, PR #25 em rascunho. Candidato de código 598b8978567dd96ef0ab215871836a82677dce7b, seguido da documentação de passagem. Conferir o HEAD remoto ao iniciar. |
| Verificação do candidato | 11 checks SUCCESS. Workflows 38754532349 e 38754524751. Job PHP Ubuntu: 759 testes, 3035 asserções. Node: 55 testes, lint e build. |
| Positivos SQL locais | People 43 verificações, instalador 41, prefixos/contas/páginas próprios removidos. Incluem dois escritores em processos separados, um commit e um evento novo. |
| Limite do CI | Em development, People SQL e installer comprovam a recusa fora de local. O positivo local não é executado por esses dois caminhos development. |
| Revisões por tarefa | Modelo e persistência aprovados após correcções. Terceira tarefa aprovada no pacote 82a0f5c..598b897, sem achados que exigissem alteração. Não substituem a revisão transversal e a aceitação normal. |
| Laboratório de Atchutchi | Último preflight SQL: zero tabelas, contas e páginas People normais, unidades na revisão 5 com LAB-B inactiva e dados anteriores preservados. Na verificação documental final, configuração com hash inicial e flag People ausente, mas serviços PHP/SQL parados. Não houve revalidação SQL nessa ocasião, activação ou Playwright normal F1-07. |
| Anexos reais | 22 originais, só administrador para revisão. Cinco circulares, quatro notas, onze despachos, um regulamento e uma comunicação sindical. Não fazem parte das fixtures People ou da entrega à Albertina. |

Os relatórios internos de agentes, runtimes, base de dados, cookies, credenciais, configuração e originais não estão no Git. Não dependas de `.git/tools` deste PC. O teu laboratório terá os seus próprios caminhos, contas técnicas, IDs e revisões. Não copies segredos Google nem peças dados reais para testar People.

Ao retomar no PC de Atchutchi, iniciar o runtime existente sem reinicializar a base e conferir o baseline privado antes de qualquer escrita. Esta indisponibilidade não obriga Albertina a recuperar o outro PC: a sua tarefa usa o laboratório próprio.

## 1. Actualizar a branch própria

Branch remota confirmada: `chore/albertina4264-F1-01-fundacao`. Na consulta de 15 de outubro estava 116 commits atrás de main, sem commits exclusivos. O nome é histórico e não significa que devas voltar a trabalhar na fundação.

Começa por `git status`, `git remote -v` e por conferir a conta autenticada e as PRs. Preserva trabalho local existente. Não uses reset, force push, rebase destrutivo ou remoção de ficheiros para obter um checkout limpo. Se o clone estiver ocupado, usa outro clone dedicado em vez de apagar alterações.

Num checkout limpo e sem commits locais divergentes, o percurso esperado é:

```powershell
git fetch origin --prune
git switch chore/albertina4264-F1-01-fundacao
git merge --ff-only origin/main
git merge --ff-only origin/feat/f1-07-directory-profile
git status
```

Se a branch ainda não existir localmente, cria o seguimento da branch remota com `git switch --track origin/chore/albertina4264-F1-01-fundacao`. Se um fast-forward for recusado, inspecciona os commits e conserva-os. Resolve por merge revisto quando adequado, sem forçar o histórico. Regista os SHAs efectivamente recebidos. Não faças cherry-pick parcial dos ficheiros People.

Enquanto a PR #25 estiver aberta, a tua PR deve ter base `feat/f1-07-directory-profile` e head `chore/albertina4264-F1-01-fundacao`. Assim a devolução contém só as tuas alterações. Se a #25 já tiver sido integrada, confirma a nova main e usa-a como base sem duplicar o módulo. A PR #2 é histórica e não deve ser reaberta.

## 2. Preparar o laboratório e o baseline

- [ ] Registar versões de Node 24.21.0, PHP 8.4, WordPress, WP-CLI e motor SQL, URL loopback, prefixo e ambiente efectivo. Não afirmar que o runtime portátil ou a MariaDB locais homologam IIS/MySQL 8.4.
- [ ] Usar apenas o teu laboratório. Não ligar a .205, .151, AD, DNS, Google Workspace, rede de produção ou email externo. Respeitar a pendência de licença do Docker Desktop do README. Não instalar Docker ou WSL no servidor ARN.
- [ ] Instalar dependências pelos lockfiles com `npm ci` e `composer install`. Não actualizar dependências ou lockfiles sem necessidade demonstrada.
- [ ] Configurar segredos e caminhos fora do Git. `wp-config.php`, passwords, cookies, dumps, logs e capturas não entram em commits. Verificar ficheiros explicitamente antes de os adicionar ao índice.
- [ ] Guardar hashes e contagens dos dados anteriores, configuração e ficheiros privados. Separar os deltas autorizados de preparação, instalação e testes. Não substituir o baseline inicial depois de escrever.

People exige ambiente `local` e os booleanos true ARN_ALLOW_LOCAL_PEOPLE, ARN_ALLOW_FICTIONAL_LOCAL_ACCESS, ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE e ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE. A raiz ARN_LOCAL_PRIVATE_FILES_DIR deve ser privada, fora do projecto e do document root e sem symlinks. Não é necessário configurar Google para este ensaio: o login é nativo com contas fictícias marcadas.

O instalador normal People consome os pré-requisitos F1-03/F1-05 e não os cria ou repara. Num laboratório novo, preparar explicitamente esses pré-requisitos por WP-CLI administradora, após as guardas local/CLI/manage_options e das três flags anteriores. A API de schema é `AuthorizationSchema`, construída com a mesma `IdentityWpdb` e prefixo do WordPress. Confirmar primeiro as quatro tabelas arn_authz_schema/accounts/grants/audit: se todas estiverem ausentes, a instalação explícita é possível. Se estiverem presentes, validar o conjunto. Recusar estado parcial ou desconhecido antes de chamar install, que não deve ser usado como mecanismo de recuperação.

Para unidades, a API é `LocalUnitInstaller::install()` e `LocalUnitInstaller::repository()`. Só inicializar uma árvore realmente vazia na revisão zero pelo cenário `initialize`, com `LocalUnitContext` do administrador CLI, motivo e hora. Se a árvore já existir, inspeccionar o snapshot. LAB-A e LAB-B têm de estar activas. Se B estiver inactiva, usar apenas `apply('reactivate-b', revisao_actual, contexto_auditado)`, desde que o superior esteja activo. Comparar atributos, revisão global e evento. Não usar SQL directo, repetir initialize ou presumir a revisão 5 observada noutro PC.

Após a revisão transversal e o baseline, activar explicitamente People e chamar por CLI administradora `(new \Arn\Intranet\People\LocalPeopleInstaller())->install()`. O retorno contém user_ids, created e pages. Não imprimir passwords. Os ficheiros normais são `f107-alfa-teste.password`, `f107-beta-teste.password` e `f107-responsavel-a-teste.password` na raiz privada. Não adoptar instalações parciais nem restaurar suspensos/revogados para fazer um teste passar.

Repetir o instalador e comparar integralmente linhas do prefixo WordPress, incluindo sessões, posts, postmeta e opções, além dos ficheiros privados. Nenhuma mudança é esperada. Voltar a provar repetição depois de uma edição legítima de contacto. Não apresentar DDL como transaccional.

## 3. Executar a aceitação e corrigir falhas

Fechar uma revisão transversal do conjunto F1-07 antes da entrega. As revisões por tarefa não substituem esta verificação. Usar Superpowers, Impeccable, UI UX Pro Max e Playwright conforme disponíveis e conforme as regras do repositório. Não reinicializar a identidade visual ou escolher outro tema. Se uma skill necessária não existir no teu ambiente, registar a limitação. Não inventar que a executaste.

| Percurso | Prova exigida |
| --- | --- |
| Login e directório | Login nativo de Alfa, Beta e responsável A. Directório com três pessoas elegíveis. Pesquisa por nome, unidade e cargo, ficha profissional e paginação coerentes. |
| Perfil próprio | Só telefone, local e apresentação editáveis. Gravar aumenta uma revisão e um evento. Nome/email/cargo/unidade, contas, concessões e sessões mantêm-se. No-op não escreve. |
| Conflitos e validação | Duas páginas com a mesma revisão, uma grava e a outra é recusada sem perder os três campos próprios. Nonce inválido, actor/unit/job/email/state/role e extras recusados sem escrita. Testar CRLF, CR isolado, limites e HTML literal escapado. |
| Equipa | Responsável A vê apenas Alfa e o próprio. Alfa/Beta não abrem a equipa e pedir B como responsável A recusa. A ficha comum não oferece edição alheia. Administrador técnico/editor não recebem excepção. |
| Suspensão e sessão | Começar por acesso positivo. Suspender Alfa pelo serviço auditado, provar bloqueio e contagens ajustadas. Reactivar, provar que a sessão antiga continua recusada e só um novo login nativo recupera o acesso. Não combinar causas que mascarem a prova. |
| Transporte | GET/HEAD/POST recusados: 404 vazio e private/no-store, sem redireccionamento para login. Filtros/IDs inválidos antes da fonte. Destinos ausentes/incompatíveis e permalinks simples testados sem alterar conteúdo existente. REST de páginas e block-renderer sem contactos ou formulário, inclusive com uma fixture autenticada e nonce REST válido. Pesquisa/meta nativas sem dados People. |
| Gate | Três acessos positivos antes, desligar só a flag People, provar recusas, restaurar a configuração com hash exacto e repetir os positivos com a mesma sessão. Usar try/finally para restaurar mesmo em falha. |
| Visual | 320/390/768/1280 px, um h1, sem transbordo, menu móvel/teclado/Escape, labels/erros, controlos de 44 px e inputs de 16 px. Confirmar Manrope/Source Sans 3 locais, logótipo e favicon. Medir foco e ampliação, distinguindo token nominal de valor efectivo e CSS a 200% de zoom nativo real. |
| Regressões | Conservar os cinco destinos anteriores e os módulos existentes. Comparar dados e ficheiros anteriores. Se não tens os 22 originais, registar que essa comparação física fica reservada ao PC de Atchutchi. Não inventar uma validação com ficheiros ausentes. |

Guardar screenshots e logs privados. A documentação Git deve conter resultados e instruções reproduzíveis, sem passwords, cookies, nonces ou payloads privados. Fechar apenas os teus contextos de teste. Não terminar sessões de outro utilizador ou alterar a sua janela do browser.

Corrigir só falhas demonstradas. Acrescentar regressão significativa antes do fix, testar os pontos afectados e rever o diff. Evitar ciclos de testes globais sem alterações que os justifiquem. Se a revisão final encontrar vários problemas, reunir uma ronda de correcções e fazer revisão restrita dos fixes.

## 4. Comandos e evidência

Os comandos `npm run test:php`, `npm run lint:php` e `npm run test:people:integration` usam wp-env. Não os apresentar como executados se Docker não estiver disponível. Num WordPress local existente, usar o PHP e WP-CLI efectivos do teu PC:

```text
<php> vendor/bin/phpunit -c phpunit.xml.dist
<php> vendor/bin/phpcs
<php> <wp-cli.phar> --path=<wordpress-local> --user=<admin-local> eval-file tests/integration/people.php
<php> <wp-cli.phar> --path=<wordpress-local> --user=<admin-local> eval-file tests/integration/people-installer.php
npm test
npm run lint
npm run build
```

Substituir os caminhos pelos existentes e verificar Node 24.21.0 no processo efectivo. Configuração OpenSSL das fixtures é local, não um segredo para publicar. Executar as integrações anteriores afectadas, sobretudo unidades/autorização/notícias/documentos se o fix tocar fronteiras comuns. Se não houver código alterado, não é necessário repetir todas as suites depois de uma edição apenas documental. O CI final deve corresponder ao SHA entregue.

## 5. Devolver a Atchutchi

- [ ] Actualizar docs/qa-directorio-perfil-local-f1-07.md com ambiente, data, SHA, matriz real passado/falhado/não executado, comandos/resultados e limitações. Preservar a distinção entre implementação, positivo exclusivo, CI negativo, browser e homologação institucional.
- [ ] Criar docs/entrega-albertina-f1-07.md com base recebida, commits, ficheiros alterados, revisão, URL da PR, instruções de reprodução, preparação/migrações necessárias e acções seguintes de Atchutchi. Não incluir dumps ou credenciais.
- [ ] Actualizar README/AGENTS sem afirmar que o laboratório de Atchutchi foi activado ou que a plataforma inteira está concluída.
- [ ] Fazer commits revistos e push apenas da tua branch. Abrir PR contra a base indicada, mantê-la aberta para Atchutchi e não integrar em main ou fazer deploy. Entregar também o estado dos checks no SHA final, sem confundir verde num SHA anterior com o actual.
- [ ] Terminar com uma mensagem de entrega: tarefa, branch, SHA, URL da PR, o que passou, o que ficou pendente, limitações e o próximo passo exacto para Atchutchi. Não iniciar F1-12 antes desta devolução.

Atchutchi deve actualizar a branch dele, rever a PR e reproduzir a instalação/aceitação no seu laboratório sem copiar a base ou segredos da Albertina. Conserva o seu baseline dos originais e a operação auditada de B. Integra a contribuição na branch F1-07 e depois a PR #25 em main apenas após revisão e todos os checks no SHA final. A tarefa seguinte é F1-12. As decisões institucionais, F2 e infraestrutura continuam segundo o plano mestre.
