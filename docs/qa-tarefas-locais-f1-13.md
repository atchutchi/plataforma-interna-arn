# QA da entrega funcional local F1-13

20 de outubro de 2026. Elenco próprio f113.alfa.teste, f113.beta.teste e f113.responsavel.a.teste, emails example.invalid. Laboratório WordPress separado em loopback. Não foram usados pré-cadastros Workspace, contas institucionais, email ou Google. A notícia fornecida entretanto pertence à entrega separada #52 e não às fixtures de tarefas.

## Percursos entregues

Minhas tarefas e Tarefas da equipa têm lista, pesquisa literal, filtros, paginação 20+1 e detalhe privado. A equipa permite atribuir, reatribuir, alterar prazo e cancelar com motivo. O destinatário inicia, conclui e comenta. Histórico e comentários permanecem após conclusão ou cancelamento. Os motivos de gestão não aparecem na vista pessoal. Os avisos são do módulo e marcar como lido não altera revisão, concessões ou sessão.

Instalação explícita por LocalInstaller em CLI local administradora. Não há instalação por GET, activação ou migração automática. Pages contém apenas marcadores dinâmicos. InstallationState verifica elenco e páginas reais, sem conceder autoridade. Repetir conserva dados editados. Colisão, marcador em falta, estrutura parcial ou conta suspensa recusam reparação. Palavras-passe ficam em ficheiros privados fora do projecto e document root.

Os menus, Serviços e os acessos do Início usam as mesmas guardas. A pesquisa integrada acrescenta o tipo Tarefa por título/descrição e apresenta só título, tipo e destino. No elenco fechado actual, a pesquisa escolhe a equipa quando autorizada e senão a vista própria. O âmbito do gestor contém a sua unidade, evitando duplicar uma tarefa presente nas duas vistas. Isto não especifica uma união futura de âmbitos institucionais. Cada página de origem revalida autoridade e fecha a ligação criada.

## Evidência executada

A suite PHP antes da ligação final à pesquisa passou 932 testes e 4254 asserções, com quatro omissões Windows preexistentes. Depois de ligar a pesquisa, a suite passou 933 testes e 4307 asserções, mantendo as quatro omissões. A composição com a correcção transaccional recebe a CI final própria. Node passou 55 testes, lint e build. PHPCS passou os ficheiros da entrega, com correcções de estilo antes do commit.

MariaDB passou 90 verificações de autoridade/persistência, incluindo sessão nativa, esquema parcial, suspensão, unidade inactiva, CAS, idempotência, rollback integral e dois processos realmente bloqueados. Um confirma e o outro recusa a revisão, sem evento ou aviso duplicado. A extensão prova 21 linhas autorizadas em 20+1, ordem sem repetição, página excessiva e ausência de contagens de outra unidade. Uma concessão fictícia temporária de gestão B, criada e revogada pelo serviço auditado apenas no prefixo exclusivo, permite preparar uma tarefa real de Beta e verificar a recusa por Alfa e pelo gestor A.

O instalador passou 31 verificações. Repetição integral, repetição após edição, colisão e estruturas parciais foram exercitadas. As 127 tabelas anteriores ficaram iguais após remover os recursos temporários. Estes ensaios finais usaram a composição com a correcção SET NAMES da branch própria, sem activar essa composição no laboratório estável.

Playwright concluiu a matriz principal com 33 verificações, a parcela de segurança com 19 e a matriz HTTP com 10. Os percursos intermédios confirmaram ainda reatribuição, cancelamento, no-op, erro de data com campos conservados e foco por teclado. Sucesso/no-op usam 303, CAS numa página autorizada mantém erro de formulário e recusa de acesso/método/nonce/forma devolve 404 vazio private/no-store. GET/HEAD não escrevem nas tabelas de negócio. O reenvio da criação conserva o mesmo objecto.

A conta suspensa perde a sessão em uso. Reactivar não recupera essa sessão e exige login novo. Unidade inactiva e concessão revogada fecham a leitura. O administrador técnico teve controlo positivo no wp-admin antes de receber 404 nas duas páginas. Beta não conta nem lê tarefas A. O antigo responsável perde detalhe e avisos depois da reatribuição.

O controlo REST positivo confirmou a identidade do browser com nonce válido. Páginas, contexto edit, revisões, renderer, search, feed e oEmbed não devolveram o título/corpo/comentário da tarefa. Upload multipart foi recusado. HTML foi recusado e reapresentado escapado. Capturas e métricas cobrem 320/390/768/1280/1440, h1 único, área útil sem overflow e controlos com pelo menos 44 px e texto de 16 px. CSS raiz a 200% foi medido separadamente. Zoom nativo do browser a 200% não foi executado.

## Falhas encontradas e correcções

A revisão encontrou o acesso por page_id a uma página com marcador editado a passar para o portal geral. Controller passou a reconhecer também o slug do objecto consultado. As recusas autenticada e anónima passaram depois da edição do marcador. A integração do tema eliminou o h1 duplicado.

O teste de formulário encontrou um motivo obrigatório indevido em alterações opcionais de responsável/prazo. Foi corrigido, conservando motivo obrigatório no cancelamento. O rendering passou a ocultar motivos também na vista pessoal e a paginação anterior passou a substituir o parâmetro antigo.

Ocorreram falhas no ensaio privado, sem serem confundidas com defeitos do produto: timeout do clique depois de o login já ter concluído, snapshot inline não lido pelo helper, cookie URL-encoded antes da geração do nonce REST e status opaco do fetch manual. Os ensaios corrigidos verificaram o estado real, usaram o cookie descodificado como no PHP e APIRequestContext para observar o 303.

## Preservação e limites

As três contas, duas páginas, doze tabelas, sessões e palavras-passe temporárias do ensaio HTTP foram removidas. A primeira limpeza parou numa chave estrangeira. A retoma removeu apenas as sete tabelas próprias restantes na ordem das dependências, sem desactivar constraints. O administrador fictício nativo e as doze tabelas WordPress da instalação separada foram conservados.

Das 115 tabelas exteriores à instalação HTTP, 114 conservaram os hashes. A única diferença foi options da demonstração separada, em quatro linhas identificadas de cache/transient de padrões do tema. O delta foi registado e não foi apagado para forçar igualdade. Contas, concessões e sessões exteriores ficaram iguais. Não alegar preservação binária de todas as opções. A igualdade global do ensaio de instalação é uma prova separada.

Logs, configurações, nonces, cookies, capturas e dados concretos ficam fora do Git. A comparação física dos 22 originais administrativos não foi repetida nesta etapa. O percurso WordPress da pesquisa foi depois provado na demonstração final. A integração exige CI do head entregue sem falhas. A entrega funcional não conclui a aceitação institucional, antimalware/anexos, media nativa, homologação IIS/MySQL, zoom nativo ou o restante site.

## Composição e demonstração

A composição 4927d67 inclui a PR #54, merge main a9e58db. A PR #53 original foi fechada como substituída, com a branch preservada. A suite composta passou 941 testes e 4591 asserções, com quatro omissões Windows. Os 20 checks da PR #55 neste head passaram. O laboratório estável recebeu os311 ficheiros WordPress versionados, todos com hash Git correspondente, e a flag Tasks explícita. LocalInstaller criou três fixtures e duas páginas. Três tarefas fictícias foram preparadas com auditoria, prazos, comentário e um exemplo em curso.

A aplicação conservou os hashes das 17 contas e 285 metadados anteriores. O novo elenco não concede acesso a contas Workspace ou aos leitores dos outros módulos. Playwright passou 28 verificações adicionais na demonstração: filtros por responsável/estado, acessos do Início, pesquisa sem comentários ou destinos repetidos, vistas próprias/equipa, Beta vazio, editor de notícias autenticado recusado em Tasks, regressão People/F1-12 e conservação da notícia com dez fotografias.

A revisão final reproduziu perda de uma nota própria na resposta de conflito quando a operação deixava de estar disponível. View::unapplied conserva apenas campos do POST do actor numa secção de leitura, com escape e sem restaurar a operação. Um segundo teste reproduziu uma âncora perdida para motivo vazio. A resposta conserva agora o destino do erro e indica Em branco. Prazo vazio mantém a intenção Sem prazo. As duas regressões passaram depois da correcção: 9 testes e 75 asserções focados, PHPCS e revisão independente sem achados pendentes. O head 00fde38 passou os 20 checks e foi aplicado aos três ficheiros de apresentação, com 311 hashes iguais e sem migração ou alteração de contas.

## Fecho do percurso local

A CI de 00fde38 confirmou 941 testes PHP e 4600 asserções, PHPCS, Node/construção e os grupos WordPress/SQL. Playwright acrescentou sete verificações na demonstração: duas páginas reais com início concorrente, nota própria conservada, estado inválido com revisão corrente sem escrita e motivo vazio com âncora acessível depois de conclusão concorrente. A preparação desse último ensaio teve um timeout ao procurar o botão dentro de details fechado. A retoma abriu o controlo visível antes de ler o formulário e passou, sem repor tarefas ou apagar auditoria. O exemplo semanal ficou Em curso e o inventário Concluído por acções auditadas das fixtures.

A flag própria foi depois colocada false apenas durante um ensaio HTTP controlado: a vista de equipa passou de 200 a 404 vazio private/no-store. O finally restaurou os bytes completos de wp-config.php e a resposta 200. CA-26 e CA-34 ficaram provados no browser/HTTP. Serviços deixa de listar Tarefas de equipa como serviço futuro. O teste de apresentação reproduziu a expectativa antiga e passou 1 teste/11 asserções depois da remoção, com PHPCS e revisão independente aprovados.

A entrega funcional local fica preparada para integração na PR #55 após CI verde no novo head. A notícia fornecida e as dez fotografias mantêm-se no laboratório. Os limites de aceitação institucional, expiração por HTTP, zoom nativo e comparação física dos 22 originais continuam explícitos. Não renovar pré-cadastros ou instalar código na base com contas reais. As provas concretas continuam privadas.
