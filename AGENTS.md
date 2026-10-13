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

O catálogo `Organization\DocumentaryCatalog` transcreve as unidades da secção 4.1. Não contém pessoas. Mantém DSU, DSGI e DSIC sem designação, as duas DGE com identificadores `DRE-DGE` e `DREC-DGE`, e as relações como `Documental` ou `Pendente` até RH confirmar.

## Limites

Não acedas a Panthera-Onca, ao controlador de `arn.local`, ao DNS ou a produção. O responsável autorizou nesta continuação de 13 de outubro a consulta e preparação de configuração Google para autenticação. Não guardar credenciais, dados de funcionários ou URLs de sessão no Git. Não alterar MFA ou promover acessos institucionais. Não cries contas a partir da lista da secção 4. As três contas da secção 5.4 não recebem privilégios por email. O acesso fictício `ana.teste` / `fixture-local-ana` só é permitido com `WP_ENVIRONMENT_TYPE=local` e a flag explicitamente verdadeira. Uma fixture já guardada também tem de ficar bloqueada quando a condição deixa de se cumprir, preservando contas legítimas e autoria.

O serviço de associação persistente F1-02 não está ligado aos hooks. Não o transformar em login sem cliente OIDC homologado. Usa uma ligação IdentityWpdb dedicada, auditoria na mesma transacção e autorização local explícita. O ensaio `npm run test:identity:integration` cria apenas fixtures e tabelas exclusivas no ambiente de testes. Não o executar com dados institucionais reais.

F1-01 continua aberta até à homologação Windows/IIS. Não marques a tarefa como concluída só porque a cópia local abre.

Commits e push ficam na branch da tarefa. Nunca faças push directo em `main` nem uses force push. A sessão de 13 de outubro de 2026 autoriza integração de PRs após revisão do diff e CI verde no SHA exacto. Esta autorização não inclui deploy.

Panthera-Onca .205 está fora de arn.local e deve permanecer assim. Windows Server 2019 Standard, 1809, build 17763, 64 bits. Hyper-V instalado e WSL ausente. Produção nativa em IIS/PHP 8.4 x64 NTS/MySQL 8.4 LTS. Não instalar WSL ou Docker Desktop no servidor. Não depender de GPO, Kerberos, LDAP, conta de computador ou autenticação AD. DNS institucional apenas se aprovado pela DCSI.

Férias ficam apenas especificadas nesta fase: 22 dias úteis por ano civil, repartíveis em pedidos prévios, aprovação final RH e saldo separado entre gozados, futuros aprovados e disponível. Não implementar F2 antes dos pré-requisitos. Validar as pendências de PEN-14 antes de alterar comportamento institucional.
