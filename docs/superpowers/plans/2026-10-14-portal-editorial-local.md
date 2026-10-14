# Portal editorial local Implementation Plan

> Usar superpowers:subagent-driven-development. Executar as tarefas com autonomia já autorizada, sem pausas de aprovação de rotina.

Goal: front end navegável de comunicação e biblioteca com revisão local privada dos anexos fornecidos.
Architecture: modelo/filtros/entrega no plugin, apresentação no tema de blocos, manifesto e originais fora do Git/document root.
Tech Stack: WordPress7.1/PHP8.4, CSS, JavaScript nativo existente, PHPUnit e Playwright.
Spec: docs/superpowers/specs/2026-10-14-portal-editorial-local-design.md.

## Global Constraints

Local + ARN_ALLOW_LOCAL_EDITORIAL_PREVIEW booleano true + manage_options para dados reais. Fonte fora documentroot e Git. 5categorias. Não abrir pendências institucionais ou F2. Fontes e marca existentes preservadas. URLs home_url. Modelo e nomes exactos definidos na spec.

## Review Focus

Contagem/pesquisa/metadados sem guarda; caminho ou symlink fora de files; hash/MIME inválidos; filtros arrays ou paginação fora intervalo; data ausente e notícias antigas; blocos serializados nativos e menu móvel; páginas existentes não pertencentes ao setup.

### Task1: fonte e adaptador local

Own src/EditorialPreview/*, bootstrapplugin e tests/phpunit/EditorialPreviewTest.php + integração sintética. Não editar tema/docsroot.
- [ ] Testes RED/GREEN para validação, filtros, limites, ordenação e identidade de ficheiro.
- [ ] Implementar leitor puro de manifesto e selector e adaptador WP, bloco e download com guardas antesIO.
- [ ] Integração prefixo/ficheiros sintéticos exclusivos, sem reais em CI, guardasdevelopmentfechadas.
- [ ] Foco PHPUnit/PHPCS, selfreview, commit e report API.
- [ ] Revisão independente e correcções.

### Task2: tema e navegação

Own tema wp-content/themes/arn-intranet, nãoeditarplugin. Consome contrato get_template_part/portal da spec.
- [ ] Implementar composição home/lista/detalhe/regulamentos/serviços com estadosvazios, links/filtros GET, sem HTMLinseguro.
- [ ] Preservar nativeblocktemplates/brand; CSSscopedportal/tokens, teclado/reducedmotion e reflow.
- [ ] Verificar PHP/CSS/JS e commit. Root prepara páginas/nav CLIforaGit com backup.
- [ ] Revisão independente e correcções.

### Task3: conteúdo local e QA

Root lê visualmente22anexos, prepara manifesto/originaisprivados22 (5circular/4nota/11despacho/1regulamento/1sindical), assinala datasincompletas/nomesdiscordantes, guardaflagexterna, CLIsetupidempotente com backup, testaPlaywrightadmin e fixture/anon semvazar metadados, screenshots/detector/contraste. ActualizadocsREADME/AGENTS. FulltestsPHPNodePHPCS/integração, revisãofinal, pushPRanexadaCIexacta, merge autorizado e mainlimpa. Nãoalterardadosautorização anteriores. ArquivarsóSDDactual.
