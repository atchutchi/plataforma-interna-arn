# Migrações institucionais: diagnóstico e aplicação

Entrega de 12 de novembro de 2026. O diagnóstico permanece exclusivamente de leitura. A aplicação explícita prepara apenas as seis tabelas de identidade e autorização. Não activa login ou módulos, não cria contas ou concessões e não importa o laboratório.

## Comando

Com o núcleo ARN carregado, executar no WP-CLI com a conta administradora técnica explicitamente seleccionada:

```text
wp arn institutional preflight --user=<administrador-tecnico> --path=<raiz-wordpress>
```

O comando preflight recusa argumentos e opções adicionais, execução sem manage_options e multisite. Não há opção --apply nesse comando. A ligação dedicada usa a configuração privada do próprio WordPress e o prefixo desse site. A saída JSON contém apenas a versão do diagnóstico e estados estruturais, sem identidades, credenciais ou conteúdo de contas. Erros usam uma mensagem constante e código de falha.

identity pode ser absent, current, legacy ou partial-generations. Os dois últimos exigem migração explícita antes do acesso. authorization só pode ser absent ou current. Autorização parcial, versões desconhecidas e estruturas incompatíveis fazem o comando falhar. O diagnóstico reutiliza os validadores persistentes e acrescenta recusa de colunas inesperadas nas tabelas de identidade. Não é uma auditoria completa de privilégios, triggers ou integridade dos dados.

write_performed e activation_permitted são sempre false. Uma resposta current não prova homologação de Google, competências institucionais ou prontidão de produção. O diagnóstico não bloqueia escritores concorrentes nem constitui um plano aplicável posteriormente sem nova validação.

## Aplicação e manutenção exclusiva

O administrador prepara um directório privado fora da raiz servida, em disco local, com ACL que impeça alteração por terceiros. Não usar partilhas de rede. Configura ARN_MIGRATION_STATE_DIR e inclui wp-content/plugins/arn-intranet-core/institutional-maintenance.php no wp-config, antes de wp-settings.php. O ficheiro faz parte do pacote do plugin, mas não é carregado automaticamente pela activação. A guarda deve estar instalada em todos os processos WordPress do destino antes de aplicar. Parar processos antigos, tarefas e ferramentas SQL que não passem por esta guarda. Não é um bloqueio de administradores SQL ou de outros servidores.

Cada pedido HTTP e CLI normal mantém um flock partilhado até terminar. apply/release são os únicos comandos admitidos através da manutenção persistente. O operador também exige manage_options, execução CLI, site simples e ARN_ALLOW_INSTITUTIONAL_MIGRATIONS estritamente true na configuração privada. A manutenção cria um marcador persistente e tenta adquirir flock exclusivo sem espera. Enquanto existirem leitores, recusa a aplicação e conserva o marcador. Novos pedidos recebem HTTP 503 com no-store, CLI normal termina com código 1. Repetir apply quando os pedidos anteriores tiverem terminado. Não há timeout que reabra o site automaticamente.

Há também um GET_LOCK SQL exclusivo por base/prefixo, mantido na ligação dedicada durante a operação e libertado no fim. A ligação não reconecta silenciosamente. Não se inicia uma transacção para simular atomicidade de DDL.

```text
wp arn institutional apply --backup-ref=<referencia-do-backup> --user=<administrador-tecnico> --path=<raiz-wordpress>
wp arn institutional release --user=<administrador-tecnico> --path=<raiz-wordpress>
```

backup-ref é uma referência operacional, nunca uma senha ou caminho de ficheiro. O operador confirma externamente backup e capacidade de restauro. O comando regista a referência mas não verifica a existência ou qualidade do backup. Depois de apply concluir, a manutenção continua activa. release relê o último recibo e valida novamente os dois schemas antes de reabrir. Nenhum destes comandos activa a identidade Google ou atribui competências.

## Recibos e retoma

Os recibos JSON ficam no directório privado, com sequência, versão 1, hash do destino, actor técnico, referência do backup, fase e instante UTC. Não contêm identidades Google, credenciais ou dados pessoais. Cada ficheiro é escrito num temporário exclusivo, sincronizado com fsync e renomeado para um novo nome sequencial. Recibos anteriores não são substituídos. Temporários de uma falha ficam preservados e não contam como conclusão. A durabilidade após perda de energia do host exige validação do sistema de ficheiros e backup, não apenas os ensaios de interrupção de processo.

Fases: started, identity, authorization, complete e released. Recibos com outro destino, sequência incompleta ou formato inválido recusam a operação. Retomar executa novamente apply, com validação das tabelas existentes antes de qualquer DDL. Só cria tabelas ausentes conhecidas e acrescenta as colunas de geração previstas. Conserva vínculos, gerações já existentes e histórico. A autorização parcialmente criada é retomável pelo aplicador quando todas as tabelas presentes são compatíveis, embora preflight continue a recusá-la como estado pronto. Não remove tabelas ou originais e não promete rollback transaccional de DDL.

Interrupção mantém o marcador. Um recibo started ou intermédio impede release, mesmo se as tabelas parecerem completas. Repetir apply para validar e concluir a sequência. Se existir estrutura desconhecida ou recibo corrompido, investigar e restaurar segundo o plano de recuperação. Não apagar o marcador ou editar recibos para contornar a recusa.

## Limites de entrada em serviço

Validar ACL, arranque precoce da guarda, bloqueio de todos os consumidores, upgrade e restauro no IIS/MySQL do destino antes de disponibilizar a execução institucional. Os testes desta entrega não constituem aplicação no servidor .205. A adopção dos módulos, aprovação de contas e concessões, homologação Google e restauro continuam pendentes.

Os pré-cadastros existentes, os seus prazos e as sessões antigas não são convertidos automaticamente. Nenhum instalador local de fixtures faz parte deste circuito. Depois da base de identidade seguem as migrações e a composição explícita de cada módulo.

## Verificação

O ensaio SQL institucional usa prefixo aleatório e dados fictícios descartáveis. Cobre ausência, schema actual, legado, gerações parcialmente migradas, autorização parcial, colunas inesperadas, versão desconhecida, repetição e conservação do histórico. Observa as queries submetidas ao wrapper para exigir apenas SELECT e SHOW. As sondas internas directas de IdentityWpdb, incluindo SAVEPOINT e RELEASE, ficam fora desse observador e não escrevem dados ou schema persistentes. A suite continua a testar callback, sessões e revogação. O novo aplicador acrescenta testes próprios de interrupção, retoma e guarda HTTP.

Nenhum comando desta entrega foi aplicado ao servidor .205. Wamp, dados reais e laboratório de demonstração permanecem fora do âmbito.

Fecho local: 109 verificações de migração, incluindo falhas nas seis criações e nas três alterações de geração, retoma, preservação do histórico, ausência de novas contas/concessões e disputa GET_LOCK entre duas ligações. Regressão de identidade/callback: 77 verificações. Fixtures removidas. Descoberta completa de 150 ficheiros PHPUnit, suite de 1232 testes aprovada com sete omissões preexistentes. Ajuste final de recusa de recibo incompleto reproduzido em RED e confirmado em dois testes focais com oito asserções. Node 72, lint, build e PHPCS dez ficheiros aprovados. Revisão independente e revisão do ajuste final sem bloqueantes. CI exacta é verificada na PR.


Ensaio de composição CLI no WordPress de testes, com bootstrap via --require antes do carregamento e prefixo aleatório: apply concluído, CLI normal em manutenção recusada com código 1 e release concluído. A execução sem opt-in/guarda também foi recusada. As seis tabelas fictícias foram verificadas vazias e removidas. Nenhuma conta nativa foi criada. Playwright no servidor PHP portátil isolado em 127.0.0.1:8894 observou 200, depois 503 com marcador persistente e novamente 200 após libertação. Browser e servidor de ensaio encerrados. Este ensaio não demonstra IIS nem TLS institucional.
