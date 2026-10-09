import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { pathToFileURL } from 'node:url';

const require = createRequire(import.meta.url);
const { loadConfig } = require('@wordpress/env/lib/config');
export const wpEnvBin = join(dirname(require.resolve('@wordpress/env/package.json')), 'bin', 'wp-env');
const portVariables = [
  'WP_ENV_PORT', 'WP_ENV_MYSQL_PORT', 'WP_ENV_TESTS_PORT', 'WP_ENV_TESTS_MYSQL_PORT',
  'WP_ENV_PHPMYADMIN_PORT', 'WP_ENV_TESTS_PHPMYADMIN_PORT',
];

export function bindingSettings(config, env) {
  for (const name of [...portVariables, 'COMPOSE_PROJECT_NAME']) {
    if (Object.prototype.hasOwnProperty.call(env, name)) {
      throw new Error(`Remove ${name} desta sessão. Configura as portas no ficheiro .wp-env.override.json ou .wp-env.test.override.json correspondente.`);
    }
  }

  const development = config.env?.development;

  if (config.testsEnvironment !== false || development?.phpmyadmin !== false) {
    throw new Error('O arranque local exige testsEnvironment=false e phpmyadmin=false, incluindo os overrides.');
  }

  const http = development.port;
  const mysql = development.mysqlPort ?? 0;

  if (!Number.isInteger(http) || http < 1 || http > 65535
    || !Number.isInteger(mysql) || mysql < 0 || mysql > 65535) {
    throw new Error('A configuração resolvida tem portas HTTP ou MariaDB inválidas.');
  }

  return {
    http,
    mysql,
    contents: `WP_ENV_PORT=127.0.0.1:${http}\nWP_ENV_MYSQL_PORT=127.0.0.1:${mysql}\n`,
  };
}

function confirmsBindings(stdout, settings) {
  try {
    const ports = JSON.parse(stdout).services?.probe?.ports;
    return Array.isArray(ports) && ports.length === 2
      && [[80, settings.http], [3306, settings.mysql]].every(([target, published]) => ports.some(
        (port) => port.target === target && port.host_ip === '127.0.0.1'
          && String(port.published) === String(published) && port.protocol === 'tcp'
      ));
  } catch {
    return false;
  }
}

export async function runWpEnv(args, {
  cwd = process.cwd(),
  env = process.env,
  load = loadConfig,
  run = spawnSync,
  error = console.error,
} = {}) {
  let temporary;

  try {
    const tests = args[0] === '--tests';
    const commandArgs = tests ? args.slice(1) : [...args];
    const command = commandArgs[0];

    if (!['start', 'stop', 'status', 'run'].includes(command)
      || commandArgs.some((arg) => /^--(?:config|auto-port|runtime)(?:=|$)/.test(arg))) {
      throw new Error('Usa start, stop, status ou run, com --tests antes do comando para o ambiente separado. Escolhe portas no override JSON, sem auto-port nem outro runtime.');
    }

    const customConfig = tests ? join(cwd, '.wp-env.test.json') : null;
    const config = await load(cwd, customConfig);
    const settings = bindingSettings(config, env);
    temporary = mkdtempSync(join(tmpdir(), 'arn-compose-'));
    const envFile = join(temporary, 'bindings.env');

    if (envFile.includes(',')) {
      throw new Error('O diretório temporário contém uma vírgula, incompatível com COMPOSE_ENV_FILES. Escolhe outro TMPDIR.');
    }

    writeFileSync(envFile, settings.contents, { mode: 0o600 });
    const childEnv = { ...env, COMPOSE_ENV_FILES: envFile, COMPOSE_DISABLE_ENV_FILE: '0' };

    if (command === 'start') {
      const probeFile = join(temporary, 'compose.yaml');
      writeFileSync(probeFile, [
        'services:',
        '  probe:',
        '    image: scratch',
        '    ports:',
        '      - "${WP_ENV_PORT}:80"',
        '      - "${WP_ENV_MYSQL_PORT}:3306"',
        '',
      ].join('\n'));
      // config interpreta o modelo sem contactar o daemon nem criar contentores.
      // A prova de capacidade evita depender de uma versão Compose presumida.
      const probe = run('docker', ['compose', '-f', probeFile, 'config', '--format', 'json'], {
        cwd, env: childEnv, encoding: 'utf8',
      });

      if (probe.error || probe.status !== 0 || !confirmsBindings(probe.stdout, settings)) {
        throw new Error('O Compose instalado não confirmou a publicação apenas em 127.0.0.1. Atualiza ou verifica o Docker Desktop antes de iniciar o ambiente. Nenhum arranque foi executado.');
      }

      commandArgs.push('--no-auto-port', '--runtime=docker');
    }

    const configArgs = tests ? ['--config=.wp-env.test.json'] : [];
    const result = run(process.execPath, [wpEnvBin, ...configArgs, ...commandArgs], {
      cwd, env: childEnv, stdio: 'inherit',
    });

    if (result.error) {
      throw new Error(`Não foi possível executar wp-env: ${result.error.message}`);
    }

    return result.status ?? 1;
  } catch (problem) {
    error(problem instanceof Error ? problem.message : String(problem));
    return 1;
  } finally {
    if (temporary) {
      rmSync(temporary, { recursive: true, force: true });
    }
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  process.exitCode = await runWpEnv(process.argv.slice(2));
}
