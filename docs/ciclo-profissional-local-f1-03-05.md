# Estado profissional e acesso, F1-03/05

Decisão confirmada em 29 de outubro de 2026: suspender ou encerrar o cadastro suspende a conta associada e invalida as suas sessões, conservando o histórico. Reactivar o cadastro nunca reactiva a conta. Uma decisão explícita de acesso continua a ser necessária.

## Circuito entregue

O submenu Cadastro > Estado e acesso destina-se ao RH fictício já autorizado. Não atribui competência RH ao administrador técnico. Mostra cada registo, o estado profissional, a conta associada e o histórico das últimas 20 decisões.

Um registo começa Por definir até existir uma decisão. Activo, Suspenso e Encerrado são estados profissionais distintos do estado da conta. Uma nova decisão pode reactivar o registo e conserva o vínculo e o histórico. A associação exige escolha explícita e justificação, não procura contas pelo email e não cria identidades Google, utilizadores, concessões ou unidades. Uma conta só pode estar associada a um registo. Substituir ou desligar uma associação existente fica recusado nesta parcela.

O laboratório limita a associação à conta fictícia dedicada arn.cadastro.conta.teste@example.invalid, Subscriber e marcada como fixture. A composição verifica login, email, marcador, papel nativo, unicidade dos metadados e conta canónica. Um identificador enviado no formulário não basta. A fonte institucional de pessoas e contas continua pendente.

Suspensão e encerramento alteram a conta canónica para Suspended na mesma transacção que a decisão profissional. A mudança de revisão torna inválidas as sessões anteriormente emitidas. A guarda nativa recusa os cookies e novos logins. A reactivação profissional deixa essa conta suspensa. Uma decisão técnica explícita e auditada pode voltar a activar a conta fictícia, sem conceder competências. Não se apagam sessões ou auditorias para esconder transições.

## Persistência e autorização

O catálogo e os hashes das importações anteriores permanecem intactos. Três tabelas arn_lifecycle_header, arn_lifecycle_batches e arn_lifecycle_audit guardam o estado, recibos e valores anteriores/novos. A instalação é explícita por CLI administradora com arn register lifecycle-install e flag local ARN_ALLOW_LOCAL_REGISTER_LIFECYCLE, além das condições existentes de cadastro. Leituras, activação do plugin e GET nunca instalam ou migram dados.

Cada operação usa autoridade RH corrente, sessão, bloqueio do catálogo e estado, revisões observadas do cadastro/ciclo/conta e justificação. O recibo identifica o decisor e o comando completo. Reenvios devolvem o recibo histórico sem repetir uma suspensão ou repor um estado antigo. A auditoria reconstitui a decisão e os hashes. Uma falha de auditoria, recibo, revisão ou sessão faz rollback do estado profissional e da suspensão canónica.

A operação interna suspendInTransaction exige transacção existente e só permite suspensão. Não confirma a transacção do chamador nem permite activação. A interface HTTP exige POST e nonce, rejeita campos extra, arrays, ficheiros, números ambíguos e métodos inesperados. Respostas privadas usam no-store e recusas não expõem registos.

## Verificação

PHPUnit: 1186 testes, 5830 asserções e sete omissões já conhecidas deste runtime Windows. O conjunto focal de cadastro/ciclo passou 36 testes e 70 asserções. SQL real: 77 verificações do cadastro e ciclo em tabelas exclusivas, incluindo associação única, manutenção de concessões vazias, suspensão, reactivação separada, encerramento, reenvio histórico, rollback, sessão perdida, concorrência com outra ligação e texto literal. As 13 tabelas do ensaio são removidas no finally, sem tocar nas tabelas normais.

Node: 61 testes. Lint, build e PHPCS dos 12 ficheiros PHP alterados passaram. A primeira execução SQL detectou um nome incorrecto de propriedade no próprio teste, corrigido para stored_grants. Não foi alterado o comportamento da aplicação para fazer essa asserção passar.

Playwright passou 26 verificações do circuito real: conta fictícia activa, autorização RH, POST/nonce/campos, associação, reenvio, CAS, suspensão de sessão já aberta, recusa de novo login, reactivação profissional sem acesso, decisão de acesso separada, encerramento e isolamento de administrador técnico/anónimo. Capturas de ecrã verificadas em desktop e 390 px, sem overflow horizontal.

A revisão independente identificou um P2 na apresentação de entidades HTML literais. O teste no WordPress/browser reais reproduziu nomes e justificações interpretados indevidamente. O escape literal existente no cadastro foi aplicado às duas saídas. A mesma prova passou depois da correcção, sem alterar os bytes guardados. As duas verificações adicionais de texto completam a aceitação browser. Não houve segunda revisão ou outros achados pendentes.

A comparação final passou 487 verificações: 133 conjuntos exteriores, linhas anteriores de 46 tabelas de negócio, 22 utilizadores anteriores e 365 ficheiros preservados. Foram acrescentados três tabelas, uma conta fictícia sem concessões, cinco eventos canónicos dessa conta e seis decisões profissionais. LAB-PER-101 termina Encerrado, ligado à nova conta Suspended na revisão 5. LAB-PER-103 fica Activo sem conta, com a justificação literal usada na regressão. As outras pessoas não receberam estado ou vínculo implícitos. A configuração só recebeu a flag local própria. Logins alteram sessões e caches nativas, não se afirma igualdade de todos os metadados WordPress.

Evidência privada externa ao Git. Não declarar esta parcela integrada antes da CI do commit entregue e CI de main.

## Limites

Esta é a implementação funcional do circuito fictício, não uma autorização para usar contas de funcionários. A adaptação institucional requer fonte RH, campos/hierarquia, matriz aprovada, responsáveis de acesso e fluxo de reactivação institucional. Faltam ainda delegações persistentes, operação de media, Google Calendar, capacidade global e homologação/release. A migração para IIS/MySQL e o deploy não fazem parte deste ensaio.
