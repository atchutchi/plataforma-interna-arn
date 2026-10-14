# Unidades locais F1-05 Implementation Plan

> Usar superpowers:subagent-driven-development. Execução autónoma local autorizada pelo utilizador.

Goal: persistir e auditar uma árvore de unidades fictícias com gestão local protegida.
Architecture: regras existentes Unit/UnitHierarchy, três tabelas isoladas com revisão global bloqueada, cenários fechados e página WordPress. Cadastro/importação apenas especificados.
Tech Stack: WordPress, PHP8.4, MariaDB11.8, PHPUnit, Playwright.
Spec: docs/superpowers/specs/2026-10-14-f1-05-unidades-locais-design.md.

## Global Constraints

Só local, flag ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE booleano true e manage_options. Só LAB-ROOT/LAB-A/LAB-B. Preservar Unit/UnitHierarchy e catálogo documental. Sem efeitos em pessoas, identidades, sessões ou concessões. Instalação WP-CLI explícita. Três tabelas InnoDB próprias com schema estrito sem reparação. Mutação, revisão e auditoria atómicas sob bloqueio global. POST nonce, revisão canónica incluindo zero, cenário fechado e motivo 1..500. Sem imports reais, emails, REST, dependências ou produção. Plugin0.8.0.

## Review Focus

Concorrência de reparent não pode produzir ciclo. Falha de auditoria não pode deixar unidades/revisão alteradas. Esquema ou payload incompatível fecha leitura/escrita. Flag desligada ou actor recusado bloqueia antes de abrir persistência. Formulário antigo não reaplica nem duplica mutações.

### Task 1: regras, persistência e gestão integrada

Files: novos ficheiros em src/Organization/Storage e src/Organization/LocalUnitsPage.php (e colaborador de cenários/serviço se necessário), testes PHPUnit e tests/integration/local-units.php, bootstrap, package.json e CI. Não editar docs root.
Interfaces: consumir Unit e UnitHierarchy. Produzir LocalUnitInstaller::install():void e repository() como factory interna, snapshot com revisão e unidades, apply de cenário com revisão e contexto auditado, página slug arn-local-units e admin_post arn-local-units. Reportar API final exacta e campos de formulário para QA.

- [ ] Escrever testes e observar RED para cenários, snapshot e guarda de página.
- [ ] Implementar domínio, schema, repositório e fronteira local, com uma responsabilidade por classe e erros constantes.
- [ ] Ligar página e bootstrap0.8.0. GET sem escrita, UI e auditoria escapadas, render revalida guarda.
- [ ] Integração exclusiva comprova roundtrip, idempotência de instalação, revisão antiga, rollback por falha SQL na auditoria, bloqueio por outra ligação, schema incompatível, erros de inputs e recusa de actor. Prefixo e utilizadores removidos em finally.
- [ ] npm test, PHP completo uma vez, PHPCS dos ficheiros tocados e integração nova. Reportar comandos/resultados, self-review e commit apenas owned.
- [ ] Revisão independente de tarefa e correcções necessárias.

### Task 2: QA local, contratos e integração

Root: ler instalador/schema e instalar no laboratório, activar nova flag fora do Git, Playwright dos cenários e recusas, screenshot e responsividade. Consultar estado de Ana antes/depois para confirmar independência. Documentar contratos de cadastro/importação e limites. Actualizar README/AGENTS/roteiro. Lint/build/PHPCS global. Revisão final, push/PR anexada, CI exacta, merge autorizado e sincronizar main limpa sem deploy.
