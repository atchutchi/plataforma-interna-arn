import { spawnSync } from 'node:child_process';
import { isAbsolute, join } from 'node:path';
import { pathToFileURL } from 'node:url';

const containerIdPattern = /^[a-f0-9]{12,64}$/i;
const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

function execute(run, command, parameters, message) {
  const result = run(command, parameters, { encoding: 'utf8' });

  if (result.error || result.status !== 0) {
    throw new Error(message);
  }

  return String(result.stdout ?? '').trim();
}

function statusDetails(run, tests) {
  const config = tests ? ['--tests'] : [];
  const output = execute(
    run,
    process.execPath,
    ['scripts/wp-env.mjs', ...config, 'status', '--json'],
    'Não foi possível consultar o estado do wp-env.'
  );

  let parsed;
  try {
    parsed = JSON.parse(output);
  } catch {
    throw new Error('A resposta de estado do wp-env não é JSON válido.');
  }

  if (parsed?.status !== 'running') {
    throw new Error('O ambiente selecionado não está em execução.');
  }
  if (parsed.runtime !== 'docker' || typeof parsed.installPath !== 'string' || !isAbsolute(parsed.installPath)) {
    throw new Error('Não foi possível identificar o projeto Docker do ambiente selecionado.');
  }

  return parsed;
}

function databaseContainer(run, installPath) {
  const output = execute(
    run,
    'docker',
    ['compose', '-f', join(installPath, 'docker-compose.yml'), 'ps', '--quiet', 'mysql'],
    'Não foi possível identificar o contentor MariaDB do ambiente selecionado.'
  );
  const ids = output.split(/\s+/).filter(Boolean);

  if (ids.length !== 1 || !containerIdPattern.test(ids[0])) {
    throw new Error('Não foi possível identificar univocamente o contentor MariaDB do ambiente selecionado.');
  }

  return ids[0];
}

function inContainer(run, container, script, message) {
  return execute(run, 'docker', ['exec', container, 'sh', '-lc', script], message);
}

function performanceSchema(run, container) {
  return inContainer(
    run,
    container,
    'set -eu; : "${MYSQL_ROOT_PASSWORD:?}"; mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -Nse \'SELECT @@performance_schema\'',
    'Não foi possível consultar o estado do Performance Schema.'
  );
}

async function enablePerformanceSchema(run, container, wait) {
  inContainer(
    run,
    container,
    "set -eu; printf '[mariadb]\\nperformance_schema=ON\\n' > /etc/mysql/conf.d/arn-transaction-state.cnf",
    'Não foi possível preparar a configuração do Performance Schema.'
  );
  execute(run, 'docker', ['restart', container], 'Não foi possível reiniciar o contentor MariaDB.');

  let available = false;
  for (let attempt = 0; attempt < 60; attempt += 1) {
    const result = run('docker', [
      'exec', container, 'sh', '-lc',
      'set -eu; : "${MYSQL_ROOT_PASSWORD:?}"; mariadb-admin ping -uroot -p"$MYSQL_ROOT_PASSWORD" --silent',
    ], { encoding: 'utf8' });

    if (!result.error && result.status === 0) {
      available = true;
      break;
    }
    await wait(2000);
  }

  if (!available || performanceSchema(run, container) !== '1') {
    throw new Error('O MariaDB não regressou com Performance Schema ativo.');
  }
}

function configureObserver(run, container) {
  const script = `set -eu
: "\${MYSQL_ROOT_PASSWORD:?}"
APP_USER="\${MYSQL_USER:-root}"
APP_PASSWORD="\${MYSQL_PASSWORD:-$MYSQL_ROOT_PASSWORD}"
case "$APP_USER" in
  *[!A-Za-z0-9_]*|'') exit 64 ;;
esac
mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
UPDATE performance_schema.setup_instruments
   SET ENABLED = 'YES', TIMED = 'YES'
 WHERE NAME = 'transaction';
UPDATE performance_schema.setup_consumers
   SET ENABLED = 'YES'
 WHERE NAME = 'events_transactions_current';
SQL
if [ "$APP_USER" != 'root' ]; then
  mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
GRANT SELECT ON performance_schema.threads TO '$APP_USER'@'%';
GRANT SELECT ON performance_schema.setup_instruments TO '$APP_USER'@'%';
GRANT SELECT ON performance_schema.setup_consumers TO '$APP_USER'@'%';
GRANT SELECT ON performance_schema.events_transactions_current TO '$APP_USER'@'%';
FLUSH PRIVILEGES;
SQL
fi
test "$(mariadb -u"$APP_USER" -p"$APP_PASSWORD" -Nse "SELECT COUNT(*) FROM performance_schema.setup_instruments WHERE NAME='transaction' AND ENABLED='YES'")" = '1'
test "$(mariadb -u"$APP_USER" -p"$APP_PASSWORD" -Nse "SELECT COUNT(*) FROM performance_schema.setup_consumers WHERE NAME='events_transactions_current' AND ENABLED='YES'")" = '1'
test "$(mariadb -u"$APP_USER" -p"$APP_PASSWORD" -Nse 'SELECT COUNT(*) FROM performance_schema.threads')" -ge 1
mariadb -u"$APP_USER" -p"$APP_PASSWORD" -Nse 'SELECT COUNT(*) FROM performance_schema.events_transactions_current' >/dev/null`;

  inContainer(
    run,
    container,
    script,
    'Não foi possível preparar a observação transaccional da base de dados.'
  );
}

export async function prepareTransactionObserver({
  tests = false,
  run = spawnSync,
  wait = delay,
  log = console.log,
  error = console.error,
} = {}) {
  try {
    const status = statusDetails(run, tests);
    const container = databaseContainer(run, status.installPath);

    if (performanceSchema(run, container) !== '1') {
      await enablePerformanceSchema(run, container, wait);
    }

    configureObserver(run, container);
    log('Observação transaccional preparada no MariaDB do ambiente selecionado.');
    return 0;
  } catch (problem) {
    error(problem instanceof Error ? problem.message : 'Não foi possível preparar a observação transaccional da base de dados.');
    return 1;
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  process.exitCode = await prepareTransactionObserver({ tests: process.argv.includes('--tests') });
}
