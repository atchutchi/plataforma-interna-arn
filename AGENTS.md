# Instruções para agentes

A referência do projeto é o `README.md`. Lê as secções que a tarefa tocar antes de alterar código. GitHub é a fonte de verdade. Este ficheiro resume o trabalho corrente. ChatGPT Work/Codex trabalha sobre GitHub e num clone separado. Cursor é opcional e não é dependência do código.

A stack é WordPress, com regras em `wp-content/plugins/arn-intranet-core` e apresentação em `wp-content/themes/arn-intranet`. Não substituas esta arquitetura. Textos de interface ficam em português de Portugal.

## Comandos

Na raiz, com Node.js 24.21.0:

```bash
npm ci
npm run env:start
npm run env:check
npm run env:stop
npm run build
npm run lint
npm test
```

O ambiente de testes é separado e usa `.wp-env.test.json`:

```bash
npm run env:start:tests
npm run env:check:tests
npm run composer:install
npm run test:php
npm run lint:php
npm run env:stop:tests
```

Usa os scripts npm: `scripts/wp-env.mjs` fornece bindings HTTP e MariaDB em `127.0.0.1` através de `COMPOSE_ENV_FILES`. Antes do arranque, confirma essa capacidade com `docker compose config`, sem criar contentores. Executar `wp-env start` diretamente não aplica o controlo. Não exportes `WP_ENV_*PORT` nem `COMPOSE_PROJECT_NAME`. Se precisares de outra porta HTTP, usa um valor numérico no override JSON correspondente. O arranque não muda a porta HTTP automaticamente.

`npm run env:check` e `npm run env:check:tests` confirmam o estado e os bindings reais do projeto selecionado. Qualquer publicação fora de loopback falha, incluindo IPs específicos da LAN. As duas configurações mantêm `testsEnvironment=false` e `phpmyadmin=false`, também nos overrides. O ambiente de testes próprio é `.wp-env.test.json`.

PHP, Composer, MariaDB e WordPress vêm dos contentores. Não há `mysqlVersion`: a base local configurada é MariaDB 11.8 e não prova compatibilidade com MySQL 8.4. `composer.lock` já está integrado pela PR #3. Quando for necessário actualizá-lo, só deve ser gerado com `npm run composer:install`, dentro do contentor. Sem Docker, PHPUnit e phpcs podem correr com um PHP 8.4 local e `vendor/` não versionado; isso verifica o código, não o ambiente.

## Código

O plugin usa o autoloader PSR-4 em `src/Autoloader.php`, namespace `Arn\Intranet`. Não acrescentes `require_once` por classe. Regras puras ficam em classes sem WordPress e com testes em `tests/phpunit`; a ligação aos hooks fica em classes separadas, como `Access\PrivatePortal`.

O painel CMS usa `Administration\AdminDashboard` e atalhos filtrados por capacidades nativas. Seguir docs/painel-administrativo-f1-06.md. O CSS do widget só carrega no index.php normal. O esquema ARN é uma preferência nativa opcional, aplicada apenas à conta de laboratório na validação. Não confundir estes atalhos com atribuições institucionais de F1-03. Os menus e destinos conservam as suas próprias guardas.

O catálogo `Organization\DocumentaryCatalog` transcreve as unidades da secção 4.1. Não contém pessoas. Mantém DSU, DSGI e DSIC sem designação, as duas DGE com identificadores `DRE-DGE` e `DREC-DGE`, e as relações como `Documental` ou `Pendente` até RH confirmar.

## Limites

Não acedas a Panthera-Onca, ao controlador de `arn.local`, ao DNS ou a produção. O responsável autorizou nesta continuação de 13 de outubro a consulta e preparação de configuração Google para autenticação. Não guardar credenciais, dados de funcionários ou URLs de sessão no Git. Não alterar MFA ou promover acessos institucionais. Não cries contas a partir da lista da secção 4. As três contas da secção 5.4 não recebem privilégios por email. O acesso fictício `ana.teste` / `fixture-local-ana` só é permitido com `WP_ENVIRONMENT_TYPE=local` e a flag explicitamente verdadeira. Uma fixture já guardada também tem de ficar bloqueada quando a condição deixa de se cumprir, preservando contas legítimas e autoria.

F1-02 tem cliente Facile 1.0.0 homologado localmente, callback e sessão Google limitados a WP_ENVIRONMENT_TYPE=local. Seguir docs/google-login-local-f1-02.md. A activação institucional continua pendente de HTTPS/IIS/MySQL/LAN/VPN. Usa uma ligação IdentityWpdb dedicada, auditoria na mesma transacção e autorização local explícita. Os ensaios `npm run test:identity:integration` e `npm run test:oidc:integration` criam apenas fixtures e tabelas exclusivas. Não os executar em produção ou sobre dados institucionais reais.

F1-01 continua aberta até à homologação Windows/IIS. Não marques a tarefa como concluída só porque a cópia local abre.

F1-03 tem uma fundação local em `Authorization`: snapshots imutáveis, catálogo fechado e delegação limitada. Seguir docs/autorizacao-local-f1-03.md. A página Ensaios de acesso local exige ambiente efectivo `local`, `ARN_ALLOW_LOCAL_AUTHORIZATION_PROBE` booleano true e `manage_options`, também antes de renderizar. Usa apenas fixtures. Não liga inputs de browser ao motor nem concede papéis WordPress.

A persistência local de funções, âmbitos e estado fica em `Authorization\Storage`, com quatro tabelas próprias, revisão concorrente e auditoria na mesma transacção. Seguir docs/persistencia-autorizacao-f1-03.md. A autoridade WordPress exige ambiente efectivo `local`, `ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE` booleano true, actor corrente com `manage_options` e alvo existente marcado como fixture. A instalação é explícita por CLI. Alterações usam o serviço auditado e controlo de revisão. A página Persistência de acessos local é apenas leitura. Não marcar lab_admin como fixture nem criar concessões a partir de emails. `npm run test:authorization:integration` usa prefixo aleatório e contas exclusivas. A guarda Authorization\Session liga o estado apenas às sessões fictícias no laboratório. Exige as flags booleanas de acesso fictício e armazenamento e marca persistente exacta. A revisão é a geração da sessão. Suspender e reactivar exige novo login. Uma geração antiga só destrói o token apresentado quando a conta está Active. A ferramenta Estado de contas local gere apenas Ana Teste com POST, nonce, revisão e justificação. Seguir docs/sessoes-estado-local-f1-03.md. npm run test:account-sessions:integration usa fixtures e prefixo exclusivos. A ferramenta Concessões locais gere apenas Ana Teste e os presets employee-own e unit-manager-a. Criar e substituir exigem Active. Revogar também funciona em Suspended. Usa o mesmo serviço auditado e invalida sessões por revisão. Seguir docs/gestao-concessoes-local-f1-03.md. Não aceitar funções, âmbitos, datas ou alvos arbitrários do browser. Registos fictícios liga o snapshot e a sessão corrente à lista e ao detalhe do catálogo local. Exige Ana Teste marcada e as flags locais. Filtrar antes de contar e devolver o mesmo 404 para objecto recusado ou inexistente. lab_admin não recebe excepção. Seguir docs/registos-locais-f1-03.md. Delegações persistentes, F0-02/06 completo e validação institucional da matriz continuam pendentes.

F1-04 inicia notificações internas locais derivadas apenas dos eventos de acesso confirmados da conta destinatária. Seguir docs/notificacoes-locais-f1-04.md. A tabela separada de recibos exige instalação explícita por CLI local administradora e schema compatível. Nunca instalar por GET. Marcar como lida não altera permissões, revisão ou auditoria. Usar nonce e verificar evento, titular e revisão na transacção. Sem emails ou canais externos. O roteiro docs/proximas-fases-f1-2026-10-14.md não substitui os pré-requisitos institucionais.

F1-05 tem persistência auditada de unidades fictícias separada do catálogo documental e da autorização. Seguir docs/unidades-locais-f1-05.md. A ferramenta exige ambiente local, ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE booleano true e manage_options. Só cenários fechados LAB-ROOT/LAB-A/LAB-B. Instalação CLI explícita, revisão global e alteração/auditoria na mesma transacção. Não criar pessoas ou privilégios a partir das unidades. O contrato de cadastro/importação ainda não é um importador executável. A pré-validação sintética em docs/validacao-sintetica-f1-05.md exige também ARN_ALLOW_LOCAL_IMPORT_PREVIEW booleano true. Só cenários CSV do servidor, sem upload ou payload livre. Snapshot de unidades apenas leitura. Auditoria e propostas só na resposta em memória, sem eventos na base, logs de payloads, transients ou ficheiros de lote. Não adicionar caminho de aplicação de propostas.

O portal editorial de revisão local usa EditorialPreview e as vistas do tema em portal/. Seguir docs/portal-editorial-local-f1-06.md e DESIGN.md. Dados reais exigem local, ARN_ALLOW_LOCAL_EDITORIAL_PREVIEW booleano true e manage_options antes da fonte, contagens ou detalhe. Manifesto e originais ficam fora do Git e do document root. Nunca usar uploads ou posts públicos para estes anexos. Downloads por ID validam caminho, assinatura, MIME e hash, com 404 indistinto para recusa e ausência. GET usa arn_year/arn_page, preservando os nomes internos do modelo. Acesso técnico de laboratório não equivale a competência editorial institucional. F1-08/09/10/11 e F2 continuam dependentes dos pré-requisitos próprios.

F1-08 tem núcleo Documents\PrivateFiles com binários imutáveis, IDs opacos, esquema explícito e registo/auditoria atómicos por objecto, versão e variante. Seguir docs/ficheiros-privados-locais-f1-08.md. A admissão técnica exige fileinfo e política explícita do servidor. Não equivale a análise antimalware. Não expor originais, miniaturas ou versões por URLs estáticas. O adaptador sintético exige ambiente local e ARN_ALLOW_LOCAL_PRIVATE_FILES booleano true, sem excepção administrativa de negócio ou alterações a concessões. Os anexos reais continuam no circuito administrativo de EditorialPreview. Media nativa, política institucional, reconciliação e homologação IIS/MySQL continuam pendentes.

F1-09 tem catálogo documental local com CPT privado, gestão CMS por competência RH fictícia e projeção apenas da versão publicada corrente em Documentos e Regulamentos. Seguir docs/catalogo-documental-local-f1-09.md. O gestor usa a página Catálogo documental, com âmbito e proprietário fixados no servidor, POST com nonce e revisão concorrente. O endpoint arn_document_file resolve objecto e versão antes de abrir o original privado. O administrador conserva os 22 anexos reais no EditorialPreview e não recebe competência documental por ser administrador. Instalação só por CLI explícita. F1-09 permanece Em curso até validação institucional, antimalware, media nativa e homologação IIS/MySQL.

F1-10 tem CMS local de autoria e submissão fictícias. Seguir docs/noticias-locais-f1-10.md. O CPT privado arn_noticia é só âncora. REST e editor nativo não expõem notícias. A evolução F1-11 abaixo substitui a entrada de instalação antiga e acrescenta a quinta flag. O autor gere rascunhos próprios com POST, nonce, CAS, justificação e ficheiros privados. O editor vê apenas versões efectivamente submetidas na fila autorizada. O administrador técnico não recebe competência. F1-10 permanece Em curso até validação institucional, antimalware, media nativa e homologação IIS/MySQL.

F1-11 tem decisão e publicação locais com cinco fixtures fechadas e esquema 2. Seguir docs/publicacao-editorial-local-f1-11.md e docs/qa-publicacao-editorial-local-f1-11.md. Exige ambiente efectivo local, as quatro flags anteriores e ARN_ALLOW_LOCAL_EDITORIAL_WORKFLOW estritamente true, com esquema compatível. A instalação/migração é explícita por CLI administradora via LocalEditorialInstaller. Nunca migrar por GET, activação ou leitura. DDL pode deixar preparação parcial com marcador 1 e rotas fechadas até retoma explícita. Não alegar rollback de DDL. Decisões têm POST, nonce, motivo, versão e CAS opaco do editor, com revalidação transaccional. A lista, o detalhe arn_news e o início projectam exclusivamente published_version após autorização e audiência da versão aprovada. Não há excepção de autor/editor/administrador na rota publicada. O histórico fica no circuito autorizado do autor/revisor. Recusa, ausência ou método errado no detalhe e nos media devolvem 404 vazio com private/no-store antes do login. Os 22 originais reais continuam apenas na revisão administrativa separada. Não alterar os originais, categorias ou catálogo de regulamentos. A activação normal e a aceitação Playwright pertencem ao controlador após revisão. F1-11 permanece Em curso até validação institucional. A próxima implementação é F1-07, seguida de F1-12/13/14/15, lacunas técnicas, F2, QA global e infraestrutura/release conforme README.

A activação F1-11 neste laboratório já ocorreu após revisão das cinco tarefas. O esquema normal é 2 e as cinco flags estão activadas. Não repetir a captura inicial nem a migração como se ainda estivesse no esquema 1. O circuito Playwright e os limites estão em docs/qa-publicacao-editorial-local-f1-11.md. Os 22 originais continuam exclusivamente na revisão administradora, incluindo Comunicação sindical, conforme confirmação do utilizador. A próxima parcela funcional é F1-07 depois da revisão final e integração F1-11.

F1-07 tem implementação local do directório, perfil próprio e equipa nesta branch. Seguir docs/directorio-perfil-local-f1-07.md e docs/qa-directorio-perfil-local-f1-07.md. O instalador People cria três páginas somente após preflight e exige os quatro gates locais, CLI administradora, autorização/unidades íntegras e LAB-A/B activas. A configuração normal conserva ARN_ALLOW_LOCAL_PEOPLE ausente. O último preflight SQL confirmou zero tabelas People ou novo elenco. Os serviços locais estavam parados na verificação documental final. Iniciar apenas o runtime existente, sem reinicializar dados, e revalidar o baseline antes de escrever. Não activar nem reactivar B antes do baseline e da revisão do controlador. Os ensaios positivos usam só prefixos exclusivos com limpeza dos seus posts, contas, tabelas e ficheiros. REST, editor e block-renderer não apresentam contactos. A revisão transversal, Playwright normal e validação institucional continuam pendentes. A próxima parcela funcional depois desta revisão é F1-12.

Passagem de 15 de outubro de 2026 pedida pelo responsável: preparar ALB-F1-07-QA para Albertina4264 na sua branch `chore/albertina4264-F1-01-fundacao`, actualizada com main e com a branch F1-07. Seguir docs/passagem-albertina-f1-07-2026-10-15.md e o prompt associado. A implementação F1-07 está na PR #25 em rascunho. A activação e a aceitação normal ainda não ocorreram no PC de Atchutchi. Albertina pode preparar e ensaiar apenas o seu laboratório local com fixtures, sem usar segredos ou dados privados de outro PC. Deve entregar uma PR contra a branch F1-07 enquanto a #25 estiver aberta, sem fazer merge, deploy ou iniciar F1-12. Atchutchi recebe a entrega, reproduz e continua. Esta instrução específica de entrega prevalece sobre a autonomia genérica de integração na tarefa da Albertina.

Commits e push ficam na branch da tarefa. Nunca faças push directo em `main` nem uses force push. A sessão de 13 de outubro de 2026 autoriza integração de PRs após revisão do diff e CI verde no SHA exacto. Esta autorização não inclui deploy e não altera a entrega sem merge especificada para ALB-F1-07-QA.

Panthera-Onca .205 está fora de arn.local e deve permanecer assim. Windows Server 2019 Standard, 1809, build 17763, 64 bits. Hyper-V instalado e WSL ausente. Produção nativa em IIS/PHP 8.4 x64 NTS/MySQL 8.4 LTS. Não instalar WSL ou Docker Desktop no servidor. Não depender de GPO, Kerberos, LDAP, conta de computador ou autenticação AD. DNS institucional apenas se aprovado pela DCSI.

Férias ficam apenas especificadas nesta fase: 22 dias úteis por ano civil, repartíveis em pedidos prévios, aprovação final RH e saldo separado entre gozados, futuros aprovados e disponível. Não implementar F2 antes dos pré-requisitos. Validar as pendências de PEN-14 antes de alterar comportamento institucional.
