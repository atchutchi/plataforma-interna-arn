# Aceitação local dos parâmetros do CMS

Registo de 20 de outubro de 2026. Branch feat/parametros-cms-f1-15, PR #59. Entrega em curso. A implementação está preparada, mas a aceitação integral CFG-01 a CFG-14 não está concluída. Não activar no laboratório estável nem fazer merge antes do circuito HTTP e da CI verde no SHA final.

## Implementação nativa

LocalInstaller exige CLI, ambiente local, as três flags booleanas e manage_options. Valida uma raiz privada exterior ao document root e ao repositório. Cria somente cms.gestor.teste e cms.recusado.teste, com email example.invalid, papel WordPress subscriber e credenciais aleatórias em ficheiros privados. Só o gestor recebe a concessão fictícia CmsAdministrator de âmbito institucional. Um administrador técnico não recebe esta competência.

No ensaio automatizado, os nomes recebem um prefixo exclusivo arn_cms_test seguido de doze dígitos hexadecimais. O instalador não adopta contas, não substitui credenciais, não reactiva suspensos e não repara esquemas parciais. Uma instalação completa repetida conserva os dados. Uma interrupção parcial exige diagnóstico explícito.

SettingsPage recusa antes da redirecção nativa para login. GET e HEAD são apenas leitura. POST exige contrato fechado, nonce ligado à operação/titular, revisão e motivo. O erro recuperável exige nova leitura autorizada antes de apresentar campos. A gravação confirmada usa redirecção 303. LocalSettings fecha a ligação dedicada em todos os percursos.

SettingsView apresenta os três destinos, valores em bytes, formatos permitidos, valores actualmente em vigor e histórico de vinte alterações por página. Motivos e valores são escapados. O erro é focável, os campos conservam labels e ajuda associada e o CSS usa as fontes locais Manrope/Source Sans 3 e os tokens visuais existentes. Impeccable e as orientações escritas UI UX Pro Max foram aplicados. O auxiliar de pesquisa desta última skill não estava instalado no caminho indicado. A inspecção visual continua pendente.

## Evidência recolhida

| Verificação | Resultado e limite |
| --- | --- |
| PHPUnit completo | 974 testes, 4983 asserções, quatro omissões Windows anteriores. Sem falhas na execução final com OPENSSL_CONF configurado |
| Node | 55 testes aprovados. A primeira invocação não definiu NODE_PATH e falhou a resolução de @wordpress/env. Execução final com o runtime existente aprovada |
| PHPCS | 19 ficheiros do CMS e ensaios. Seis avisos de alinhamento no teste de renderização corrigidos. Execução final sem avisos ou erros |
| Lint/build | Aprovados. O build verifica a fundação existente e não compila blocos novos |
| Instalador e transporte nativos | 31 controlos com WordPress/MariaDB, utilizadores, tokens e nonces nativos. Invocação do transporte por CLI, sem pedidos HTTP |
| Preservação | As 152 tabelas anteriores ao ensaio final conservaram todas as linhas. As duas fixtures e sete tabelas exclusivas do ensaio foram removidas. O ensaio anterior de 27 controlos preservara 133 tabelas, antes da preparação de outro laboratório isolado |
| Consumidores e ficheiros | 92 controlos MariaDB no incremento anterior, com runtimes reais e ficheiros físicos. Os fornecedores de identidade/sessão desse ensaio eram sintéticos. As 40 tabelas exteriores de negócio conservaram os hashes |
| Revisão independente | Revisão estática do instalador, autoridade, página e recuperação sem achados P1/P2. Não substitui o browser |
| CI anterior | da5837e com 20 checks aprovados. O novo incremento exige a sua própria CI |

O ensaio nativo cobre colisão de credenciais, criação restrita, idempotência, administrador técnico e conta sem concessão recusados, sessão corrente, nonce válido/inválido, revisão/auditoria, conflito, no-op, expiração, marca de fixture incompatível, suspensão, recusa de reparação de esquema e conservação dos dados anteriores. A renderização adicional verifica escape dos motivos, distinção entre campos recuperados e política actual, associação de ajuda/foco do erro e ausência de escritas.

## Pendência HTTP e Playwright

Foi preparada uma instalação WordPress distinta para localhost:8892, com prefixo SQL próprio e três contas fictícias. Não contém dados Workspace, originais editoriais ou credenciais Google. Correio, cron e HTTP externo estão bloqueados. A demonstração em localhost:8888 conserva o código integrado anterior.

A revisão automática rejeitou o comando de arranque do servidor PHP, indicando apenas «blocked by policy». O servidor não foi iniciado pelo agente. Um ensaio visual alternativo renderizou duas vistas fictícias em ficheiros privados, mas o browser recusou a navegação file:. Não há resultado Playwright aprovado para esta parcela. Foi preparado um script privado de arranque local e solicitada intervenção ao responsável. Não contornar os bloqueios nem apresentar a renderização CLI como aceitação no browser.

Depois do arranque autorizado, validar no browser a sessão nativa do gestor, menu, edição, histórico, no-op e conflito em duas páginas. Confirmar recusa indistinta da conta sem competência e do administrador técnico, nonce inválido, sessão expirada, suspensão/revogação entre GET e POST, flags desligadas e esquema incompatível, sem DDL ou reparação por HTTP. Usar apenas fixtures novas e restituir os estados de ensaio explicitamente.

Completar a composição dos dois consumidores com ficheiros fictícios, verificando admissão permitida/recusada, política alterada durante a operação, conservação dos ficheiros anteriores e ausência de novas referências numa tentativa recusada. Conferir REST, teclado, foco do erro, texto ampliado, reflow a 320/390/768/1440 e alvos de 44px. Capturas e provas concretas permanecem privadas.

Só então preencher a matriz CFG-01 a CFG-14 com a evidência efectiva, actualizar o README, confirmar a CI do SHA exacto e considerar a integração. Google Calendar, serviços F2 e homologação de produção continuam no objectivo completo e não são concluídos por esta entrega.
