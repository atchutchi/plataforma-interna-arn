# QA do pré-cadastro Workspace

Estado corrente: implementação revista, integrada e aplicada apenas neste laboratório. As 73 contas autorizadas foram criadas e auditadas sem competências. Todas continuam AwaitingGoogle. As secções iniciais conservam a sequência dos ensaios. A conclusão e os deltas da aplicação estão no fim. Provas, dados e configuração ficam privados.

## Verificações já executadas

Fonte privada e política pura: RED observado. Primeira suite completa com 810 testes, 3651 assertions e quatro skips existentes no Windows. Leitura limitada, hashes, selecção fechada, metadados temporais e recusas de caminhos/ACL têm cobertura.

Criação auditada: 25 verificações SQL em tabelas nativas exclusivas, com rollback de criação/auditoria, retoma, colisões e ausência de notificações, sessões ou roles. A role por defeito dessa instalação fictícia é administrator para provar que não é herdada.

Guardas e estado: 16 verificações SQL, incluindo wp_authenticate com password tecnicamente correcta, recuperação e application passwords nativas. CAS, rollback da auditoria e no-op do estado foram verificados. Suite completa nessa etapa: 819 testes, 3696 assertions e quatro skips existentes.

Primeiro vínculo: 22 verificações SQL. Incluem falha na última auditoria, rollback entre tabelas, par anterior preservado e revogação sem fallback para email. Dois processos em ligações diferentes ficaram simultaneamente bloqueados, com observação em INNODB_LOCK_WAITS antes da libertação da barreira. Resultado: um commit e um conflito confirmado, uma passagem a Linked e os dois eventos esperados da identidade. Regressão focada: 213 testes e 409 assertions. PHPCS dos 24 ficheiros dessa etapa sem erros ou avisos.

## Ensaio completo em WordPress separado

Foi criada uma instalação nova com prefixo exclusivo, apenas o plugin principal e o tema ARN, servida em 127.0.0.1:8889. Todos os utilizadores, passwords, chaves e emails desse ensaio são fictícios. A preparação positiva da readiness foi executada por CLI antes de instalar o fornecedor de testes. Foram criadas 73 contas seleccionadas fictícias com a role por defeito deliberadamente administrator. Nenhuma herdou capabilities. Depois de dois primeiros vínculos e várias mudanças explícitas de estado, reaplicar as 73 entradas conservou os hashes integrais das 17 tabelas. Não enviou email ou criou sessões durante a aplicação.

O fornecedor substitui apenas transporte e redireccionamento. Mantém Facile, PKCE S256, JWT RS256, JWKS, state, nonce, callback e emissão de sessão reais do adaptador. A chave RSA é efémera. O helper exige flag própria, ambiente local, origem de ensaio e prefixo exclusivo. Não é incluído no plugin normal, não oferece controlo HTTP e não aceita identidades fora de example.invalid.

Playwright confirmou primeiro vínculo, nova entrada pelo par existente, logout e recusa do cookie anterior. Um callback repetido depois do logout foi recusado sem nova troca do código. Onze cenários adversos ficaram sem sessão: pré-cadastro expirado, email não seleccionado, conta genérica excluída, alias, email não verificado, verificação textual, hd errado, nonce errado, state errado, assinatura alheia e token expirado.

As matrizes de sessão verificaram readiness desligada, versão incompatível, tabela removida, marcador malformado ou removido, capability indevida, vínculo revogado e suspensão sem destruir previamente os tokens. Cada restauro exigiu nova entrada. Uma revisão de sessão antiga foi recusada sem destruir outra sessão válida da mesma conta. Flags ausente, false e textual e configuração OIDC ausente fecharam a sessão. A configuração fictícia foi reposta byte a byte.

As vias nativas foram exercitadas por HTTP com password tecnicamente correcta, recuperação nova, chave antiga válida, application password de hash válido e cookie nativo anterior ao vínculo. Foram recusadas. A password técnica não mudou após o POST de recuperação. A guarda continuou a funcionar com a flag desligada e sem o carregador das dependências OIDC. A API REST foi também testada pelo endereço canónico rest_route, pois a instalação nova não tem ligações permanentes configuradas. XML-RPC permaneceu fechado.

Uma conta autenticada só recebeu nome próprio, mensagem de preparação e logout. CMS, perfil administrativo, pessoas, documentos, regulamentos, comunicados, calendário, pesquisa, REST, AJAX e feeds foram recusados antes das fontes. HEAD ficou sem corpo. Os cabeçalhos pessoais incluem private/no-store, Vary: Cookie e noindex/nofollow.

Foram inspeccionadas capturas em 375, 768 e 1440 px, percurso de teclado, foco e texto a 200%, sem overflow após corrigir a quebra do título. Não se afirma ensaio de zoom nativo. Não houve erros JavaScript da vista. Os recursos da aplicação são locais. O browser deste computador recebeu ainda scripts injectados pelo Kaspersky, registados como intervenção do ambiente e não ignorados na prova. Capturas e storageState ficam privados.

Verificação final local: 846 testes PHP, 3734 assertions e quatro skips existentes no Windows, 386 ficheiros PHPCS sem erros ou avisos, 55 testes Node, lint e build. As integrações anteriores passaram com 21 asserções de identidade, 24 de sessão federada e 78 de sessões locais. Os quatro gates negativos development passaram. A integração de primeiro vínculo tem agora 24 verificações, incluindo conflito unique entre descoberta e inserção. Renovação: 11 verificações, com rollback da auditoria.

A integração revelou duas falhas corrigidas com RED/GREEN: distinção entre o filtro SQL nativo remove_placeholder_escape e filtros externos e preservação da limpeza nativa de cookies. A preparação continua a recusar transformações SQL externas. A CI ganhou uma job exclusiva das quatro integrações Workspace. A revisão independente, a CI no SHA exacto e a aplicação real continuam pendentes nesta altura.

## Preservação do laboratório normal

As integrações SQL usam prefixos novos e contas fictícias. Uma guarda recusa DML/DDL contra as tabelas normais durante o ensaio. O código HTTP normal ainda não foi ligado ao candidato.

Na comparação privada entre as duas primeiras provas de implementação, houve alteração de três transientes de temas e uma sessão federada nova da conta técnica anterior. Não se repôs nem podou essa sessão. A origem exacta da actividade HTTP paralela não ficou demonstrada. As restantes linhas anteriores, dados de negócio, configuração e 89 ficheiros privados mantiveram os hashes. Não se afirma igualdade integral do intervalo global. A aplicação final terá novos snapshots imutáveis e comparação por delta.

## Direcção visual

Mantém PRODUCT.md e DESIGN.md. Aplicam-se os princípios Impeccable do registo product e as orientações de acessibilidade de UI UX Pro Max. O auxiliar search.py desta instalação está indisponível: scripts/data são ficheiros com referências relativas para um destino ausente. Não se alega uma pesquisa de design executada. A vista será validada no browser com os recursos institucionais existentes.

Nenhuma destas verificações constitui homologação de produção, de IIS/MySQL ou aprovação institucional de permissões.

## Revisão independente antes da aplicação

A revisão do conjunto identificou três achados importantes. A consulta corrente passa a determinar primeiro a pertença pelo marcador nativo, pelo login opaco reservado ou pelo registo persistente, sem depender da auditoria para reconhecer uma conta anterior. Só depois exige o esquema completo. A perda da auditoria e a versão incompatível preservam a autenticação e a sessão da conta anterior mas recusam o pré-cadastro, incluindo quando o marcador foi removido.

A emissão de sessão de pré-cadastro relê o vínculo persistido antes de criar o token e depois da criação, antes do cookie. Confirma issuer, sub, alvo, active e authorized. Uma revogação detectada no segundo controlo elimina o token criado e não emite cookie nem wp_login. As guardas por pedido continuam a cobrir alterações posteriores.

A pré-validação agora consulta todas as entradas sem DML. Distingue create, resume e conflict e guarda um relatório novo e privado em cada execução. Os detalhes identificáveis não entram na consola. Uma colisão conhecida impede a aplicação de qualquer entrada do lote. A aplicação repete a análise antes do primeiro INSERT e mantém as verificações transaccionais por conta. A concorrência posterior pode ainda interromper a aplicação. Nesse caso, uma nova pré-validação revela as entradas exactas já confirmadas e o conflito, sem adoptar contas anteriores ou renovar prazos.

Regressão SQL exclusiva: 17 verificações, incluindo colisão no fim sem escrita, esquema parcial e preservação da sessão anterior, revogação antes e durante a criação do token. Suite PHP completa: 847 testes e 3738 asserções, com quatro omissões Windows anteriores. Integrações existentes de identidade, sessão federada e sessões locais: 21, 24 e 78 verificações. Os ensaios usam contas fictícias. A aplicação real continua pendente da CI do commit corrigido.
## Aplicação real concluída neste PC

Produto integrado na PR #33, merge ece43e0bfc92aed59f1d80f1a7642deb6c566a9b. Os 15 checks passaram no head def66450c2e500a61e74101de88741abddb3fee7 antes da activação. A revisão independente identificou três achados importantes e nenhum menor ou crítico. As três correcções tiveram RED/GREEN e regressão. Contagens finais: PHP 847 testes e 3738 asserções, quatro omissões Windows anteriores, PHPCS 389 ficheiros sem erros/avisos, Node 55, lint e build aprovados. A CI também executou a regressão SQL nova com 17 verificações.

A execução automática rejeitou a operação combinada de substituição de junctions e paragem do servidor antes de a executar, sem motivo mais específico além do bloqueio por política. Usou-se a integração Git autorizada e fast-forward do checkout principal. As duas junctions e o processo HTTP original foram conservados. O produto carregado foi confirmado por Reflection no checkout integrado. Não houve alteração de portas, cliente OAuth, âmbitos, MFA ou segredo. A configuração recebeu apenas duas constantes próprias, com os bytes anteriores guardados privadamente.

Foram guardados dump, configuração, ACL, fontes e snapshots novos sem substituir provas anteriores. A fonte original permaneceu intacta. O inventário original guardava source_sha256 em maiúsculas. Criou-se uma cópia privada de aplicação, alterando apenas a capitalização desse campo para o contrato canónico. A igualdade estrutural de todos os restantes campos foi confirmada. Os bytes da exportação e a população 73/4 não mudaram. A leitura protegida e o prazo voltaram a ser verificados.

Readiness explícita concluída por CLI. Pré-validação antes da criação: create=73, resume=0, conflict=0, com relatório privado. Aplicação: 73 utilizadores, 292 metadados, 73 pré-cadastros e 73 eventos novos. Zero alterações ou remoções de linhas anteriores. Zero alterações de opções, configuração, sessões anteriores ou dos 89 ficheiros durante a criação. Só aquelas quatro tabelas receberam linhas. Não houve concessões ou perfis People, roles/capabilities individuais, sessões, vínculos Google ou tentativas de email para as contas novas. As quatro genéricas ficaram excluídas.

Retoma: create=0, resume=73, conflict=0. Os hashes integrais das 52 tabelas, configuração, sessões e 89 ficheiros ficaram iguais antes e depois. Assim, IDs, nomes, emails, passwords técnicas, revisões, estados e auditoria não foram regenerados. Os relatórios de pré-validação são provas privadas novas por execução e não entram no conjunto dos originais.

O login Google real do titular autorizado entrou na conta administrativa anterior. O vínculo persistido e a sua auditoria conservaram os hashes. A sessão nova tem marca OIDC e limite absoluto de uma hora, sem revisão de pré-cadastro porque pertence à conta anterior. O pré-cadastro pendente para o mesmo email não foi associado. Ficou um conflito privado deduplicado com referências opacas e alvo anterior preservado. Depois desta entrada, as 73 contas seleccionadas continuaram AwaitingGoogle, sem capabilities, sessões ou vínculos e com exactamente 73 eventos de criação. Não se afirma primeiro vínculo real novo ou 73 logins reais.

A observação original é de 16 de outubro de 2026 às 10:55:33 UTC, com fracções arredondadas para baixo. O prazo inicial termina em 17 de outubro de 2026 às 10:55:33 UTC. As entradas ainda pendentes deixam de poder obter o primeiro vínculo depois desse instante. Só uma origem novamente verificada e renovação CLI explícita reabrem a janela. Reaplicar o lote, GET e login não renovam o prazo.

## Deltas nativos da aplicação e do login real

Entre o snapshot anterior à activação e o anterior à criação, as sessões e os 89 ficheiros ficaram iguais. A configuração recebeu as duas constantes e surgiram três tabelas Workspace, com uma linha de versão. A opção de readiness foi acrescentada. A opção cron mudou e o par de transientes wp_theme_files_patterns foi regenerado com novos IDs. Nenhuma outra linha anterior de negócio, identidade ou utilizadores mudou.

O dump anterior e o cron actual mostram reagendamentos de rotinas nativas de manutenção, actualizações, privacidade e limpeza. O valor do dump não coincide exactamente com o snapshot intermédio de cron. Portanto, não se atribui cada diferença desse intervalo a um evento isolado nem se afirma igualdade integral na activação. Os estados não foram restaurados para apagar a diferença. A comparação imediatamente anterior e posterior à criação tem zero deltas anteriores, como indicado acima.

Entre a retoma e o login Google real, mudou uma linha session_tokens da conta administrativa. O par de transientes available_translations foi regenerado durante a página de login. As restantes linhas anteriores, os registos Workspace, vínculos/auditorias e a configuração conservaram os hashes. Os 89 ficheiros ficaram iguais. A limpeza normal de tokens expirados na emissão não foi executada como preparação manual nem revertida depois.

## Decisões de execução e custo

Os auxiliares de extracção esperavam títulos Task em inglês. Foram criados briefs privados equivalentes aos títulos Tarefa aprovados. Custo: manutenção dos briefs privados. O vendor ignorado foi copiado de forma independente para resolver classes no checkout de ensaio. Não se mudou o lock ou dependências. Custo: espaço local adicional.

A observação aceita fracções UTC canónicas e arredonda para baixo. Custo: uma janela inicial inferior por uma fracção de segundo. Nomes e emails têm os limites dos campos nativos e nunca são truncados. Uma entrada excessiva faz parar para correcção. A verificação de ACL usa o módulo nativo do PowerShell e apenas operador, SYSTEM e Administrators. Custo: preparação explícita da ACL local, sem alargar a política para facilitar testes.

Os ensaios SQL usam tabelas nativas exclusivas e o browser usa um WordPress separado. Custo: não homologam o servidor institucional. As vias nativas HTTP que não cabiam nos ensaios SQL iniciais foram concluídas no ensaio completo. Reactivar o estado Workspace não reactiva um vínculo Google revogado separadamente. Custo: decisão própria para restaurar esse vínculo.

O tempo em espera SQL conta para a validade do primeiro vínculo. Custo: um pedido atrasado pode ser recusado. O relatório de conflito usa referências opacas e deduplicação. Custo: a identificação humana só é feita por revisão privada autorizada. GET/HEAD conservam a actualização last_seen da sessão federada. Custo: há DML de sessão, embora não haja escrita de negócio, esquema ou auditoria pela página pessoal.

O auxiliar de pesquisa UI UX Pro Max não existe nesta instalação. Foram usados as instruções disponíveis, PRODUCT/DESIGN, Impeccable e verificação visual, sem alegar pesquisa executada. Custo: a direcção visual depende do sistema institucional já existente. A cópia canónica do digest mantém o original e todos os dados. Custo: gerir dois inventários privados distintos e usar o hash correcto. A integração Git anterior à activação evitou operações locais bloqueadas. Custo: uma PR adicional só para documentar a aplicação.

Não há achados menores adiados. RH, concessões institucionais, outros titulares, escala real, segundo PC e IIS/MySQL continuam fora desta aceitação. As Issues #36/#37 e os ficheiros exclusivos da Albertina não foram alterados.