# Aceitação da preparação F1-17

Entrega de 28 de outubro de 2026. Base integrada 9dd065d, PR #73. Não altera o runtime WordPress, schemas, contas, media ou configuração dos laboratórios.

Manuais de utilização e administração preparados com base nos circuitos implementados. Guião de instalação/recuperação distingue fontes, dependências, configuração, migrações e aceitação. Os instaladores fictícios não são apresentados como migrações institucionais. O README mantém F1-17 parcial.

A ferramenta release-inventory lê blobs de um commit Git completo e emite inventário de fontes com SHA256, tamanho e modo. Não lê a instalação, não gera pacote e não declara deploy pronto. As referências incompletas ou inexistentes, fontes incompletas, caminhos privados reconhecidos e ligações simbólicas são recusados. Material sensível versionado com nomes comuns continua a exigir revisão de segredos própria.

Validação local: 61 testes Node passaram, incluindo seis cenários do inventário em repositórios temporários fictícios. O ensaio focal final passou os seis cenários depois de reforçar a guarda de limpeza das pastas temporárias. Lint e build passaram. A primeira execução global falhou porque NODE_PATH não apontava para a instalação local existente de @wordpress/env. O caminho do processo foi corrigido e a suite completa passou, sem alterar código para esconder a falha.

Esta parcela não altera interface ou comportamento PHP. Não repete mutações Playwright do laboratório. Os seis controlos de leitura Playwright de férias/composição executados nesta continuação estão registados na aceitação F2-03. A CI desta branch e a de main devem passar antes de declarar a preparação integrada.

Continuam pendentes: pacote instalável e inventário de dependências/runtime, migrações institucionais, ensaio de upgrade/restauro, recuperação auditada, metas de capacidade, aceitação piloto e decisões institucionais do README. Os manuais são material de preparação, não prova de execução desses procedimentos.
