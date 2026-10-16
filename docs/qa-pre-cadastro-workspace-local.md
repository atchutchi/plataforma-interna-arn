# QA do pré-cadastro Workspace

Estado em execução. A preparação do plano foi aprovada para execução directa com revisão independente final. As provas operacionais ficam privadas. Não contém uma lista de funcionários ou configuração OAuth.

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
