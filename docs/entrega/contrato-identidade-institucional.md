# Identidade institucional: contrato e aceitação

Preparação sobre main 01c5412, em 9 de novembro de 2026. Este contrato orienta a implementação. Não activa login institucional, não migra contas e não concede funções. Os ensaios usam identidades inventadas e um fornecedor OIDC simulado.

## Fronteira actual

GoogleLoginController::configuration fecha fora de local. CurrentLocalAccount resolve uma fixture por login fixo e LocalAccountSessionPolicy exige arn_authz_local. WordPressFederatedSession guarda metadados OIDC e, no circuito de pré-cadastro, uma revisão própria. Estes mecanismos não constituem uma fonte institucional comum.

LocalIdentityLink contém user_id, issuer, subject, authorized e active. Não contém uma geração persistente do vínculo. Comparar apenas issuer/sub não impede que uma sessão antiga volte a ser aceite depois de revogar e recriar o mesmo vínculo. A implementação deve tratar essa geração antes de abrir o circuito institucional. Não remover as guardas locais para contornar esta lacuna.

## Contrato de leitura

Institutional/Principal representa uma decisão corrente e imutável: identificador WordPress positivo, identidade canónica, geração persistente do vínculo e revisão da autorização. A fonte não recebe email, função, unidade ou identificador de actor a partir de parâmetros do browser. Resolve o utilizador efectivamente autenticado e os metadados do seu token nativo.

Institutional/PrincipalSource expõe current(): ?Principal. Ausência de configuração, schema incompatível, conta inexistente, vínculo revogado, fonte indisponível ou sessão inválida devolvem ausência de principal. Erros técnicos podem produzir diagnóstico controlado sem tokens, emails ou claims. Nunca usar um snapshot antigo como alternativa a uma falha SQL.

A leitura exige conta Active, vínculo autorizado e activo, correspondência do titular e das gerações e sessão federada válida. Mantêm-se os limites temporais de FederatedSessionPolicy existentes. A validade de uma sessão não concede competências. As concessões e os âmbitos continuam sujeitos ao motor Authorization em cada operação.

Uma conta válida sem concessões pode receber apenas a vista de ausência de acesso. Não pode consultar listas, contagens, perfis de terceiros ou recursos de negócio. Ser administrador técnico WordPress não cria uma excepção institucional.

## Emissão e revogação

Criar metadados distintos dos marcadores de fixture. Guardar no servidor uma versão explícita do formato, user_id, geração do vínculo e revisão de autorização. Não reutilizar arn_authz_local. Não guardar ID token, access token, refresh token ou email nestes metadados.

A geração do vínculo deve persistir e avançar em revogação e substituição. Não a repor quando se associa novamente o mesmo issuer/sub. Uma suspensão avança a revisão de autorização e invalida a geração anterior. Reactivar o cadastro profissional não reactiva a conta. A reactivação explícita da conta exige novo login e não recupera sessões anteriores.

Antes de emitir, revalidar a prova OIDC e as leituras persistentes. Depois de guardar os metadados, confirmar que a sessão contém os valores esperados e que as fontes continuam válidas. Só depois emitir o cookie. Se esta confirmação falhar, destruir apenas o token recém-criado. Uma revogação posterior deve ser detectada no pedido seguinte, antes de qualquer acesso a negócio.

As escritas do vínculo e da sua auditoria usam a mesma transacção. A sessão nativa WordPress não deve ser descrita como parte dessa transacção sem demonstração. Falhas entre a gravação da sessão e a emissão do cookie deixam, no máximo, um token sem acesso que pode ser invalidado pelo seu identificador. Não apagar todas as sessões de outras contas como compensação.

## Migração e composição

Acrescentar geração persistente ao contrato de armazenamento de identidade mediante migração explícita, versionada e verificável. Definir a representação SQL depois de rever o schema e os seus consumidores. Não actualizar por GET, activação do plugin ou carregamento do tema. DDL parcial deixa o circuito fechado. Não declarar rollback transaccional de DDL.

Instalação vazia não contém fixtures, contas, concessões ou gestores. Upgrade conserva issuer/sub, titulares e histórico. Sessões existentes não recebem metadados institucionais por inferência. Exigem novo login depois da activação autorizada. Os 73 pré-cadastros reais e os seus prazos não são renovados por esta migração.

Primeiro compor a fonte nova apenas no laboratório dedicado. Manter os consumidores locais existentes. Cada módulo institucional futuro adopta a fonte explicitamente, depois da respectiva migração e aceitação. Não adicionar uma flag que abra simultaneamente todos os módulos.

## Critérios de aceitação

| ID | Cenário | Resultado exigido |
| --- | --- | --- |
| IDI-01 | Fonte ou configuração ausente | Nenhum principal e nenhum cookie novo |
| IDI-02 | Mesmo email com issuer/sub diferente | Recusa, sem reassociação |
| IDI-03 | Conta ou vínculo inexistente, revogado ou inactivo | Recusa antes dos dados de negócio |
| IDI-04 | Token de outro titular ou metadados com tipos incorrectos | Recusa sem coerção |
| IDI-05 | Suspensão com sessão aberta | Pedido seguinte recusado |
| IDI-06 | Reactivação explícita | Sessão anterior continua recusada |
| IDI-07 | Revogar e recriar o mesmo issuer/sub | Geração antiga continua recusada |
| IDI-08 | Alterar concessões durante uma sessão | Revisão antiga não autoriza novas operações |
| IDI-09 | Revogar entre a leitura inicial e a emissão | Não emitir cookie válido, invalidar apenas o token criado |
| IDI-10 | Falha SQL ou metadados não persistidos | Fechar acesso, sem recurso a cache antiga |
| IDI-11 | Conta sem concessões | Vista sem competências, sem listas ou contagens privadas |
| IDI-12 | Administrador técnico sem concessão de negócio | Mesma recusa de negócio |
| IDI-13 | Instalação vazia e reexecução | Schema verificado, zero contas ou direitos criados |
| IDI-14 | Upgrade interrompido ou schema desconhecido | Circuito fechado e retoma explícita |
| IDI-15 | Escrita recusada ou concorrente | Auditoria coerente, sem sucesso fictício ou revisão reposta |
| IDI-16 | Regressão dos circuitos locais | Fixtures e pré-cadastro mantêm as suas guardas |

## Entregas de implementação

Primeira entrega: geração persistente de vínculos, migração vazia e testes SQL de revogação/recriação, sem activar o controlador institucional. Segunda: Principal e PrincipalSource, emissão e revalidação nativa, com testes de concorrência e Playwright no fornecedor simulado. Terceira: composição explícita do primeiro consumidor e vista sem competências. Não marcar as três entregas concluídas com testes apenas do núcleo puro.

Para cada entrega, agrupar no fecho os testes PHP pertinentes, integração SQL, Node/lint/build e Playwright quando existir alteração HTTP. Conferir a descoberta de testes antes de aceitar a suite. Registar preservação dos dados anteriores, revisão independente, commit/push e CI do SHA exacto. Só integrar com os controlos aprovados.

Continuam externos a esta preparação a aprovação do FQDN, certificado, configuração Google institucional, matriz de competências, gestores designados e homologação IIS/MySQL. Esses pontos não impedem implementar e testar o contrato com dados fictícios, mas impedem activar acessos institucionais.
