# Pré-visualizações privadas de PDF no laboratório

Entrega de 26 de outubro de 2026, branch `feat/previews-pdf-privados`, base integrada `73b6b39`. Apenas laboratório fictício 8892. Não altera a demonstração 8888, contas reais, documentos institucionais ou produção.

## Comportamento entregue

Os novos PDFs enviados pelos formulários de notícias, documentos e tarefas passam pela fila existente. Depois da análise do original, um processo separado produz duas imagens PNG da primeira página: miniatura até 320 píxeis e pré-visualização até 1280 píxeis no lado maior. Cada imagem recebe análise antimalware antes do registo atómico e auditado pelo consumidor. O autor continua a confirmar a conclusão do envio. A análise técnica não publica conteúdo.

A vista editorial e a notícia publicada, o detalhe documental e os anexos de tarefas apresentam um controlo de expansão acessível por teclado. A legenda esclarece que a imagem mostra apenas a primeira página. O download conserva o PDF completo, incluindo os seus bytes e eventuais assinaturas. A imagem não constitui validação de assinatura.

A disponibilidade é consultada pelo leitor autorizado de cada consumidor. O handle de verificação é fechado. O pedido da imagem revalida novamente identidade, sessão, objecto, versão e audiência. Não existem URLs estáticas ou identificadores autónomos de derivados na interface. As respostas privadas conservam `no-store`, `private` e `nosniff`. Recusa ou ausência produz o mesmo 404 vazio.

PDFs antigos sem derivados mantêm o download e não apresentam um controlo quebrado. A fila aceita o resultado histórico com apenas `original` ou o novo conjunto exacto `original/thumbnail/preview`. Nenhum GET gera imagens ou reescreve o arquivo.

## Operação local

A activação exige o processamento privado já configurado, ambiente `local`, `ARN_ALLOW_LOCAL_PDF_PREVIEWS` estritamente `true` e `ARN_MEDIA_PDF_PYTHON` com caminho absoluto canónico para o executável Python. As definições pertencem à configuração privada do laboratório. O repositório contém apenas código e requisitos, sem caminhos privados concretos ou segredos.

O runtime usa as versões fixadas em `scripts/requirements-pdf-preview.txt`: pypdfium2 5.14.0 e Pillow 12.3.0. A instalação local utilizou o runtime já disponível. A CI instala essas mesmas versões e reportlab 4.4.9 apenas para gerar fixtures de teste. Fontes técnicas: [projecto pypdfium2](https://pypi.org/project/pypdfium2/) e [API de rasterização](https://pypdfium2.readthedocs.io/en/stable/python_api.html).

O worker arranca com Python isolado `-I -B`, sem inicializar formulários ou JavaScript e sem seguir links. Recusa PDFs encriptados, incluindo os abertos com palavra-passe vazia, e PDFs com formulários interactivos. Os limites são uma primeira página, documento até 500 páginas, dimensões entre 1 e 14400 pontos, entrada e cada PNG até 1 MiB, memória de processo 384 MiB, CPU oito segundos e espera exterior quinze segundos. Windows aplica um Job Object e POSIX aplica limites de recursos. Se o host não conseguir aplicar os limites, a conversão falha.

Os limites de processo não constituem uma sandbox completa nem homologam a operação no servidor. A manutenção do descodificador e das assinaturas antimalware permanece no backlog operacional. Uma falha deixa a cópia e o evento `not_admitted` em quarentena. Não há libertação manual que dispense a análise.

Desactivar a flag impede novas conversões, mas conserva a leitura autorizada dos derivados existentes. Depois de gravar um resultado PDF com derivados, não repor leitores antigos de QueueState ou BlobDescriptor: não reconhecem esse contrato. Não apagar ou editar diários para permitir rollback.

## Aceitação

Playwright passou 86 verificações no WordPress real. Foram criados uma notícia, um regulamento e uma tarefa fictícios. Os três envios passaram pela fila, análise ClamAV e conclusão explícita do autor. O leitor A consultou a notícia aprovada, RH Teste consultou o regulamento e Alfa Teste consultou a tarefa. Os três originais descarregados conservaram o SHA-256 da fonte. As variantes responderam como PNG, com os limites esperados e cabeçalhos privados. Outra audiência, outra unidade, administração técnica e sessão ausente não obtiveram os ficheiros. IDs directos de derivados, variantes inválidas e métodos não permitidos foram recusados.

O controlo abriu por teclado, mostrou foco e cumpriu o alvo mínimo de 44 píxeis. O reflow passou a 320, 390 e 1440 píxeis. As capturas dos três módulos foram inspeccionadas. Isto não substitui aceitação num segundo PC, leitor de ecrã ou servidor IIS.

A rasterização real passou nove testes Python. A integração MariaDB passou 66 verificações de registo, auditoria, selecção e rollback. Node passou 55 testes e lint/build. PHPCS passou nos dezoito ficheiros PHP afectados. A regressão PHP completa passou 1082 testes e 5448 asserções, incluindo o teste adicional da reconciliação. Mantêm-se seis omissões Windows documentadas na suite, sem as converter em testes aprovados.

A reconciliação real detectou inicialmente seis falsos `consumer_mismatch`, porque a sua regra anterior só admitia originais PNG/JPEG. O novo teste reproduziu a falha. A inclusão explícita de PDF corrigiu-a para os três consumidores sem retirar as verificações de objecto, versão, autor ou data. O teste de autor divergente continua a falhar fechado. A revisão independente confirmou esta correcção sem novos achados.

Um PDF fictício inválido passou pela análise ClamAV e foi recusado pelo conversor. O diário conservou `received`, `quarantined`, `clean` e `not_admitted`, mantendo os bytes. As nove provas Python abrangem também encriptação, palavra-passe vazia, formulários, dimensões excessivas, limite de páginas, entrada excessiva e recusa de sobrescrita.

Foram preservadas 133 tabelas exteriores, todas as linhas anteriores das 29 tabelas de negócio, 13 contas e 218 ficheiros anteriores. Acrescentaram-se três lotes concluídos, doze binários de entrega e os respectivos eventos. O total de 93 ficheiros novos inclui cópias, logs técnicos e quarentena, sem apagar originais. Os 39 binários registados passaram a verificação de integridade. A reconciliação ficou completa, conservando apenas os candidatos a órfão e entradas desconhecidas anteriores, sem os reparar.

Evidência detalhada, capturas, PDFs fictícios, resultados SQL e configuração permanecem fora do Git. A revisão independente não encontrou falhas bloqueantes pendentes. A CI do SHA final e a confirmação de main são condições da integração. A manutenção operacional, retenção, recuperação de conclusões indeterminadas e homologação no servidor continuam no README.
