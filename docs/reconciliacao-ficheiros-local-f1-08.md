# Reconciliação de ficheiros no laboratório

## Entrega e limite

Diagnóstico apenas de leitura de binários privados, registo, auditoria e referências de notícias/documentos. Não apaga, move, substitui, importa, reassocia ou publica ficheiros. Não corrige auditoria nem concede permissões. Não varre uploads públicos, anexos reais de EditorialPreview, backups ou outros volumes.

`FileReconciliation` compara dois inventários físicos e dois snapshots SQL. Cada snapshot usa uma ligação dedicada, REPEATABLE READ e uma transacção terminada por ROLLBACK. Não selecciona títulos, corpos, descrições ou metadados editoriais. Valida os schemas antes da leitura. Uma instalação parcial, falha de SQL ou limite de linhas gera análise inconclusiva, nunca uma origem vazia interpretada como íntegra.

O relatório distingue binário ausente, hash/tamanho/MIME divergente, binário não verificável, auditoria ausente/duplicada/divergente, auditoria sem registo, referência de negócio ausente/divergente, objecto ou versão pai ausente, referência sem registo e possível órfão. Inclui todas as versões registadas, não apenas as publicadas. Os tuples do catálogo fictício antigo são validados contra os três objectos e variantes fechadas. Tipos desconhecidos são cobertura incompleta.

Um órfão é sempre um candidato à investigação. A cópia física e o commit SQL não são atómicos entre si. Mesmo dois inventários iguais não provam que não existe um escritor activo, nem impedem alterações posteriores. A execução não bloqueia escritores ou autoriza limpeza. Para confirmar observações, repetir numa janela sem escrita e comparar evidência protegida.

## Armazenamento e limites

O inventário é plano e limitado a 1000 entradas. Não percorre subdirectórios nem segue ligações detectáveis. Nomes inesperados só produzem uma observação genérica, sem reproduzir o nome ou caminho. Entradas não regulares ou com múltiplas ligações não são classificadas como órfãos normais.

Cada verificação de bytes usa um handle de leitura, lock partilhado não bloqueante, assinatura, MIME, tamanho e SHA-256. Compara a identidade e estatísticas do ficheiro antes/depois. O orçamento desconta bytes efectivamente lidos, incluindo falhas. O leitor normal continua a verificar os bytes antes da entrega e a exigir autorização própria.

Limites de laboratório: 1000 linhas por fonte SQL, 1 MiB por binário na composição actual, 64 MiB lidos por execução e uma janela de 10 segundos para iniciar verificações de binários. O tempo SQL e de I/O do sistema não constitui um timeout garantido. O relatório apresenta no máximo 200 observações e conserva a contagem total. Limite, indisponibilidade ou alteração detectada tornam a análise inconclusiva. Não é antimalware, nem prova de integridade de todos os ficheiros do servidor.

## Interface e autorização

Ferramentas > Reconciliação de ficheiros, `tools.php?page=arn-file-reconciliation`. A activação exige ambiente efectivo `local`, `ARN_ALLOW_LOCAL_FILE_RECONCILIATION` estritamente `true` e `ARN_LOCAL_FILE_RECONCILIATION_USER_ID` inteiro positivo configurado no servidor. A conta correspondente tem de possuir `manage_options`, email de fixture `example.invalid` ou `example.test` e sessão WordPress nativa válida. Estes parâmetros não se recebem do browser. Não altera contas existentes para lhes dar acesso.

GET mostra apenas o formulário, sem executar inventários. POST aceita apenas a acção fechada `diagnose` e nonce da sessão. Não aceita caminhos, prefixos, nomes de tabelas, comandos de reparação ou alvos. Os outros métodos, sessões recusadas e pedidos malformados devolvem 404 vazio com `private, no-store`, antes de qualquer redireccionamento para login. Não existe endpoint REST/AJAX de relatório, cron ou exportação pública. A conta é novamente validada antes de apresentar o resultado.

A interface apresenta estados técnicos, identificadores opacos, contagens, cobertura e instante. Não mostra conteúdo, nome original, caminho, hash, título, audiência ou identidade dos autores. Administração técnica não se torna leitura editorial.

## Auditoria e recuperação

A análise verifica exactamente um evento `file_registered` por registo, com actor e instante coincidentes. Não fabrica eventos em falta. O relatório recebe ID e instantes, mas não é gravado automaticamente em tabelas ou ficheiros. O operador conserva a evidência num destino protegido quando abre um incidente. Actualizar a página com GET não recupera um relatório anterior.

Procedimento de recuperação definido, sem implementação de escrita nesta entrega:

1. Conservar os originais e suspender novas escritas no âmbito afectado através do procedimento operacional aprovado. Guardar o relatório, commit, prefixo e inventário numa pasta protegida.
2. Repetir a análise, confirmar os IDs, a origem e a versão numa cópia de investigação e comparar com backup verificado. Não concluir abandono apenas pela idade ou falta de referência observada.
3. Submeter a intervenção ao responsável competente, com causa, alvo exacto, hashes esperados, evidência anterior e plano de reversão. Nunca editar o histórico para fazê-lo coincidir com um ficheiro desconhecido.
4. Uma futura operação de recuperação deve revalidar o estado corrente, preservar versões anteriores e registar actor, motivo, referência do incidente e resultado atomicamente com as alterações de metadados. Os mecanismos de recuperação e aprovação institucional continuam separados deste diagnóstico.
5. Repetir a verificação e conservar o resultado posterior. Uma falha mantém os bytes preservados e o incidente aberto.

## Critérios de aceitação

Diagnóstico limpo de notícias/documentos fictícios e histórico. Detecção de ficheiros em falta, corrupção, órfãos possíveis, ligações/entradas inesperadas, divergência de auditoria e referências. Falha fechada de fontes parciais, SQL, limites e alterações concorrentes. Recusas HTTP por sessão, método, nonce e parâmetros. Repetição sem escrita e comparação antes/depois de dados e ficheiros.

Aceitação local concluída: 998 testes PHP, 5104 asserções e cinco omissões de symlink no Windows, quatro anteriores e uma nova. Os 17 testes específicos passaram 76 asserções. Passaram 55 testes Node, lint/build, PHPCS em 509 ficheiros, 23 verificações SQL com WordPress/MariaDB e 33 verificações Playwright. As omissões Windows não substituem a execução Linux da CI. Revisão independente sem achados pendentes depois da validação dos tuples antigos e da correcção do orçamento de bytes.

Playwright cobriu GET sem análise, POST nativo com nonce, repetição, recusa de métodos/acções/caminhos, quatro perfis fictícios recusados e visitante antes de login. O relatório foi inspeccionado em 1440 px e 390 px. Não houve zoom nativo, leitor de ecrã ou segundo PC nesta parcela. Recursos externos de avatar continuam bloqueados no laboratório.

A análise do laboratório verificou dez binários registados. Identificou dois possíveis órfãos anteriores e oito entradas desconhecidas. A comparação independente confirmou que estas oito entradas são ficheiros de credenciais fictícias existentes no mesmo directório privado. O diagnóstico não lê o seu conteúdo nem revela os nomes. Todos ficaram preservados. Não se alterou o teste nem o inventário para exigir um relatório artificialmente sem observações.

A comparação final manteve exactamente os hashes das 133 tabelas exteriores ao prefixo, das 21 tabelas de negócio, das dez linhas de utilizadores e dos vinte ficheiros anteriores. Não se alega igualdade dos metadados de sessões nativas usados nos logins. O ensaio SQL cria apenas tabelas/ficheiros temporários próprios e remove apenas essas fixtures no fecho, respeitando a ordem das chaves estrangeiras. O diagnóstico do produto não possui essa limpeza.

Código aplicado apenas à cópia fictícia em 8892, com opt-in privado exclusivo para a conta técnica já existente. A demonstração 8888, as contas reais e a produção não foram alteradas. Artefactos de QA e configuração ficam fora do Git. A integração exige CI verde no SHA exacto e confirmação posterior em main, rastreáveis na PR.

Continuam pendentes: restauro executável auditado, retenção/aprovação institucional, processamento privado, antimalware/quarentena, grandes volumes e homologação NTFS/reparse points/IIS/MySQL. A estrutura física existente não oferece uma transacção distribuída e não deve ser apresentada como tal.
