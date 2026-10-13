# Plano F1-03 persistência local

Objectivo: persistir estado, funções/âmbitos e auditoria atomicamente, com prova de laboratório.

Spec: docs/superpowers/specs/2026-10-13-f1-03-persistencia-local-design.md.

Stack: WordPress/PHP 8.4, MariaDB 11.8 local, ligação dedicada IdentityWpdb. Reutilizar clone separado e branch feat/atchutchi-F1-03-persistencia-local. Sem dependências novas.

## Global Constraints

- WordPress e namespace Arn\Intranet\Authorization\Storage. Reutilizar enums/Grant/Scope existentes.
- Sem alteração de Identity, sessão Google, roles nativos, concessões reais, produção ou F2.
- Operações locais WordPress exigem local, ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE exacttrue e conta alvo marcada LocalFixtureUser::META_KEY.
- Actor corrente manage_options apenas como autoridade técnica de laboratório, sem função institucional inferida.
- Auditoria e mudança na mesma transacção; revisão positiva com CAS, sem reconnect, cache ou grants parcialmente hidratados.
- Sem endpoints de escrita ou dados de funcionários no Git. Interface só lê e revalida gates.

## Review Focus

- Item inválido no fim de snapshot recusa tudo. Valores/DAO: tarefas1/2.
- Auditoria falhada não deixa estado, concessão ou revisão alterados. Integração: tarefa2.
- Duas ligações com revisão igual: a segunda escrita recusa, sem evento parcial. Tarefa2.
- Scope JSON object com chaves numéricas não é aceite como array, IDs float e overflow recusam. Tarefas1/2.
- Schema incompatível/perda de ligação não cria acesso ou migra silenciosamente. Tarefa2.

### Task 1: Valores, contratos e serviço

Criar src/Authorization/Storage/ChangeContext.php, StoredGrant.php, AuthorizationSnapshot.php, ScopeCodec.php, AuthorizationStorageAuthority.php, AuthorizationRepository.php e AuthorizationStorageService.php no plugin. Criar tests/phpunit/AuthorizationStorageValuesTest.php e AuthorizationStorageServiceTest.php. Contratos exactos na spec, sem WordPress nestes ficheiros.

- [ ] RED antes de classes. Casos de contexto inválido, snapshot misto/duplicado/referências externas, Scope JSON object/float/nomes desconhecidos e roundtrip canónico.
- [ ] Serviço verifica autoridade do actor e fixture alvo antes de encaminhar, parâmetros/limites e grant novo não revogado. Dados de repo são internos, nunca inferir concessões por email. Testar recusa e nenhum encaminhamento a repo antes da autoridade.
- [ ] GREEN dirigido e PHPCS. Commit apenas estes ficheiros. Report task-1-report.md com API e RED/GREEN. Revisão independente antes da tarefa2.

### Task 2: Repositório SQL e integração real

Criar src/Authorization/Storage/WpdbAuthorizationRepository.php e, se útil para separar schema, AuthorizationSchema.php. Criar tests/integration/authorization-persistence.php. Alterar só package.json para test:authorization:integration e .github/workflows/verificacao.yml para executar esse script depois do teste OIDC. Usar APIs definitivas do report1 e spec.

- [ ] Escrever ensaio integrado RED antes do DAO, com contas/tabelas exclusivas e guarda CLI local/development/manage_options. Não ler configs ou segredos em ferramentas. Credenciais vêm das constantes WordPress em execução.
- [ ] Implementar versão1/4tabelas, validação estrutural rigorosa, valores preparados e transacções com conta bloqueada antes de grants. Schema inválido não se repara. Reutilizar IdentityWpdb sem a alterar e sem ligação global.
- [ ] Exercitar CRUD histórico, CAS com duas ligações, trigger de auditoria para rollback, perda de ligação/erro de query, dados inválidos por SQL nas tabelas exclusivas, references/FK, grants mistos e leitura pelo motor. Testar states/no-op/revisão e substituição atómica. Nunca imprimir SQL/last_error ou dados de contas reais.
- [ ] GREEN integrado e PHPCS. Commit ownedfiles. Report task-2-report.md com comandos/resultados/limites e contratos de history. Revisão independente antes da tarefa3.

### Task 3: Adaptador local, leitura e validação final

Criar src/Authorization/Storage/LocalAuthorizationStorageAuthority.php e AuthorizationStorageProbe.php, testes dirigidos para gates. Modificar bootstrap para ligar menu no momento seguro, .wp-env.json para flag local true (não .wp-env.test.json). Criar docs/persistencia-autorizacao-f1-03.md. Actualizar README/AGENTS apenas quanto ao marco persistente.

- [ ] RED/GREEN do gate exacto, actor corrente, fixture marcada e recusa de lab_admin como alvo. Menu/read-only revalida local/flag/manage_options. Saída escapada, tabela acessível, sem formulário ou input de provas. Serviço consulta apenas fixture marcada.
- [ ] Activar flag no portátil fora do Git, preparar Ana Teste pelo serviço e conservar eventos de estado/grant no prefixo local. Não escrever nas contas Google/roles. Nenhuma escrita em GET. Playwright administrador Google lê estado/concessões/auditoria; anónimo/subscriber recusados; painel e fontes preservados. Guardar capturas ignoradas.
- [ ] PHP completo com OPENSSL_CONF das fixtures, PHPCS, Node54/lint/build e os três ensaios integrados. Documentar exactamente resultados e limites. Revisão final, commit/push/PR anexada e merge só com CI verde no SHA exacto. Main limpo.

## Execução

O pedido actual autoriza este modelo local. Decisões institucionais MP e F0-02 continuam pendentes. O desenho usa a matriz como proposta para fixtures. Não apresentar a validação técnica como aprovação de funções reais. Registar progresso num workspace SDD exclusivo deste plano.
