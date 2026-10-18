import assert from 'node:assert/strict';
import { test } from 'node:test';
import { prepareTransactionObserver } from '../../scripts/prepare-transaction-observer.mjs';

const containerId = 'abcdef1234567890';

function statusJson() {
  return JSON.stringify({ status: 'running', runtime: 'docker', installPath: '/tmp/wp-env' });
}

function successfulRun({ performance = ['1'], pingFailures = 0 } = {}) {
  const calls = [];
  let performanceRead = 0;
  let pings = 0;

  const run = (command, parameters, options = {}) => {
    calls.push({ command, parameters, options });

    if (command === process.execPath) {
      return { status: 0, stdout: statusJson(), stderr: '' };
    }
    if (command === 'docker' && parameters[0] === 'compose') {
      return { status: 0, stdout: `${containerId}\n`, stderr: '' };
    }
    if (command === 'docker' && parameters[0] === 'restart') {
      return { status: 0, stdout: `${containerId}\n`, stderr: '' };
    }
    if (command === 'docker' && parameters[0] === 'exec') {
      const script = parameters.at(-1);
      if (script.includes('SELECT @@performance_schema')) {
        const value = performance[Math.min(performanceRead, performance.length - 1)];
        performanceRead += 1;
        return { status: 0, stdout: `${value}\n`, stderr: '' };
      }
      if (script.includes('mariadb-admin ping')) {
        pings += 1;
        return pings <= pingFailures
          ? { status: 1, stdout: '', stderr: '' }
          : { status: 0, stdout: 'mysqld is alive\n', stderr: '' };
      }
      return { status: 0, stdout: '', stderr: '' };
    }
    return { status: 1, stdout: '', stderr: 'comando inesperado' };
  };

  return { calls, run };
}

test('prepara instrumentos e privilégios sem reiniciar quando Performance Schema já está activo', async () => {
  const fixture = successfulRun();
  const logs = [];
  const errors = [];
  const result = await prepareTransactionObserver({
    tests: true,
    run: fixture.run,
    wait: async () => {},
    log: (message) => logs.push(message),
    error: (message) => errors.push(message),
  });

  assert.equal(result, 0);
  assert.equal(errors.length, 0);
  assert.equal(fixture.calls.some(({ command, parameters }) => command === 'docker' && parameters[0] === 'restart'), false);
  assert.equal(fixture.calls.some(({ parameters }) => parameters.at(-1)?.includes('GRANT SELECT ON performance_schema.threads')), true);
  assert.match(logs[0], /observação transaccional preparada/i);
});

test('activa Performance Schema, reinicia apenas a base e espera pela disponibilidade', async () => {
  const fixture = successfulRun({ performance: ['0', '1'], pingFailures: 1 });
  let waits = 0;
  const result = await prepareTransactionObserver({
    run: fixture.run,
    wait: async () => { waits += 1; },
    log: () => {},
    error: () => {},
  });

  assert.equal(result, 0);
  assert.equal(fixture.calls.some(({ command, parameters }) => command === 'docker' && parameters[0] === 'restart'), true);
  assert.equal(fixture.calls.some(({ parameters }) => parameters.at(-1)?.includes('performance_schema=ON')), true);
  assert.equal(waits, 1);
});

test('recusa estado wp-env inválido ou contentor MariaDB ambíguo', async () => {
  const errors = [];
  const run = (command, parameters) => {
    if (command === process.execPath) {
      return { status: 0, stdout: JSON.stringify({ status: 'stopped', runtime: 'docker', installPath: '/tmp/wp-env' }), stderr: '' };
    }
    return { status: 0, stdout: `${containerId}\noutrocontentor123\n`, stderr: '' };
  };
  const result = await prepareTransactionObserver({ run, error: (message) => errors.push(message), log: () => {} });
  assert.equal(result, 1);
  assert.match(errors[0], /não está em execução/i);
});

test('não aparenta sucesso quando a preparação SQL falha', async () => {
  const errors = [];
  const fixture = successfulRun();
  const run = (command, parameters, options) => {
    const result = fixture.run(command, parameters, options);
    if (command === 'docker' && parameters[0] === 'exec' && parameters.at(-1)?.includes('GRANT SELECT ON performance_schema.threads')) {
      return { status: 2, stdout: '', stderr: 'falha SQL privada' };
    }
    return result;
  };
  const result = await prepareTransactionObserver({ run, error: (message) => errors.push(message), log: () => {} });
  assert.equal(result, 1);
  assert.match(errors[0], /não foi possível preparar/i);
  assert.doesNotMatch(errors[0], /falha SQL privada/);
});
