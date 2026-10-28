# Férias e ausências locais, F2-03

Execução directa autorizada pelo responsável. Fonte: requisitos-plataforma.md, secção 11.5, e decisoes-laboratorio-2026-10-26.md. A base inicial é o head da composição F2-05/06 e será ajustada para o squash integrado depois da CI. Sem produção, contas reais, emails ou Calendar externo.

## Contrato local

Usar as quatro contas fictícias de pedidos já existentes e as competências explícitas Employee e HumanResources. A administração técnica não ganha acesso de negócio. Um gate independente de férias exige também as guardas de pedidos, organização e autorização locais. Nenhuma instalação automática em GET, activação ou renderização.

Direito anual de 22 dias úteis. Calendário de feriados anual, versionado e identificado como fictício, configurado por RH institucional. Não presumir feriados oficiais. Datas estritas ISO, fins de semana excluídos, pelo menos um dia útil e período futuro dentro do mesmo ano. Sem transporte, antecipação ou proporção inventada. Cada pessoa e ano têm saldo independente.

Pedido com estados Rascunho, Submetido, Em análise, Devolvido, Aprovado, Rejeitado, Retirado e Cancelado. Autor edita rascunho/devolvido e retira antes da decisão. RH com competência na unidade inicia análise, devolve, aprova, rejeita e cancela aprovação futura com motivo. Autoaprovação recusada. Não herdar substituições do pedido genérico ou acrescentar cadeia de chefia.

Na submissão, guardar a versão do calendário e os dias úteis exactos. Bloquear sobreposição com Submetido, Em análise e Aprovado da mesma pessoa. Os pendentes não reservam saldo. Na aprovação, serializar saldo e sobreposição dentro da mesma transacção, revalidar sessão, unidades, competências, versão do calendário e data futura. Se o calendário mudou, devolver para nova submissão e cálculo explícito, sem alterar silenciosamente os dias. Aprovações anteriores conservam a versão e os dias originais.

Saldo calculado a partir dos pedidos aprovados e movimentos auditados. Separar os dias aprovados anteriores a hoje dos dias aprovados de hoje em diante, com explicação de que é uma classificação pelo calendário e não um registo de assiduidade. Cancelar só antes do início e repor o período futuro integral. Recusar cancelamentos iniciados e cruzamento de anos com mensagem explícita. Não alterar dias já decorridos nem criar compensações.

Calendário de ausências limitado às aprovações autorizadas, com pessoa fictícia, unidade e datas. Não projectar motivos, observações ou documentos. Autor só consulta os próprios pedidos e saldo, RH só a sua competência. Dados clínicos e anexos não fazem parte deste circuito de férias.

## Implementação

1. Modelo puro de calendário, cálculo, pedido reconstituível e saldo com limites explícitos. Testes agrupados no fecho conforme pedido do utilizador.
2. Persistência independente com schema exacto, conta/sessão/unidades bloqueadas na ligação, CAS, recibos, auditoria e avisos atómicos. Instalação explícita das tabelas e páginas sem repor contas/revisões.
3. Formulários nativos próprios/RH, calendário e saldos, protecção CSRF, páginas privadas, cache fechada e navegação por competência. Conservar o tema ARN. Compor depois pendências, pesquisa e avisos com os leitores nativos.
4. SQL concorrente para excesso de saldo, sobreposição, rollback, recibos e revogação. PHP, PHPCS, Node, lint/build e Playwright com datas/feriados fictícios. Comparar todos os registos e ficheiros anteriores.
5. Uma revisão independente final da branch, corrigir achados com regressão observada. README e relatório, commit/push, CI exacta verde, merge e confirmação de main.

## Aceitação mínima

Cinco dias úteis, dois períodos parciais no mesmo ano, 5+15=20 e saldo2, completar22 e recusar excesso. Excluir sábado/domingo/feriado fictício. Recusar período sem dias úteis, datas inválidas, sobreposição pendente e aprovada. Rejeição/retirada sem consumo. Cancelamento futuro repõe saldo. Duas aprovações concorrentes não excedem22. Outro funcionário mantém saldo próprio. Recusar autoaprovação, outra unidade, rascunho alheio, sessão revogada e operação sem recibo/auditoria. Calendário alterado exige nova submissão. Ano seguinte não herda saldo. Períodos entre anos e cancelamentos iniciados ficam bloqueados.

Fontes institucionais de feriados e saldos, horários especiais, proporcionalidade, tratamento após início e homologação permanecem pendentes e não serão declarados concluídos nesta parcela.
