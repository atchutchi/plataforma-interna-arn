# Persistência de vínculos e sessões institucionais

Entrega de 12 de novembro de 2026, apenas no laboratório. Dá continuidade à proposta da PR #81. Não instala componentes no servidor .205, não altera o Wamp e não transporta dados do laboratório.

## Implementação

O vínculo persistido tem uma geração positiva. A criação escreve explicitamente a geração 1. Uma alteração efectiva de estado incrementa a geração na mesma transacção que grava o evento de auditoria. Uma repetição sem alteração não incrementa. A auditoria guarda a geração anterior e a seguinte. O vínculo removido fora da aplicação não pode ser recriado pela API quando conserva histórico, evitando reutilizar uma geração anterior.

`migrateGenerations()` é uma operação explícita para o schema anterior suportado. Acrescenta as colunas em etapas verificadas e admite retoma de uma interrupção conhecida. As leituras normais recusam schemas incompletos. Os eventos históricos conservam os seus dados e recebem gerações nulas, sem inventar provas retrospectivas. O DDL não é apresentado como reversível por transacção. Ainda falta integrar esta operação num comando institucional com manutenção exclusiva, recibo e restauro ensaiado.

`PrincipalSource` relê o vínculo e a autorização antes e depois de obter a sessão nativa. Exige conta activa, vínculo autorizado, issuer/sub, geração e revisão coincidentes. Erros SQL, alterações concorrentes observadas ou metadados inválidos recusam o acesso. Não mantém decisões em cache entre pedidos.

`SessionIssuer` exige HTTPS e uma identidade previamente validada pelo chamador. Grava os metadados no token nativo, confirma a leitura e só depois emite o cookie. Uma falha elimina apenas o novo token. A emissão ainda não está ligada ao callback Google institucional. Este serviço não substitui a validação OIDC.

`WordPressFederatedAccess` aceita uma fonte institucional composta explicitamente. Uma sessão marcada como institucional nunca recua para o circuito local. Sem fonte válida, destrói apenas o token corrente. `ModuleAccess` liga essa fonte ao núcleo de autorização e às concessões persistidas. Um administrador WordPress não recebe competências de negócio por esse papel. As factories de notícias, documentos, tarefas e restantes módulos continuam por adaptar individualmente.

Reactivar uma conta ou vínculo não recupera sessões antigas. Alterar a revisão de autorização também exige nova sessão. A verificação não transforma cookies e SQL numa única transacção. As operações de negócio continuam responsáveis por confirmar a autorização corrente dentro dos respectivos contratos de escrita.

## Aceitação local

O ensaio `npm run test:institutional-identity:integration` usa contas e tabelas fictícias com prefixo aleatório e remove-as no fecho. Cobre schema novo e anterior, interrupção e retoma de DDL, preservação do histórico, geração explícita apesar de um default diferente, associação inicial, emissão nativa, concessões, revogação, suspensão, concorrência observada, compensação limitada ao token novo, falha de auditoria com rollback e recusa de recriação com histórico.

Resultado local: 35 verificações integradas, 1228 testes PHPUnit com 6067 asserções e sete ignorados, 72 testes Node, lint e build sem erros. PHPCS passou nos 46 ficheiros de identidade e testes abrangidos. Os seis cenários Playwright passaram e as fixtures HTTP foram removidas. O teste foi incluído na CI de integração. A descoberta portátil encontrou os 148 ficheiros PHPUnit. A descoberta dentro do contentor Windows apresentou uma montagem incompleta e não foi aceite como execução da suite completa. A CI Linux deve confirmar a suite sobre o commit publicado.

Playwright foi executado num WordPress isolado em localhost:8890. O circuito privado de ensaio compõe a fonte institucional com uma conta fictícia e verifica acesso sem concessão, acesso autorizado, revogação, reactivação sem recuperação do token anterior, nova sessão e suspensão. O transporte HTTP do laboratório não constitui prova de HTTPS ou de login Google real. Não se publica o estado do browser, cookies ou o preparador privado das fixtures.

Uma revisão independente identificou dois problemas na geração inicial e na dependência do default SQL. Ambos foram corrigidos e receberam cobertura integrada. A revisão da composição na guarda de sessões não identificou bloqueantes. A implantação e a aceitação institucional permanecem pendentes.

## Próxima parcela

Compor o callback e a configuração institucional de identidade. Preparar a migração operacional sem fixtures. Depois adaptar e ensaiar cada módulo com a fonte comum, começando pelos actores e unidades persistentes. Conservar as guardas existentes até cada fluxo ter instalação, concessões, leitura e escrita aceites. Não activar opções de laboratório no servidor.
