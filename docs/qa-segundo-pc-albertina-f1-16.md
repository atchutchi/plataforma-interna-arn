# QA do segundo PC: ALB-F1-16-01

16 de outubro de 2026. Issue [#28](https://github.com/atchutchi/plataforma-interna-arn/issues/28). Executante: Albertina4264. Branch: `qa/albertina-f1-16-segundo-pc`. Este relatório é a aceitação independente no laboratório deste PC. Não substitui a aceitação F1-07 já integrada pela PR #25/#27 no PC de Atchutchi e não activa esse laboratório.

## SHA e ambiente

| Item | Valor observado |
| --- | --- |
| SHA recebido | `fffef58323636d59985a073e6b03bfc8e3c40e69` (origin/main, merge da PR #30) |
| PR #25 em main | Confirmada: merge `a1a3fbb`. Head F1-07 aceite `7e8ff4a3606f13b0fb5e4bd32e4b5edc3bfa1748` |
| Node | 24.21.0 |
| npm | 11.19.0 |
| PHP | 8.4.25 WinGet, ZTS x64 |
| WordPress | 7.1.3 |
| WP-CLI | 2.12.0 |
| SQL efectivo | MySQL 8.0.44, `127.0.0.1:33317` |
| HTTP | PHP built-in, `127.0.0.1:8888` |
| Docker / wp-env | Ausente. Não foram inventados resultados de `npm run env:*` |
| Browser | Chromium do Playwright MCP. capturas privadas em `output/playwright/` (ignorado pelo Git) |

Este runtime não prova MariaDB 11.8, MySQL 8.4 LTS, IIS nem produção. A licença Docker Desktop continua pendente neste PC; o laboratório usa PHP e MySQL isolados em loopback, com plugin/tema sincronizados a partir do SHA recebido.

## Trabalho local não publicado (antes de criar a branch)

O clone estava em `chore/albertina4264-F1-01-fundacao` em `6f30d76022332c557826b4d4fbd304046df8383b`, 124 commits à frente de `origin/chore/albertina4264-F1-01-fundacao` (`16890af`). Esses 124 commits são de Atchutchi (F1-07), sem commits exclusivos da Albertina no remoto.

Havia alterações locais não commitadas, agora em `stash@{0}` e cópia privada fora do Git:

- `IdentityWpdb.php`: profundidade local de transacção, sem `@@session.in_transaction`
- `WpdbAuthorizationRepository.php` e `WpdbLocalUnitRepository.php`: passam a `inTransaction()`
- `tests/phpunit/IdentityWpdbTransactionTest.php` por versionar

Não foram misturadas nesta PR. A causa reproduzida está na matriz. A branch antiga e o stash permanecem.

## Baselines

Snapshots privados em `C:\arn-f107-lab\qa-f116\` (fora do Git e do document root). Não se imprimem valores, passwords, cookies nem `wp-config.php`.

| Snapshot | Tabelas | Opções | People | Auditoria People | Grants |
| --- | --- | --- | --- | --- | --- |
| Antes de sincronizar o plugin do main | 22 | 138 | 3 | 21 | 4 |
| Depois de copiar plugin/tema do SHA recebido | 22 | 139 | 3 | 21 | 4 |
| Depois da repetição do instalador (helper demasiado amplo falhou; ver abaixo) | 22 | 138 | 3 | 21 | 4 |

Flags efectivas: `WP_ENVIRONMENT_TYPE=local`, `ARN_ALLOW_FICTIONAL_LOCAL_ACCESS`, `ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE`, `ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE` e `ARN_ALLOW_LOCAL_PEOPLE` booleanos true. Ficheiros privados presentes: `f107-alfa-teste.password`, `f107-beta-teste.password`, `f107-responsavel-a-teste.password` (tamanho 48, hashes só no snapshot privado).

Deltas de `wp_options` entre snapshots, por nome e sem valores: transientes de temas/core/plugins e `cron`. Não se normalizou o digest para o declarar igual. Contas, concessões People e ficheiros de password conservaram os mesmos hashes de tabela/ficheiro nessas comparações.

O laboratório deste PC já tinha People instalado de uma sessão anterior da mesma executante. Não se importou a base, LAB-B, revisão ou configuração do PC de Atchutchi. A comparação física dos 22 originais reais **Não executada** neste PC.

## Isolamento

`Get-NetTCPConnection` mostrou apenas `127.0.0.1:8888` (PHP) e `127.0.0.1:33317` (MySQL). Sem publicação LAN observada. O anónimo em `/` recebeu 302 para login. GET anónimo a `/funcionarios/` devolveu 404 (guarda People dos caminhos reservados, alinhada com o fix I1 da aceitação F1-07).

## Matriz

Cada linha usa Passou / Falhou / Não executado. Comandos e prova resumidos. SHA de código: `fffef583`.

| ID | Percurso | Resultado | Prova |
| --- | --- | --- | --- |
| F116-01 | SHA, versões, loopback | Passou | `git fetch`; `node -v`; `php -v`; `wp cli version`; listeners 127.0.0.1 |
| F116-02 | Baseline imutável antes de escrever | Passou | `baseline-before-sync.json`: 22 tabelas, hashes de opção por nome, config por hash/tamanho/data |
| F116-03 | Pré-requisitos F1-03/F1-05 e People já presentes | Passou | Tabelas `wp_arn_authz_*`, `wp_arn_org_*` e `wp_arn_people*` existentes; LAB-A/B não foram reinitializadas |
| F116-04 | Repetir `LocalPeopleInstaller::install()` duas vezes | Passou | WP-CLI: «Instalador People idempotente: 3 perfis, 3 páginas.» `created` vazio na segunda chamada |
| F116-05 | Helper que hasheava todas as tabelas WP na repetição | Falhou | `prepare-people.php` recusou porque a sessão CLI altera `wp_usermeta`/`wp_options`. Não é prova de mutação People |
| F116-06 | `php vendor/bin/phpunit --filter People` | Passou | 35 testes, 242 asserções, 0 falhas |
| F116-07 | PHPCS People + portal + testes | Passou | 26/26 |
| F116-08 | `npm test` | Passou | 55 testes Node |
| F116-09 | `npm run lint` e `npm run build` | Passou | Fundação completa; sem pacote de blocos |
| F116-10 | Integração exclusiva `tests/integration/people.php` | Falhou | `RuntimeException: Persistência local indisponível` em `WpdbLocalUnitRepository::snapshotInTransaction` |
| F116-11 | Integração exclusiva `tests/integration/people-installer.php` | Falhou | `Alteração People recusada. Recarrega a página.` em `WpdbPeopleRepository::updateOwn` |
| F116-12 | Causa SQL no segundo PC | Passou (diagnóstico) | `SELECT @@session.in_transaction` → MySQL 1193 Unknown system variable `in_transaction` (8.0.44). Variável existe em MariaDB, não neste MySQL |
| F116-13 | Login Playwright nativo Alfa | Passou | Chromium, `f107.alfa.teste`, sessão em `/wp-admin/profile.php` e «Howdy, f107.alfa.teste» |
| F116-14 | Directório: três pessoas, filtros visíveis, sem formulário alheio | Passou | Lista Alfa/Beta/Responsável A, «3 pessoas», inputs Nome/Unidade/Cargo, sem `name=phone` |
| F116-15 | Login HTTP Beta e responsável A (sessão anterior deste PC) | Passou | `accept-http.php` 16/10: `login-f107.beta.teste` e `login-f107.responsavel.a.teste` |
| F116-16 | Equipa do responsável A: duas pessoas, sem Beta | Passou | HTTP 16/10 `team-manager-two`; Playwright da equipa A **Não executado** nesta sessão (falha de escrita MySQL bloqueou o circuito completo) |
| F116-17 | Equipa recusada a Alfa | Passou | Playwright GET `/minha-equipa/` → `net::ERR_HTTP_RESPONSE_CODE_FAILURE` (404 vazio). HTTP 16/10: `team-alfa-404` e `team-beta-404` |
| F116-18 | Perfil próprio: só telefone/local/apresentação | Passou | Labels Telefone, Local de trabalho, Apresentação; campos `phone`/`office`/`bio`; sem `input[name=job]` |
| F116-19 | Gravação, CAS, no-op | Falhou | HTTP 16/10: POST próprio devolveu 200 em vez de 303. Integração exclusiva falha na mesma transacção. Causa: F116-12 |
| F116-20 | Nonce inválido, campos extra, HTML literal, CR isolado, limites | Passou | HTTP 16/10: 404 vazio ou erro recuperável sem escrita profissional |
| F116-21 | Suspensão/reactivação e sessão antiga | Não executado | Bloqueado pela falha de transacção; não se misturou outra causa |
| F116-22 | Gate People off/on com hash exacto | Não executado | Mesma dependência de escrita estável |
| F116-23 | REST páginas/block-renderer/pesquisa | Parcial | HTTP 16/10: block-renderer e search REST sem contactos; páginas REST com nonce WP-CLI devolveram 403 (`rest_cookie_invalid_nonce` provável). Sem alegar prova completa |
| F116-24 | Isolamento HTTP/SQL loopback | Passou | Listeners só 127.0.0.1; home anónimo 302 |
| F116-25 | Edição de contactos não altera grants/sessões | Não executado | Gravação não confirmada neste motor SQL |
| F116-26 | Visual 320/390/768/1280, um h1, sem overflow | Passou | 320: scrollWidth=clientWidth=320. 390/768/1280 capturados. h1 único «Funcionários» / «Meu perfil» |
| F116-27 | Labels, alvos 44 px, inputs 16 px, fontes locais, logo | Passou | minHeight 44px; font-size 16px; Manrope + Source Sans 3; img alt «ARN, Autoridade Reguladora Nacional». Foco efectivo não medido em px nesta sessão |
| F116-28 | Teclado, menu, Escape | Não executado | Menu visível em desktop; abertura móvel/Escape não ensaiada até ao fim |
| F116-29 | CSS raiz a 200% | Passou (distinto do zoom nativo) | h1 30→60 px, corpo 17→34 px, input 16→32 px, sem overflow a 768. Não equivale a zoom nativo |
| F116-30 | Zoom nativo real 200% | Falhou / não comprovado | `Control+Equal` manteve `visualViewport.scale=1`, DPR≈1 e fontes 30/17/16 px. Igual limite do browser automatizado já registado na aceitação F1-07 |
| F116-31 | 22 originais reais | Não executado | Ausentes neste PC. Reservado a Atchutchi. Não se copiaram nem inventaram substitutos |
| F116-32 | Desenvolvimento CI People (recusa fora de local) | Não executado | Distinto do positivo local; Issue #31 de CI fica com Atchutchi |
| F116-33 | Superpowers | Não executado | Skill indisponível neste ambiente |
| F116-34 | Impeccable / UI UX Pro Max | Passou (orientação) | DESIGN.md/PRODUCT.md. UI UX Pro Max: labels associadas, `role=alert` em erros, foco visível, alvos tácteis. Sem novo tema |

## Bug de produto reproduzido (não corrigido nesta PR)

O repositório consulta `SELECT @@session.in_transaction` para recusar leituras fora de transacção. No MySQL 8.0.44 desta máquina a variável não existe (erro 1193). MariaDB 11.8 do outro PC aceita-a. Efeito: `snapshotInTransaction` e `updateOwn` falham; o instalador exclusivo não completa a edição de contacto; o POST de perfil no WordPress real não confirma 303.

Proposta de correção (stash local, PR própria futura, não misturada aqui): profundidade de transacção na ligação `IdentityWpdb`, sem `information_schema.innodb_trx` (exige PROCESS) e sem variável só da MariaDB. Reproduzir RED neste MySQL 8 e GREEN no mesmo ensaio exclusivo.

## Limites

- Sem Docker/wp-env, IIS, WSL, produção, .205/.151, AD, DNS, Google Workspace ou email.
- Sem certificação de acessibilidade nem homologação MySQL 8.4.
- Capturas, dumps, passwords, cookies e snapshots ficam fora do Git.
- O menu mostrou «Sample Page» (página nativa) e duplicou ligações People; as guardas HTTP da equipa a Alfa continuaram a recusar.
- `wp_options` mudou só em transientes/cron; causa não atribuída ao delta noutro PC.

## Próximos passos para Atchutchi

1. Rever esta PR documental. Não integrar correcções de produto por aqui.
2. Se aceitar o diagnóstico MySQL 8, pedir PR própria com o stash `IdentityWpdb::inTransaction` e regressão, sem alterar README/AGENTS nesta entrega.
3. Repetir no PC dele apenas o runtime existente, sem reinicializar dados, se quiser comparar o mesmo SHA.
4. A Issue #29 (especificação F1-13) segue em PR separada, sem implementação.
