# Fila nos formulários de notícias, documentos e tarefas

Entrega local sobre main 693591f, na branch feat/media-fila-consumidores. Só laboratório fictício 8892. O estado da integração e o SHA final ficam na PR.

## Comportamento

Os três formulários mantêm a sua validação de transporte, nonce, campos, autoria e competência. Com ARN_ALLOW_LOCAL_MEDIA_CONSUMERS=true, media nova ou conservada é copiada para a fila privada antes de qualquer registo no módulo. O contexto imutável inclui operação, objecto, revisão esperada, motivo e conteúdo tipado. Não guarda sessão, nonce, caminho temporário ou identidade indicada pelo browser. Notícias sem media continuam a guardar texto directamente.

O worker CLI existente analisa os originais e os derivados. Não usa a sessão do autor, não cria conteúdo e não publica. A página Os meus envios apresenta apenas envios próprios com acesso corrente ao módulo/objecto. O autor confirma Concluir envio numa sessão válida. A conclusão revalida as permissões, a revisão esperada, os bytes analisados e o prazo local de 24 horas. O repositório nativo volta a validar a autoridade, a política CMS e a revisão dentro da transacção. Notícias e documentos ficam sujeitos ao circuito editorial existente. Anexos só ficam disponíveis quando o registo da tarefa termina.

Um identificador determinístico do pedido combina o formulário nativo, autor, contexto e hashes dos originais com HMAC. Reenviar o mesmo formulário devolve o lote existente. A mesma intenção não volta a invocar o negócio após o marcador durável applying. O recibo applied permite confirmar uma repetição sem criar outro registo. A marca anterior à operação e o recibo posterior não constituem uma transacção distribuída entre ficheiros e SQL. Uma resposta perdida fica indeterminada e exige diagnóstico manual pela auditoria. Não existe repetição automática nem reparação por inferência.

A conta técnica pode gerir a análise e a quarentena, mas não concluir envios alheios. A finalização mantém o lock do worker durante a operação curta de registo. Depois de applying, retenção, repetição, recuperação e cancelamento ficam bloqueados. Os originais e tentativas anteriores são preservados. ImportBatch mantém a limpeza apenas das cópias novas cuja ausência de registo esteja provada. Cópias com resultado SQL incerto ficam conservadas.

## Activação e operação

Exige as flags locais de fila e processamento, os directórios privados existentes e a nova flag de consumidores. A activação exige ambiente local, contas marcadas como fictícias, email example.invalid ou example.test, sessão nativa válida e estado activo. Cada módulo conserva o seu elenco e competências. Não activar esta flag em produção.

O worker continua explícito: wp arn media work --limit=1, 2 ou 3, executado pela conta técnica fictícia configurada. Não foi instalado um agendador. A página de acompanhamento não executa análises por GET. Capacidade: 50 lotes conservados, até oito originais e 1 MiB por original. Expiração exige retenção e nova análise pelo operador. Um conflito de revisão exige reabrir o formulário com a revisão actual. A operação anterior pode ser cancelada pelo operador antes de iniciar a conclusão, com motivo auditado e conservação dos bytes.

Depois de aceitar um contexto submitted, o código anterior não lê esse diário. Um rollback exige preservar a fila e suspender a sua utilização. Não remover os eventos novos nem regressar ao leitor antigo sobre lotes já criados.

## Verificação

Testes agrupados no fecho. A suite PHP passou 1062 testes e 5351 asserções, com seis omissões Windows anteriores. Node passou 55 testes. Lint e build passaram. O ensaio SQL passou 42 verificações, incluindo análise sem escrita de negócio, registo de original/derivados com actor do autor, recibo idempotente, rollback e resposta perdida após commit. A reconciliação SQL passou 23 verificações. Playwright passou 47 verificações distintas: os três formulários, criação e revisão com originais conservados, análise ClamAV real, conclusão pelo autor, repetição sem duplicados, conflito de revisão da tarefa, leitura alheia recusada, conta técnica, ausência de sessão, transporte inválido, teclado, foco e reflow a 320, 390, 768 e 1440 píxeis. Os screenshots desktop e mobile foram inspeccionados. PHPCS passou os 18 ficheiros PHP alterados.

A comparação final passou 390 asserções. Preservadas 133 tabelas exteriores, 13 contas e 98 ficheiros anteriores. As 29 tabelas de negócio não mudaram durante a recepção/análise. No fecho, conservaram-se todas as linhas anteriores excepto a linha corrente da tarefa fictícia de aceitação, que passou da revisão 4 para 6 com um comentário e um anexo auditados. Criados uma notícia e um documento, ambos em rascunho com duas versões, nove binários de entrega e seis lotes. Cinco lotes têm recibo de conclusão. O sexto conserva a análise, mas a revisão desactualizada bloqueia a conclusão sem escrever no módulo. Os 49 ficheiros novos incluem processamento e entrega, sem apagar originais. A reconciliação permanece completa e sem novas divergências de integridade. Não foram criadas contas, concedidas funções ou alterados dados da demonstração 8888 ou da produção.

Os auxiliares Playwright tiveram três problemas corrigidos: concorrência na mesma sessão durante mudança de login, propriedade form.action ocultada por um input com esse nome e nome incorrecto do campo de comentário. Os cenários afectados passaram na repetição sequencial. Um erro final no contador do relatório foi corrigido e consolidado a partir das verificações guardadas. Estes incidentes não foram apresentados como falhas da aplicação. Os logs originais foram conservados.

Evidência privada em media-consumers-20261025: baselines, logs PHP/Node/SQL, estados, auditoria, hashes, verificações Playwright e screenshots. Nenhuma credencial, sessão, binário ou conteúdo institucional entra no Git. A CI e o merge são registados na PR, com confirmação posterior de main.

A revisão independente final não encontrou achados bloqueantes. Os limites de Playwright, preservação, ClamAV e CI indicados pelo revisor são comprovados pela execução principal. Não foi realizado um corte físico de energia, ensaio de IIS/MySQL institucional ou deploy. Esses resultados não são inferidos da revisão estática.

## Pendências operacionais

Agendamento supervisionado do worker, capacidade e conservação institucionais, manutenção de assinaturas, preview PDF e resolução auditada de conclusões indeterminadas. Não confundir esta aceitação local com a homologação institucional da F1-08 ou a prontidão de produção.
