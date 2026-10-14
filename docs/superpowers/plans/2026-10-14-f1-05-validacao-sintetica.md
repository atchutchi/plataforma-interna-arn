# Pré-validação sintética F1-05 Implementation Plan

> Usar superpowers:subagent-driven-development. Avanço autónomo explicitamente autorizado no laboratório.

Goal: validar CSVs sintéticos e apresentar propostas e auditoria em memória, sem persistência.
Architecture: parser e validador puros, catálogo sintético fechado, snapshot de unidades apenas leitura e adaptador administrativo protegido. Sem caminho de commit/import.
Tech Stack: WordPress/PHP8.4, PHPUnit, MariaDB11.8 apenas leitura depois de setup exclusivo, Playwright.
Spec: docs/superpowers/specs/2026-10-14-f1-05-validacao-sintetica-design.md.

## Global Constraints

Somente local + manage_options + duas flags booleanas ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE e ARN_ALLOW_LOCAL_IMPORT_PREVIEW. CSV apenas de cenário servidor. UTF-8/BOM, vírgula, aspas rigorosas, máximo16384bytes/20registos. Header professional_id,name,email,unit_id. Só LAB-PER-001..999, Pessoa fictícia e example.invalid. Sem upload, gravação de dados/auditoria/logs/sessões/transients ou aplicação de propostas. Snapshot unidades apenas leitura, não instalar schema. Não tocar Google, autorização, sessões e catálogo documental. Plugin0.9.0.

## Review Focus

CSV malformado não pode ser aceite por tolerância do parser. Duplicados invalidam todas as ocorrências. Payload arbitrário/ficheiros e pedidos recusados não abrem leitura. Relatório fica ligado ao snapshot e não atribui autoridade de importação. Nenhum caminho de validação escreve DML/DDL ou faz log de dados.

### Task 1: validador e ferramenta de pré-visualização

Own novos ficheiros em src/Organization/ImportPreview (parser, catálogo/cenários, relatório/validador e fronteira/página conforme responsabilidade), bootstrap0.9.0, testes PHPUnit, tests/integration/import-preview.php, npm e CI. Não editar docs root.
Interfaces: parser CSV estrito com linhas iniciais; validador consome CSV, catálogo sintético e LocalUnitSnapshot; relatório imutável com linhas, totais, hashes e identidade. Página arn-local-import-preview e nonce homónimo. Default snapshot reader invoca apenas repository()->snapshot(). Reportar API e campos exactos ao root.

- [ ] TDDRED parser/validador e guardas. Cobrir aspas escapadas, quoted newline, malformed quotes, BOM, UTF8 inválido, limite exacto e excedido, duplicados, conflitos, unidade inactiva, contagem create/update/unchanged/invalid e digest determinístico.
- [ ] Implementar funções puras sem IO persistente e dados fechados. Não usar parser permissivo sem validação lexical.
- [ ] Página carrega/prepara POST antes do header, guarda e nonce/campos antes de snapshot, render revalida. Formulário sem inputs livres e resultado apenas da resposta actual. Escaping e no-store. Sem apply endpoint.
- [ ] Integração com prefixo exclusivo e setup sintético. Bloquear DML/DDL durante validação e comprovar estado idêntico. local/development CLI admin, sem abrir guardas app em CI. Cleanup finally só recursos exclusivos.
- [ ] PHP completo uma vez com OPENSSL_CONF local se necessário, Node54, integração nova e PHPCS owned incluindo bootstrap. Selfreview e commit owned, relatório com comandos/outputs e API.
- [ ] Revisão independente e correcções necessárias.

### Task 2: ensaio local e integração Git

Root prepara só flag externa booleana, captura baseline das tabelas existentes, Playwright com sessão administrativa já autorizada, casos positivos/negativos/responsividade e screenshots. Sem criar contas para o importador. Provar ausência de escrita e auditoria apenas no relatório, não na base. Actualizar docs/README/AGENTS/roteiro. Lint/build/PHPCS global, revisão final, push/PR anexada, CI exacta, merge autorizado, main sincronizada e limpa. Sem deploy.
