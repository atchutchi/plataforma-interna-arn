# Homologação da guarda, permissões e restauro no .205

Preparação de 12 de novembro de 2026. A PR #85 foi integrada em cfe21528ec9e2aee4fa0353a23bacbe8af154b53 após revisão e CI verde. Este guião não executa deploy nem autoriza a abertura do serviço. Todos os resultados da matriz abaixo estão por executar no IIS/MySQL do destino. Os ensaios do laboratório estão em [migrações institucionais](migracoes-institucionais.md).

## Âmbito e sequência

O destino institucional é ARN-Intranet, C:\Sites\ARN\public, no servidor 192.168.17.205. Preservar Default Web Site, RDWeb, Rpc, Wamp, os seus serviços, bases, ficheiros e permissões. Não executar iisreset nem reiniciar serviços exteriores à ARN. A manutenção actual por IP continua activa até decisão expressa de abertura.

Primeiro recolher o inventário só de leitura. Depois preparar um ambiente descartável com IIS e MySQL 8.4, site, application pool, raiz, configuração, base, utilizador SQL e armazenamento privados próprios. Preferir uma VM isolada. Se tiver de residir no .205, aprovar previamente caminhos, recursos, porta e nomes sem colisões. Nenhum comando de ensaio deve apontar para arnplataforma ou C:\Sites\ARN\public. Não reutilizar a instância Wamp. Criar dados fictícios de raiz apenas nesse destino de ensaio, nunca transportar o laboratório para produção.

Ensaiar guarda, ACL, interrupção e restauro nesse destino. Só depois preparar uma janela institucional, backup consistente e candidato selado. A aplicação no site institucional é uma etapa posterior, com evidências e responsável identificados. Não executar instaladores de fixtures ou activar flags locais em produção.

## Inventário inicial, só de leitura

Executar no PowerShell administrativo do .205. Guardar a saída num arquivo privado de operação, fora do Git e da raiz web. Não recolher o conteúdo de wp-config.php, my.ini, recibos pessoais ou configurações com segredos.

```powershell
Import-Module WebAdministration
Get-Website -Name 'ARN-Intranet' | Select-Object Name, State, PhysicalPath, ApplicationPool
Get-WebBinding -Name 'ARN-Intranet' | Select-Object protocol, bindingInformation, sslFlags, certificateHash
Get-Item 'IIS:\AppPools\ARN-Intranet' | Select-Object Name, state, managedRuntimeVersion, enable32BitAppOnWin64
Get-CimInstance Win32_Service -Filter "Name='ARNMySQL84'" | Select-Object Name, State, StartName, PathName
Get-NetTCPConnection -State Listen | Where-Object { $_.LocalPort -in @(3308,33080) } | Select-Object LocalAddress, LocalPort, OwningProcess
Get-Service | Where-Object { $_.Name -match '^wamp|^ARNMySQL84$' } | Select-Object Name, Status
Get-Acl -LiteralPath 'C:\Sites\ARN\public' | Format-List Owner, AccessToString
Get-Acl -LiteralPath 'C:\ProgramData\ARN\WordPress' | Format-List Owner, AccessToString
```

O processo que escuta pode ser filho do PID do serviço MySQL. Confirmar o executável antes de concluir que pertence a outra instância. Na ligação SQL exclusiva da ARN, verificar SELECT DATABASE(), @@port, @@bind_address, @@datadir e SHOW GRANTS. Não deduzir datadir a partir da localização de my.ini. Já existiu uma instalação com Data\Data. Não guardar palavras-passe na linha de comandos.

Registar também versões efectivas de PHP/FastCGI, extensões, WordPress, WP-CLI e MySQL, SHA do candidato, hash do manifesto selado, identidade efectiva do pool e da conta que executará WP-CLI. Conferir a resolução interna do hostname e a validade TLS de outro PC. O inventário de ACL não demonstra permissões efectivas: testar com as identidades reais de execução na cópia isolada.

## Preparação da guarda e das permissões

Rever wp-config.php localmente sem o publicar. Definir ABSPATH e carregar a configuração privada antes de incluir wp-content/plugins/arn-intranet-core/institutional-maintenance.php. A inclusão deve ocorrer antes de wp-settings.php e antes de qualquer carregamento WordPress. A activação do plugin não instala a guarda. Confirmar que o bloqueio anterior por IP não impede a execução CLI autorizada e que não foi removido.

ARN_MIGRATION_STATE_DIR deve identificar uma pasta existente em disco local, fora de todas as raízes servidas. Não usar junctions, symlinks ou partilhas. A pasta e os recibos têm de permanecer intactos entre execuções e reinícios. ARN_ALLOW_INSTITUTIONAL_MIGRATIONS só pode ser true durante a janela autorizada. O operador necessita de manage_options, sem concessão implícita de competências institucionais.

| Recurso | Pool HTTP ARN | Operador de migrações | Outros utilizadores sem função operacional |
| --- | --- | --- | --- |
| Código e bootstrap | Leitura e execução, sem escrita | Publicação controlada pela conta de entrega | Sem escrita |
| Configuração privada | Leitura necessária, sem escrita | Leitura necessária ao CLI | Sem acesso |
| Estado e recibos | Acesso mínimo necessário à guarda | Criar marcador, temporários e recibos, libertar marcador | Sem acesso |
| Logs, sessões e temporários PHP | Escrita apenas nas pastas dedicadas | Conforme operação aprovada | Sem acesso |
| Backup e evidências | Sem acesso | Conta de backup/restauro explicitamente autorizada | Sem acesso |

A implementação abre gate.lock com c+b, mesmo num pedido HTTP. Preparar esse ficheiro antes de retirar a criação ao pool e testar leitura/escrita e flock no ficheiro existente. O pool necessita de acesso à pasta e ao marcador para verificar a manutenção, mas não deve poder apagar o marcador ou alterar recibos. Não conceder Modify recursivo a todo o estado como atalho. Uma ACL que impeça a abertura exigida faz a guarda recusar pedidos, incluindo antes da manutenção. Validar herança e permissões de novos recibos, não apenas os ficheiros existentes. O operador e administradores continuam dentro da fronteira de confiança dos recibos.

Antes da primeira aplicação institucional, reciclar apenas o pool ARN e parar os seus workers/agendamentos para eliminar processos anteriores à guarda. Inventariar CLI, cron, REST, AJAX e ferramentas SQL. Um acesso directo à base não passa pelo flock. A guarda também não protege ficheiros estáticos servidos directamente pelo IIS. Confirmar separadamente o bloqueio HTTP das pastas privadas e que nenhum anexo privado reside numa pasta pública.

## Matriz de aceitação

Registar para cada linha: Por executar, Passou ou Falhou, instante UTC, operador, SHA, destino e referência privada da evidência. Uma falha bloqueia a etapa seguinte. Não preencher com os resultados do laboratório.

| ID | Ensaio no destino isolado | Resultado exigido |
| --- | --- | --- |
| G01 | Pedido normal e comando CLI normal antes da manutenção | Funcionam no âmbito autorizado e usam a guarda precoce |
| G02 | Pedido PHP controlado ainda activo quando apply tenta obter exclusividade | apply recusa, não executa DDL e conserva manutenção. Novos HTTP recebem 503/no-store, CLI normal termina em 1 |
| G03 | Terminar o leitor e repetir apply | Exclusividade adquirida, recibos sequenciais, conclusão sem abertura automática |
| G04 | Durante manutenção pedir início, login, administração, REST, AJAX e cron de outro PC | Todas as entradas PHP bloqueadas. A excepção de IP de preparação não ultrapassa a guarda |
| G05 | Segundo aplicador e segunda ligação SQL com GET_LOCK disputado | Não há execução concorrente nem declaração falsa de conclusão |
| G06 | Interromper o processo aplicador depois de um recibo intermédio, apenas na cópia descartável | Marcador persiste, release recusa e nova aplicação retoma sem duplicar histórico ou concessões |
| G07 | Recibo inválido, destino diferente e schema desconhecido, em cópias separadas | Recusa conservadora. Originais e evidências preservados, sem edição correctiva de recibos |
| G08 | release depois de complete e validação estrutural | Nova validação e recibo released antes de retirar marcador. Continua a manutenção por IP existente |
| P01 | Testar criação, leitura, escrita e eliminação com pool e operador | ACL cumpre a matriz, pool não altera código/configuração/recibos nem apaga marcador |
| P02 | Pedido HTTP directo aos caminhos privados e tentativas por utilizador não autorizado | Nenhum conteúdo privado acessível |
| P03 | Verificar utilizadores, grants e histórico antes/depois | Nenhuma conta ou competência criada implicitamente pela migração |
| R01 | Restauro completo em destino separado | Código, configuração, base, ficheiros privados e histórico reconciliados |
| R02 | Reinício do pool/host com marcador presente na cópia isolada | Manutenção permanece. Não declarar resistência a perda de energia apenas com este ensaio |
| R03 | Revalidar sessões e autorização após restauro | Conta suspensa e sessão revogada recusadas. Não ressuscitar sessões de um backup antigo |
| W01 | Comparar inventário dos serviços e recursos Wamp/Default Web Site | Sem alterações provocadas pelo ensaio |

Os comandos apply/release e o formato de backup-ref estão no [contrato de migrações](migracoes-institucionais.md). Preflight normal fica bloqueado quando a guarda está em manutenção. Não retirar a guarda para o executar. apply e release fazem a validação estrutural necessária no circuito autorizado. O ensaio de interrupção não deve matar o serviço SQL nem afectar outros sites.

## Backup, restauro e retoma

Fixar primeiro responsável, destino privado de backup, retenção, RPO e RTO. Fazer uma captura consistente com escritores e workers ARN suspensos. Incluir a base exclusiva, configuração/chaves por canal protegido, código e manifesto, versões de runtime, estado da guarda, recibos, volumes privados, filas e auditoria. Registar hashes, versão de schema e instante do ponto de recuperação. backup-ref é apenas uma referência declarada, não prova que o backup existe.

Restaurar primeiro num destino isolado, sem DNS de produção, notificações, Calendar ou OAuth externos activos. Usar credenciais próprias desse destino. Não sobrescrever o original. Conferir inventários de tabelas, contagens e conteúdo por comparação protegida, gerações, histórico, referências de ficheiros e hashes dos blobs. Medir tempo de recuperação e identificar qualquer perda face ao ponto de captura.

Os recibos estão vinculados ao host SQL, nome da base, prefixo e caminho real do WordPress. Uma cópia restaurada noutro destino não pode reutilizá-los como recibos válidos desse destino. Preservá-los como evidência externa e usar estado de operação novo, privado, para o ensaio isolado, mantendo o site bloqueado por controlo independente. Não editar o hash de destino nos originais. O ensaio deve comprovar a recusa dos recibos deslocados e documentar a associação entre backup original e novo destino.

No restauro efectivo para o mesmo destino institucional, conservar o conjunto coerente de dados e recibos do ponto de captura e a manutenção. Antes de reabrir, confrontar suspensões, revogações e decisões posteriores ao backup. Se não for possível reconstruí-las com autoridade, manter o serviço fechado. Não assumir que sessões restauradas são seguras. Definir a invalidação de sessões e a revalidação de acessos antes da abertura, sem transformar um restauro em concessão automática.

Uma interrupção de apply com schema conhecido retoma pelo próprio apply. Um incidente com corrupção, escrita exterior ou incompatibilidade exige diagnóstico e decisão de restauro. Não apagar maintenance, gate.lock, temporários ou recibos para desbloquear. Não fazer rollback só de código sobre schema novo sem compatibilidade demonstrada.

## Recibo de homologação e passagem à instalação

Guardar fora do Git: release/SHA/manifesto, destino e identidades técnicas, inventário anterior/posterior, matriz com evidências, backup e prova de restauro, tempos medidos, desvios, responsáveis e decisão. Não incluir senhas, tokens ou dados de funcionários em issues/PRs.

Só com G01 a W01 aprovados e desvios resolvidos se prepara a aplicação institucional. Ainda faltam a publicação do candidato, confirmação local da guarda/ACL, backup do destino, aplicação/release na janela acordada e homologação Google. As migrações dos restantes módulos e a transição de actores mantêm-se no README. Este guião não dá essas fases como concluídas.
