# Suporte TI no laboratório local, F2-04

Entrega de 26 de outubro de 2026. Implementação confinada ao laboratório fictício 8892. A demonstração 8888, as contas reais e a produção não foram alteradas.

## Percurso entregue

O funcionário abre um ticket com unidade, assunto, descrição e prioridade baixa, normal ou alta. Recebe uma referência LAB-TI. A prioridade serve para ordenar a leitura humana e não implica SLA. O solicitante acompanha o estado, responde e acrescenta anexos privados. Não consulta tickets de outras pessoas.

O suporte competente consulta os tickets das unidades autorizadas ou de toda a instituição quando possui esse âmbito explícito. Um técnico assume um ticket novo e passa a ser responsável pelo atendimento. Outro técnico pode consultá-lo dentro do seu âmbito mas não toma a responsabilidade nem responde em seu nome. O técnico não assume o próprio pedido. A resolução exige uma solução escrita. Os estados são Novo, Em tratamento e Resolvido.

Todas as respostas e anexos são visíveis ao solicitante e ao suporte autorizado. Não existem notas internas. Fecho adicional, reabertura, reatribuição e SLA continuam sujeitos a definição TI. O módulo não concede permissões com base no papel técnico WordPress.

## Persistência e fronteiras

Quatro tabelas próprias guardam tickets, auditoria, avisos por destinatário e versão de schema. O instalador CLI cria explicitamente quatro contas fictícias e duas páginas. A repetição verifica os recursos existentes sem repor revisões, reactivar contas ou reconstruir uma instalação parcial.

Cada mutação bloqueia a autoridade e revalida sessão, estado, competências e unidades antes do commit. A revisão esperada impede respostas e assunções concorrentes incompatíveis. O recibo da operação não permite duplicar um ticket, resposta ou aviso por reenvio. A conversa e o estado são reconstruídos pelo histórico. Falhas na auditoria ou nos avisos revertem a transacção inteira.

Os anexos passam pela fila, análise antimalware e confirmação explícita com a autoridade e a revisão actuais. O original e os derivados são privados e têm registo ligado ao ticket. A pré-visualização PDF é uma imagem estática. A reconciliação inclui os tickets e recusa fontes parcialmente instaladas. Nenhum URL de ficheiro público é criado.

Os limites locais são 500 tickets, 20 por página, oito anexos e 50 mensagens por ticket, incluindo a solução final. O limite de respostas reserva espaço para essa solução. Os anexos usam a política CMS efectiva e a admissão de laboratório até 1 MiB.

## Validação

O ensaio SQL descartável passou 56 verificações, incluindo duas ligações concorrentes, idempotência, isolamento entre pessoas e unidades, rollback de auditoria/avisos, adulteração do payload e expiração de sessão antes do commit. Conservou as 182 tabelas anteriores. A primeira execução encontrou um problema no próprio ensaio, que criava uma sessão dentro dos bloqueios. O teste passou depois de preparar as duas sessões antes da concorrência.

A regressão PHP passou 1148 testes e 5723 asserções com sete omissões Windows anteriores. O contrato da fila inclui o consumidor tickets e rejeita vistas não previstas. Os 55 testes Node, lint e construção passaram. Reconciliação SQL passou 23 verificações. A revisão independente não identificou defeitos accionáveis.

Playwright confirmou 43 verificações: abertura, conversa, assunção, resolução, fila e conclusão do PDF, entrega privada, outras contas, anónimos, HTTP fechado, REST, HEAD, teclado e reflow em 320/390/768/1440 píxeis. A suspensão auditada da nova conta fictícia bloqueou ticket, PDF, preview, fila e POST. A reactivação exigiu nova sessão e conservou o ticket. As imagens de desktop e telemóvel foram inspeccionadas. Uma falha histórica do script usava o rótulo incorrecto do botão de conclusão. Ficou conservada na evidência e passou depois de usar o rótulo observado.

A comparação final confirmou 577 asserções de preservação: 133 tabelas externas, linhas anteriores de 37 tabelas de negócio, 18 contas e 338 ficheiros intactos. Foram acrescentadas quatro contas fictícias, quatro tabelas de suporte e três binários de entrega, o original PDF e dois derivados. Não houve limpeza de originais. A CI do SHA exacto e a confirmação de main continuam obrigatórias antes de declarar a integração. Não confundir esta aceitação local com homologação institucional, teste IIS ou disponibilidade para produção.

## Activação local

Exige os gates independentes ARN_ALLOW_LOCAL_TICKETS e ARN_ALLOW_LOCAL_TICKET_ATTACHMENTS, ambiente local, autorização e organização fictícias previamente preparadas. As credenciais, cópias de segurança e evidência do browser ficam na pasta privada externa ao Git. O comando explícito é `wp arn tickets install` com o operador fictício autorizado. Não executar em produção nem sobre uma instalação parcial.

A composição com painel inicial, pesquisa integrada e avisos globais pertence à F2-05/06. O módulo já fornece páginas, navegação, pesquisa e avisos próprios. As regras de férias e as decisões de Calendar/delegações permanecem independentes desta entrega.
