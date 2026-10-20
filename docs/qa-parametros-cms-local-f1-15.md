# Aceitação local dos parâmetros do CMS

Fecho funcional de 20 de outubro de 2026. Branch feat/parametros-cms-f1-15, [PR #59](https://github.com/atchutchi/plataforma-interna-arn/pull/59). A parcela de limites e formatos de uploads passou a aceitação local. O código ensaiado é 4bf27a6, com vinte checks aprovados. Este fecho documental exige novamente CI verde no SHA exacto antes do merge. Não conclui F1-15 institucional, F2 ou o site completo.

## Implementação e fronteira

LocalInstaller exige CLI, ambiente local, flags booleanas e manage_options. Prepara apenas cms.gestor.teste e cms.recusado.teste, com email example.invalid, papel subscriber e credenciais privadas aleatórias. Só o gestor recebe a concessão fictícia CmsAdministrator de âmbito institucional. O administrador técnico não recebe competência de negócio. Não há adopção de contas, substituição de credenciais, reactivação ou reparação de instalação parcial.

SettingsPage verifica autoridade antes da redirecção de login. GET e HEAD são de leitura. POST exige contrato fechado, nonce nativo, revisão e motivo. A recuperação de erros faz uma nova leitura autorizada. A confirmação usa redirecção 303. LocalSettings fecha as ligações dedicadas. O histórico conserva os valores anteriores/novos, conta interveniente, motivo e UTC, com vinte alterações por página.

Os três destinos são imagens de notícias, anexos de notícias e documentos. O limite configurável só restringe a admissão de bytes novos. MIME efectivo, assinatura, descritor, origem da versão, audiência e restantes limites continuam verificados. ImportBatch só descarta cópias próprias intactas quando a ausência de referências e auditoria foi confirmada sob bloqueios. Na incerteza conserva os bytes. Não apaga versões anteriores.

O responsável iniciou manualmente o servidor de localhost:8892. A recusa automática anterior de arranque e a recusa de navegação file: não foram contornadas. A prova agora usa HTTP WordPress. O laboratório tem prefixo SQL exclusivo, dez contas inteiramente fictícias, ficheiros privados e credenciais fora do Git/document root. Inclui uma Ana Teste sem autorização persistente, criada pelo mecanismo local anterior, que não foi promovida. Correio, cron e HTTP externo estão bloqueados. Nos ensaios dos consumidores, os recursos externos do browser foram recusados. A demonstração 8888 e o laboratório de contas reais não foram alterados.

## Resultados agrupados

| Verificação | Resultado e limite |
| --- | --- |
| PHPUnit | 974 testes, 4983 asserções, quatro omissões Windows anteriores. Execução final sem falhas com OPENSSL_CONF configurado |
| Node | 55 testes aprovados com o runtime existente e NODE_PATH configurado |
| PHPCS, lint e build | 19 ficheiros PHP aprovados, sem avisos/erros no fecho. Lint/build da fundação aprovados |
| Instalador e transporte CLI nativo | 31 controlos WordPress/MariaDB, tokens e nonces nativos. Preservou as 152 tabelas anteriores. Não é contado como HTTP |
| Concorrência e consumidores SQL | 92 controlos MariaDB com runtimes e ficheiros físicos, mas fornecedores de identidade/sessão sintéticos. Preservou 40 tabelas exteriores de negócio |
| Playwright sobre HTTP | 89 asserções finais aprovadas nos grupos abaixo. Sessões e formulários WordPress nativos, uploads multipart e decisões locais com contas fictícias |
| Integridade do laboratório HTTP | 332 ficheiros WordPress versionados iguais à branch. As 133 tabelas exteriores ao prefixo exclusivo conservaram todas as linhas por hash |
| Revisão independente | Revisão estática de instalador, autoridade, transporte, recuperação e consumidores sem achados P1/P2. Nenhuma alteração de produto necessária no fecho HTTP |
| CI | Vinte checks verdes em 4bf27a6. O SHA do commit documental de fecho e a integração ficam verificáveis na PR #59. Merge apenas com a CI desse SHA verde |

- ui: 27 asserções.
- security: 14 asserções.
- access: 6 asserções.
- layout: 2 asserções.
- consumer-create: 1 asserção.
- consumer-submit: 1 asserção.
- consumer-approve: 1 asserção.
- consumer-doc-create: 1 asserção.
- consumer-doc-publish: 2 asserções.
- admission-documents: 7 asserções.
- admission-news: 5 asserções.
- admission-news-finish: 3 asserções.
- revision-repeat: 2 asserções.
- published: 12 asserções.
- history: 4 asserções.
- restoration: 1 asserção.

A interface foi inspeccionada em capturas reais a 1440 e 320 píxeis. Passaram reflow a 320/390/768/1440, limites físicos dos controlos no viewport, alvos de 44px, navegação inicial por teclado e foco do erro após conflito. Texto do corpo ampliado por CSS a 200% não provocou overflow. Não foi ensaiado zoom nativo, leitor de ecrã ou segundo PC. Foram aplicadas as orientações Impeccable e UI UX Pro Max à interface existente. O auxiliar de pesquisa desta última skill não estava instalado, pelo que se usaram as orientações escritas.

## Matriz de aceitação

| ID | Evidência efectiva |
| --- | --- |
| CFG-01 | Domínio/CLI e página nativa com exactamente três destinos, defaults de 1 MiB e catálogo MIME fechado |
| CFG-02 | Testes PHP de formas/tipos e limites. HTTP recusou nonce inválido, chave extra, 0, negativo, excesso, notação exponencial e SVG |
| CFG-03 | Conta sem concessão, administrador técnico e anónimo recebem 404 vazio. Menu ausente. Âmbitos e geração anterior cobertos nos ensaios PHP/SQL |
| CFG-04 | Suspensão, revogação e expiração entre leitura e POST recusaram gravação. Restituições fictícias foram explícitas e auditadas, seguidas de novo login |
| CFG-05 | Estado/revisão/auditoria atómicos no ensaio SQL, incluindo rollback de falha injectada. Gravação nativa confirmada no browser |
| CFG-06 | Duas páginas reais com a mesma revisão: uma grava, a outra mostra conflito com valores propostos e política actual separados |
| CFG-07 | No-op não alterou revisão, auditoria ou ficheiros. Motivo com marcação não executou script. Histórico de mais de vinte alterações paginou sem duplicar eventos ou escrever |
| CFG-08 | PNG/PDF aceites inicialmente. Pedidos multipart forjados acima do limite ou com PDF desactivado recusados em notícias/documentos, sem cópias, referências, auditoria ou âncoras nativas novas |
| CFG-09 | PDF documental e media de notícia publicados antes da restrição conservaram os hashes e o acesso autorizado. Outra audiência e anónimo continuaram recusados. Revisões sem bytes novos conservaram os originais verificados |
| CFG-10 | Concorrência real entre ligações SQL, bloqueio da política e revalidação antes do commit nos dois repositórios. Limpeza e resposta perdida simulada cobertas nos 92 controlos. Não se afirma concorrência de dois uploads HTTP no servidor PHP de um só processo |
| CFG-11 | Instalador CLI restrito/idempotente e colisões cobertos pelos 31 controlos. HTTP com flag desligada ou esquema incompatível devolveu 404 sem reparação |
| CFG-12 | GET/HEAD e paginação preservaram negócio/ficheiros. REST autenticado tinha controlo positivo de sessão e rota CMS inexistente. Acessos recusados não expuseram estado/motivos |
| CFG-13 | Playwright confirmou configuração, CAS, revogação, uploads, downloads, teclado, foco, reflow e alvos. Capturas permanecem privadas |
| CFG-14 | Verificações agrupadas, preservação das tabelas exteriores, código exacto e revisão independente concluídos. CI do commit final é uma condição de integração, conferida na PR antes do merge |

## Incidentes do ensaio e limites

Foram corrigidos auxiliares privados de QA, sem alterar produto: ligação global wpdb ausente no comparador inicial, selecção de formulários/URL com campo HTML chamado action, comparação sensível a espaços e tentativa de fetch a partir da página de erro do browser. As verificações finais repetiram os percursos corrigidos.

O primeiro upload documental seleccionava também Ana Teste, sem snapshot de autorização. A guarda recusou a operação. O mesmo pedido com apenas o destinatário fictício activo foi aceite. Não se concederam acessos para fazer passar o teste.

Uma invocação do auxiliar de revisão de notícia excedeu os 60 segundos sem resultado. Antes de repetir, confirmaram-se a revisão anterior e a ausência de transacções pendentes. O clique nativo criou exactamente uma revisão e a repetição posterior por FormData passou, avançando uma única revisão. A causa exacta do atraso do auxiliar não foi demonstrada. O incidente não é apresentado como um teste de desempenho aprovado.

Os erros de consola observados incluíram recursos externos bloqueados, nomeadamente avatar e injecção do software de segurança local. Não se desactivou o software de segurança. O ensaio não é uma homologação de desempenho, antimalware, IIS/MySQL, HTTPS ou rede institucional. A falha pós-commit SQL simula uma resposta perdida por excepção, não um corte físico da rede.

Os valores de admissão regressaram aos defaults através de uma alteração auditada. Credenciais, cookies, fontes, capturas, HTML e dados concretos permanecem privados. O commit de fecho contém documentação e não copia o laboratório. Próximos passos: lacunas comuns de ficheiros/media, serviços F2, integração Google Calendar confirmada pelo responsável, QA global e homologação/release segundo o README.
