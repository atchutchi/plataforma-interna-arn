# F1-12: execução local em curso

16 de outubro de 2026. Branch `feat/f1-12-comunicados-calendario`, iniciada em main `fffef58323636d59985a073e6b03bfc8e3c40e69`. A proposta da PR #26 foi adoptada pelo responsável. Plano: [cinco tarefas](superpowers/plans/2026-10-16-f112.md). Especificação e 32 critérios permanecem a referência de comportamento, sem substituir provas executadas.

## Parcelas executadas

O commit `e9829b4` adoptou a especificação, os critérios e o plano contra o main com F1-07 integrada. O commit `315f7f6` acrescentou modelos puros de comunicados, referências publicadas separadas, histórico validado na hidratação, validade exclusiva, CAS, revisão institucional por segundo actor, token opaco e permissões específicas. Os pacotes anteriores conservam as operações anteriores. Gestão não concede leitura Employee.

Provas desta parcela: falha inicial de oito testes por classes ausentes, testes dirigidos e suite completa com 767 testes PHP, 3269 asserções e quatro omissões Windows preexistentes. PHPCS dos 18 ficheiros afectados sem erros ou avisos. A suite Node de preparação passou 55 testes. O Composer no worktree usa uma cópia privada independente das dependências para evitar carregar classes do main através de uma junction. Sem alteração de versões ou lockfiles.

O commit 1a369a7 acrescentou modelos/política/agenda de eventos, intervalos UTC canónicos, dia inteiro com fim exclusivo, versões separadas, cancelamento sem reactivação e intersecção mensal. A suite completa passou 773 testes PHP/3361 asserções, com quatro omissões anteriores. PHPCS dos nove ficheiros desta parcela sem erros/avisos. O CI da primeira parcela, SHA 7a49a116f767f321d72ffb55c409a243bf79eb8a, passou na execução 38892473690. O novo baseline privado pré-SQL registou 39 tabelas e 83 ficheiros, com hashes por opção e sessões brutas. Não substitui os baselines F1-07/F1-11.

Estas provas são de modelos PHP. Não há nesta parcela tabelas F1-12, recursos instalados, formulários, rotas ou testes Playwright F1-12. A configuração e as junctions do WordPress normal mantêm-se no main.

## Próximas parcelas

Implementar persistência transaccional e avisos de cada módulo, instalador explícito com seis fixtures e guardas nativas, CMS e vistas do tema. Depois da revisão independente do conjunto, executar a aceitação WordPress/SQL/Playwright e preencher os 32 critérios com resultados reais. Não declarar o módulo aceite enquanto houver critérios de segurança sem prova.

## Inventário Workspace separado

O responsável autorizou consultar os nomes e emails no Admin Console e escolheu apenas contas nominativas para futuro pré-cadastro. Foram observadas 77 contas activas, com 73 nominativas seleccionadas e quatro contas genéricas para revisão. Nomes apresentados preservados exactamente como constam no Workspace. Não equivalem a validação RH, cargo, unidade, competência ou identidade civil completa.

O export e o inventário mínimo ficam fora dos repositórios e do document root, na área privada local. Nenhum nome/email desta consulta foi acrescentado aos fixtures ou ao Git. Não foram provisionadas contas reais na aplicação, atribuídas funções ou alteradas credenciais Workspace. O pré-cadastro e a associação autenticada devem seguir o circuito de identidade/autorização já existente, com auditoria e regras de activação próprias. A F1-12 usa exclusivamente dados fictícios.

As tarefas #28/#29, branches e laboratório da Albertina mantêm-se independentes. Sem alteração de produção, servidores, AD, DNS, originais privados ou fontes institucionais.
