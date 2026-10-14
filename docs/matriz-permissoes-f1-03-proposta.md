# Proposta de matriz de permissões F1-03

Versão 0.1, 13 de outubro de 2026. Estado: proposta para validação, sem atribuições ou activação. Fonte funcional: README, secções 5, 7, 9, 10 e 11. Histórias principais: US-A01, US-A02, US-S03, US-ARN-02 e US-ARN-03.

Esta proposta define quem pode executar cada acção e sobre que registos. Não identifica funcionários, não confirma cargos ou hierarquias e não substitui a validação F0-02/F0-06. O login local homologado em F1-02 permanece independente desta preparação. F1-03 estava Por iniciar à data inicial desta proposta. A continuação autorizada preparou depois o motor, a persistência auditada e a ligação local do estado às sessões fictícias. Isso não valida esta matriz para atribuições institucionais. Ver docs/autorizacao-local-f1-03.md, docs/persistencia-autorizacao-f1-03.md e docs/sessoes-estado-local-f1-03.md.

## Regra de decisão

Proposta: permitir uma operação apenas quando a conta local está activa, a sessão é válida, existe uma função atribuída explicitamente, a acção pertence a essa função, o âmbito abrange o registo e o estado do processo admite a operação. Ausência, erro, conflito ou informação insuficiente recusam a operação. O módulo respectivo tem de estar implementado e activado.

Cada atribuição tem conta local, função, âmbito, início de vigência, término quando temporária, actor responsável, justificação e referência da decisão. Uma pessoa pode acumular atribuições válidas. Uma suspensão ou restrição explícita prevalece sobre uma concessão. Um cargo, uma unidade, um email ou um privilégio Google não criam uma atribuição.

## Âmbitos propostos

| Âmbito | Significado | Limite |
| --- | --- | --- |
| Próprio | Perfil ou registo cujo titular é a conta actual. | Autoria não concede publicação, aprovação ou acesso a todos os intervenientes. |
| Unidade | Lista explícita de identificadores estáveis de unidades autorizadas. | Não inclui unidades descendentes por inferência. As duas DGE mantêm identificadores distintos. |
| Pessoas | Lista explícita de contas abrangidas por uma competência. | Não resulta de nomes iguais, email ou pertença presumida a uma equipa. |
| Fila | Conjunto identificado de processos editoriais ou, futuramente, de suporte. | Uma fila não dá acesso às outras nem a todos os documentos pessoais. |
| Audiência | Destinatários autorizados do conteúdo aprovado. | Toda a ARN continua a significar apenas contas locais activas autorizadas. |
| Institucional | Competência transversal concedida expressamente para uma acção. | Nunca é a predefinição nem significa acesso universal a dados confidenciais. |

No laboratório, estes âmbitos podem ser simulados com fixtures. O uso institucional exige contas, dados e atribuições validados. Relações documentais ou pendentes do catálogo não criam permissões. Uma mudança de unidade retira as concessões antigas expressamente afectadas e exige nova decisão para o novo âmbito.

## Matriz funcional proposta

Os perfis abaixo são propostas de pacotes funcionais. Não correspondem automaticamente a papéis nativos WordPress. A acumulação exige atribuição explícita de cada pacote.

| Perfil | Acções propostas em F1 | Âmbito | Restrições |
| --- | --- | --- | --- |
| Funcionário | Consultar conteúdos aprovados para a sua audiência, consultar directório com campos autorizados, actualizar campos próprios de autosserviço, consultar tarefas atribuídas e actualizar o estado permitido, criar rascunhos de notícias e submetê-los. | Próprio, tarefas atribuídas e audiência. | Não altera cargo, unidade, estado da conta ou funções. Não publica notícias nem consulta processos RH de colegas. Campos de autosserviço dependem de PEN-08. |
| Responsável de unidade | Consultar dados profissionais necessários da equipa, criar e atribuir tarefas, acompanhar execução e publicar comunicados operacionais da equipa. | Unidades e pessoas expressamente atribuídas. | Não herda Administrator, acesso RH confidencial ou gestão de acessos. Comunicados para toda a ARN exigem competência institucional separada e validação PEN-07. |
| RH | Manter dados profissionais e documentos administrativos autorizados. Consultar e corrigir cadastro dentro da competência atribuída. | Unidades ou pessoas explicitamente abrangidas. | Não administra WordPress ou acessos por esse perfil. Férias, pedidos e saldos ficam reservados para F2. Acesso próprio a documentos pessoais depende da classificação aprovada. |
| Equipa editorial | Consultar submissões, analisar a versão submetida e a audiência, aprovar, rejeitar com motivo e publicar a versão aprovada. | Fila editorial e audiências expressamente autorizadas. | Não recebe leitura global de RH. Não altera competências de outras pessoas. Proposta: recusar aprovação da própria notícia até PEN-07 definir a excepção ou outro circuito. |
| Administração de acessos | Manter registos locais autorizados, associar identidade verificada, activar ou suspender acesso, atribuir e revogar funções e âmbitos previamente aprovados. | Contas e pacotes abrangidos por delegação expressa. | Não instala código, atribui administração técnica ou amplia a própria delegação. Não consulta conteúdo RH confidencial apenas por gerir a conta. Requer auditoria e motivo. |
| Administração técnica | Manter WordPress, tema, plugins, configuração e operação conforme procedimento técnico. | Instalação e ambiente autorizado. | Acesso técnico privilegiado tem controlo próprio. Não recebe funções editoriais, RH ou de gestão por inferência. A matriz não isola dados de quem controla código, base ou servidor. |
| Órgãos de direcção e fiscalização | Consultar ou decidir os assuntos expressamente definidos numa competência aprovada. | Matérias, unidades, pessoas ou audiências identificadas na decisão. | Sem pacote genérico de acesso universal. Detalhar necessidade antes de acrescentar qualquer acção. |
| Suporte TI | Sem acções operacionais de suporte nesta fase. Reservar consulta, assunção e resolução de tickets para F2. | Fila de suporte futuramente autorizada. | Pertença à DCSI não concede administração técnica ou acessos. Nenhuma permissão de tickets será activada em F1-03. |

As competências profissionais só são implementadas com o módulo respectivo. Esta matriz não antecipa a entrega de cadastro, notícias, tarefas, ficheiros privados ou F2. Não conceder capacidades nativas amplas para simular módulos ausentes.

## Contas, delegação e suspensão

Proposta de estados locais: Pendente, Activa e Suspensa. Pendente não entra no portal. Activa exige também identidade vinculada e autorização de login. Suspensa impede novos logins e todos os pedidos autenticados seguintes. A suspensão destrói sessões WordPress e preserva autoria, decisões e histórico. Reactivar a conta não reactiva automaticamente atribuições revogadas ou expiradas.

O vínculo Google e o estado local têm funções distintas. O vínculo prova a associação issuer/sub. O estado local controla a autorização corrente da conta. Um vínculo revogado continua revogado após reactivar o estado local. Suspender no Workspace não substitui a suspensão local. Não criar contas automaticamente a partir do domínio ou da lista documental.

Proposta de delegação: um administrador de acessos só concede os pacotes e âmbitos incluídos na sua própria delegação administrativa. Ser utilizador de RH não permite atribuir RH. Ser utilizador de uma unidade não permite nomear responsáveis. A delegação identifica separadamente quais as contas geridas e quais as funções atribuíveis. Todas as concessões e revogações verificam o actor, o alvo e a versão actual da delegação no servidor.

Proposta: o actor não aumenta as suas próprias competências. Concessão de administração de acessos ou administração técnica exige um procedimento distinto, decidido pela coordenação e DCSI. A primeira activação e a recuperação de uma situação sem administrador autorizado dependem de um procedimento aprovado. Não criar uma excepção automática baseada em email ou numa conta de recuperação inventada.

Uma suspensão da própria conta ou do último administrador autorizado exige tratamento próprio. A decisão sobre continuidade e saída urgente está identificada abaixo. O acesso técnico ao servidor não constitui, por si só, uma função de administração institucional.

## Aplicação técnica a especificar após validação

Manter regras puras de decisão no plugin e adaptadores WordPress separados. Distinguir concessões funcionais de permissões nativas do CMS. Verificar acção e objecto em cada pedido e aplicar a mesma decisão a páginas, REST, pesquisa, exportações, contagens, notificações e entrega de ficheiros. Um menu oculto ou um nonce válido não substituem autorização.

O WordPress fornece [papéis e capacidades](https://developer.wordpress.org/plugins/users/roles-and-capabilities/) e [map_meta_cap](https://developer.wordpress.org/reference/functions/map_meta_cap/) para a integração. O desenho ARN acrescentará âmbito, estado e vigência. A decisão funcional precisa de ocorrer no servidor. Não atribuir Editor, Author ou Administrator aos perfis profissionais como atalho.

Não copiar permissões duradouras para o ID token ou confiar numa fotografia da sessão. Consultar o estado e a versão corrente das atribuições. Mudanças têm efeito no pedido seguinte. Escrita e decisão administrativa devem revalidar dentro da transacção e produzir auditoria atómica. Falha de auditoria recusa a alteração.

A atribuição auditada regista IDs locais, pacote, âmbito, vigência, actor, motivo e versão, evitando segredos e conteúdo confidencial desnecessário. Retenção e acesso operacional a esta auditoria ficam para F1-04 e PEN-09. A estrutura persistente e a migração exigem especificação F0-06 antes de escrever o adaptador.

## Casos de aceitação propostos

| Caso | Resultado exigido |
| --- | --- |
| Google válido, sem conta local autorizada ou sem função aplicável. | Recusa, sem criação ou promoção automáticas. |
| Funcionário consulta documento de colega por URL directa ou REST. | Recusa se não for destinatário autorizado. |
| Responsável actua numa unidade fora da lista atribuída. | Recusa, mesmo que o título profissional seja igual. |
| RH autorizado apenas a algumas pessoas consulta registo de outra pessoa. | Recusa. |
| Editor modifica uma submissão depois de esta ter sido analisada. | A versão anterior de aprovação não aprova a nova versão. |
| Editorial tenta aprovar notícia própria antes de decisão PEN-07. | Recusa pela regra proposta. |
| Administrador de acessos tenta conceder âmbito maior ou administração técnica. | Recusa fora da delegação. |
| Atribuição expira, é revogada ou muda de âmbito com sessão activa. | Nova decisão observa imediatamente a alteração. |
| Conta é suspensa com duas sessões abertas. | Ambas perdem acesso e novos logins são recusados. |
| Conta é reactivada com vínculo Google revogado. | Login continua recusado. |
| Base, estado, âmbito ou auditoria indisponíveis. | Recusa sem exposição de dados ou alteração parcial. |
| Consulta, pesquisa ou exportação agrega registos autorizados e restritos. | Só devolve os autorizados, incluindo títulos, contagens e derivados. |

Estes casos são critérios de implementação futura, não testes já executados. Os ensaios usam contas e unidades fictícias. Dados ou concessões reais exigem validação própria.

## Decisões a validar

| ID | Decisão proposta | Validação necessária |
| --- | --- | --- |
| MP-01 | Separar administração de acessos e administração técnica, sem atribuição por cargo ou email. | Coordenação e DCSI confirmam perfis e procedimento inicial. |
| MP-02 | Conceder âmbitos por listas explícitas, sem herança automática de unidades. | RH e coordenação validam identificadores, equipas e hierarquia F0-01/F0-02. |
| MP-03 | Bloquear aumento da própria delegação e reservar concessões administrativas a procedimento distinto. | Identificar autoridade decisora e possibilidade de acumulação de funções. |
| MP-04 | RH recebe apenas pessoas ou unidades da competência atribuída. | RH define quando existe competência institucional e acesso a processos próprios. |
| MP-05 | Recusar aprovação da própria notícia e separar comunicado de equipa de comunicado institucional. | Editorial e coordenação resolvem PEN-07. |
| MP-06 | Usar Pendente, Activa e Suspensa, com suspensão imediata de sessões e preservação do histórico. | DCSI e RH confirmam ciclo de conta e tratamento da saída. |
| MP-07 | Delegações temporárias têm término obrigatório e não são renovadas automaticamente. | Coordenação define quem concede, duração aplicável e substituições. |
| MP-08 | Autosserviço limitado a campos profissionais autorizados. | RH e DCSI definem campos visíveis e editáveis em PEN-08. |
| MP-09 | Tratar suspensão do último administrador, auto-suspensão e recuperação num procedimento próprio. | DCSI e coordenação definem resposta a saída urgente e continuidade em PEN-10. |
| MP-10 | Preparar autorização local com fixtures antes de qualquer atribuição institucional. | Responsável valida a matriz, desenho persistente F0-06 e âmbito de implementação. |

Validar esta proposta não nomeia pessoas, não autoriza concessões reais e não conclui F1-03. Depois da validação, preparar a especificação técnica e o plano de implementação da fundação local. A homologação institucional F1-01/F1-02 e as restantes dependências mantêm-se. F2 continua condicionado pelos seus pré-requisitos.
