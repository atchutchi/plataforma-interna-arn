# F1-12: execução local e continuidade

16 de outubro de 2026. Branch `feat/f1-12-comunicados-calendario`, iniciada em main `fffef58323636d59985a073e6b03bfc8e3c40e69`. A proposta da PR #26 foi adoptada pelo responsável. Plano: [cinco tarefas](superpowers/plans/2026-10-16-f112.md). Especificação e 32 critérios permanecem a referência de comportamento, sem substituir provas executadas.

## Parcelas executadas

O commit `e9829b4` adoptou a especificação, os critérios e o plano contra o main com F1-07 integrada. O commit `315f7f6` acrescentou modelos puros de comunicados, referências publicadas separadas, histórico validado na hidratação, validade exclusiva, CAS, revisão institucional por segundo actor, token opaco e permissões específicas. Os pacotes anteriores conservam as operações anteriores. Gestão não concede leitura Employee.

Provas desta parcela: falha inicial de oito testes por classes ausentes, testes dirigidos e suite completa com 767 testes PHP, 3269 asserções e quatro omissões Windows preexistentes. PHPCS dos 18 ficheiros afectados sem erros ou avisos. A suite Node de preparação passou 55 testes. O Composer no worktree usa uma cópia privada independente das dependências para evitar carregar classes do main através de uma junction. Sem alteração de versões ou lockfiles.

O commit 1a369a7 acrescentou modelos/política/agenda de eventos, intervalos UTC canónicos, dia inteiro com fim exclusivo, versões separadas, cancelamento sem reactivação e intersecção mensal. A suite completa passou 773 testes PHP/3361 asserções, com quatro omissões anteriores. PHPCS dos nove ficheiros desta parcela sem erros/avisos. O CI da primeira parcela, SHA 7a49a116f767f321d72ffb55c409a243bf79eb8a, passou na execução 38892473690. O novo baseline privado pré-SQL registou 39 tabelas e 83 ficheiros, com hashes por opção e sessões brutas. Não substitui os baselines F1-07/F1-11.

O commit 4f55242 acrescentou repositórios transaccionais separados, cinco tabelas por módulo, versões imutáveis, auditoria e avisos atómicos. A autoridade revalida contas, concessões, unidades e sessão nativa sob locks. Os ensaios SQL usam prefixos e contas fictícias próprios. Incluem concorrência com dois escritores reais, uma confirmação e um conflito CAS, suspensão/revogação concorrentes, falha tardia com rollback, recibos idempotentes e perda de audiência.

A suite PHP passou 781 testes e 3414 asserções, com quatro omissões anteriores. Cada módulo passou 65 verificações SQL, incluindo comparação integral das linhas normais e ausência de tabelas residuais no seu prefixo. O commit 4404997 reforçou a limpeza, corrigindo a ordem de remoção das tabelas com chave estrangeira e incluindo o marcador de schema. Resíduos sintéticos anteriores foram inspeccionados e removidos com registo privado. Não foram removidos recursos normais. O bootstrap CLI dos ensaios impede o arranque assíncrono de cron apenas nesses processos. Uma execução falhou por alterações em transients nativos do tema e permanece registada como falha, sem excluir opções da comparação. A verificação conjunta final desta parcela continua no ledger.

As cinco tarefas de código estão executadas. O commit a7f298e acrescentou CMS, vistas publicadas, avisos internos, navegação autorizada e guardas HTTP. A revisão independente de toda a branch identificou instalação parcial, redireccionamento após decisão, relógio da projecção e duas omissões de navegação/metadados. A passagem final corrigiu estes pontos com regressões RED/GREEN. As decisões e os seus custos estão no registo próprio.

## Activação e aceitação executadas

A revisão independente foi seguida da correcção bc60cf5 e CI 38907064548 aprovada em todas as seis jobs. A activação normal ocorreu depois dessa confirmação, com backup e baselines próprios. As duas junctions ligam o código F1-12 revisto ao laboratório, as duas flags novas estão true e existem seis contas Subscriber fictícias, 13 grants e duas páginas. Verificar os destinos efectivos antes de mudar de checkout. A conclusão verificada é obrigatória antes de abrir rotas. Não repetir a instalação como se o laboratório estivesse vazio.

A suite passou 800 testes PHP/3612 asserções/quatro omissões Windows preexistentes, SQL70/70/52/52 e Node55/lint/build. Playwright executou os percursos e os 32 critérios estão preenchidos por camada na [QA](qa-comunicados-calendario-local-f1-12.md). A correcção visual 65a01e2 aumentou as áreas de clique após medição real. Passaram 16 combinações front end, 12 CMS e quatro títulos longos, com texto CSS200%, foco e teclado. Zoom nativo/segundo PC não foram apresentados como executados.

O snapshot final preserva todas as linhas anteriores de negócio, contas, sessões e os 83 ficheiros privados anteriores. Acrescentou seis ficheiros de password. As opções nativas foram reconciliadas sem excluir deltas: cache regenerada, contador das seis contas e o mesmo hook cron horário avançado 3600 segundos. Doze GET/HEAD e repetição do instalador antes/depois de edição passaram comparação integral idêntica das 49 tabelas. Ver detalhes e limites na QA.

## Próximas parcelas

A entrega está na [PR #32](https://github.com/atchutchi/plataforma-interna-arn/pull/32), que contém o estado Git e a CI do SHA entregue. Integração apenas depois de CI verde, sem deploy. Preservar a instalação já activa neste PC, os baselines e a auditoria. Rever depois a proposta F1-13 da Issue #29 quando for entregue pela Albertina, sem executar a tarefa documental dela em paralelo. PEN-07, escala com população real e IIS/MySQL permanecem pendentes. O pré-cadastro Workspace é uma evolução separada de identidade/autorização, nunca uma atribuição automática de competências por email ou nome.

## Inventário Workspace separado

O responsável autorizou consultar os nomes e emails no Admin Console e escolheu apenas contas nominativas para futuro pré-cadastro. Foram observadas 77 contas activas, com 73 nominativas seleccionadas e quatro contas genéricas para revisão. Nomes apresentados preservados exactamente como constam no Workspace. Não equivalem a validação RH, cargo, unidade, competência ou identidade civil completa.

O export e o inventário mínimo ficam fora dos repositórios e do document root, na área privada local. Nenhum nome/email desta consulta foi acrescentado aos fixtures ou ao Git. Não foram provisionadas contas reais na aplicação, atribuídas funções ou alteradas credenciais Workspace. O pré-cadastro e a associação autenticada devem seguir o circuito de identidade/autorização já existente, com auditoria e regras de activação próprias. A F1-12 usa exclusivamente dados fictícios.

As tarefas #28/#29, branches e laboratório da Albertina mantêm-se independentes. Sem alteração de produção, servidores, AD, DNS, originais privados ou fontes institucionais.
Registo histórico das fases: Task 3 fechou em 4404997 e Task 4 em a5f9b76, então ainda sem activação normal. Task 5 fechou em a7f298e. A revisão, activação e aceitação posteriores descritas acima substituem esse estado de preparação, sem repetir os baselines ou apagar os primeiros resultados.
