# F1-04 notificações locais Implementation Plan

> Use superpowers:subagent-driven-development. Avanço autónomo local autorizado pelo utilizador.

Goal: primeira parcela local de F1-04, notificações internas derivadas de eventos auditados confirmados e recibo persistente idempotente.
Architecture: projecção só de eventos autorizados da conta, tabela própria de recibos, fronteira de sessão partilhada com registos e página administrativa com POST protegido. Sem dispatcher externo.
Tech Stack: WordPress/PHP8.4/MariaDB11.8, PHPUnit, Playwright.
Spec: docs/superpowers/specs/2026-10-14-f1-04-notificacoes-locais-design.md.

## Global Constraints

Só Ana Teste exacta marcada, local e duasflagsbooleanas, snapshotActive/revisãosessão válida, semexcepçãolab_admin. Eventosconfirmadosstate_changed/grant_created/grant_replaced/grant_revoked. Nãoexpormotivos/actor/payloads. Lista20+1 comcursor e filtroSQLuser/event. Tabela própriarecibos(user_id,event_id,read_at),schemaestrito/idempotente/explícitoCLI. POSTnonce/revisão/idcanónicos,verificaeventoalvo/catalogo e Active/revisãocomlockna transacção. Recibo não alteraauditoria/concessões/revisão e preservaprimeirotimestamp. Sememails,REST,Google,tema,dependências,produção,F2. Plugin0.7.0.

## Review Focus

Dados deoutra conta no ID/cursor não podemfugir. Falha SQL/DDL ou esquema incompatível não pode operar parcialmente. Revogação/suspensão ou sessão antiga entre página e POST bloqueiam. Reenvio/concorência não duplica nem muda read_at. Extracção comum preserva registos existentes e guardas development fechadas.

### Task 1: notificação interna integrada

Own classes Notifications e pequeno colaboradorAuthorizationSession reutilizado por LocalRecordAccess, bootstrap0.7.0, unitários, nova integraçãoexclusiva local-notifications.php, npm/CI. Nomes internos à escolha do implementador, documentar comandos de instalação explicitamente no relatório. Root ownsdocsREADME/AGENTS,roadmap/QA. Não alterar Ana persistente ou instalar tabela no prefixo normal. Integração prefixoexclusivocleanupfinally.

- [ ] TDDRED para mensagens/repositório/adaptador e casos críticos.
- [ ] Implementar schema/DAO/read-receipt, helpercorrente, UIlistaPOST e wiring.
- [ ] GREENfocado, integração nova e regressãoaccount-sessions, fullPHPuma vez,Node54,PHPCSowned.
- [ ] Selfreview,commitowned,reporttask-1 com comandos/resultados/nomeAPI/instalação.
- [ ] Revisãotarefaindependente, resolverachados.

### Task 2: laboratório e integração

Root preparatableexplicitamenteapóscódigo/revisãoinstalação, PlaywrightAna e negativos/responsividade, comprovarestadopersistente e preservaçãoauditoria. DocumentarF1-04Emcurso e roadmapF1-05/06/08compré-requisitosreais semmarcarfaseinstitucionalconcluída. Lint/build/PHPCSglobal,finalreview,pushPRattached,CIexacthead,mergeautorizado,mainlimpa. Segredosexternosinalterados.
