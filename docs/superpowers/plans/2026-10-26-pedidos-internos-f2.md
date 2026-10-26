# Pedidos internos, circuito fictício F2-01/02

Continuação autorizada do backlog no laboratório 8892. Base main c916e21, PR69 integrada após 22 controlos verdes. Confirmar também a CI de main. Não alterar a demonstração, dados reais ou produção. A decisão funcional sobre revogação de permissões delegadas continua pendente e não pertence a esta parcela.

## Entrega

Circuito completo de pedidos internos fictícios: tipos configuráveis pelo responsável competente, rascunho, submissão, início de análise, devolução fundamentada, nova submissão, aprovação, rejeição, retirada e cancelamento fundamentado. Referência estável, versões concorrentes, histórico e notificações apenas após commit. Não executar efeitos financeiros, salariais ou de férias a partir de pedidos genéricos.

Configuração local de tipos vazia após instalação. Os exemplos de QA são explicitamente fictícios, sem assumir que despesas ou declarações foram aprovadas em PEN-06. Campos de título e descrição com etiquetas/instruções configuradas e limites fechados. Tipo tem unidade, responsável, estado activo e versão. Mudanças não reescrevem decisões anteriores.

O funcionário cria e consulta apenas os próprios pedidos. RH consulta pedidos submetidos dentro do âmbito atribuído. Rascunhos e respectivas media ficam privados ao autor. Decisão exige o responsável do pedido ou substituto explícito com prazo e a competência corrente na unidade. Autoaprovação sempre recusada neste preset local. A substituição não concede funções e só pode designar uma conta que já tenha a competência necessária. Não confundir substituição de processo com delegação de gestão de acessos.

Pedidos em análise podem ser retirados pelo autor antes da decisão. Aprovação/rejeição são mutuamente exclusivas sob CAS. Cancelar um aprovado exige competência RH e motivo, conservando a decisão anterior no histórico. Férias mantêm módulo e saldo próprios e não herdam estes efeitos implicitamente.

Anexos só em rascunho/devolvido, com pipeline privado existente: fila, análise antimalware, conservação do original e derivados autorizados. A conclusão revalida sessão, autoria, estado e revisão. Downloads reavaliam o pedido corrente antes de abrir o binário. O mesmo ficheiro nunca fica acessível por uma projecção pública WordPress.

Páginas do portal para pedidos próprios e tratamento autorizado, ligadas aos Serviços e ao tema ARN. API de bloco apenas com marcador sem conteúdo. HTTP/REST/AJAX não expõem payloads de negócio. POST com nonce e parser fechado. Pesquisa e notificações do módulo respeitam o acesso corrente. A composição global F2-05/06 será actualizada quando os três serviços estiverem entregues.

## Sequência

1. Domínio de tipos, pedidos, transições, referência, substituição e auditoria.
2. Schema explícito, autoridade transaccional, CAS, recibos e notificações.
3. Composição local e instalação exclusiva de fixtures/páginas sem repor dados existentes.
4. Formulários e vistas no tema, anexos e entrega privada, navegação autorizada.
5. Testes agrupados no fecho: PHP, SQL real, concorrência, rollback, perfis/cross-unit/self-approval, Playwright, media, reflow/teclado e preservação de dados anteriores.
6. Revisão independente, correcções, README, evidência, commit/push, CI exacta, merge e confirmação de main. Continuar o backlog sem considerar o site pronto para deploy.

## Limites institucionais

PEN-06 define tipos e circuitos reais. Este laboratório não resolve essa decisão com exemplos fictícios. Regras de férias, saldos e feriados ficam em F2-03. Permissões reais, vínculo profissional-conta, delegações e operação no servidor mantêm os seus contratos. Não transformar um admin técnico em autoridade RH. Os testes agrupados prevalecem sobre a ordem TDD por pedido explícito do responsável.
