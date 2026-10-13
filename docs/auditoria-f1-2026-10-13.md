# Auditoria F1 em 13 de outubro de 2026

Executante: Codex a pedido de Atchutchi. Fonte: GitHub, main após PR #3, commit 2e743c16013e698535e2748bb79c4aa417c5f2b3. README lido integralmente, AGENTS, árvore, branches, commits, PRs e Actions inspeccionados. Nenhuma alteração a produção.

A branch de Albertina4264 está integrada pela PR #2. A branch docs/servidor-fora-dominio aponta ao mesmo commit anterior de main e não contém uma correcção adicional de infraestrutura. A branch de validação de Atchutchi acrescentava apenas composer.lock. A PR #3 foi integrada após CI verde no SHA a230ffd4e4eee55a9ec67fba8493d50f39f072fe. main não tem protecção de branch activada no momento da auditoria. A recomendação de protecção existente no README ainda não é configuração real.

| ID | Estado real | Código existente | Testes existentes | Dependências | Bloqueios | Próxima acção |
| --- | --- | --- | --- | --- | --- | --- |
| F1-01 | Em curso, marco local validado pelo responsável | wp-env, scripts, plugin, autoloader, tema mínimo, composer.lock | 54 Node e 63 PHPUnit/284 asserções. PHPCS 28/28 fornecido por Atchutchi. CI Node/PHP verde | F0-03/04, INF-06 para homologação | Segundo PC e Windows/IIS/MySQL 8.4 sem homologação | Preservar fundação e preparar ensaios representativos quando autorizados |
| F1-02 | Por iniciar | Sem Identity, cliente OIDC, vínculo persistente ou callback | Nenhum teste OIDC | F1-01, F0-04 | Cliente institucional, credenciais, callback HTTPS e homologação do candidato | Implementar contratos e regras locais testáveis sem segredos. Não activar login real |
| F1-03 | Por iniciar, apenas guardas preliminares | LocalAccessPolicy, LocalFixtureAccess, PrivatePortalPolicy e PrivatePortal. Sem matriz real | Testes de fixture, REST após nonce, AJAX, XML-RPC e política privada | F0-02/06, F1-02, DEC-15 | Matriz funcional, âmbitos e ciclo de conta por validar | Detalhar funções e autorização por objecto. Não deduzir papéis de emails |
| F1-04 | Por iniciar | Sem módulo Audit ou Notifications | Nenhum | F1-03 | Autorização e modelo de eventos | Implementar auditoria e eventos após contratos de acesso |
| F1-05 | Preparação parcial, tarefa não concluída | DocumentaryCatalog, Unit, UnitHierarchy, UnitType, ParentStatus. 37 unidades em memória. Sem persistência, funcionários ou CSV | DocumentaryCatalogTest e UnitHierarchyTest | F0-01, F1-03/04 | PEN-01/05 e autorização | Validar modelo persistente, cadastro e importação fictícia. Preservar duas DGE e nomes pendentes |
| F1-06 | Fundação mínima, tarefa por iniciar | Tema de blocos, theme.json, templates, header/footer, aviso local e CSS | Contrato estrutural Node. Verificações pontuais do tema históricas. Sem testes de navegador | F1-01/03 | Contratos de acesso, conteúdo e identidade visual final | Evoluir navegação e componentes editáveis após acesso definido |
| F1-07 | Por iniciar | Sem directório ou autosserviço de perfil | Nenhum | F1-05/06 | Cadastro, campos públicos autorizados e permissões | Implementar pesquisa e perfil depois do cadastro |
| F1-08 | Por iniciar, requisito de segurança | Sem armazenamento privado, entrega autenticada ou protecção de uploads estáticos | Testes PHP do portal não provam privacidade de binários | F0-03/06, F1-03/04 | Configuração local/IIS, política de anexos e autorização | Guardar fora da raiz pública e testar URL, API, derivados e objecto |
| F1-09 | Por iniciar | Sem Documents, catálogo ou versões | Nenhum | F1-08, F0-05 | Ficheiros privados e fonte documental | Implementar metadados e versões com entrega privada |
| F1-10 | Por iniciar | Sem Editorial, tipos de notícia ou submissão | Nenhum | F1-04/06/08 | Auditoria, tema e ficheiros | Implementar rascunho, galeria e submissão |
| F1-11 | Por iniciar | Sem fila, máquina editorial ou aprovação de versão | Nenhum | F1-10, F0-05 | Equipa e PEN-07 | Implementar decisões e reapreciação sem publicação directa por autor |
| F1-12 | Por iniciar | Sem comunicados ou Calendar | Nenhum | F1-04/06, F0-05 | Públicos e responsáveis | Implementar comunicados e eventos conforme decisão funcional |
| F1-13 | Por iniciar | Sem Tasks | Nenhum | F1-04/05/06 | Equipa, autorização e critérios | Implementar tarefas com histórico e âmbito |
| F1-14 | Por iniciar | Front-page mínima sem agregação real ou pesquisa | Sem testes do painel funcional | F1-07/09/11/12/13 | Módulos anteriores | Agregar apenas dados autorizados e módulos activos |
| F1-15 | Por iniciar | CMS nativo e aviso local. Sem parâmetros institucionais ou administração funcional | Sem testes destas operações | F1-05/09/11/12/13 | Módulos e competências | Expor gestão autorizada sem editar código |
| F1-16 | Por iniciar | CI de código e guardas preliminares, sem restauro ou validação completa | Unitários não equivalem a homologação, acessibilidade ou restauro | F1-14/15, INF-09, PEN-08/09/10 | Ambiente representativo, dados e políticas | Ensaiar segurança por objecto, teclado, cargas e restauro isolado |
| F1-17 | Por iniciar | Sem pacote operacional ou formação | Nenhuma aceitação funcional | F1-16, INF-10, F0-07 | Homologação, responsáveis e plano | Preparar formação e disponibilização após aceitação. Sem deploy agora |

Os testes PHP usam doubles WordPress em memória. O workflow executa PHPUnit e PHPCS com PHP 8.4 e os comandos Node na versão fixada. Não executa WordPress integrado, Google real ou IIS. O comando build verifica o contrato estrutural e não compila uma interface final.

Férias: apenas especificação actualizada no README. Não existe módulo F2. A proposta de bloquear sobreposição pendente, tratamento entre anos, classificação de gozo e saldo de 31 de dezembro precisam de validação RH antes de implementação.

Continuação permitida: preparação técnica testável de F1-02 sem segredos e sem activar autenticação. A homologação F1-01/F0-04 continua a bloquear activação institucional de F1-02. Não declarar estas dependências cumpridas apenas porque é possível escrever testes isolados.
