# Cadastro e importação auditada F1-05

26 de outubro de 2026. Entrega no laboratório fictício 8892, a partir de main 25de8d3. A PR #68 do diagnóstico de media foi integrada após 22 controlos aprovados e os 12 controlos de main também passaram. Esta parcela não altera a demonstração 8888, contas reais ou produção.

## Circuito entregue

A página Cadastro acrescenta um circuito de criação e actualização de registos profissionais. Conserva o identificador próprio, nome, email e unidade. Não cria contas WordPress, associações Google, funções ou permissões. Não projecta estes registos no directório existente sem um contrato de associação explícita.

O gestor cola CSV UTF-8 com cabeçalho `professional_id,name,email,unit_id`. Limites: 16 KiB, 20 linhas por lote e 999 registos no catálogo. Apenas identificadores LAB-PER-001 a LAB-PER-999, nomes iniciados por Pessoa fictícia e emails example.invalid. A unidade deve existir e estar activa no snapshot fictício. Dados reais, colunas de permissões, duplicados, conflitos de email e lotes malformados são recusados. Uma linha inválida impede o lote inteiro.

A validação não grava nada. Mostra os valores propostos, erros por linha e contagens de criação, alteração e ausência de mudança. A confirmação tem HMAC ligada ao actor, token de sessão, conteúdo e revisões do cadastro e das unidades. Expira ao fim de dez minutos. O servidor volta a validar o CSV e a autoridade na transacção. O browser não escolhe propostas, revisões ou actor. Alterações entretanto ocorridas exigem nova validação.

A ausência de um registo no CSV conserva-o. Não há eliminação, saída ou suspensão implícita. Um lote confirmado sem mudanças pode produzir um recibo e uma nova revisão de importação. Reenviar a mesma confirmação válida devolve o recibo original e não repõe dados anteriores. Depois de expirar a prova, o histórico continua disponível e é necessária nova validação para uma nova operação.

## Autoridade e persistência

Activação apenas com ambiente local, site único e flags booleanas de cadastro, unidades, autorização e acesso fictício. `ARN_ALLOW_LOCAL_REGISTER` é a flag nova. A conta de laboratório tem login `arn.rh.cadastro.teste`, email correspondente example.invalid, papel WordPress subscriber e marca fictícia persistente. Além dessa fronteira local, a sessão precisa de geração corrente e de concessão HumanResources institucional para ReadHrRecord e WriteHrRecord. Uma concessão parcial por unidade não cobre esta consulta global. Administração técnica não recebe excepção de negócio.

O comando `wp arn register install --user=<administrador-local>` instala apenas o schema. Não cria pessoas, contas ou concessões. A conta fictícia de aceitação foi preparada explicitamente por script privado e serviço de autorização auditado. Não existe instalação, migração ou concessão por GET.

Três tabelas InnoDB próprias: arn_reg_header, arn_reg_batches e arn_reg_audit, sempre sob o prefixo do site. O singleton guarda a versão do schema, revisão e snapshot do cadastro. Os recibos conservam identificador de lote, actor, hashes, revisão das unidades, contagens e instante UTC. A auditoria conserva antes/depois e a referência ao recibo. O schema exige colunas, índices, motor e versão exactos. Uma preparação parcial ou divergente é recusada e exige diagnóstico explícito.

A ligação dedicada bloqueia actor, unidades e cadastro nessa ordem. Mudança, revisão, recibo e auditoria pertencem ao mesmo commit. Erros revertem todo o lote. A sessão e a competência são verificadas novamente antes do commit. O último recibo e a auditoria devem corresponder ao snapshot corrente. Há limite de 10 000 lotes neste circuito local. O histórico apresenta os últimos 20 sem apagar os restantes.

## Aceitação e revisão

Fecho: 1114 testes PHP e 5556 asserções, com sete omissões específicas do runtime Windows já existentes. Suite focal: 16 testes e 46 asserções. Node: 55 aprovados. SQL: 41 verificações. Playwright: 44 verificações. PHPCS: dez ficheiros, sem erros. Lint, construção e comparação de integridade aprovados. A integração Git continua condicionada à CI do SHA exacto e à confirmação posterior de main.

O ensaio SQL `npm run test:register:integration` usa dez tabelas com prefixo aleatório próprio e fornecedores sintéticos de identidade/sessão. Não cria utilizadores nativos. Tem 41 verificações, incluindo preview sem DML, schema ausente sem instalação implícita, criação, actualização, preservação dos ausentes, recibo idempotente, conflito de revisões, estrutura alterada, rollback por falha de recibo/auditoria, perda de sessão antes do commit, suspensão e geração antiga. Uma segunda ligação tenta importar enquanto a primeira mantém os bloqueios. Outra tentativa altera a estrutura sem depender do bloqueio da conta e também é recusada por timeout. O teste limpa exclusivamente as suas dez tabelas.

Playwright usa WordPress real e uma nova conta RH fictícia. Foram exercitados os botões nativos de validação e confirmação, dados reais recusados, criação, actualização, ausentes preservados, reenvio, adulteração do CSV, CAS, nonce, campos extra, métodos indevidos, administração técnica, outro colaborador, anónimo, suspensão, reactivação com novo login, teclado e reflow a 320/390/768/1440 px. A flag desligada devolve 404 vazio privado. A reposição exacta conserva os dados e a sessão. Capturas desktop e mobile inspeccionadas. O teste de suspensão altera apenas a nova conta de aceitação, com revisão e auditoria crescentes.

A revisão independente identificou um P2: esc_attr preservava entidades HTML existentes e o browser alterava os bytes do CSV oculto. O caso com entidades nomeadas, numéricas e aspas foi reproduzido no browser antes da correcção, sem gravar o lote. A codificação literal agora conserva os bytes e os valores apresentados. A regressão passou pelo mesmo formulário e criou o quarto registo fictício. Há também cobertura PHPUnit e WordPress real desta transformação. A prova específica do bloqueio das unidades foi acrescentada após a observação do revisor. Não ficaram achados P1/P2 por corrigir.

O primeiro ensaio Node falhou por ausência de NODE_PATH para as dependências já instaladas. A execução com o runtime correcto passou 55 testes. Um teste de acesso do browser tentou fetch a partir da página interna de erro do Chrome, após um redireccionamento para um módulo recusado ao perfil. Foi retomado a partir de wp-login.php no mesmo origin, preservando as provas anteriores. Nenhuma destas falhas foi tratada como sucesso nem motivou alteração de permissões.

Relatórios, credenciais fictícias, snapshots, capturas e provas RED/GREEN ficam na pasta privada register-import-20261026, fora do Git e do document root. A comparação conservou 133 tabelas exteriores, todas as linhas anteriores de 29 tabelas de negócio, 13 contas anteriores e 311 ficheiros. Foram acrescentados somente três tabelas de cadastro, quatro registos fictícios com quatro recibos e uma nova conta RH fictícia com concessão e transições auditadas. Os originais e os ficheiros de media não mudaram.

## Limites e continuação

Conclui a importação e actualização em lote de cadastro fictício, sem modificar a ferramenta antiga de pré-validação sintética. Faltam manutenção individual de estados, associação explícita profissional-conta e delegações persistentes. RH continua a validar identificadores, campos, hierarquia, competências e dados reais. Conservação institucional, recuperação a partir de backup e homologação no servidor têm aceitação própria. Um operador com controlo directo da base pode alterar snapshots e hashes. Esta auditoria não é um arquivo inviolável contra o administrador da base.

Não executar rollback para código sem o módulo como forma de apagar ou repor dados. Conservar as três tabelas e os recibos. Desactivar a flag fecha o acesso local e não elimina os dados. O pacote de deploy e a migração institucional permanecem no backlog.
