# Gestão de concessões fictícias F1-03

14 de outubro de 2026. Esta continuação acrescenta gestão local de funções e âmbitos pelo painel WordPress, sobre o serviço persistente e auditado existente. A especificação está em docs/superpowers/specs/2026-10-14-f1-03-concessoes-locais-design.md. A matriz institucional mantém a validação pendente.

## Âmbito

Ferramentas, Concessões locais, destina-se apenas a Ana Teste, existente e marcada como fixture. Exige ambiente efectivo local, ambas as flags booleanas de acesso fictício e persistência e manage_options. O alvo e o actor são resolvidos no servidor. A ferramenta não atribui roles WordPress nem concede competências a pessoas reais.

O catálogo contém dois presets fictícios já usados nos ensaios: Funcionário com âmbito próprio e Responsável da unidade fictícia A. O browser não pode fornecer funções administrativas, âmbitos arbitrários, datas ou outra conta. A nova concessão começa no instante do servidor e não tem fim configurável nesta ferramenta de laboratório.

Criar e substituir exigem conta Active. Revogar continua possível em Suspended. A substituição conserva a concessão anterior revogada. Não há eliminação do histórico. O serviço exige revisão esperada e confirma a alteração e auditoria na mesma transacção. A mudança efectiva aumenta a revisão e obriga a novo login na sessão fictícia.

GET só lê. POST exige nonce, operação conhecida, identificadores e revisão canónicos e justificação validada. A referência de auditoria é LAB-GRANT-MANAGEMENT. Um formulário antigo ou pedido inválido é recusado sem escrita parcial. Não há novo schema ou dependência.

## Verificação

PHPUnit completo: 558 testes e 1910 asserções. Após tornar estrita a comparação de âmbito no rótulo do histórico, a verificação focada final passou 93 testes e 156 asserções. PHPCS global: 112 ficheiros sem erros. Node: 54 testes. Lint e build concluídos.

Integração no WordPress 7.1.3, PHP 8.4.26 e MariaDB 11.8.3 portáteis, com serviços ligados apenas a loopback: sessões locais com 61 asserções, autorização persistente com 97, identidade com 21 e sessão federada com 24. As suites usam contas e tabelas exclusivas, com limpeza. O ensaio de sessões existente foi alargado e já pertence ao comando npm run test:account-sessions:integration e à CI. Não foi necessário outro comando ou nova configuração.

No browser, o administrador de laboratório criou uma concessão de unidade, substituiu-a por âmbito próprio e revogou a concessão de ensaio. Cada operação aumentou a revisão e registou um único evento com actor, justificação e referência LAB-GRANT-MANAGEMENT. Um formulário desactualizado foi recusado com HTTP 409 sem alteração parcial. A auditoria ficou visível na ferramenta de leitura.

Playwright confirmou, após cada operação, que o cookie anterior deixa de dar acesso ao portal, REST e AJAX. As duas últimas vias devolveram 401. Um novo login da fixture activa voltou a funcionar sem erros JavaScript. Uma conta subscriber recebeu 403 na página e 400 no dispatcher POST, sem formulário ou dados da fixture. Um visitante anónimo foi encaminhado para login. A ferramenta de estado manteve a revisão ao submeter novamente Active, sem criar outro evento.

Estado final de Ana Teste: Active, revisão 17, papel nativo subscriber. Existem cinco concessões no histórico e 17 eventos auditados. A concessão própria original ID 3 continua vigente. As concessões 1, 2, 4 e 5 estão revogadas. lab_admin conserva o seu papel e não está marcado como fixture.

Interface verificada a 375, 768 e 1440 pixels, sem deslocamento horizontal e com rótulos associados. Capturas locais em output/playwright/concessoes-locais.png e output/playwright/concessoes-locais-mobile.png, excluídas do Git. A página administrativa não apresentou avisos ou erros na consola.

## Limites

Esta ferramenta é administrativa e fictícia. Não implementa uma autoridade delegada institucional ou autorização por objecto dos módulos futuros. A conta técnica local não se torna administrador de acessos institucional. F1-03 permanece Em curso quanto ao âmbito institucional, que depende de F0-02/F0-06 e homologação. F2 e produção mantêm os seus pré-requisitos.
