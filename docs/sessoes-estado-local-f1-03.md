# Estado de conta e sessões locais F1-03

14 de outubro de 2026. Continuação autorizada exclusivamente no laboratório, com contas fictícias. O desenho está em docs/superpowers/specs/2026-10-14-f1-03-sessoes-locais-design.md. Esta entrega liga o estado funcional persistente às sessões WordPress. Não atribui competências institucionais.

## Regra de sessão

Uma fixture só pode iniciar e conservar sessão quando existe, mantém a marca fictícia exacta, o ambiente efectivo é local, as duas flags de acesso fictício e armazenamento são booleanas true e a conta funcional está Active. Pending, Suspended, ausência, leitura inválida ou indisponível recusam. O fallback legado identifica candidatos mas não substitui a marca obrigatória.

O token WordPress transporta a identificação local e a revisão da conta. A guarda consulta novamente o snapshot em cada pedido. Não guarda uma decisão de autorização em cache. Uma alteração efectiva de estado ou concessão aumenta a revisão e exige novo login. Reactivar não recupera cookies antigos, mesmo que não tenham sido apresentados durante a suspensão.

A emissão é verificada na autenticação, na criação dos metadados e antes de enviar cookies. Estados não activos ou falhas destroem as sessões da fixture quando detectados. Com uma conta actualmente Active, uma geração antiga remove apenas o token apresentado, para preservar uma sessão nova válida. Pedidos já em execução não são interrompidos retroactivamente.

A recuperação de senha recusa contas não activas, incluindo chaves anteriormente emitidas. Application passwords não ficam disponíveis para estas fixtures. REST, AJAX e as páginas privadas usam a identidade revalidada. XML-RPC continua fechado. A conta administrativa legítima permanece fora dos alvos.

## Gestão e auditoria

A ferramenta Estado de contas local permite apenas suspender e reactivar Ana Teste, resolvida no servidor e marcada como fixture. Exige manage_options, ambiente e flags efectivas, POST com nonce, revisão esperada e justificação. GET não grava. Um alvo arbitrário, uma revisão desactualizada ou uma entrada inválida não podem alterar a conta.

Cada transição usa AuthorizationStorageService e ChangeContext. A alteração e o seu evento de auditoria confirmam na mesma transacção. O histórico anterior fica preservado. A invalidação dos tokens é uma consequência da revisão e não uma transacção distribuída com wp_usermeta. Leituras e pedidos recusados não criam eventos repetitivos nem aumentam a revisão.

A ferramenta anterior Persistência de acessos local continua exclusivamente de leitura. O adaptador de sessão usa uma ligação dedicada e leitura directa do repositório. Não chama a autoridade administrativa durante a resolução da identidade, evitando recursão. Não há alteração de schema, dependências, protocolo ou vínculo Google, roles nativos ou segredos. A revisão identificou a necessidade de proteger a abertura inicial da ligação dedicada contra terminação prematura e apresentação de detalhes técnicos. Essa correcção mínima exige regressão das integrações de identidade existentes.

## Evidência da entrega

A suite final passou com 491 testes PHPUnit e 1799 asserções. PHPCS verificou 107 ficheiros sem erros ou avisos. Passaram 54 testes Node, lint e build. Não foram acrescentadas dependências nem alterados lockfiles.

No WordPress 7.1.3, PHP 8.4.26 e MariaDB 11.8.3 do laboratório portátil, passaram as integrações de identidade com 21 asserções, sessão federada com 24, persistência de autorização com 97 e sessões locais com 46. As contas e tabelas exclusivas dos ensaios foram limpas. O novo ensaio valida o bootstrap real antes de compor callbacks internos para as suas fixtures, sem alterar flags ou configuração do site. O CI mantém ambiente development e não activa os gates locais.

A regressão standalone de IdentityWpdb usa class-wpdb.php real, credenciais fictícias e uma porta loopback fechada. Com WP_DEBUG e WP_DEBUG_DISPLAY activos, confirmou erro estático sem saída, restauro do handler e reporting e passagem pelo caminho de revogação. Não prova indisponibilidade simultânea de toda a base, pois a limpeza dos tokens continua dependente do armazenamento WordPress. O pedido fica recusado quando o snapshot não pode ser lido.

Playwright CLI em sessões isoladas confirmou login Active, página privada, suspensão auditada, recusa do cookie antigo e de novo login, REST e AJAX com HTTP 401. Noutro ciclo, a suspensão e reactivação ocorreram sem apresentar o cookie antigo entre ambas. O cookie continuou recusado depois da reactivação e uma sessão nova, aberta num browser isolado, permaneceu válida.

No browser do Codex, o formulário passou de revisão 12 para Suspensa 13. Uma segunda cópia do formulário, ainda com revisão 12, foi recusada. A conta e auditoria permaneceram em 13. A cópia actual reactivou a conta para revisão 14. O login fictício voltou a funcionar. Ana Teste ficou Active, com três concessões preservadas, 14 eventos e o papel nativo subscriber. Os oito eventos da fase anterior foram conservados. lab_admin não foi marcado como fixture nem recebeu conta funcional ou novas permissões.

A ferramenta recusou acesso directo de subscriber com HTTP 403, sem formulário ou dados da fixture. Um POST desse utilizador recebeu HTTP 400 porque o handler não é registado sem capacidade. Depois do logout, o anónimo foi encaminhado para o login sem dados da conta. A navegação GET e a tentativa sem capacidade não alteraram a revisão.

A página foi inspeccionada a 375, 768 e 1440 px, sem excesso de largura. Os campos mantiveram rótulos e a interface nativa conservou a marca ARN. A consola administrativa não apresentou erros ou avisos. A viewport temporária foi reposta. Num primeiro script, a saída prematura do perfil nativo provocou um erro de beforeunload do WordPress. O script passou a esperar pela inicialização jQuery da página. Os fluxos finais de login tiveram zero erros JavaScript. Os erros de recurso HTTP 403 foram esperados nos testes de recusa.

As capturas ficam fora do Git em output/playwright/estado-contas-local.png, estado-contas-mobile.png, estado-local-subscriber-recusado.png, sessao-local-activa.png, sessao-local-recusada.png e sessao-suspensa-login.png. Helpers, dados, cookies e segredos não foram versionados. Não foram exportados cookies. HTTP e MariaDB permaneceram ligados apenas a 127.0.0.1.

As revisões das duas tarefas aprovaram o resultado após a correcção da ligação inicial e a cobertura de uma conta comum sem marca. A revisão global e a CI no SHA exacto são condições da integração e ficam registadas na PR. A validação local não substitui essa execução.

## Próximos marcos e limites

Esta é a ligação local do estado às sessões fictícias. A aplicação institucional exige a matriz validada, procedimento de delegação e recuperação, autorização por objecto e homologação de infraestrutura. O laboratório portátil continua a usar MariaDB e não comprova IIS/MySQL 8.4. F1-03 mantém o estado Em curso. As fases dependentes de dados reais ou de decisões RH/DCSI não são dadas como concluídas por estes ensaios. F2 continua condicionado pelos pré-requisitos documentados.
