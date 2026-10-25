# Anexos privados de tarefas, F1-08/13

## Âmbito entregue

Parcela de US-ARN-08 sobre main ac63081, em branch feat/anexos-tarefas-privados. O responsável pela tarefa pode acrescentar um ficheiro com UpdateOwnTask. O autor gestor pode fazê-lo com AssignUnitTask no âmbito corrente. A administração técnica não recebe competência de negócio. A leitura acompanha a atribuição e as permissões actuais, incluindo depois de a tarefa terminar.

O detalhe apresenta uma lista de anexos e um formulário com descrição e ficheiro. Aceita um PDF, PNG ou JPEG por operação, até oito anexos por tarefa e 1 MiB por original. A política de documentos do CMS pode restringir estes valores. O rótulo do CMS foi actualizado para explicitar que a política é partilhada com tarefas. A alteração não cria uma política nova nem modifica os valores configurados.

Cada inclusão cria uma revisão imutável com descritores, rótulo e evento attached. Os comentários e transições posteriores conservam os anexos. Não existe remoção nem substituição de anexos. As tarefas anteriores sem anexos conservam a forma histórica e o cálculo do seu hash.

## Segurança e persistência

O envio exige POST, sessão fictícia válida, nonce ligado ao actor/operação/tarefa, forma fechada do multipart e upload HTTP real. O MIME declarado e o nome do ficheiro no browser não determinam o armazenamento. A análise usa ImportBatch, quarentena, ClamAV e derivados privados já existentes. O processamento decorre antes de abrir a transacção de autoridade.

Depois da análise, a transacção volta a verificar conta, sessão, concessões, unidade, relação com a tarefa, revisão CAS e política CMS. Registo de original/derivados, auditoria de ficheiros, revisão da tarefa, evento e aviso partilham o commit. A política é reavaliada com o relógio actualizado antes e depois da persistência. Uma concessão que expire durante a operação provoca rollback. Na incerteza sobre o resultado, a limpeza conserva os bytes.

O download exige o identificador da tarefa e o identificador do original. A variante é um selector fechado. Um identificador de derivado não abre outra rota. O servidor compara o descritor do histórico com o registo, confirma hash/tamanho/MIME e abre o ficheiro sob os bloqueios da autorização. A permissão é verificada antes e depois da abertura. A entrega usa no-store, private, nosniff e Content-Disposition attachment, sem nomes ou caminhos privados.

A reconciliação passou a incluir tarefas e todos os seus derivados auditados. Detecta variantes sem registo e divergência entre registo e descritor, mesmo quando os bytes concordam com o registo. O diagnóstico não apaga, repara ou altera referências.

## Activação e recuperação

Só no ambiente local, além dos gates de tarefas e ficheiros privados, ARN_ALLOW_LOCAL_TASK_ATTACHMENTS tem de ser exactamente true. Novos uploads exigem também ARN_ALLOW_LOCAL_MEDIA_PROCESSING exactamente true e pipeline disponível. Desligar o scanner não autoriza uploads sem análise. A leitura de anexos existentes não depende de executar o scanner de novo.

Não há alteração do schema SQL. Há evolução do payload e histórico de tarefas. Depois de criar anexos, uma versão antiga do código não compreende estes eventos e recusa a leitura das tarefas afectadas. Um rollback não pode simplesmente instalar o leitor antigo nem apagar eventos para forçar compatibilidade. Desactivar novos envios, conservar o leitor compatível e aplicar a release correctiva ou um restauro integral previamente ensaiado.

## Verificação local

PHP: 1027 testes, 5198 asserções e cinco omissões Windows. A execução final não teve falhas ou avisos. O primeiro arranque não tinha GD activado e foi repetido com a extensão explícita, como na CI. Os dois testes focados no template e revalidação após falha passaram depois da revisão final da apresentação.

Node: 55 testes, lint e build aprovados. O runtime usa as dependências já instaladas na máquina. PHPCS: 24 ficheiros PHP alterados aprovados.

MariaDB/WordPress: 135 verificações no ensaio exclusivo de tarefas. Inclui registo de variantes, replay, rollback injectado, CAS, administrador técnico sem acesso, outra unidade, reatribuição, terminal, suspensão e expiração da concessão antes/depois da escrita e abertura. As tabelas e contas temporárias do ensaio foram removidas pelo seu próprio harness.

Playwright: 42 verificações distintas aprovadas no laboratório 8892. Criação pelo formulário, envio de PNG/PDF com scanner real, original e variantes privados, hashes dos originais, transporte inválido, nonce, limite, CAS, MIME real, recusa de outra unidade/administrador técnico/anónimo, reatribuição, quatro larguras de ecrã, rótulos e foco por teclado. Capturas desktop e mobile inspeccionadas. O tema existente foi conservado e a descrição obsoleta que negava anexos foi corrigida.

Conservação: 133 tabelas exteriores, linhas anteriores de 21 tabelas de negócio, dez utilizadores existentes e 24 ficheiros anteriores mantiveram os respectivos hashes. O laboratório recebeu três contas fictícias, duas páginas, três tabelas de unidades e cinco de tarefas porque esses recursos não existiam. Não foram reinstalados recursos existentes. A tarefa chegou à revisão 4, com dois eventos attached e uma reatribuição. Os quatro novos binários têm registo e auditoria concordantes. A reconciliação incluiu 18 binários registados sem divergências de tarefas. As 13 observações sobre possíveis órfãos e entradas desconhecidas foram conservadas, incluindo os três novos ficheiros de palavra-passe privados, sem remoções.

Credenciais e artefactos de QA ficaram fora do Git. Os 18 ficheiros de aplicação alterados correspondem à cópia ensaiada. A demonstração 8888 e a produção não receberam alterações. O estado de CI e integração deve ser consultado na PR desta branch, sem confundir aceitação local com implantação.

A revisão independente identificou duas falhas antes do fecho: variantes esperadas omitidas na reconciliação e expiração temporal das concessões depois da verificação inicial. Ambas foram corrigidas e cobertas na integração. Uma segunda revisão não encontrou problemas concretos nos deltas. O Controller foi também corrigido para reler acesso e detalhe depois de uma falha de processamento, sem devolver uma projecção antiga após revogação.

## Limites que permanecem

Esta parcela não homologa produção. Mantém processamento síncrono local e limites conservadores. Continuam por desenvolver fila/lotes, preview PDF quando aplicável, manutenção operacional das assinaturas, gestão auditada da quarentena e recuperação controlada. A implantação IIS/MySQL, classificação institucional, retenção, escala, segundo PC e aceitação por perfis reais continuam no README. Não há email externo nem alteração de contas reais.
