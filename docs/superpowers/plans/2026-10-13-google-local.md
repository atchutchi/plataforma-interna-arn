# Plano de implementação do login Google local

> Para agentes: executar com superpowers:subagent-driven-development, TDD e revisão independente. A sessão autoriza commits, push e integração após CI verde no SHA exacto. Não autoriza deploy institucional.

Objectivo: homologar um cliente OIDC mantido e validar entrada Google local, callback, vínculo explícito persistente e sessão WordPress.

Arquitectura: manter o tema WordPress e o plugin institucional. Facile OpenID Client executa o protocolo e a verificação JOSE. O adaptador ARN exige state, nonce, PKCE e autorização local, sem implementar JWT próprio. A configuração protegida é JSON fora do repositório e da raiz pública.

Especificação: README, secções 7.2, 16.8 e docs/identity-persistence-f1-02.md, complementados pelo pedido explícito desta sessão para login local. PHP 8.4, Node 24.21.0, WordPress 7.1.3 e MariaDB 11.8. Nenhum email promove papéis. Nenhuma conta institucional é criada automaticamente.

Decisão: o callback local é http://localhost:8888/wp-login.php?action=arn_google_callback. HTTP exige ambiente local e flag própria. A configuração HTTPS mantém o comportamento restritivo existente.

Decisão: a primeira associação é iniciada por administrador WordPress autenticado, com nonce e conta local previamente existente. O callback Google prova a identidade. Não é suficiente coincidir email. O administrador deve confirmar a conta alvo e a activação. Uma sessão Google não concede, por si só, funções.

Decisão: no laboratório, a sessão federada dura no máximo uma hora e tem limite de inactividade de 30 minutos. Isto é uma política de ensaio local, sem encerrar a decisão institucional sobre sessões. Vínculo revogado ou inválido bloqueia a sessão. As vias password, recuperação e application passwords da conta vinculada são recusadas.

## Tarefas

- [x] Homologar Facile 1.0.0 com RSA/JWKS reais, claims inválidas, state/nonce obrigatórios e PKCE. Fixar dependências e auditar. Gerar composer.lock dentro do contentor de referência, usando CI sem segredos quando o Docker local não funcionar.
- [x] Configuração local gated e PhpOidcAttemptStore consumível, com TTL e sessão PHP dedicada, antes da troca de código. TDD.
- [x] Acrescentar consulta por conta local ao repositório existente. Implementar carregamento protegido da configuração, sessão federada, guarda de revogação e bloqueios das vias alternativas. TDD e ensaio real WordPress.
- [x] Implementar hooks de entrada/callback e associação administrativa auditada. Sem redireccionamento fornecido pelo utilizador ou tokens em mensagens/logs.
- [x] Rever o conjunto e testar com fornecedor assinado simulado. Concluir todo o código local antes da confirmação de criação de credenciais Google.
- [x] Preparar cliente OAuth Web dedicado no Google Cloud. A política do browser exige confirmação no momento de criar credenciais persistentes. Guardar o ficheiro descarregado fora do Git e do webroot, com permissões locais restritas.
- [x] Playwright do login Google real, callback, sessão, logout, recusa não autorizada e revogação. Segredos, códigos, state e cookies não são artefactos versionados.
- [ ] Documentar alcance da homologação local, rever e integrar PR com CI verde. F1-01/IIS/MySQL, F1-03 e F2 mantêm dependências institucionais.

Foco de revisão: callback recebido de outro browser, sessão WordPress do actor trocada durante associação, falha de auditoria, replay e configuração removida com sessões existentes. A falha deve impedir acesso.

Evidência local: docs/google-login-local-f1-02.md. O fornecedor simulado foi ensaiado no transporte PSR-18 com tokens RSA, complementado pelo fluxo Google real no browser. A criação OAuth e a associação da identidade à conta de laboratório existente receberam confirmação no momento da acção. A integração da PR permanece pendente da CI final.
