# Plano F1-03: gestão local de concessões

> Usar superpowers:subagent-driven-development. O utilizador autorizou avanço autónomo no laboratório e integração após revisão e CI.

Goal: gerir concessões fictícias no painel com auditoria, CAS e invalidação de sessões.
Architecture: catálogo fechado e adaptador administrativo sobre AuthorizationStorageService existente. Sem nova persistência.
Tech Stack: WordPress/PHP8.4/MariaDB11.8/PHPUnit/Playwright.
Spec: docs/superpowers/specs/2026-10-14-f1-03-concessoes-locais-design.md.

## Restrições

Só Ana Teste marcada, local, flags booleanas e manage_options. Preservar lab_admin e roles WordPress. Sem schema, dependências, segredos, Google, produção ou F2. GET só lê. POST nonce, revisão canónica, motivo e CAS auditado. Usar apenas employee-own e unit-manager-a. Evitar blocos de lógica duplicados com a página de estado. Preservar todos os testes existentes.

### Task 1: catálogo e ferramenta de concessões

Implementar catálogo fechado, página administrativa, bootstrap e testes dirigidos RED/GREEN. Extrair colaborador administrativo comum apenas se necessário para eliminar duplicação relevante, preservando comportamento da ferramenta de estado. Acrescentar ou estender integração real exclusiva para o novo adaptador/serviço e ligá-la ao npm/CI se novo ficheiro. Versão plugin0.5.0. Executar testes focados, suite PHP completa uma vez, PHPCS owned, self-review e commit. Relatório e revisão independente.

### Task 2: validação local e integração

Controlador executa Playwright sobre o WordPress local com a fixture existente, auditando todas as alterações sem apagar histórico. Confirma criação, substituição, revogação, CAS, invalidação da sessão, gates e responsividade. Executa verificações finais relevantes, documenta evidência e limites no README/AGENTS e docs/gestao-concessoes-local-f1-03.md. Revisão global, push/PR anexada, CI exactSHA, merge autorizado, main sincronizado e limpo. Não dar fases institucionais como concluídas.
