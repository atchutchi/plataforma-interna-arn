# Instruções para agentes

A referência do projeto é o `README.md`. Lê as secções que a tarefa tocar antes de alterar código. Este ficheiro só resume o trabalho corrente.

A stack é WordPress, com regras em `wp-content/plugins/arn-intranet-core` e apresentação em `wp-content/themes/arn-intranet`. Não substituas esta arquitetura. Textos de interface ficam em português de Portugal.

## Comandos

Na raiz, com Node.js 24.21.0:

```bash
npm ci
npm run env:start
npm run env:stop
npm run build
npm run lint
npm test
```

O ambiente de testes é separado e usa `.wp-env.test.json`:

```bash
npm run env:start:tests
npm run composer:install
npm run test:php
npm run lint:php
npm run env:stop:tests
```

`npm run env:check` confirma se o wp-env está em execução e se as portas ficam só em localhost. O `@wordpress/env` 11.17.0 publica o HTTP e pode publicar a MariaDB em todas as interfaces; o check falha nesse caso e isso não conta como ambiente fechado no PC. As duas configurações desligam `testsEnvironment`, porque esta versão ainda cria o ambiente legado quando a opção não é `false`. O ambiente de testes próprio é `.wp-env.test.json`.

PHP, Composer, MariaDB e WordPress vêm dos contentores. Não há `mysqlVersion`: a base local configurada é MariaDB 11.8 e não prova compatibilidade com MySQL 8.4. `composer.lock` só deve ser gerado com `npm run composer:install`, dentro do contentor. Sem Docker, PHPUnit e phpcs podem correr com um PHP 8.4 local e `vendor/` não versionado; isso verifica o código, não o ambiente.

## Código

O plugin usa o autoloader PSR-4 em `src/Autoloader.php`, namespace `Arn\Intranet`. Não acrescentes `require_once` por classe. Regras puras ficam em classes sem WordPress e com testes em `tests/phpunit`; a ligação aos hooks fica em classes separadas, como `Access\PrivatePortal`.

O catálogo `Organization\DocumentaryCatalog` transcreve as unidades da secção 4.1. Não contém pessoas. Mantém DSU, DSGI e DSIC sem designação, as duas DGE com identificadores `DRE-DGE` e `DREC-DGE`, e as relações como `Documental` ou `Pendente` até RH confirmar.

## Limites

Não acedas a Panthera-Onca, ao controlador de `arn.local`, ao DNS, ao Google Workspace nem a produção. Não cries contas a partir da lista da secção 4. As três contas da secção 5.4 não recebem privilégios por email. O acesso fictício `ana.teste` / `fixture-local-ana` só pode existir com `WP_ENVIRONMENT_TYPE=local` e a flag explícita do `.wp-env.json`.

F1-01 continua aberta até à homologação Windows/IIS. Não marques a tarefa como concluída só porque a cópia local abre.

Commits e push ficam na branch da tarefa. Não integres em `main` nem uses force push.
