# F1-03 registos locais Implementation Plan

> Use superpowers:subagent-driven-development. Avanço autónomo local autorizado pelo utilizador.

Goal: comprovar que a lista e o detalhe de registos fictícios obedecem às concessões persistentes e à sessão corrente.
Architecture: catálogo puro fechado e adaptador WordPress de leitura sobre AuthorizationPolicy, AuthorizationSnapshot e LocalAccountSessionPolicy existentes. Sem novo schema.
Tech Stack: PHP8.4, WordPress, MariaDB11.8, PHPUnit e Playwright.
Spec: docs/superpowers/specs/2026-10-14-f1-03-registos-locais-design.md.

## Global Constraints

Só Ana Teste marcada, ambiente local e duas flags booleanas verdadeiras. Nenhuma excepção lab_admin. Prova de sessão nativa e revisão corrente, DAO dedicado sem cache. Catálogo proprio/unidade-a/unidade-b fechado, Action::ReadDirectory. Filtrar antes de contar. Detalhe recusado ou desconhecido404 igual. GET só lê. Sem REST novo, tema, Google, schema, dependências, F2 ou dados reais. Auditoria nas concessões existentes. Plugin0.6.0.

## Review Focus

Testar GET record como array ou string desconhecida sem fuga. Testar ambiente/flag/titular alterados após registo. Testar ausência ou erro SQL e geração obsoleta. Testar administrador legítimo excluído sem mudança de papel. Testar conjunto misto e concessões expiradas/revogadas, sem contar linhas recusadas.

### Task 1: catálogo, leitura protegida e integração

Criar classes pequenas em Authorization/Records para catálogo e selecção pura e adaptador administrativo. Nomes e composição internos definidos pelo implementador, sem modificar contratos públicos existentes. Reutilizar snapshot/policy/sessionpolicy e padrões de ligação dedicada. Registar bootstrap wp_loaded e versão0.6.0. Testes em tests/phpunit e estender tests/integration/account-sessions.php, já em npm/CI, com snapshot real e alterações auditadas. Não ligar flags development no ensaio CI. Não tocar na Ana persistente.

- [ ] Escrever testes relevantes e observar RED.
- [ ] Implementar catálogo, lista/detalhe autorizado e gates sem duplicar lógica do motor.
- [ ] Verificar GREEN focado, integração real61+novos, suitePHPcompleta uma vez, PHPCS owned e Node54.
- [ ] Self-review, commit só ficheiros owned e relatório com comandos/resultados e nomes de classes/rotas para QA.
- [ ] Revisão de tarefa independente e resolver achados.

### Task 2: browser e integração final

Controlador preparaQA de leitura com fixtureAna, alterações auditadas pelo painel existente e sem apagar histórico. Verifica own/listcount1,unidade-a404,b404,concedeA,oldsessionrefused,newloginlistcount2,A200,B404,revokeA,oldsessionrefused,newloginlistcount1,A404. Anónimo e lab_admin não vêem dados. Verifica tampering e responsividade, screenshot. Actualiza README/AGENTS e docsumária com evidência e limites. Lint/build e checks adicionais relevantes. Revisão global, push/PRanexada,CIexactSHA,mergeautorizado,mainlimpa sincronizada. ArquivarworkspaceSDD sem apagar evidência.
