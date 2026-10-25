# Anexos privados de tarefas: plano de implementação

Objectivo: concluir a parcela de anexos F1-08/13 no laboratório fictício, conforme US-ARN-08. Execução directa autorizada no chat, testes agrupados no fecho e revisão independente final.

Base: main ac63081. Demonstração 8888, contas reais, originais e produção preservados.

## Decisões

Reutilizar ImportBatch, quarentena, ClamAV, derivados e registo privado. Não usar attachments nativos do WordPress nem criar outro armazém. Um anexo por mutação, oito por tarefa. PDF/PNG/JPEG até 1 MiB e política documental do CMS aplicada sob bloqueio. A interface CMS explicita a partilha da política. Uma política própria exigiria migração e decisão posterior.

Os anexos são eventos imutáveis no histórico existente. A revisão de inclusão identifica a versão do ficheiro. Descritores e rótulo ficam no evento auditado, sem alterar a forma dos registos históricos anteriores. Não apagar nem substituir. Tarefas terminais conservam leitura mas recusam novos anexos.

Leitura segue TaskPolicy e a atribuição corrente. Escrita exige UpdateOwnTask do responsável ou AssignUnitTask do autor gestor. Sem privilégios técnicos implícitos. Permissões, sessão, revisão e política são verificadas novamente na transacção depois da análise. O download valida metadados e abre o ficheiro sob a autoridade bloqueada. Originais e derivados partilham o mesmo acesso.

## Entregas

1. Attachment e evento attached em Task, replay estrito, política e projecção. Ensaiar adulteração, duplicação, limite, CAS, terminal e histórico antigo.
2. TaskRepository regista ficheiro, evento, revisão e aviso na mesma transacção. Portal prepara ImportBatch fora da transacção. Download valida objecto/revisão/variante e hash antes de entregar. Reconciliação reconhece task_attachment sem reparação.
3. Transporte fechado com POST/nonce/upload real e GET privado. Formulário e lista no detalhe, mantendo tema e acessibilidade. Nenhum nome/caminho original vindo do browser é usado no armazenamento ou Content-Disposition.
4. Integração SQL: sucesso, recusa, rollback, concessão revogada, reatribuição e conservação. Playwright: upload fictício, leitura e recusas por perfil, ficheiro inválido, mobile. PHP/Node/lint/build e revisão no fecho. Evidências privadas.
5. Actualizar README/aceitação, commit/push/PR. Merge apenas com CI verde no SHA exacto, depois confirmar main.

## Riscos a verificar

Não manter locks SQL durante ClamAV. Não abrir bytes antes da autorização. Não ligar ID de derivado a rota independente. Não aceitar descritores do browser. Não limpar binários quando o resultado SQL for incerto. Não alargar silenciosamente limites CMS. Conservar os controlos históricos e não alegar homologação de produção.

## Estado

Implementação e aceitação local concluídas. PHP 1027/5198/cinco omissões Windows, Node 55, SQL 135, Playwright 42, PHPCS 24 e lint/build. Revisão independente final sem achados. Ver docs/anexos-tarefas-privados-local-f1-08-13.md. Integração sujeita à CI do SHA final. A fila operacional, lotes, preview PDF e retenção continuam no backlog separado.
