# Fila técnica de media e quarentena F1-08

## Parcela entregue

A página Ferramentas > Fila de media recebe entre um e oito ficheiros fictícios PDF, PNG ou JPEG, até 1 MiB cada. O pedido copia os bytes para uma pasta privada e regista o lote. A análise decorre exclusivamente num worker CLI explícito, limitado a uma, duas ou três entradas por execução. Não usa cron, daemon ou rede externa.

A fila é técnica. Não cria notícias, documentos, anexos, permissões ou publicações. Os formulários existentes conservam a admissão síncrona. A próxima parcela deve integrar os consumidores, incluindo controlo da versão, identidade original, autoridade corrente, política CMS e registo/auditoria atómicos. Um resultado analisado na fila não substitui essas condições.

## Estados e decisões

Cada entrada começa em fila, passa a em análise e termina analisada ou retida. O worker reutiliza MediaPipeline, ClamAV com assinaturas oficiais recentes e o processo isolado de imagens. Cada ficheiro recebe o orçamento integral da admissão, sem partilhar os 120 segundos com os restantes ficheiros do lote. Os originais e derivados analisados ficam em quarentena, sem endpoint de entrega.

O operador pode reter uma entrada, pedir nova análise, cancelar ou arquivar o lote. Cada decisão exige motivo de 10 a 300 caracteres, nonce e revisão corrente. Há um máximo de três análises e três pedidos de repetição por entrada. Não existe libertação manual, supressão de scanner, remoção de ficheiros ou reabertura de lote arquivado.

Uma interrupção deixa a entrada em análise. Recuperar interrupção passa-a a retida, com motivo auditado. Só funciona com o bloqueio do worker livre. Depois é necessária uma decisão explícita para repetir a análise integral. Não existe expiração de lease que permita roubar trabalho de um processo ainda vivo.

## Persistência e limites

A fila usa um diário privado por lote. Cada evento conserva sequência, actor, instante UTC, operação, motivo e metadados técnicos fechados. Uma cadeia de hashes detecta alterações e marcadores imutáveis por revisão, escritos e sincronizados antes do append, detectam truncagem de eventos completos. Um marcador sem evento ou evento sem marcador torna o lote indisponível. Não há reparação silenciosa. Isto não oferece resistência a um administrador do sistema de ficheiros que reescreva integralmente o diário e os marcadores.

Bloqueios de ficheiro não bloqueantes serializam recepção, alterações do lote e execução do worker. A análise não mantém transacções SQL nem o bloqueio do diário. A autoridade é consultada antes das operações e novamente antes da confirmação do resultado. Se for revogada, o resultado não é confirmado e a recuperação continua explícita. Erros e resultados indeterminados conservam as cópias.

Um pedido de upload tem identificador aleatório. Repetir o mesmo pedido não cria outro lote. Um lote parcialmente recebido fica identificado como incompleto, consome capacidade e exige diagnóstico. Não se apagam as cópias já recebidas.

A admissão existente conserva o seu limite independente de 100 tentativas de quarentena. Quando se esgota, a entrada fica retida e exige intervenção operacional, sem remoção automática. O limite local da fila é de 50 lotes conservados e exige pelo menos 64 MiB livres antes da recepção. Arquivar não recupera capacidade física. Cada diário aceita até 1 MiB e 200 eventos, acima do máximo de transições permitido pelas repetições limitadas. Um ensaio cobre 114 eventos com motivos Unicode máximos. A manutenção física e a política de conservação institucional permanecem pendentes.

## Activação e operação local

A activação exige ambiente local, instalação não multisite, ARN_ALLOW_LOCAL_MEDIA_QUEUE e ARN_ALLOW_LOCAL_MEDIA_PROCESSING exactamente true, ARN_LOCAL_MEDIA_QUEUE_USER_ID inteiro e a pasta existente ARN_MEDIA_QUEUE_DIR. A pasta deve estar separada das raízes públicas, do armazém de entrega e da quarentena do scanner. São conservadas as configurações privadas do scanner e de GD da admissão existente.

A conta tem de coincidir exactamente com o ID configurado, ter email fictício example.invalid ou example.test e capacidade nativa manage_options. A página exige sessão nativa válida e as guardas correntes de estado da conta. O worker exige WP-CLI e a mesma conta técnica explícita. Esta configuração não atribui competências de negócio.

Exemplo, no WordPress do laboratório autorizado:

~~~sh
wp arn media work --limit=3 --user=<operador-ficticio>
~~~

A página mostra estados, tentativas, revisões e histórico. Não mostra caminhos físicos, saídas brutas do scanner ou ligações para os bytes. Nonce inválido, conta não configurada, método ou forma de pedido inválidos recebem recusa privada. Decisões concorrentes exigem actualizar a página.

## Aceitação

Aceitação local em 25/10/2026: PHP 1049 testes e 5292 asserções, seis omissões específicas de Windows. A fila acrescenta 22 testes e 94 asserções. Node 55, lint/build e PHPCS dos sete ficheiros afectados aprovados. Integração MariaDB: 24 verificações da admissão e 23 da reconciliação. Playwright: 26 verificações aprovadas, incluindo recepção em cerca de 3,6 segundos, processamento real, decisões auditadas, nonce, revisão antiga, repetição do pedido, contas recusadas, cabeçalhos privados, teclado e vista móvel. Screenshots de desktop e móvel inspeccionadas. Os ensaios ficaram agrupados no fecho. Os testes injectados de falhas não são prova de detecção real de malware. O percurso HTTP usa PDF e imagem sintéticos e o ClamAV real. Originais, configuração, screenshots, diários e relatórios completos ficam fora do Git.

A revisão independente detectou truncagem de eventos completos, esgotamento de revisões por repetição manual e dois problemas de limites Unicode. Todos foram corrigidos com regressões. Não se reduz a exigência de auditoria nem se ignora um erro para fazer o worker avançar.

A comparação final confirmou inalteradas 133 tabelas exteriores, 29 tabelas de negócio, 13 linhas de contas e os 82 ficheiros anteriores abrangidos pelo snapshot. Foram acrescentados 16 ficheiros de processamento na quarentena e um lote privado separado com duas cópias de origem, diário e marcadores. Os hashes dos dois originais e das quatro saídas de processamento foram verificados após cancelamento e arquivo. Não houve escrita de negócio nem criação de contas. As sessões nativas do ensaio são alterações esperadas.

Dois erros do guião Playwright foram corrigidos sem repetir decisões de negócio: o selector do elemento summary e a string de um PDF sintético no teste de pedido repetido. As provas anteriores foram conservadas. A revisão independente final não tem achados pendentes. O merge continua condicionado a todos os controlos verdes no SHA exacto, com confirmação posterior da CI de main.

## Próxima parcela

Integração assíncrona dos consumidores e aplicação das políticas de negócio na confirmação. Continuam pendentes preview PDF, manutenção automática de assinaturas, política de conservação, capacidade de produção, ACL NTFS e homologação IIS. Esta entrega não activa a demonstração 8888 nem produção.
