# Preparação técnica da próxima parcela F1-13

16 de outubro de 2026. Base lida: main 8b1bf8d. Esta é a próxima preparação autónoma do README. É uma análise de compatibilidade e um guião para a especificação conciliada. Não é implementação, plano executável aprovado ou escolha de políticas institucionais. Não cria código, tabelas, flags, páginas, utilizadores ou concessões.

## Objectivo e âmbito confirmado

O README exige consulta e actualização de tarefas próprias, criação/atribuição no âmbito autorizado, acompanhamento da equipa, prazos, histórico e continuidade. As fontes são US-F04, US-F05, US-M02, US-M03, US-ARN-08 e US-ARN-10. O laboratório futuro usa apenas contas f113 e dados fictícios. Os 73 pré-cadastros Workspace reais, os perfis People existentes e os anexos administrativos ficam fora deste trabalho.

A preparação preserva a Issue37: não altera docs/superpowers/specs/2026-10-16-tarefas-locais-design.md, docs/criterios-aceitacao-f1-13.md ou docs/entrega-albertina-f1-13-spec.md. Os 32 critérios continuam Por executar. A conciliação da Albertina pode apoiar-se nos contratos abaixo, mas continua uma entrega própria.

## Compatibilidade confirmada no código integrado

| Fonte existente | Facto verificado | Consequência para a preparação F1-13 |
| --- | --- | --- |
| F112/Configuration | Só reconhece communications e calendar e exige flags booleanas explícitas | Não reutilizar o nome de um módulo para abrir tarefas. A configuração F1-13 precisa de contrato próprio e não deve abrir módulos anteriores |
| F112/CurrentActor e ActorContext | Composição por ligação e módulo, autorização revalidada na transacção | Reutilizar o padrão, sem tratar uma instância communications como autoridade de tarefas |
| Communications/NoticeRepository::transaction e persist | Revalida actores, persiste evento e avisos na mesma transacção e só confirma depois | Fixar os destinatários do estado resultante antes do COMMIT. Apresentação posterior não significa decidir destinatários depois do COMMIT |
| Calendar/EventRepository::notices e markRead | Avisos e recibos pertencem ao módulo e revalidam elegibilidade actual | Não prometer que qualquer aviso novo aparece automaticamente na superfície F1-04 |
| Notifications/WpdbNotificationRepository::page | Projecta apenas eventos de auditoria de autorização do catálogo fechado Notification::EVENTS | Um aviso de tarefa precisa de extensão deliberada ou fonte própria, com destinatário e elegibilidade testados |
| Authorization/Action e política integrada | Há ReadOwnTask, UpdateOwnTask, AssignUnitTask e ReadUnitTask | Não inferir Employee a partir de UnitManager nem competência a partir de manage_options |
| Identity/PreRegistration | Contas reais sem competências ficam no portal de preparação | Não usar essas contas como fixtures ou lhes atribuir Employee para demonstrar tarefas |
| IdentityWpdb actual e PR39 | A nova API portável foi proposta mas a revisão encontrou divergências reais | O adaptador SQL F1-13 não pode ser declarado seguro com base nesse head. Não duplicar um rastreador próprio ou trocar para uma variável exclusiva da MariaDB |

A frase «classes F1-12 inexistentes» só é verdadeira na base histórica fffef583 da primeira proposta. Communications, Calendar e F112 existem agora em main. A passagem documental da Issue37 deve corrigir o enquadramento sem apagar essa história.

## Contratos a fechar na conciliação, antes de produto

Avisos: a especificação deve distinguir determinação dos destinatários, persistência atómica e apresentação. Identificador da tarefa, revisão resultante, destinatário e tipo formam uma chave estável, sem colisão entre tarefas distintas. Falha no último aviso reverte tarefa e auditoria. O recibo conserva o primeiro instante e nunca concede acesso ou altera a revisão da tarefa.

Comentários: definir onde são persistidos, identidade do autor, tarefa, ordem, revisão associada e visibilidade. A proposta anuncia comentários append-only mas não fixa a sua tabela ou representação. Não escolher silenciosamente um quarto agregado nem guardar comentários em logs. A elegibilidade deve ser a da tarefa no momento da leitura e da escrita. Anexos binários continuam dependentes da parcela própria de ficheiros.

HTTP: separar dados imutáveis derivados da sessão e do servidor de campos editáveis permitidos por uma operação fechada. O actor e a unidade não vêm do browser. Um estado alvo pode ser permitido por uma operação específica, sem transformar o POST num editor arbitrário. Fixar o resultado de CAS desactualizado e de no-op, incluindo código HTTP, preservação dos campos e ausência de revisão/evento/aviso novo.

Políticas: reabertura, cancelamento por gestor de outro autor, reatribuição, fuso de apresentação e superfície de avisos continuam propostas. O mecanismo futuro pode ser configurável no laboratório. Nenhum valor de fixture se converte numa aprovação institucional ou numa concessão a pessoa real. Não decidir regras institucionais pela lista de cargos, pelo email ou pelo papel técnico WordPress.

## Pacote de verificação preparado

A implementação futura tem de provar primeiro o contrato transaccional corrigido nos motores de ensaio. Incluir escrita sentinela, rollback e confirmação externa dos dados, falha de auditoria/último aviso, perda real de ligação, fronteiras SQL e recusa de estado desconhecido. Dois processos precisam de prova de espera no lock, não apenas barreiras anunciadas antes da instrução.

Depois da especificação conciliada e revista, a ordem de um plano executável deve separar política pura, persistência/auditoria/avisos, autoridade/sessão, transporte HTTP, apresentação e instalador explícito. Cada tarefa terá testes RED/GREEN, interfaces fixadas e prova por critério. Esta ordem é uma preparação para o plano, não uma instrução para começar produto antes da revisão.

Os ensaios devem cobrir Own e Units sem excepção administrativa, reatribuição com perda de acesso, suspensão/revogação, CAS e no-op, 20+1 resultados depois de autorização, REST com nonce nativo válido, rotas privadas indistintas e zero escrita de negócio em GET/HEAD. Devem incluir Playwright com o elenco fictício próprio, teclado, foco, fontes locais, 320/390/768/1280 e zoom nativo separado de CSS 200%. Não afirmar resultados desses testes antes de o módulo existir.

Conservar baselines novos e imutáveis do laboratório, sem sobrescrever as provas anteriores. Os deltas de opções nativas e de sessões têm de ser identificados. Código, documentação e fixtures podem seguir para GitHub. Configuração, segredos, dados reais, dumps, capturas e storageState ficam privados.

## Critério de passagem

A próxima acção de produto é preparar/rever o plano executável da F1-13 depois de existir uma especificação conciliada e aceite e uma fronteira SQL que passe os contra-exemplos da revisão. Até lá, esta análise e as regressões dos módulos integrados são trabalho autónomo possível. Não se salta para F2 ou se apresenta F1-13 como implementada por existir este ficheiro.
