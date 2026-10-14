# F1-04: notificações internas do laboratório

## Âmbito escolhido

O README15.3 coloca auditoria e eventos de notificação após F1-03. A fundação local já confirma alterações de conta e concessões numa auditoria transaccional. Esta parcela usa esses eventos confirmados como fonte durável das notificações internas, sem duplicar eventos nem instalar um emissor de email. Não conclui a F1-04 institucional, US-S01 de todos os módulos ou entrega por canais externos.

## Origem e isolamento

Só eventos state_changed, grant_created, grant_replaced e grant_revoked da conta destinatária geram avisos. A identidade de uma notificação é o ID do evento de auditoria, sempre associado ao destinatário. Um rollback não cria aviso e repetir a consulta não duplica avisos. Não gerar notificações de account_created nem a partir de callbacks antes de commit. As mensagens são constantes, em português, sobre alteração de estado ou concessão de acesso. Expor apenas ID, data UTC, título genérico e estado lida/não lida. Não expor actor, motivos, referências, payloads ou informação de outra conta. A auditoria existente continua inalterada.

A página Ferramentas > Notificações locais, slug arn-local-notifications, exige capability read e a mesma fronteira de Ana Teste marcada: ambiente efectivo local, ARN_ALLOW_FICTIONAL_LOCAL_ACCESS===true e ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE===true, login exacto LocalFixtureUser::LOGIN, meta exacta string1, snapshot Active e metadados da sessão corrente validados contra titular e revisão. Nenhuma excepção para lab_admin. Revalidar em load/render/POST. Nunca receber actor/destinatário/prova de sessão do browser.

Extrair colaborador pequeno de leitura da conta corrente a partir de LocalRecordAccess para reutilizar gates e prova de sessão, preservando o construtor e testes de registos. Não mudar semântica do motor ou sessões, atribuições nativas, Google, temas ou ficheiros de segredos.

## Persistência e leitura

Adicionar uma tabela própria de recibos locais, com chave única ou primária composta (user_id,event_id) e read_at UTC Unix. Instalação explícita por CLI local administradora, nunca GET/activation. Usar IdentityWpdb dedicada, identificadores preparados e prefixo fixado/validado. Verificar schema, PK, tipos e InnoDB, recusar tabela incompatível sem reparar ou apagar silenciosamente. Não alterar as quatro tabelas AuthorizationSchema nem a sua versão. Implementar instalação idempotente de tabela ausente e validação rigorosa da existente. Falta de tabela ou falha SQL recusa de forma estática sem avisos ou SQL públicos.

Lista em ordem decrescente de ID, máximo20 avisos por página e cursor before positivo canónico ou ausente. Consultar21 para decidir existência de página seguinte. Filtro user_id e catálogo de eventos aplicado em SQL antes de limite. Não carregar histórico inteiro ou apresentar contagens globais. Usar ligação de próxima página com cursor do último evento visível e link de retorno ao início. Headerno-store, outputescapado, labels e datas legíveis. Antes da consulta validar estado/revisão correntes com o snapshot autorizado. Não há REST, exportação ou emails.

Marcar uma notificação como lida usa POST action arn-local-notifications-read, nonce próprio, event_id e revision positivos canónicos e sem overflow. Revalidar a sessão e obter actor/destinatário no servidor. Na transacção, bloquear a linha de conta autorização, exigir Active e revisão esperada, verificar que o evento existe, pertence ao destinatário, não excede a revisão corrente e pertence ao catálogo permitido. Só depois inserir recibo. Operação idempotente conserva primeiro read_at em reenvio. Commit ou rollback explícito. Não altera revisão de autorização, papéis, concessões ou dados de auditoria, nem equivale a aprovação formal. Recusa genérica para evento inexistente/alheio, revisão obsoleta, CSRF ou falhaSQL, sem dados do alvo. Redireccionamento303paraURLfixa com mensagem constante. Sem marcar todas, apagar ou desfazer nesta parcela.

## Ensaios e final

TDD unitário para mensagens sanitizadas, gates, parsing, cursor, método/nonce, recibo idempotente e erros. Integração WordPress/MariaDB exclusiva com prefixo e contas descartáveis, rollback sem aviso, dois destinatários sem fuga, paginação sem duplicados, evento desconhecido excluído, esquema incompatível recusado, first-read preservado, estado/revisão obsoletos recusados e leituras sem mudar auditoria. Nova suite tests/integration/local-notifications.php ligada a npm/CI, sem activar flags development. Preservar registos/sessões existentes com regressão focada e integração78.

Root instala explicitamente a tabela local pelo método documentado do repositório, com guardas local/admin e sem imprimir segredos. Playwright usa AnaActive19 e histórico fictício existente para ler e marcar um aviso. Confirma persistência apósreload, reenvio idempotente, falhasnonce/id/cursor/actorforjado, anónimo e lab_admin recusados, sessão anterior recusada após alteração auditada/revogada de concessão se necessário, layout375/768/1440. A revisão e histórico de acesso só mudam se for necessário ensaio de concessão, nunca pelo recibo. Não apagar recibos/histórico persistentes. F1-04 passa Em curso com parcela local descrita. Actualizar roteiro das fases em documento com dependências reais. Plugin0.7.0. Commits/push, revisão independente, CIexactSHA e merge autorizado sem deploy.
