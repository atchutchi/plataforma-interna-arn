# Processamento privado de media no laboratório

## Entrega

Notícias e documentos podem activar uma admissão privada com quarentena, ClamAV e derivados PNG ligados à versão. A configuração é exclusiva do laboratório. Não altera contas, competências, originais anteriores ou a demonstração na porta 8888. Os anexos de tarefas continuam no backlog.

Cada tentativa cria uma pasta aleatória fora das raízes públicas e do armazém de entrega. Conserva a cópia recebida e um diário obrigatório com actor, instante, tamanho, SHA-256 e resultado. A quarentena não tem endpoint, libertação manual ou limpeza automática. O evento `prepared_for_registration` identifica cópias preparadas. A confirmação da transacção pertence à auditoria SQL, não a esse evento.

O executável ClamAV analisa cada cópia antes de descodificar imagens, com argumentos fechados e sem shell. Assinaturas oficiais com mais de três dias, motor indisponível, erro, timeout, ficheiro cifrado ou limite ultrapassado impedem a admissão. O resultado limpo exige código zero, um ficheiro analisado, zero detecções e ausência de erros ou avisos. Os hashes são verificados antes e depois das análises e novamente no fim. Não existe garantia absoluta de ausência de malware.

PNG e JPEG aprovados geram thumbnail até 320 px e preview até 1280 px, sem ampliação, num processo PHP/GD isolado. Ambos passam por nova análise. O original conserva os seus bytes. PDF permanece para download, sem renderização activa. A transacção regista original e variantes com a mesma versão, actor e auditoria. Uma falha parcial reverte o registo e só permite a limpeza das cópias novas cuja propriedade e ausência de registo estejam demonstradas.

Cada pedido de leitura revalida a autorização do original. IDs de derivados não criam uma rota independente. Selectores inválidos, variantes de PDF, métodos diferentes de GET e utilizadores sem audiência recebem 404 vazio. A ausência de derivados em imagens anteriores conserva a entrega autorizada do original. A reconciliação reconhece a relação entre variantes e consumidores.

## Limites e configuração

O opt-in `ARN_ALLOW_LOCAL_MEDIA_PROCESSING` exige booleano true e ambiente local. Sem opt-in mantém-se o comportamento anterior, que não pode ser apresentado como protegido por esta admissão. Uma configuração activa incompleta recusa uploads.

As constantes privadas são `ARN_MEDIA_QUARANTINE_DIR`, `ARN_MEDIA_CLAMSCAN`, `ARN_MEDIA_SIGNATURES_DIR`, `ARN_MEDIA_PHP_CLI` e `ARN_MEDIA_GD_EXTENSION_DIR`, além do armazém privado existente. O caminho GD é explícito e resolvido antes de arrancar o worker. Não se herda o php.ini Web no processo de imagens.

Mantêm-se 1 MiB por original, oito ficheiros e 8 MiB por tentativa, incluindo cópias conservadas numa nova versão. O CMS revalida os seus próprios limites na transacção. As imagens ficam limitadas a 4096 px por dimensão e 16 milhões de píxeis. O worker tem 192 MiB e cinco segundos. O scanner tem até 60 segundos por processo, limitado pelo restante de um orçamento de 120 segundos por lote. O opt-in local exige conseguir definir 150 segundos no pedido PHP. Não constitui um limite integral de SQL ou homologação de capacidade.

Um bloqueio exclusivo não bloqueante serializa a admissão na quarentena. A pasta admite até 100 tentativas conservadas e exige 64 MiB livres. A falta de capacidade recusa novos envios. O operador deve definir conservação e recuperação antes do uso institucional. Não se apagam tentativas para fazer os testes passar.

O processo isolado carrega as assinaturas em cada análise. No portátil, uma imagem com dois derivados demora cerca de 90 segundos. Lotes maiores podem esgotar o orçamento e são recusados integralmente. Esta implementação não é uma fila assíncrona de produção.

## Verificação local

A aceitação usa WordPress e MariaDB na porta 8892, perfis fictícios e evidência privada fora do Git. Uma imagem sintética de 1600 × 900 percorre autoria, análise, geração de variantes, submissão e decisão editorial. O download verifica os bytes do original, dimensões, cache privada, audiência e recusas. Um PDF sintético percorre o catálogo documental.

O pacote portátil ClamAV 1.4.6 teve a assinatura GPG verificada com a chave publicada pelo Cisco Talos. As bases oficiais foram obtidas pelo FreshClam. A execução de 24/10/2026 usou a versão de assinaturas 28163. O executável não apresenta assinatura Authenticode. A evidência GPG e os binários ficam fora do Git.

O controlo EICAR foi recusado pelo acesso local ao ficheiro e o adaptador devolveu resultado inconclusivo. Não é prova de detecção pelo ClamAV. Os testes injectados cobrem explicitamente o código de detecção, erros, resultados incompletos e indisponibilidade. Não foram desactivadas protecções do computador.

As falhas descobertas durante a aceitação foram conservadas: o prazo PHP original de 30 segundos interrompia a primeira análise e a imagem mínima antiga foi recusada pelo worker GD. O percurso final usa uma imagem sintética válida, a pasta GD explícita e o orçamento local descrito acima. A tentativa de usar um daemon foi retirada porque a configuração no disco não provava a configuração efectiva do processo residente.

## Pendências

Aceitação local concluída em 24/10/2026: PHP 1016 testes e 5163 asserções, cinco omissões Windows, Node 55, lint/build, PHPCS completo com os dois apontamentos corrigidos e nova verificação dos dez ficheiros afectados. Integração SQL: 24 verificações da admissão e 23 da reconciliação. Playwright: 34 verificações finais aprovadas, incluindo dimensões, conservação, publicação, PDF, cache, métodos/selectores inválidos, IDs directos de derivados, audiências e vista móvel. A notícia e o PDF passaram pelo ClamAV real.

A execução conjunta de PHPUnit e PHPCS apresentou um tempo de 7,03 segundos num teste que exige interrupção antes de cinco segundos e um aviso de limpeza OIDC. Os 45 testes envolvidos passaram isoladamente e a suite completa seguinte passou sem avisos. Não se alterou o limiar nem se atribuiu uma causa não demonstrada. O primeiro ensaio da página encontrou uma lacuna da fixture: o laboratório CMS não tinha a página Notícias. Criou-se uma página nova com o bloco nativo, sem substituir páginas anteriores. O percurso final usa o permalink devolvido pelo WordPress. Mantêm-se os registos privados das tentativas anteriores.

Preservação confirmada por hashes: 133 tabelas exteriores, todos os registos anteriores das 21 tabelas de negócio, dez linhas de utilizadores e vinte ficheiros anteriores. Foram acrescentados uma notícia, um documento, quatro binários e os seus registos/auditorias/notificações. As sessões nativas de teste e a nova página são alterações esperadas. A reconciliação verificou 14 binários e conservou somente os dois possíveis órfãos e oito entradas desconhecidas já existentes. Vinte ficheiros de código alterado no laboratório coincidem com a branch. Nenhuma credencial de laboratório consta dos ficheiros alterados.

Revisão independente concluída sem achados pendentes. A integração continua sujeita a todos os controlos verdes no SHA exacto da PR e à confirmação de main. Os resultados locais não substituem essa condição.

Permanecem pendentes anexos de tarefas, renderização PDF, orientação EXIF quando aplicável, processamento assíncrono e capacidade de lotes de produção, manutenção de assinaturas, ACL NTFS, retenção/recuperação e homologação IIS. Esta entrega não activa produção nem conclui toda a F1-08.

Referências oficiais: [instalação e assinatura](https://docs.clamav.net/manual/Installing.html), [análise ClamAV](https://docs.clamav.net/manual/Usage/Scanning.html) e [opções clamscan](https://github.com/Cisco-Talos/clamav/blob/main/docs/man/clamscan.1.in).
