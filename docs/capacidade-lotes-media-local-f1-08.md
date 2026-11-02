# Capacidade de media em lotes, F1-08

## Âmbito e decisão

Laboratório fictício 8892. A fila existente admite até oito originais de 1 MiB e conserva progresso por ficheiro em ciclos de uma a três entradas. Reutilizar esse mecanismo. Não aumentar os limites de tempo, tamanho, tentativas ou permissões. Não alterar 8888, produção, contas reais ou originais anteriores.

Cada imagem ou PDF com pré-visualização era analisado em três processos clamscan. O original continua analisado individualmente antes da conversão. Os dois derivados passam a uma análise conjunta com caminhos explícitos, sem recursão, com exactamente dois ficheiros contabilizados, zero infecções, saída completa e sem erros/avisos. Qualquer resultado parcial ou alteração dos bytes retém o ficheiro inteiro. Os hashes e eventos por variante mantêm o formato. Não reutilizar veredictos de outros ficheiros ou lotes.

A adaptação opcional de MediaPipeline conserva consumidores/testes que só fornecem scanner individual. A composição local activa a análise conjunta. ClamAvScanner conserva scan e acrescenta scanMany com lista estrita de um a dois caminhos distintos. Mantém 60 segundos por processo e o prazo global de 120 segundos. Sem daemon, serviços persistentes ou paralelismo de scanners.

O autor verá quantos ficheiros estão prontos e quantos faltam, sem expor caminhos, resultados antimalware internos ou ficheiros de outros autores. A conclusão só fica disponível quando todos estiverem prontos.

## Aceitação

Testes agrupados: respostas incompletas, duplicadas, infecção, erros, lista inválida, argumentos seguros, prazo esgotado e mutação de derivado. Provar original antes de conversão e dois derivados numa só chamada. Testar lote máximo em ciclos 3/3/2, nenhum ficheiro pronto repetido e interrupção antes da conclusão. Exercitar um envio real de oito ficheiros fictícios com scanner, GD/PDF e Playwright, conclusão única auditada e verificações de acesso. Medir processos e tempos, sem prometer capacidade geral a partir de uma amostra. Comparar uma amostra igual com modo anterior quando viável. Preservação dos registos e ficheiros anteriores.

Fonte técnica: https://docs.clamav.net/manual/Usage/Scanning.html, consultada em 2 de novembro de 2026. Clamscan carrega assinaturas por execução e aceita caminhos explícitos. A prova com o binário local é necessária.

## Verificações de código

Em 2 de novembro de 2026: PHPUnit completo com 1214 testes e 5967 asserções, sem falhas e sete omissões Windows. Suite focal com 61 testes e 310 asserções, uma omissão. PHPCS dos sete ficheiros PHP alterados passou. Os 61 testes Node, lint e build passaram. A integração SQL de media passou 98 verificações, incluindo a análise conjunta injectada e o registo/rollback transaccional dos derivados. As respostas injectadas não constituem prova antimalware.

A revisão independente única não identificou defeitos concretos. Não foi ensaiado um atacante local privilegiado que substitua ficheiros transitoriamente durante a leitura. Mantém-se a exigência de armazenamento privado e ACL restritas, além da verificação dos bytes antes e depois.

## Recusa preservada

Uma primeira imagem sintética de ruído com 640 × 480 produziu uma pré-visualização PNG de 1 076 545 bytes. A análise foi recusada pelo limite existente de 1 MiB. O resultado e os originais ficaram conservados. O lote fictício associado foi cancelado por decisão técnica auditada, antes de qualquer criação editorial. O ensaio positivo usa novas imagens de 512 × 384 e cinco PDFs de uma página, sem alterar os ficheiros anteriores.

## Medição com ClamAV real

O mesmo PNG válido produziu hashes idênticos do original, miniatura e pré-visualização nos dois modos. Foram usadas as mesmas assinaturas oficiais locais e os mesmos limites. A comparação isolada inverteu a ordem, executando primeiro o modo conjunto, sem outras acções de browser ou testes lançados pelo agente durante a medição.

| Amostra | Individual, três execuções | Conjunto, duas execuções |
| --- | --- | --- |
| Com preparação do formulário em paralelo | 34,201 s | 43,868 s |
| Sem outro ensaio lançado em paralelo, ordem invertida | 39,982 s | 26,721 s |

A segunda amostra reduziu o tempo em cerca de 33%. A primeira demonstra variabilidade sob trabalho concorrente. São duas observações neste PC, não uma estimativa estatística, SLA ou homologação de carga. A redução estrutural demonstrada é uma inicialização a menos do scanner por original com dois derivados. Nenhum resultado clean foi reutilizado entre ficheiros.

## Lote real de oito ficheiros

O formulário normal recebeu três PNGs sintéticos e cinco PDFs sintéticos de uma página. O worker local real usou ClamAV, GD e o rasterizador PDF. Três invocações separadas processaram 3, 3 e 2 entradas em 56,179 s, 130,092 s e 57,670 s. O total de trabalho medido foi 243,941 s. Os limites continuam por ficheiro/processo. Um ciclo de três entradas pode demorar mais de 120 segundos no total.

Cada ficheiro ficou pronto na primeira tentativa. O progresso passou por 3/8, 6/8 e 8/8. As entradas prontas permaneceram iguais entre ciclos. As verificações Playwright confirmaram oito entradas visíveis e ausência de conclusão antes de todas estarem prontas. A conclusão normal pelo autor criou um rascunho de revisão 1, oito assets, 24 registos privados e uma única auditoria editorial. Não houve submissão ou publicação. As capturas desktop e móvel foram revistas.

A operação continua limitada ao laboratório. A conservação/limpeza da quarentena, manutenção de assinaturas e descodificadores, capacidade do servidor, processamento síncrono de vários anexos e operação persistente não ficam homologados por este ensaio. O leitor e o formato do diário mantêm compatibilidade, sem migração de dados.

## Preservação e repetição

A comparação final confirmou 133 tabelas exteriores, 26 contas, 454 linhas anteriores de negócio e 473 ficheiros anteriores intactos. Os ficheiros novos pertencem aos ensaios fictícios desta entrega. O lote cancelado e a quarentena de recusa foram conservados.

Repetir o POST original de conclusão devolveu o mesmo rascunho. A comparação integral do resultado e do digest das tabelas de negócio antes e depois da repetição foi idêntica. Permaneceram uma versão, oito assets, 24 registos de ficheiros e uma auditoria editorial.

O fecho Playwright passou dez verificações de repetição, referências privadas, leitura de três amostras com no-store, largura móvel e recusas para outro autor/anónimo. O formulário, os estados 0/8, 3/8, 6/8 e 8/8, a conclusão e as capturas foram ensaiados separadamente. Foram observadas 13 ligações privadas na pré-visualização. Os relatórios detalhados permanecem fora do Git.
