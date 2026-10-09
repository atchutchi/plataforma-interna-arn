import assert from 'node:assert/strict';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { checkEnvironment } from '../../scripts/check-env.mjs';

const installPath = join(tmpdir(), 'wp-env-arn-fixture');
const containerIds = ['a'.repeat(64), 'b'.repeat(64)];

function fixtures(address = '127.0.0.1') {
  return [
    {
      name: '/arn-wordpress-1',
      service: 'wordpress',
      state: 'running',
      networkMode: 'arn_default',
      ports: { '80/tcp': [{ HostIp: address, HostPort: '8888' }] },
    },
    {
      name: '/arn-mysql-1',
      service: 'mysql',
      state: 'running',
      networkMode: 'arn_default',
      ports: { '3306/tcp': null },
    },
  ];
}

function check({ containers = fixtures(), responses = {}, tests = false } = {}) {
  const calls = [];
  const messages = [];
  const errors = [];
  const defaults = [
    '28.5.1',
    JSON.stringify({ status: 'running', runtime: 'docker', installPath }),
    containerIds.join('\n'),
    containers.map((container) => JSON.stringify(container)).join('\n'),
  ];
  const result = checkEnvironment({
    tests,
    run(command, args) {
      const index = calls.length;
      calls.push({ command, args });
      return responses[index] ?? { status: 0, stdout: defaults[index], stderr: '' };
    },
    log: (message) => messages.push(message),
    error: (message) => errors.push(message),
  });

  return { result, calls, messages, errors };
}

test('aprova HTTP em loopback e MariaDB sem porta publicada', () => {
  const observed = check();
  assert.equal(observed.result, 0);
  assert.equal(observed.errors.length, 0);
  assert.equal(observed.messages.length, 1);
});

for (const address of ['127.0.0.2', '::1', '0:0:0:0:0:0:0:1']) {
  test(`reconhece o endereço de loopback ${address}`, () => {
    assert.equal(check({ containers: fixtures(address) }).result, 0);
  });
}

for (const address of ['0.0.0.0', '::', '192.168.17.30', '10.0.0.5', '2001:db8::10', 'fe80::1', '']) {
  test(`reprova publicação no endereço não loopback ${address || 'não especificado'}`, () => {
    const observed = check({ containers: fixtures(address) });
    assert.equal(observed.result, 1);
    assert.match(observed.errors.join('\n'), /fora de localhost/);
    assert.equal(observed.messages.length, 0);
  });
}

test('reprova uma publicação LAN da base de dados mesmo com HTTP local', () => {
  const containers = fixtures();
  containers[1].ports['3306/tcp'] = [{ HostIp: '192.168.17.30', HostPort: '3306' }];
  const observed = check({ containers });
  assert.equal(observed.result, 1);
  assert.match(observed.errors.join('\n'), /arn-mysql-1.*3306/);
});

test('reprova IPv6 aberto mesmo quando IPv4 está em loopback', () => {
  const containers = fixtures();
  containers[0].ports['80/tcp'].push({ HostIp: '::', HostPort: '8888' });
  assert.equal(check({ containers }).result, 1);
});

test('inspeciona apenas os IDs devolvidos pelo Compose deste ambiente', () => {
  const observed = check();
  assert.deepEqual(observed.calls[2], {
    command: 'docker',
    args: ['compose', '-f', join(installPath, 'docker-compose.yml'), 'ps', '--all', '--quiet'],
  });
  assert.deepEqual(observed.calls[3].args.slice(-2), containerIds);
  assert.equal(observed.calls.some((call) => call.command === 'docker' && call.args[0] === 'ps'), false);
});

test('a verificação de testes seleciona a configuração isolada antes de consultar o estado', () => {
  const observed = check({ tests: true });
  assert.deepEqual(observed.calls[1].args, [
    'scripts/wp-env.mjs', '--tests', 'status', '--json',
  ]);
});

for (const service of ['wordpress', 'mysql']) {
  test(`não aprova status running quando o serviço ${service} está parado`, () => {
    const containers = fixtures();
    containers.find((container) => container.service === service).state = 'exited';
    const observed = check({ containers });
    assert.equal(observed.result, 1);
    assert.match(observed.errors.join('\n'), new RegExp(`serviço ${service}.*não está em execução`));
  });
}

test('reprova rede do anfitrião e ausência de HTTP publicado', () => {
  const containers = fixtures();
  containers[0].networkMode = 'host';
  containers[0].ports = {};
  const observed = check({ containers });
  assert.equal(observed.result, 1);
  assert.match(observed.errors.join('\n'), /rede do anfitrião/);
  assert.match(observed.errors.join('\n'), /porta HTTP/);
});

for (const ports of [undefined, [], { '80/tcp': {} }, { '80/tcp': [{ HostPort: '8888' }] }]) {
  test(`não aprova uma resposta de portas inválida ${JSON.stringify(ports)}`, () => {
    const containers = fixtures();
    containers[0].ports = ports;
    const observed = check({ containers });
    assert.equal(observed.result, 1);
    assert.equal(observed.messages.length, 0);
  });
}

test('falha se Docker estiver indisponível, sem consultar mais comandos', () => {
  const observed = check({ responses: { 0: { status: null, error: new Error('ENOENT') } } });
  assert.equal(observed.result, 1);
  assert.equal(observed.calls.length, 1);
});

for (const [stage, response] of [
  [1, { status: 1, stderr: 'wp-env indisponível' }],
  [1, { status: 0, stdout: 'não é JSON' }],
  [1, { status: 0, stdout: JSON.stringify({ status: 'running', runtime: 'playground', installPath }) }],
  [2, { status: 0, stdout: '' }],
  [3, { status: 1, stderr: 'inspeção falhou' }],
  [3, { status: 0, stdout: 'não é JSON' }],
  [3, { status: 0, stdout: JSON.stringify(fixtures()[0]) }],
]) {
  test(`uma falha ou resposta incompleta na etapa ${stage} nunca aprova o ambiente: ${response.stdout ?? response.stderr}`, () => {
    const observed = check({ responses: { [stage]: response } });
    assert.equal(observed.result, 1);
    assert.equal(observed.messages.length, 0);
  });
}
