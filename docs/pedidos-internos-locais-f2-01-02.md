# Pedidos internos e decisões, F2-01/02

Entrega de 26/10/2026 na branch feat/pedidos-internos-f2, baseada em main c916e21. Apenas laboratório fictício 8892. Não habilita produção, contas Workspace ou efeitos financeiros, salariais e de férias.

## Circuito entregue

O colaborador prepara um rascunho privado com referência estável, descrição e anexos. A submissão torna-o visível ao RH com competência na unidade. O responsável designado inicia a análise e aprova, rejeita ou devolve com fundamentação. O autor pode corrigir e voltar a submeter ou retirar antes da decisão. O cancelamento de uma aprovação conserva a decisão e exige fundamentação.

Os tipos são configuráveis pelo RH institucional fictício. A instalação deixa o catálogo vazio. Cada tipo tem unidade, responsável, estado, versão, etiquetas, instruções e política de novos anexos. A submissão captura a versão corrente. Alterações posteriores não reescrevem decisões anteriores. Anexos já aceites são conservados quando o tipo deixa de permitir novos anexos.

A substituição é específica do pedido, com início e fim UTC e limite de 30 dias. Exige uma conta que já tenha competência RH na unidade. Não cria concessões. O autor nunca decide o próprio pedido. A revogação e a expiração retiram a capacidade de decidir por substituição. A política de delegação de permissões de F1-03 permanece separada.

Meus pedidos e Tratar pedidos usam o tema ARN, navegação e Serviços autorizados. Pesquisa por referência/assunto, filtros, paginação, histórico e avisos pertencem ao módulo. A composição global com painel e pesquisa F2-05/06 continua no backlog. Os avisos são internos, sem envio de email.

## Persistência e ficheiros

Cinco tabelas de pedidos, tipos, auditoria, avisos e versão de schema. Instalação CLI explícita, integral ou recusada. Não repara instalações parciais nem repõe revisões. O instalador de QA usa prefixo exclusivo, quatro contas fictícias e duas páginas. O administrador técnico instala mas não recebe competência de negócio por esse papel.

Cada operação bloqueia contas, sessão e catálogo na mesma ligação SQL. Revalida a sessão e a competência antes do commit. CAS impede decisões incompatíveis. Recibos associam actor, chave e comando. Decisão, auditoria e avisos são atómicos. Leituras reconstroem o histórico e recusam inconsistências. O preset local limita o catálogo a 50 tipos, 500 pedidos e 1000 revisões por objecto. A serialização é deliberada neste laboratório e não constitui ensaio de capacidade institucional.

Os anexos passam pela fila privada, scanner e renderizador existentes. A conclusão revalida autoria, sessão, estado e revisão. O original e os derivados têm registo auditado associado ao pedido. A descarga e o preview reavaliam o acesso corrente. Rascunhos nunca ficam visíveis ao RH. Não existem URLs públicas WordPress para estes binários. A reconciliação inclui a nova fonte de referências.

## Validação local

Fecho: PHP 1140 testes, 5653 asserções e sete omissões existentes no Windows. Node 55 testes. PHPCS aprovado nos 40 ficheiros PHP alterados ou novos. Lint/build aprovados. Playwright 34 verificações finais aprovadas, com uma falha inicial de criação de tipo conservada e posteriormente corrigida. A revisão independente não deixou achados por corrigir.

Testes SQL reais cobrem instalação, preservação, idempotência, CAS, duas ligações concorrentes, isolamento por unidade, autoaprovação recusada, substituição e revogação, expiração da sessão durante a escrita, rollback de auditoria/avisos e corrupção de payload. Resultado: 67 verificações, preservando as 182 tabelas anteriores ao ensaio e removendo apenas os recursos exclusivos desse ensaio.

Playwright executa configuração nativa, rascunho, fila PDF, conclusão, preview/descarga, submissão, análise, devolução, edição com parágrafos, nova submissão, aprovação, cancelamento, avisos, pesquisa e recusas entre contas/anónimos. Reflow a 320, 390, 768 e 1440 px. Evidência completa, screenshots e credenciais ficam fora do Git.

A revisão independente identificou a rejeição de CRLF dos formulários e o bloqueio de submissão após desactivar anexos. Ambos foram reproduzidos em testes vermelhos e corrigidos. O browser também revelou a colisão do campo name com a rota WordPress, títulos/menus repetidos e o filtro vazio obrigatório. Essas falhas foram corrigidas. A primeira suite completa sem GD falhou num teste de imagens existente. A execução de fecho carrega GD explicitamente. As falhas iniciais foram conservadas na evidência privada.

A comparação de preservação confirmou 133 tabelas externas, os registos anteriores de 32 tabelas de negócio, 14 linhas de contas e 311 ficheiros inalterados. Acrescentou quatro contas fictícias e um PDF com dois derivados. Reconciliação completa sem novos problemas de integridade. Não equivale a auditoria de todas as tabelas nativas WordPress.

## Continuação

PEN-06 continua a definir os tipos e circuitos institucionais. Não preencher essa decisão com os exemplos de QA. F2-03 trata férias e ausências com saldos próprios. F2-04 trata suporte. F2-05/06 reúne os serviços nas projecções globais. Só fazer merge após CI verde no SHA exacto e confirmar também main. A operação no servidor exige os critérios de deploy do README.
