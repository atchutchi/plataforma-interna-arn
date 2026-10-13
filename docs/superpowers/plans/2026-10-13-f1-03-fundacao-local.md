# Plano de implementação da fundação local F1-03

> Para agentes: executar por tarefas com superpowers:subagent-driven-development ou superpowers:executing-plans. A continuação da sessão autoriza trabalho local, commits, push e integração após revisão e CI verde no SHA exacto. Não autoriza concessões institucionais ou deploy.

Objectivo: motor de autorização puro e ensaios WordPress locais com fixtures.

Arquitectura: inputs imutáveis e catálogo fechado no namespace Authorization. Adaptador WordPress de leitura sob flag local. Nenhuma concessão aplicada ao CMS ou a conta real.

Stack: WordPress 7.1.3, PHP 8.4, MariaDB 11.8 local, Node 24.21.0. Sem dependências novas.

Especificação: docs/superpowers/specs/2026-10-13-f1-03-fundacao-local-design.md.

## Global Constraints

- Manter WordPress, plugin ARN, autoloader PSR-4 e namespace Arn\Intranet\Authorization.
- Inputs imutáveis. Nenhum input contém email, senha, token ou cargo nominal.
- Não alterar roles, vínculos ou sessões Google existentes. Não criar concessões institucionais, persistência ou F2.
- ARN_ALLOW_LOCAL_AUTHORIZATION_PROBE tem de ser booleano true e ambiente efectivo local. Página exige manage_options no momento de renderizar.
- Textos de interface em pt-PT. Nenhuma acção de concessão ou prova de autorização fornecida pelo browser.

## Review Focus

- Concessão válida seguida de item mal tipado ou de outra conta não pode conceder acesso. Testar na tarefa 1.
- Duas concessões parciais não cobrem um objecto com vários alvos. Testar na tarefa 1.
- Institutional não contorna propriedade própria ou aprovação da própria notícia. Testar na tarefa 1.
- Delegação próprio é relativa ao destinatário e não permite promoção do actor. Testar na tarefa 1.
- Página local sob flag tem de desaparecer em development/production e recusar subscriber ou anónimo. Testar na tarefa 2 e Playwright na tarefa 3.

### Task 1: Motor puro de autorização e delegação

Ficheiros novos em wp-content/plugins/arn-intranet-core/src/Authorization: AccountState.php, Role.php, Action.php, ScopeType.php, Scope.php, Account.php, Grant.php, Request.php, AuthorizationPolicy.php, Delegation.php e DelegationPolicy.php. Testes em tests/phpunit/AuthorizationPolicyTest.php, AuthorizationValuesTest.php e DelegationPolicyTest.php.

- [ ] Escrever testes das decisões válidas, recusas e invariantes antes das classes. Executar com .git/tools/php/php.exe vendor/bin/phpunit -c phpunit.xml.dist --filter Authorization ou Delegation. Registar RED por ausência de comportamento.
- [ ] Implementar os contratos exactos da especificação. Usar Scope::covers(Scope required, int user_id, int owner_id): bool, Grant::validAt(int now): bool e Delegation::validAt(int now): bool como fronteiras reutilizáveis.
- [ ] Verificar os cinco focos aplicáveis, Grant no fim exacto de vigência, conta pendente/suspensa, sessão inválida, módulo ausente, versão alterada, alvo desconhecido, auto-gestão e pacotes sem acções. Recusas não deixam de validar a colecção por encontrarem antes uma concessão válida.
- [ ] Executar testes dirigidos e PHPCS dos ficheiros. Relatar comandos, resultados RED/GREEN e limites. Commit só destes ficheiros e testes. Revisão de tarefa antes da tarefa 2.

### Task 2: Cenários e página administrativa local de leitura

Criar AuthorizationFixtures.php e AuthorizationProbe.php no mesmo namespace. Modificar apenas arn-intranet-core.php para registar o adaptador e .wp-env.json para a flag local. Criar testes AuthorizationFixturesTest.php e AuthorizationProbeTest.php. Não tocar na integração OIDC.

- [ ] Escrever e executar RED para pelo menos 14 cenários consistentes e para gate local/flag/capacidade.
- [ ] Implementar fixtures usando os contratos da tarefa 1. Expor texto, decisão esperada e obtida. Garantir determinismo com relógio recebido, sem leitura de contas reais ou escrita em DB.
- [ ] Registar ferramenta local de leitura. Verificar ambiente, flag e capacidade novamente antes de imprimir. Escapar a saída. Texto em pt-PT, tabela com cabeçalhos e legenda textual. Nenhum POST ou input para fabricar provas.
- [ ] Executar os testes dirigidos e PHPCS. Commit apenas dos ficheiros desta tarefa. Relatar interface concreta usada e prova de gate. Revisão de tarefa antes da tarefa 3.

### Task 3: Validação do laboratório, documentação e integração

Criar docs/autorizacao-local-f1-03.md. Actualizar apenas o estado/evidência F1-03 no README e AGENTS. Guardar artefactos de browser em output/playwright ignorado.

- [ ] Activar a flag apenas na instalação portátil local, sem expor configuração protegida. Playwright: administrador Google na página, todos os casos coincidem, página inacessível a anónimo/subscriber, navegação Google e ícone preservados. Não interferir em alterações do editor que o utilizador tenha abertas.
- [ ] Executar suite PHP completa com OPENSSL_CONF local para fixtures RSA, PHPCS, 54 testes Node, lint/build e ensaios integrados existentes. Não alterar composer.lock.
- [ ] Registar o alcance: decisões simuladas e ferramenta local, sem suspensão ou atribuição real. F1-03 Em curso com marco local apenas. Persistência, auditoria, matrizes institucionais e integração por objecto ainda pendentes.
- [ ] Revisão independente do conjunto, correcções verificadas, commit/push, PR anexada e integração apenas após todos os checks verdes no SHA exacto. main fica limpo. Conservar a sessão de utilizador e o laboratório local.

## Execução

Continuar no clone separado já usado nesta conversa, branch feature/atchutchi-F1-03-fundacao-local. O responsável pediu avançar após a matriz proposta. As escolhas técnicas são limitadas à demonstração fictícia e não substituem as validações institucionais. Registar progresso em workspace SDD próprio desta tarefa. Não repetir tarefas concluídas.
