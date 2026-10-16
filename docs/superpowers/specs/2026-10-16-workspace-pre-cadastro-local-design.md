# Pré-cadastro Workspace no laboratório local

Data: 16 de outubro de 2026. Estado: especificação aprovada pelo responsável neste chat, depois do commit c5a127b. O plano de implementação está preparado para revisão. Não implementada nem activada.

## Pedido e evidência

O responsável autorizou o pré-cadastro local das 73 contas nominativas seleccionadas no inventário privado do Workspace, com os nomes e emails de origem, entrada Google e nenhuma atribuição automática de permissões. Os dados pessoais, segredos, cópias da base e provas identificáveis ficam fora do GitHub. As quatro contas genéricas continuam para revisão. Esta autorização não permite alterar o Workspace, MFA, produção, AD, DNS, atribuições institucionais ou o laboratório da Albertina.

O inventário tem 77 registos. A comparação do SHA-256 com a exportação de origem passou. Todos os 73 seleccionados têm nome apresentado e email. A consulta local de preparação não encontrou utilizadores WordPress com esses emails. Esta consulta não prova ausência de vínculos Google prévios com outro email WordPress. O vínculo administrativo existente conserva-se. Os nomes são os apresentados pelo Workspace. Não se afirmam nomes civis completos, cargos ou unidades.

O ficheiro exportado contém nome, apelido, email, estado e campos de utilização. Não contém a claim sub. O identificador mostrado nos URLs da consola não é usado como sub. O adaptador actual só admite entrada de pares issuer/sub previamente associados. Também só bloqueia passwords nativas depois de existir vínculo. O pré-cadastro necessita de uma guarda anterior ao primeiro vínculo.

## Alternativas consideradas

A associação administrativa individual existente conserva o modelo actual mas exige autenticar separadamente cada titular. Não prepara o primeiro acesso autónomo das 73 identidades.

A proposta recomendada é uma lista nominativa fechada, importada explicitamente pelo operador local. No primeiro callback Google válido, o servidor encontra exclusivamente o email primário previamente seleccionado, confirma email_verified e hd e fixa definitivamente issuer/sub. O email serve apenas para resolver esta pré-autorização inicial. Os acessos seguintes exigem o par persistente. Não há autorização de negócio por email ou por domínio.

Esta escolha permite que o titular actual desse email Google reclame o pré-cadastro. Uma reutilização do endereço no Workspace antes da primeira associação não se distingue pelo inventário sem sub. A proposta limita a associação inicial às 24 horas seguintes à observação verificável da fonte. Depois desse prazo, o registo continua fechado até nova verificação administrativa explícita da origem. Este limite reduz a exposição mas não prova continuidade do titular. Por isso, a política de primeira associação faz parte da revisão pedida ao responsável.

Recolher previamente um identificador Google estável por canal administrativo documentado seria outra opção. Exigiria uma nova fonte que o inventário actual não fornece. Não é necessário alargar os âmbitos OIDC para Directory, Gmail ou Drive. Um cadastro aberto a todo o domínio fica excluído.

## Modelo proposto

Criar um registo próprio por pré-cadastro com ID opaco, nome apresentado, email primário canónico, hash do lote de origem, conta WordPress, estado de associação e revisão. A unicidade aplica-se ao email e à conta WordPress. Uma tabela própria de auditoria guarda actor local, acção, ID do registo, revisão, referência do lote e instante UTC. Não copiar a exportação inteira nem incluir dados pessoais em mensagens de erro ou logs.

Guardar também o instante UTC da observação e o prazo de associação inicial. Uma revalidação explícita de origem pode renovar o prazo de uma entrada pendente exacta e regista um evento próprio. Não altera uma identidade já vinculada, nome, email, conta, password ou concessões. Não existe renovação automática por GET, callback ou nova tentativa de login.

Os estados deste registo são AwaitingGoogle, Linked e Disabled. São estados de associação e não substituem AccountState nem activam o motor de concessões de F1-03. O pré-cadastro não cria grants ou perfis People. Não marca pessoas reais como LocalFixtureUser.

A instalação de esquema é CLI explícita, limitada ao WordPress local esperado, operador corrente com manage_options e flag booleana própria. A flag não pode retirar as guardas de contas já pré-cadastradas. Ausência, corrupção ou incompatibilidade de esquema após instalação recusa autenticação dessas contas. Não instalar ou reparar tabelas por GET, activação do plugin, login ou callback.

## Preparação e aplicação do lote

O comando faz primeiro uma pré-validação sem escrita. Recebe um caminho local exterior ao repositório e ao document root e um hash esperado explícito. Confirma ficheiro regular, origem, tamanho limitado, JSON válido e integridade. Usa a classificação seleccionada já autorizada, exige exactamente 73 registos nominativos activos neste lote e exclui os quatro genéricos. Confirma nomes válidos, emails primários do domínio configurado e ausência de duplicados. Não deriva emails de nomes, linhas do README, aliases ou unidades organizacionais.

O relatório privado enumera conflitos. A consola apresenta apenas totais e códigos de erro. Antes da aplicação, o operador captura uma cópia privada e um inventário verificável da base, configuração, sessões e originais existentes. Nenhum conflito é resolvido por adopção de conta, mudança de email ou reassociação silenciosa.

Na aplicação, cada nova conta WordPress recebe nome e email exactos, login opaco e nenhuma role ou capability individual. Não usar a role por defeito do site. A password técnica tem entropia criptográfica e nunca é apresentada ou entregue. Password, recuperação, chaves antigas e application passwords ficam bloqueadas desde a criação pelo marcador persistente de pré-cadastro. Não enviar notificações ou convites. Não criar sessão durante a importação.

Conta, marcador, registo e evento de criação confirmam juntos numa transacção sobre tabelas InnoDB verificadas. O código tem de recusar hooks ou tabelas que tornem esta garantia falsa e limpar os caches tocados se reverter. Não envolver DDL nessa transacção. O lote usa exclusão mútua. Uma repetição retoma apenas entradas exactas criadas pelo mesmo lote e conserva IDs, estados, passwords, sessões e auditoria. Falha parcial deixa contas já confirmadas fechadas em AwaitingGoogle e apresenta contagens privadas para retoma. Não se alega atomicidade global do lote.

## Primeiro login e colisões

Manter Facile e o fluxo actual Authorization Code, PKCE S256, state e nonce de uso único. O cliente continua a validar assinatura, issuer, audience, expiração e configuração antes de resolver o pré-cadastro. Os âmbitos continuam openid e email.

Um vínculo issuer/sub existente é resolvido pelas regras actuais. Nunca criar outro vínculo ou mudar o alvo por coincidência de email. Se houver pré-cadastro pendente para o email de uma identidade já vinculada a outra conta, não o adoptar ou associar. Preservar a associação existente e registar o conflito para revisão privada. O pré-cadastro fica sem acesso. Esta situação pode existir mesmo quando não há colisão na tabela wp_users.

Na ausência de vínculo prévio, exigir email_verified estritamente true, hd exacto e email primário exacto na lista fechada. Não aceitar aliases, domínios secundários ou uma conta apenas por pertencer a arn.gw. Bloquear o registo, revalidar estado, revisão, conta e ausência total de capabilities antes da associação. Reservar issuer/sub com unicidade e guardar associação e auditoria na mesma transacção. Duas primeiras entradas concorrentes não podem associar a mesma conta a duas identidades. A sessão só é emitida depois da confirmação e releitura do vínculo activo. Falha entre etapas não pode abrir acesso nativo ou produzir associação sem evento.

Depois do primeiro vínculo, uma alteração de email não reassocia identidades. Reactivação, revogação e qualquer alteração de ligação exigem decisão administrativa explícita e auditada. A desactivação do pré-cadastro ou da configuração OIDC recusa a sessão. A recuperação não pode usar o email para substituir sub.

## Acesso depois de autenticar

A autenticação confirma identidade. Não atribui Employee, Own, unidade, fila editorial, privilégios WordPress ou acesso a documentos. A conta permanece sem concessões de negócio. O portal apresenta um estado pessoal de acesso em preparação, sem projectar nomes de colegas, contagens, notícias, eventos, ficheiros ou ferramentas de laboratório. A associação de identidade não cria um perfil People nem um cargo institucional.

As contas novas não recebem acesso ao painel administrativo. A sessão federada conserva os limites locais actuais de uma hora e 30 minutos de inactividade e a revalidação em cada pedido. A gestão de estado tem de reconhecer pré-cadastros reais sem os converter em fixtures e invalidar sessões quando o acesso de identidade é desactivado. A integração com as decisões de RH e a homologação institucional continuam pendentes.

## Critérios de aceitação

1. Pré-validação: 73 seleccionados e quatro excluídos, fonte íntegra, zero duplicados, zero escrita e mensagens sem dados pessoais.
2. Aplicação explícita: 73 registos e contas sem funções, zero grants, zero perfis People, zero emails enviados e zero sessões criadas. Identidades Google já ligadas a outras contas são conflitos separados para revisão.
3. Retoma: repetir o mesmo lote não duplica contas ou eventos, não altera passwords, nomes, estados, revisões, sessões ou vínculos. Um lote diferente não substitui entradas existentes.
4. Auditoria: falha deliberada da gravação do evento reverte criação ou associação. Nenhuma conta parcialmente preparada aceita password ou sessão.
5. OIDC: assinatura e claims adversas, email não seleccionado, alias, hd errado, email não verificado, state/nonce inválido, callback repetido, vínculo revogado e conflito de sub são recusados.
6. Primeiro vínculo: duas ligações à base exercitam concorrência real. Só um par pode confirmar. As entradas seguintes usam issuer/sub e não fazem reassociação por email.
7. Vias nativas: password correcta tecnicamente conhecida numa fixture, recuperação nova e antiga, application password, REST, XML-RPC e cookie anterior ao vínculo não contornam a guarda. As contas legítimas anteriores mantêm o seu comportamento.
8. Estado: Disabled, flag desactivada, configuração indisponível e esquema corrompido fecham a conta e a sessão conforme o contrato. A reactivação exige autenticação nova.
9. Ausência de competências: a conta autenticada vê apenas o seu estado de preparação. Não recebe conteúdo real ou fictício, ficheiros, nomes de colegas, CMS, contagens ou concessões pela simples entrada Google.
10. Preservação: comparação dos registos anteriores, sessões, configuração e 89 ficheiros privados. Qualquer delta nativo é identificado e explicado. Não substituir as provas anteriores.
11. Playwright: ensaio integrado com fornecedor simulado e contas fictícias cobre primeiro vínculo, repetição, recusa e portal sem competências. Uma entrada Google real só pode ser testada pelo próprio titular autorizado. Não afirmar que 73 logins reais foram testados.
12. Entrega: testes PHP/SQL/Node, lint/build e revisão no SHA entregue. Commit e push contêm código, documentação e fixtures fictícias. Verificar o diff contra os nomes, emails e identificadores do inventário privado antes do push.

## Estado desta entrega documental

Apenas consulta, pré-verificação privada e especificação agora aprovada. Não há importador executável, esquema novo, contas criadas, vínculos novos ou autorização por email activada. O plano está em docs/superpowers/plans/2026-10-16-workspace-pre-cadastro-local.md e aguarda revisão. A implementação e a aplicação das 73 contas ainda ficam por executar. F1-12 e as tarefas da Albertina permanecem preservadas.

## Registo de execução posterior à especificação

O estado documental anterior é o registo da entrega original. Depois da aprovação do plano, a implementação avançou na branch separada, com 73 contas fictícias e ensaios HTTP/SQL. A revisão independente e a CI exacta precedem a activação no laboratório normal e a aplicação das 73 contas nominativas reais. O estado corrente está no README e em docs/qa-pre-cadastro-workspace-local.md.

A implementação posterior foi revista e integrada pela PR33 e o lote real autorizado foi aplicado e repetido localmente. As 73 contas permanecem sem competências em AwaitingGoogle. O login real preservou um vínculo anterior e não provou um primeiro vínculo novo. O README e a QA contêm o estado corrente, o prazo inicial e as diferenças nativas observadas. Os parágrafos documentais anteriores conservam a história da aprovação e não descrevem o estado operacional actual.
