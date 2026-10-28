# Férias e ausências: circuito fictício F2-03

## Entrega

O funcionário cria, altera, submete e retira pedidos próprios. RH consulta a sua unidade, inicia a análise, devolve, aprova, rejeita e cancela integralmente férias futuras. Cada operação conserva revisão, autor, motivo quando exigido e histórico. A conta administradora técnica não recebe competências RH por esta instalação.

O laboratório usa os quatro perfis fictícios já existentes de Pedidos. A instalação explícita acrescenta cinco tabelas e duas páginas, sem criar contas ou conceder funções. O módulo exige a opção local específica, a instalação de Pedidos íntegra, sessão corrente e competências por unidade. Nada é instalado numa visita ao site.

O modelo local estabelece 22 dias úteis por ano. Os períodos são futuros e pertencem ao mesmo ano civil. Excluem sábados, domingos e datas do calendário fictício configurado por RH. Os pedidos pendentes não reservam saldo, mas impedem nova submissão sobreposta do mesmo funcionário. Uma aprovação verifica novamente sobreposições, saldo e versão do calendário dentro da transacção. Pedidos aprovados consomem saldo. Cancelar antes do início repõe o período integral.

O saldo distingue dias aprovados decorridos e futuros. Esta classificação depende da data, não prova presença ou ausência efectiva. Não há transferência de saldo entre anos, proporcionalidade, horários especiais, cancelamento iniciado, dados clínicos ou anexos neste circuito. As regras e fontes institucionais permanecem em PEN-14.

## Autoridade e integridade

Conta, sessão, competências e unidades são verificadas na mesma ligação das mutações. O cabeçalho do módulo serializa as aprovações. CAS impede decisões sobre revisões antigas. Um recibo identifica a repetição da mesma operação. Pedido, histórico e avisos são atómicos. Falha de auditoria ou aviso provoca rollback.

O histórico reconstitui o pedido e verifica o snapshot actual. O calendário tem versões auditadas. Uma alteração exige devolução e nova submissão dos pendentes antes de aprovar. Férias aprovadas conservam os dias e a versão original. Autoaprovação, rascunhos alheios, outras unidades e sessões suspensas são recusados.

As páginas nativas revalidam autorização em cada leitura e mutação, usam CSRF e cache privada e recusam parâmetros fora do contrato. HEAD não produz corpo. Os avisos escolhem a vista pessoal ou de equipa do destinatário. O painel, a pesquisa e os avisos gerais recebem apenas projecções autorizadas, sem motivos ou históricos. Consultar não marca avisos como lidos.

## Validação

O ensaio SQL usa recursos exclusivos e remove apenas os que criou. Passou 50 verificações, incluindo saldo 20+2, excesso, concorrência real, overlap pendente, isolamento, recibos, rollback de auditoria e avisos, alteração de calendário, integridade e suspensão. A execução de diagnóstico preservou as 186 tabelas anteriores. Uma execução anterior acusou diferença global sem identificar a tabela. A asserção passou a reportar apenas os nomes das tabelas afectadas, sem expor dados. Não se atribui causa nem se apaga essa ocorrência.

A revisão independente identificou uma ligação de aviso para a vista errada. A regressão reproduziu a falha antes da correcção e passou depois, nas duas direcções. A verificação visual levou também a integrar as páginas no catálogo de navegação privada e a retirar o título duplicado do tema.

PHP final: 1166 testes e 5806 asserções, com sete omissões anteriores dependentes do ambiente Windows. Os testes do transporte e avisos passaram também isoladamente. Node: 55 testes. Lint, build e PHPCS dos 37 ficheiros PHP alterados aprovados. O SQL final passou 52 verificações e preservou as 191 tabelas anteriores. As provas privadas ficam fora do Git e não incluem credenciais na documentação.

A comparação após aprovação e cancelamento preservou as 133 tabelas exteriores, as 41 tabelas de negócio anteriores, as 22 contas e os 365 ficheiros. Foram acrescentadas exclusivamente as cinco tabelas Leave. Os logins do ensaio alteram sessões nativas das fixtures, por isso esta comparação não alega igualdade de todos os metadados de sessão.

Playwright validou o formulário RH, rascunho e submissão pessoal, isolamento entre funcionários, ligação correcta do aviso de equipa, aprovação de cinco dias, saldo 17 e cancelamento futuro com reposição para 22. HEAD privado, CSRF inválido, parâmetros de identidade, título único e reflow a 390, 768 e 1440 px passaram. A composição passou depois da correcção abaixo, incluindo painel, pesquisa, aviso, recibo persistente, REST e acesso anónimo. A CI do commit entregue é obrigatória antes de integrar.

O primeiro ensaio do painel excedeu o limite PHP de 30 segundos. A medição encontrou 2096 consultas na composição dos avisos, incluindo consultas a serviços alheios à fonte. O adaptador passou a resolver apenas os destinos da própria fonte. A navegação de férias resolve as vistas pessoal e RH na mesma transacção. Não há cache de autorização. A regressão observada falhou antes da correcção e passou depois. Em 28 de outubro, o painel abriu em 12,573 segundos e apresentou o pedido existente. Este tempo ainda exige melhoria e aceitação de desempenho em F1-16, sem aumentar o limite PHP para esconder o problema.

## Operação local

A activação exige `ARN_ALLOW_LOCAL_LEAVE=true` exclusivamente no ambiente local. O comando explícito é `wp arn leave install`, depois de Pedidos estar íntegro. A execução repetida valida os recursos existentes e não repõe calendários, pedidos, sessões ou permissões. Não usar este instalador de fixtures para produção.

O laboratório da entrega utiliza a porta 8892. A demonstração da porta 8888, contas reais, Google e produção ficam fora desta operação. A sessão administradora disponibilizada ao responsável continua separada dos browsers de teste.
