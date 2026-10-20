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

Logs, configurações, nonces, cookies, capturas e dados concretos ficam fora do Git. A comparação física dos 22 originais administrativos não foi repetida nesta etapa. A pesquisa de tarefas exige ainda o percurso WordPress na demonstração final. CI do head entregue e activação estável só depois da revisão e ausência de falhas. A entrega funcional não conclui a aceitação institucional, antimalware/anexos, media nativa, homologação IIS/MySQL, zoom nativo ou o restante site.
