# Notificações internas locais F1-04

14 de outubro de 2026. Primeira parcela local de US-S01 e US-ARN-10. Usa a auditoria transaccional de acessos existente como fonte dos avisos, sem email ou outro canal externo. US-A04 conserva a consulta administrativa protegida já existente. As histórias institucionais não ficam concluídas com este laboratório.

## Contrato

Só Ana Teste marcada, em ambiente local com ambas as flags booleanas activas, snapshot Active e sessão nativa da revisão corrente pode consultar ou marcar os seus avisos. O administrador nativo não recebe excepção. Motivos, intervenientes, referências e payloads de auditoria não são apresentados ao destinatário.

A fonte contém apenas alterações confirmadas de estado, criação, substituição e revogação de concessões. O ID do evento identifica o aviso e não há cópia ou envio repetido. A lista usa filtro de destinatário e evento antes do limite, com paginação por cursor. Os recibos persistem separadamente. Marcar como lida exige nonce, evento pertencente à conta e revisão corrente validada na transacção. Reenvios conservam o primeiro instante de leitura. A operação não altera a revisão de autorização ou a auditoria.

A tabela de recibos tem instalação explícita por CLI local, validação de estrutura e recusa de incompatibilidades. GET não instala nem escreve. O código conserva as quatro tabelas e a versão do schema de autorização.

## Verificação

A suite PHP passou com 581 testes e 2027 asserções. A regressão dirigida de notificações e registos passou com 19 testes e 83 asserções após o ajuste final do tratamento de erros. Os 54 testes Node, o lint, o build e o PHPCS dos 127 ficheiros passaram. A integração real de estado e sessões manteve 78 verificações aprovadas. A integração própria de notificações passou 47 verificações. Usa tabelas e contas exclusivas, removidas no final, e verifica isolamento, paginação, recibos idempotentes, rollback, conflito de revisão, bloqueio transaccional, falha de escrita e estruturas incompatíveis.

O Playwright validou a conta fictícia Ana Teste no WordPress local. Uma sessão antiga foi recusada. A sessão corrente mostrou 20 avisos na primeira página e um na segunda, sem repetir IDs. A marcação como lida persistiu após recarregar. Reenvios, incluindo campos de destinatário e actor adulterados, conservaram um único recibo e o primeiro instante de leitura. Nonce inválido, revisão antiga, evento inexistente e identificadores malformados foram recusados. O administrador nativo e o visitante anónimo não receberam os avisos da fixture.

Foram verificadas larguras de 375, 768 e 1440 píxeis sem transbordo horizontal. Não houve erros JavaScript. A regressão de Registos fictícios conservou o acesso ao único registo próprio e os cinco casos de recusa com 404 indistinguível. As respostas privadas conservaram `Cache-Control: no-store`.

No final do ensaio, Ana Teste permaneceu Active na revisão 22, com a concessão própria original como única concessão válida. Os três eventos adicionais de criação, substituição e revogação ficaram na auditoria. O recibo pertence apenas ao evento 22 da fixture. Marcar e repetir a leitura não alterou essa revisão nem acrescentou eventos de autorização. As capturas e os helpers operacionais ficam locais e ignorados pelo Git.

## Instalação no laboratório

A instalação foi executada explicitamente por WP-CLI, como administrador local, através de `Arn\Intranet\Notifications\LocalNotificationInstaller::install()`. O método exige `WP_CLI === true`, ambiente efectivo `local` e `manage_options`. Não existe instalação automática por activação do plugin ou acesso HTTP. A tabela nova não altera o schema de autorização existente. Uma estrutura incompatível exige diagnóstico antes de qualquer intervenção, pois o instalador recusa repará-la silenciosamente.

Depois da instalação, a conta fictícia autorizada encontra Notificações locais no menu Ferramentas. É necessário iniciar uma nova sessão quando a revisão de autorização muda. Marcar como lida regista apenas a leitura do aviso e não confirma uma decisão institucional.

## Limites

O ensaio prepara a notificação interna para os eventos de acesso existentes. Não implementa avisos de tarefas, decisões, notícias ou tickets ainda inexistentes. Não envia emails, não executa entregas externas nem valida infraestrutura institucional. A homologação F1-03 e os contratos dos módulos continuam necessários para a F1-04 institucional.
