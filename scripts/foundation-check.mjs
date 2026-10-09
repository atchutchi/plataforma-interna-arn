import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const requiredFiles = [
  '.nvmrc',
  '.wp-env.json',
  '.wp-env.test.json',
  'package.json',
  'composer.json',
  'AGENTS.md',
  '.github/workflows/verificacao.yml',
  'phpunit.xml.dist',
  'phpcs.xml.dist',
  'wp-content/plugins/arn-intranet-core/arn-intranet-core.php',
  'wp-content/plugins/arn-intranet-core/src/Autoloader.php',
  'wp-content/plugins/arn-intranet-core/src/Access/LocalAccessPolicy.php',
  'wp-content/plugins/arn-intranet-core/src/Access/LocalFixtureUser.php',
  'wp-content/plugins/arn-intranet-core/src/Access/PrivatePortalPolicy.php',
  'wp-content/plugins/arn-intranet-core/src/Access/PrivatePortal.php',
  'wp-content/plugins/arn-intranet-core/src/Organization/Unit.php',
  'wp-content/plugins/arn-intranet-core/src/Organization/UnitHierarchy.php',
  'wp-content/plugins/arn-intranet-core/src/Organization/DocumentaryCatalog.php',
  'tests/phpunit/bootstrap.php',
  'wp-content/themes/arn-intranet/style.css',
  'wp-content/themes/arn-intranet/theme.json',
  'wp-content/themes/arn-intranet/functions.php',
  'wp-content/themes/arn-intranet/templates/index.html',
  'wp-content/themes/arn-intranet/templates/front-page.html',
  'wp-content/themes/arn-intranet/templates/404.html',
  'wp-content/themes/arn-intranet/parts/header.html',
  'wp-content/themes/arn-intranet/parts/footer.html',
  'wp-content/themes/arn-intranet/patterns/aviso-local.php',
  'wp-content/themes/arn-intranet/assets/css/theme.css',
  'wp-content/themes/arn-intranet/assets/js/navigation.js',
];

// Pessoas da secção 4.1. Unidades podem aparecer no catálogo; nomes de pessoas não.
const forbiddenOutsideReadme = [
  'Herry Mané',
  'Atchutchi Ferreira',
  'Clayton Correia',
  'Lyssarides Pereira',
  'Frederik Djata',
  'Edmundo Oliveira',
  'Nivaldo Pereira',
];

function readJson(path) {
  return JSON.parse(readFileSync(path, 'utf8'));
}

function walk(directory, files) {
  for (const entry of readdirSync(directory)) {
    if (entry === '.git' || entry === 'node_modules' || entry === 'vendor') {
      continue;
    }

    const path = join(directory, entry);
    const info = statSync(path);

    if (info.isDirectory()) {
      walk(path, files);
      continue;
    }

    files.push(path);
  }
}

function assertEnvironment(name, config, errors) {
  if (Object.prototype.hasOwnProperty.call(config, 'mysqlVersion')) {
    errors.push(`${name} define mysqlVersion, que não pertence ao esquema do wp-env.`);
  }

  if (config.testsEnvironment !== false) {
    errors.push(`${name} deve desligar testsEnvironment. O @wordpress/env 11.17.0 ainda cria o ambiente legado se a opção não for false.`);
  }

  if (config.phpVersion !== '8.4') {
    errors.push(`${name} deve fixar phpVersion em 8.4.`);
  }

  if (config.mariadbVersion !== '11.8') {
    errors.push(`${name} deve fixar mariadbVersion em 11.8.`);
  }

  if (!String(config.core).includes('wordpress-7.1.3.zip')) {
    errors.push(`${name} deve usar o pacote WordPress 7.1.3.`);
  }

  if (config.phpmyadmin !== false) {
    errors.push(`${name} não deve publicar o phpMyAdmin.`);
  }

  if (Object.prototype.hasOwnProperty.call(config, 'mysqlPort')) {
    errors.push(`${name} não deve publicar a porta da base de dados.`);
  }
}

export function checkFoundation(root) {
  const errors = [];

  for (const file of requiredFiles) {
    if (!existsSync(join(root, file))) {
      errors.push(`Falta o ficheiro ${file}.`);
    }
  }

  const development = readJson(join(root, '.wp-env.json'));
  const tests = readJson(join(root, '.wp-env.test.json'));
  const packageJson = readJson(join(root, 'package.json'));
  const composer = readJson(join(root, 'composer.json'));
  const theme = readJson(join(root, 'wp-content/themes/arn-intranet/theme.json'));

  assertEnvironment('.wp-env.json', development, errors);
  assertEnvironment('.wp-env.test.json', tests, errors);

  if (development.config?.WP_ENVIRONMENT_TYPE !== 'local') {
    errors.push('O desenvolvimento tem de declarar WP_ENVIRONMENT_TYPE local.');
  }

  if (development.config?.ARN_ALLOW_FICTIONAL_LOCAL_ACCESS !== true) {
    errors.push('O acesso fictício do desenvolvimento tem de ser uma flag explícita.');
  }

  if (Object.prototype.hasOwnProperty.call(tests.config ?? {}, 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS')) {
    errors.push('O ambiente de testes não pode ativar o acesso fictício.');
  }

  if (tests.config?.WP_ENVIRONMENT_TYPE === 'local') {
    errors.push('O ambiente de testes não deve identificar-se como local.');
  }

  if (development.port === tests.port) {
    errors.push('Desenvolvimento e testes precisam de portas HTTP diferentes.');
  }

  const scripts = packageJson.scripts ?? {};

  for (const name of ['env:start', 'env:stop', 'build', 'lint', 'test']) {
    if (typeof scripts[name] !== 'string' || scripts[name].length === 0) {
      errors.push(`Falta o script npm ${name}.`);
    }
  }

  if (packageJson.devDependencies?.['@wordpress/env'] !== '11.17.0') {
    errors.push('A dependência @wordpress/env tem de estar fixada em 11.17.0.');
  }

  if (composer.require?.php !== '>=8.4') {
    errors.push('O Composer tem de exigir PHP 8.4 ou superior.');
  }

  if (readFileSync(join(root, '.nvmrc'), 'utf8').trim() !== '24.21.0') {
    errors.push('.nvmrc deve fixar Node.js 24.21.0.');
  }

  const gitignore = readFileSync(join(root, '.gitignore'), 'utf8');

  for (const entry of ['/node_modules/', '/vendor/', '.wp-env.override.json', '/wordpress/']) {
    if (!gitignore.includes(entry)) {
      errors.push(`.gitignore deve excluir ${entry}.`);
    }
  }

  if (theme.version !== 3) {
    errors.push('theme.json tem de usar a versão 3.');
  }

  const style = readFileSync(join(root, 'wp-content/themes/arn-intranet/style.css'), 'utf8');

  if (!style.includes('Theme Name: Intranet ARN')) {
    errors.push('O tema não tem cabeçalho ativável.');
  }

  const plugin = readFileSync(join(root, 'wp-content/plugins/arn-intranet-core/arn-intranet-core.php'), 'utf8');

  if (!plugin.includes('Plugin Name: Núcleo da intranet ARN')) {
    errors.push('O plugin não tem cabeçalho ativável.');
  }

  const fixture = readFileSync(
    join(root, 'wp-content/plugins/arn-intranet-core/src/Access/LocalFixtureUser.php'),
    'utf8'
  );

  if (!fixture.includes("@example.test")) {
    errors.push('A conta fictícia tem de usar um email de exemplo.');
  }

  if (fixture.includes('@arn.gw') || fixture.includes('administrator')) {
    errors.push('A conta fictícia não pode ser institucional nem administradora.');
  }

  const catalog = readFileSync(
    join(root, 'wp-content/plugins/arn-intranet-core/src/Organization/DocumentaryCatalog.php'),
    'utf8'
  );

  if (/ParentStatus::(Confirmada|Raiz)/.test(catalog)) {
    errors.push('O catálogo documental não pode declarar relações confirmadas antes da validação de RH.');
  }

  for (const acronym of ['DSU', 'DSGI', 'DSIC']) {
    const pattern = new RegExp(`new Unit\\(\\s*'${acronym}',\\s*'${acronym}',\\s*null,`);

    if (!pattern.test(catalog)) {
      errors.push(`O catálogo não pode inventar a designação de ${acronym}.`);
    }
  }

  if (!catalog.includes("'DRE-DGE', 'DGE'") || !catalog.includes("'DREC-DGE', 'DGE'")) {
    errors.push('As duas unidades DGE precisam de identificadores distintos e da sigla original.');
  }

  const mainPlugin = plugin;

  if (!mainPlugin.includes('PrivatePortal::register()')) {
    errors.push('O plugin tem de registar o portal privado.');
  }

  const files = [];
  walk(root, files);

  for (const path of files) {
    const rel = relative(root, path).replaceAll('\\', '/');

    if (
      rel === 'README.md'
      || rel === 'scripts/foundation-check.mjs'
      || rel === 'package-lock.json'
      || rel.endsWith('.lock')
    ) {
      continue;
    }

    const source = readFileSync(path, 'utf8');

    for (const name of forbiddenOutsideReadme) {
      if (source.includes(name)) {
        errors.push(`${rel} contém o nome institucional ${name}.`);
      }
    }

    const policyFile = rel.endsWith('src/Access/LocalAccessPolicy.php')
      || rel.endsWith('tests/phpunit/LocalAccessPolicyTest.php')
      || rel.endsWith('tests/phpunit/LocalFixtureAccessTest.php');

    if (!policyFile && source.includes('@arn.gw')) {
      errors.push(`${rel} referencia o domínio institucional fora da política de não promoção.`);
    }
  }

  return errors;
}
