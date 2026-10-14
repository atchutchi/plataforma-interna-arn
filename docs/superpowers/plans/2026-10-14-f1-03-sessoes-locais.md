# Plano F1-03: estado e sessões locais

> Usar superpowers:subagent-driven-development para implementar e rever cada tarefa. O responsável autorizou avanço autónomo no laboratório. Não repetir pedidos de aprovação técnica dentro desse âmbito.

Goal: bloquear contas fictícias não activas e invalidar gerações antigas de sessão com transições auditadas e gestão local controlada.

Architecture: política pura e guarda WordPress em Authorization/Session. Consumir snapshots do DAO existente numa ligação dedicada. A revisão é a geração da sessão. A gestão usa o serviço administrativo existente.

Tech Stack: WordPress 7.1.3, PHP8.4, MariaDB11.8, PHPUnit e Playwright.

Spec: docs/superpowers/specs/2026-10-14-f1-03-sessoes-locais-design.md.

## Restrições

Só laboratório e fixtures explicitamente marcadas. Não consultar segredos, alterar Identity/OIDC ou schema, promover utilizadores ou começar F2. Sem nova dependência. PHP_INT_MAX dos filtros, após guardas existentes. Bootstrap antes da determinação da identidade. Não tratar auditoria de estado como prova de logging de cada pedido recusado. Não revogar sessão nova por apresentar uma geração antiga.

### Task 1: política e ligação às sessões

Criar LocalAccountSessionPolicy.php e WordPressLocalAccountSessions.php em src/Authorization/Session, testes dirigidos em tests/phpunit e registar no bootstrap. Versão plugin0.4.0.

- [ ] Testes RED das APIs exactas da spec, estados/revisões/flags, identidade e hooks. Defaults usam ligação dedicada apenas para fixtures.
- [ ] Implementar GREEN, incluindo send_auth_cookies, reset e application passwords. Não cachear decisões.
- [ ] PHPCS owned, report detalhado de APIs/resultados e commit. Revisão independente antes da fase seguinte.

### Task 2: integração real e ferramenta de estado

Criar tests/integration/account-sessions.php, script test:account-sessions:integration e etapa CI. Criar src/Authorization/Session/LocalAccountStatePage.php com menu e POST guardado. Bootstrap liga wp_loaded/admin_post no momento apropriado. Novos testes PHPUnit para entrada/nonce/CAS/alvo fixo, sem mudar a ferramenta de leitura antiga.

- [ ] RED/GREEN com WP tokens/cookies reais, prefixo aleatório, contas descartáveis e limpeza em finally. Cobrir transições/revisão/erro/ausência/estadoSuspended/Reactivate/noresurrection e conta legítima preservada.
- [ ] RED/GREEN do POST: método, nonce, capacidade, flags, revisão, justificação, estado, fixture. GET nunca escreve. UI usa texto ptPT e markup nativo acessível.
- [ ] Commit e revisão. Não preparar contas reais ou exportar cookies.

### Task 3: laboratório, Playwright e integração

O controlador prepara helpers ignorados e usa a fixture existente pelo serviço. Testa ponta a ponta e conserva histórico. Actualiza README/AGENTS e docs/sessoes-estado-local-f1-03.md com resultados e limites. Executa suites PHP/PHPCS/Node e quatro integrações WP. Verifica UI local e cookies em browser isolado, sem Google novo ou contas reais. Revisão global, push/PR anexada, integração só com exactSHA CIverde. Main sincronizado e limpo. Avaliar próximos marcos contra dependências reais, sem declarar a plataforma institucional concluída.
