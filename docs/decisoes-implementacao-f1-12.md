# F1-12: decisões de implementação e revisão

16 de outubro de 2026. Registo das decisões das cinco tarefas, revisão e aceitação, pela ordem em que ocorreram. Conserva hipóteses substituídas e confirmações repetidas. Custos indicados caso a decisão esteja errada. A prova efectiva consta da QA F1-12. Nenhum ponto representa aprovação institucional.

D01. Fechar as interfaces auxiliares PlainText::value, serialização Audience e prova de audiência de ActorContext::allows. Custo: ajustar adaptadores internos. Ainda não existia persistência.

D02. Substituir a junction vendor por cópia privada independente. O Composer carregava enums do main. Sem mudar versões/lockfiles. Custo: espaço local.

D03. Confirmar a mesma decisão após o teste detectar novos casos Role ausentes. Entrada repetida conservada como confirmação. Custo: o mesmo espaço local, sem mudança de código ou lockfile.

D04. Rascunho aceita motivo privado opcional do servidor e selecção recebe modo de gestão interno. Conserva o motivo efectivo e evita pesquisar rascunhos privados na lista publicada. Custo: ajustar adaptadores antes de activar SQL/HTTP.

D05. Aplicar o mesmo contrato aos eventos. Cancelamento referencia a última publicação mesmo com rascunho posterior. Custo: rever assinaturas e interpretação da auditoria antes da persistência.

D06. Antecipar Configuration da tarefa 4 para a 3 para fechar antes do SQL. CurrentActor recebe elenco de servidor e resolver opcional de ensaios exclusivos. canReceive avalia destinatários sem inventar sessões. Rotas exigem sessão nativa corrente. Custo: ajustar composição antes da activação.

D07. Membro fictício precisa de uma pertença activa, também para audiência geral. Fixtures institucionais não recebem unidade pessoal inventada. Custo: confirmar presets antes de uso real.

D08. Falha de worker conserva apenas diagnóstico técnico mínimo privado, remove payload e termina processos/transacções próprios. Sucesso limpa o directório. Custo: limpar diagnósticos residuais explicitamente.

D09. Experimentar a barreira de concorrência na primeira conta bloqueada, mantendo duas esperas reais e um commit/um conflito. Hipótese substituída por D10. Custo: diagnóstico inadequado do observador, sem alterar locks de produção.

D10. Restaurar a barreira original do agregado e usar polling de 200 ms. O polling de 50 ms reiniciava o intervalo da cache de metadados MariaDB 11.8. Mantiveram-se duas esperas reais, CAS e todos os resultados. Sem mudar configuração SQL, locks ou retries. Custo: o teste continuaria a falhar visivelmente, sem sucesso enfraquecido.

D11. Histórico institucional exclusivo do autor. Revisor recebe submissão corrente e token opaco, sem cronologia privada ou CAS do autor. Gestor conserva histórico da equipa. Custo: uma futura necessidade de histórico de decisão exige projecção redigida própria.

D12. Exigir snapshot organizacional preparado de três unidades, também para fixtures institucionais. Snapshot vazio da fundação não prova dependência pronta. Custo: acesso fechado até preparar dependências.

D13. Bootstrap exclusivo WP-CLI impede apenas descoberta de cron pronto nos processos de ensaio/workers. Não muda agendas/opções/configuração ou comparações. Falhas por cache nativa ficam registadas. Custo: preservação estrita falha visivelmente, sem substituir baselines. Não se garante ausência de efeitos em todo o bootstrap nativo.

D14. find aceita modos internos fechados read/manage/either. Portal/CMS pedem políticas distintas sob transacção. markRead usa relógio efectivo sob lock no HTTP e injecção explícita no teste. Browser não fornece relógio. Custo: ajustar adaptadores, sem alargar permissões.

D15. Páginas marcadoras são infraestrutura. Pages::install conserva fresh_site com filtro temporário apenas em CLI/admin/com ambos os gates, removido em finally. A falha real da CI em site novo originou a regressão. Custo: indicação de primeira publicação permanece até conteúdo efectivo. Sem mudar conteúdo ou autoridade.

D16. Reclassificar de Minor para Important o regresso aos avisos e a validade na lista. A navegação mudava de colecção e faltavam metadados exigidos. Corrigidos na mesma passagem final. Custo: classificação superior ao impacto, sem funcionalidade fora da especificação.

D17. Presets, audiências e conservação fictícios. Revisor não decide PEN-07. Custo: activação real exige decisões institucionais.

D18. IIS/MySQL 8.4, servidores, AD, DNS e produção fora desta homologação. Custo: compatibilidade nesses ambientes sem prova.

D19. Revisor não executou aceitação nativa. Controlador assumiu os 32 critérios e WordPress/REST/Playwright após correcções. Custo: revisão pura não permitiria aceitar a interface.

D20. Revisor não reproduziu preservação SQL. Conservar ensaios do executante e baselines imutáveis e comparar novamente. Custo: revisão isolada não serve de prova SQL.

D21. Locks e pesquisas sobre o elenco completo aceites só para seis fixtures. Custo: expansão exige medir e rever desenho antes de uso real.

D22. Conservar suites e pontos de integração anteriores. Sem nova auditoria integral do legado. Custo: regressão anterior exige investigação dirigida.

D23. Anexos, recorrência, ICS, lembretes, convites e Google Calendar excluídos. Custo: exigem especificação separada.

D24. Albertina #28/#29 e identidades reais fora da F1-12. Inventário privado não concede acessos. Custo: pré-cadastro exige trabalho controlado próprio.

D25. Conclusão usa opção própria sem autoload, por hash do prefixo, apenas com versão/prefixo/IDs das seis fixtures e duas páginas. Sem grant, migração ou reparação automática. Após conclusão cada módulo verifica só a sua página/schema e dependências comuns. Custo: recuperação manual e regressões de independência necessárias.

D26. publishedList/publishedDetail projectam no repositório com o mesmo relógio transaccional da política/selecção. visible/find/manageable conservados. Custo: outro consumidor precisa de método explícito, sem reconstruir publicação com relógio diferente.

D27. Aceitação mediu ligações com 25 a 27 px. Correcção limitada a ligações dos módulos com mínimo de 44 px, validada em 16 vistas front e 12 CMS. Sem segundo ciclo de revisão do conjunto. Custo: efeito no espaçamento, coberto nas quatro larguras.

D28. Deltas nativos de opções não são excluídos ou apagados. Reconciliar cache, contador de seis contas e avanço horário do mesmo hook cron com hashes dos registos técnicos. Leituras finais/repetições continuam a exigir identidade integral. Custo: delta inexplicado impede afirmar preservação total e exige diagnóstico.

D29. Aceitar apenas o âmbito local fictício medido nos 32 critérios. Texto CSS200% não prova zoom nativo ou segundo PC. PEN-07, IIS/MySQL e população real ficam separados. Custo: nenhum resultado de produção ou de contas reais pode ser inferido desta aceitação.

Não há Minor adiado. As duas omissões iniciais foram tratadas como Important. Zoom nativo, segundo PC, escala real e homologação institucional são limites separados e não testes executados.
