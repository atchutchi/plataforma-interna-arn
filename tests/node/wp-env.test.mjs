import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { test } from 'node:test';
import { bindingSettings, runWpEnv, wpEnvBin } from '../../scripts/wp-env.mjs';

function configuration(http = 8888, mysqlPort) {
  return {
    testsEnvironment: false,
    env: { development: { port: http, mysqlPort, phpmyadmin: false } },
  };
}

function probeResult(http = 8888, mysql = 0, hostIp = '127.0.0.1') {
  return JSON.stringify({
    services: {
      probe: {
        ports: [
          { target: 80, published: String(http), host_ip: hostIp, protocol: 'tcp' },
          { target: 3306, published: String(mysql), host_ip: hostIp, protocol: 'tcp' },
        ],
      },
    },
  });
}

async function invoke(args, { config = configuration(), env = {}, probe = probeResult(), exitCode = 0 } = {}) {
  const calls = [];
  const loaded = [];
  const errors = [];
  const result = await runWpEnv(args, {
    env,
    load: async (...parameters) => {
      loaded.push(parameters);
      return config;
    },
    run: (command, parameters, options) => {
      const envFile = options.env.COMPOSE_ENV_FILES;
      calls.push({ command, parameters, options, envFile, bindings: readFileSync(envFile, 'utf8') });
      return command === 'docker' ? { status: 0, stdout: probe } : { status: exitCode };
    },
    error: (message) => errors.push(message),
  });
  return { result, calls, loaded, errors };
}

test('o entrypoint público executa a CLI e apresenta os comandos sem Docker', () => {
  const result = spawnSync(process.execPath, [wpEnvBin, '--help'], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, /wp-env start/);
  assert.match(result.stdout, /wp-env status/);
});

test('o arranque prova os bindings antes de executar wp-env e limpa apenas o ficheiro temporário', async () => {
  const observed = await invoke(['start']);
  assert.equal(observed.result, 0);
  assert.equal(observed.calls.length, 2);
  assert.equal(observed.calls[0].command, 'docker');
  assert.equal(observed.calls[0].parameters.at(-2), '--format');
  assert.equal(observed.calls[1].parameters[0], wpEnvBin);
  assert.deepEqual(observed.calls[1].parameters.slice(1), ['start', '--no-auto-port', '--runtime=docker']);
  assert.equal(observed.calls[1].bindings, 'WP_ENV_PORT=127.0.0.1:8888\nWP_ENV_MYSQL_PORT=127.0.0.1:0\n');
  assert.equal(observed.calls[1].options.env.WP_ENV_PORT, undefined);
  assert.equal(existsSync(observed.calls[1].envFile), false);
});

test('o arranque de testes lê a configuração separada e preserva a sua porta', async () => {
  const observed = await invoke(['--tests', 'start'], {
    config: configuration(8890), probe: probeResult(8890),
  });
  assert.equal(observed.result, 0);
  assert.equal(observed.loaded[0][1], join(process.cwd(), '.wp-env.test.json'));
  assert.equal(observed.calls[1].parameters[1], '--config=.wp-env.test.json');
  assert.match(observed.calls[1].bindings, /127\.0\.0\.1:8890/);
});

test('aplica os valores numéricos já resolvidos dos overrides JSON', async () => {
  const observed = await invoke(['start'], {
    config: configuration(8989, 13306), probe: probeResult(8989, 13306),
  });
  assert.equal(observed.result, 0);
  assert.equal(observed.calls[1].bindings, 'WP_ENV_PORT=127.0.0.1:8989\nWP_ENV_MYSQL_PORT=127.0.0.1:13306\n');
});

for (const env of [
  { WP_ENV_PORT: '8989' },
  { WP_ENV_PORT: '127.0.0.1:8989' },
  { WP_ENV_MYSQL_PORT: '' },
  { WP_ENV_TESTS_PORT: '8890' },
  { WP_ENV_PHPMYADMIN_PORT: '8889' },
  { COMPOSE_PROJECT_NAME: 'partilhado' },
]) {
  test(`recusa overrides exportados que anulariam bindings ou isolamento ${Object.keys(env)[0]}`, async () => {
    const observed = await invoke(['start'], { env });
    assert.equal(observed.result, 1);
    assert.equal(observed.calls.length, 0);
  });
}

for (const probe of ['{}', 'não é JSON', probeResult(8888, 0, '0.0.0.0'), probeResult(1234)]) {
  test(`não inicia se a prova Compose falha ou ignora COMPOSE_ENV_FILES: ${probe}`, async () => {
    const observed = await invoke(['start'], { probe });
    assert.equal(observed.result, 1);
    assert.equal(observed.calls.length, 1);
    assert.equal(existsSync(observed.calls[0].envFile), false);
  });
}

for (const command of ['stop', 'status', 'run']) {
  test(`o comando ${command} conserva bindings e a configuração de testes`, async () => {
    const observed = await invoke(['--tests', command, ...(command === 'run' ? ['cli', 'wp', 'theme', 'list'] : [])], {
      config: configuration(8890),
      env: { COMPOSE_ENV_FILES: '/ficheiro-herdado-do-bootstrap', COMPOSE_DISABLE_ENV_FILE: '1' },
    });
    assert.equal(observed.result, 0);
    assert.equal(observed.calls.length, 1);
    assert.equal(observed.calls[0].parameters[1], '--config=.wp-env.test.json');
    assert.match(observed.calls[0].bindings, /127\.0\.0\.1:8890/);
    assert.equal(observed.calls[0].options.env.COMPOSE_DISABLE_ENV_FILE, '0');
  });
}

test('um erro do comando wp-env chega ao chamador sem aparentar sucesso', async () => {
  assert.equal((await invoke(['run', 'cli', 'wp', 'theme', 'activate', 'ausente'], { exitCode: 3 })).result, 3);
});

for (const args of [['start', '--auto-port'], ['start', '--runtime=playground'], ['start', '--config=outro.json'], ['destroy']]) {
  test(`recusa uma invocação fora do contrato de arranque ${args.join(' ')}`, async () => {
    const observed = await invoke(args);
    assert.equal(observed.result, 1);
    assert.equal(observed.calls.length, 0);
  });
}

test('não habilita silenciosamente ambiente legado ou phpMyAdmin por override', () => {
  const config = configuration();
  config.testsEnvironment = true;
  assert.throws(() => bindingSettings(config, {}), /testsEnvironment/);
  config.testsEnvironment = false;
  config.env.development.phpmyadmin = true;
  assert.throws(() => bindingSettings(config, {}), /phpmyadmin/);
});
