# Estado operacional de media no laboratório

Entrega local de 26/10/2026, branch `feat/estado-operacional-media`, base main `47f4427` com a PR #67 integrada e os doze controlos de main aprovados. Acrescenta Ferramentas > Estado de media e `wp arn media check --user=<operador-ficticio>`.

## Funcionamento

Verificar agora executa três ensaios sintéticos. O scanner analisa texto benigno usando os argumentos de admissão existentes, incluindo assinaturas oficiais e idade máxima de três dias. O processo GD converte um PNG de um píxel. O conversor PDF processa um PDF mínimo gerado pelo servidor com as versões e limites já homologados localmente. O ensaio benigno não comprova detecção de malware e o diagnóstico nunca substitui a análise individual de cada envio.

O inventário percorre apenas metadados da fila, quarentena e entrega privada. Mede ficheiros, bytes, directórios e espaço livre. Não lê documentos ou executa operações de negócio. Recusa links, hard links de ficheiros, entradas especiais, profundidade superior a quatro níveis, mais de cinco mil entradas e travessia acima de dois segundos por raiz. Uma leitura incompleta aparece indisponível, sem apresentar um total parcial como completo.

O painel identifica data UTC e actor de cada execução. Disponível significa que o ensaio passou naquele instante. Não certifica versões futuras, validade contínua das assinaturas ou o servidor institucional. As métricas podem mudar durante a observação. Os limites reais da fila e quarentena continuam a ser verificados na admissão. O painel não reserva espaço.

## Recibos, acesso e conservação

Cada pedido possui ID opaco único, recibo inicial e recibo final com marcador de hash. As escritas são exclusivas e sincronizadas. Reenviar um pedido concluído devolve o mesmo recibo sem repetir os processos. Um pedido incompleto não pode ser repetido com o mesmo ID. Uma nova verificação é uma acção explícita. Um bloqueio impede duas execuções simultâneas. Cinquenta relatórios conservados ou menos de 64 MiB livres bloqueiam novo diagnóstico.

Uma interrupção conserva o início e os bytes técnicos. A UI distingue sem recibo final de recibo divergente ou ilegível. Recibos corrompidos bloqueiam novas verificações. Não existe limpeza ou reparação automática. Os hashes detectam divergências, mas não protegem contra um administrador de ficheiros que reescreva todo o arquivo.

GET só lê o histórico. Não cria bloqueios, recibos, processos ou esquemas. POST exige forma fechada, nonce e ID canónico. O operador precisa da guarda fictícia da fila, sessão/estado válidos e `ARN_ALLOW_LOCAL_MEDIA_HEALTH` exactamente true. CLI também verifica o estado da conta. `ARN_MEDIA_HEALTH_DIR` aponta para pasta privada própria, separada das raízes públicas, fila, quarentena, entrega, assinaturas e supervisor. A autoridade é revalidada antes da confirmação e da resposta. Outra conta ou sessão ausente recebe 404 vazio com cabeçalhos privados.

As três fontes sintéticas, derivados e logs permanecem no directório privado do diagnóstico. Nunca se publicam caminhos, stdout, stderr ou excepções. Não há actualização de executáveis ou bases antimalware nesta operação. Política institucional de retenção, troca de versões, manutenção automática, recuperação de conclusões de negócio indeterminadas e serviço persistente continuam pendentes.

## Aceitação

Playwright passou 31 verificações no laboratório 8892: estado vazio, GET sem escrita, cabeçalhos privados, controlo com foco e alvo de 44 píxeis, POST real, scanner/imagem/PDF disponíveis, inventário, reenvio idempotente, nonce inválido, campos adicionais, ID inválido, métodos e query recusados, autor sem competência, anónimo, recibo incompleto visível e conservação por GET. Reflow a 320, 390, 768 e 1440 píxeis. Capturas de desktop e móvel inspeccionadas.

CLI confirmou separadamente os três ensaios reais. Ficaram dois relatórios concluídos e uma interrupção sintética conservada. Não se executou a fila de negócio. A baseline e a comparação final confirmaram igualdade das 133 tabelas exteriores, das 29 tabelas de negócio, das 13 contas e dos 311 ficheiros anteriores. A leitura de inventário inclui os 52 ficheiros existentes na entrega, incluindo candidatos a órfão e entradas desconhecidas, em vez de contar apenas os 39 binários registados. A primeira expectativa do guião confundia estes dois conjuntos. Foi corrigida com base no inventário anterior, conservando a evidência inicial.

PHP passou 1098 testes e 5510 asserções. Sete testes foram omitidos no Windows, seis anteriores e a nova regressão de FIFO POSIX. Os dezasseis testes de diagnóstico passaram com essa omissão. Node passou 55 testes, lint/build e PHPCS dos seis ficheiros afectados passaram. A revisão independente identificou abertura de recibo antes de validar o tipo. A correcção verifica lstat antes de abrir e fstat depois, incluindo identidade e tamanho. A regressão FIFO fica na CI Linux e não é apresentada como executada no Windows.

O laboratório estava parado após a continuação. Foram retomados os processos existentes, confirmados em loopback nas portas 33317 e 8892, sem reinstalar a base. Não se iniciou a demonstração 8888, não se alteraram contas reais e não houve deploy. Evidência, credenciais, configuração e relatórios técnicos ficam fora do Git. A integração exige CI verde do SHA final e confirmação de main.
