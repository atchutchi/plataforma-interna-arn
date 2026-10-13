# Fundação local de autorização F1-03

13 de outubro de 2026. Esta entrega implementa a decisão de autorização em classes puras e uma ferramenta WordPress de leitura com cenários fictícios. Não cria atribuições em contas reais, não altera papéis nativos e não substitui a homologação institucional.

A matriz da PR #9 é a base de trabalho local escolhida nesta continuação. As decisões MP-01 a MP-10 continuam propostas quanto à atribuição e operação institucional. A especificação técnica está em docs/superpowers/specs/2026-10-13-f1-03-fundacao-local-design.md e o plano em docs/superpowers/plans/2026-10-13-f1-03-fundacao-local.md.

## Decisão de acesso

AuthorizationPolicy recebe conta, pedido e concessões imutáveis, com relógio explícito. Exige estado Active, sessão válida, módulo disponível e operação admissível. O pacote funcional tem de incluir a acção. A concessão tem de estar na vigência, não revogada e pertencer à conta corrente.

O âmbito cobre o objecto completo. Unidades, pessoas, filas e audiências usam listas explícitas e comparações exactas. Não há herança de unidades ou permissões por email, nome, cargo ou privilégio Google. Duas concessões parciais não autorizam um pedido agregado que nenhuma cobre por inteiro. As futuras consultas têm de filtrar por objecto antes de devolver dados e contagens.

Um âmbito institucional explicitamente concedido não contorna operações próprias, audiência admissível, autoria ou versão. A aprovação e publicação de notícia própria ficam recusadas no laboratório. A decisão institucional sobre esse circuito permanece em PEN-07. Os pacotes TechnicalAdministrator, Leadership e Support não concedem acções funcionais por defeito. Para acumular funções, é necessário fornecer concessões distintas.

DelegationPolicy exige autorização de gestão sobre o alvo e uma delegação válida que inclua as contas geridas, o pacote atribuível e todo o âmbito solicitado. O âmbito próprio refere-se à conta destinatária. Não permite auto-promoção ou concessão dos dois pacotes administrativos por esta via. O procedimento inicial, a auto-suspensão e a recuperação de administração permanecem pendentes.

Inputs malformados falham com erro constante ou recusa. Uma colecção que contenha um item mal tipado ou uma concessão de outra conta não é parcialmente aceite. Relógio inválido, fim exacto de vigência, estado suspenso ou sessão inválida recusam. Não há cache de autorização nestas classes.

## Ferramenta do laboratório

Em Ferramentas, Ensaios de acesso local, um administrador WordPress vê uma tabela de cenários predefinidos. Cada linha apresenta a decisão esperada, a obtida e a correspondência. Dados e IDs são fictícios. A ferramenta só lê as fixtures e não recebe provas de autorização do browser.

O gate exige WP_ENVIRONMENT_TYPE=local, ARN_ALLOW_LOCAL_AUTHORIZATION_PROBE booleano true e capacidade manage_options actual. A flag do wp-env de desenvolvimento não é incluída no ambiente de testes development nem em homologação/produção. Se a flag estiver ausente, falsa ou em formato texto, a ferramenta não aparece. Não existem formulário de concessão, POST ou endpoint REST deste motor.

O laboratório portátil usa a mesma flag na configuração WordPress local. Não copiar a configuração OIDC protegida para activar a ferramenta. A página não precisa de novos âmbitos Google, credenciais ou acesso a funcionários.

## Alcance

O ensaio deste primeiro marco avalia snapshots fictícios construídos em memória. A continuação em docs/persistencia-autorizacao-f1-03.md acrescenta um repositório local auditado, cuja leitura fornece os mesmos valores ao motor. Não suspende sessões WordPress reais nem filtra conteúdos dos módulos ainda ausentes. O login e as guardas Google de F1-02 conservam o comportamento existente. O administrador técnico nativo mantém acesso privilegiado ao CMS, código e base conforme o seu papel actual.

A persistência e as decisões auditadas atomicamente são preparadas na continuação local. Uma futura integração institucional exige ainda leitura actual do estado e das concessões, obtenção dos dados do objecto no servidor e os pré-requisitos documentados. Nenhum cliente poderá declarar que uma sessão é válida, que um módulo está pronto ou que uma audiência foi aprovada. Esta entrega não é uma licença para ligar os inputs de teste a pedidos externos.

F1-03 passa a Em curso com este marco local, sem concluir US-A01/02, US-S03 ou autorização institucional por objecto. F0-02/F0-06, F1-01/F1-02 institucional e as decisões de administração continuam relevantes. F2 mantém os pré-requisitos.

## Verificação

A verificação final local passou em PHP 8.4.26: 410 testes e 1324 asserções. Os 95 testes novos cobrem o motor, a delegação, a imutabilidade dos inputs, os cenários e os gates. PHPCS verificou os 81 ficheiros sem erros ou warnings. Passaram também os 54 testes Node, lint e build. O Composer e o lockfile mantiveram-se sem alterações.

Os ensaios integrados existentes, executados depois da ligação do adaptador e da activação da flag local, passaram com WordPress 7.1.3 e MariaDB 11.8.3: persistência de identidade com 21 asserções e sessão federada com 24. Estes ensaios usam fixtures e tabelas exclusivas. Não verificam IIS ou MySQL 8.4.

Playwright no browser do Codex, com a sessão administrativa Google já existente, mostrou 15 cenários: 4 permitidos e 11 recusados. Todos apresentaram Coincide. A tabela tem caption e cabeçalhos explícitos e a página não contém formulários. Não foram registados erros de consola nesta página. Ficou aberta numa nova aba, sem navegar ou alterar o editor do utilizador.

O ensaio Playwright CLI numa sessão de browser separada confirmou redireccionamento do visitante para login, ausência da tabela para visitante, HTTP 403 e ausência da tabela para a fixture subscriber, portal anónimo bloqueado e REST anónima HTTP 401. O botão Entrar com Google e o favicon mantêm-se presentes. A página inicial da fixture mantém logótipo, favicon e ausência de overflow a 390 px, sem erros JavaScript no fluxo final.

A primeira execução CLI saiu da página de perfil antes da inicialização jQuery e produziu um erro no beforeunload nativo do WordPress. O ensaio passou depois de aguardar o carregamento e o ready da página. Não foi necessário alterar o core ou o plugin para esta sincronização. Capturas locais ignoradas pelo Git: output/playwright/f1-03-ensaios-local.png e output/playwright/f1-03-mobile.png.

As duas tarefas de código tiveram revisão independente sem findings. A integração exige ainda revisão do conjunto e todos os checks verdes no SHA exacto da PR. Os resultados dos cenários demonstram o motor com dados fictícios e não aprovam atribuições institucionais.
