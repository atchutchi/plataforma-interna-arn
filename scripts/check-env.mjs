import { spawnSync } from 'node:child_process';
import { isIP } from 'node:net';
import { isAbsolute, join } from 'node:path';
import { pathToFileURL } from 'node:url';

// Limitar a inspeção aos campos necessários, sem ler variáveis ou credenciais.
const inspectFormat = '{"name":{{json .Name}},"state":{{json .State.Status}},'
  + '"service":{{json (index .Config.Labels "com.docker.compose.service")}},'
  + '"networkMode":{{json .HostConfig.NetworkMode}},'
  + '"ports":{{json .NetworkSettings.Ports}}}';

function isLoopback(address) {
  if (typeof address !== 'string') {
    return false;
  }

  if (isIP(address) === 4) {
    return address.split('.')[0] === '127';
  }

  if (isIP(address) === 6) {
    try {
      return new URL(`http://[${address}]/`).hostname === '[::1]';
    } catch {
      return false;
    }
  }

  return false;
}

function validateContainers(containers) {
  const errors = [];

  for (const service of ['wordpress', 'mysql']) {
    if (!containers.some((container) => container?.service === service && container.state === 'running')) {
      errors.push(`O serviço ${service} do ambiente selecionado não está em execução.`);
    }
  }

  for (const container of containers) {
    if (!container || typeof container !== 'object' || typeof container.state !== 'string') {
      errors.push('A inspeção Docker contém um contentor inválido.');
      continue;
    }

    if (container.state !== 'running') {
      continue;
    }

    const name = container.name || container.service || 'contentor sem nome';

    if (container.networkMode === 'host') {
      errors.push(`${name} usa a rede do anfitrião, sem isolamento das portas.`);
    }

    if (!Object.prototype.hasOwnProperty.call(container, 'ports')
      || (container.ports !== null && (typeof container.ports !== 'object' || Array.isArray(container.ports)))) {
      errors.push(`Não foi possível interpretar as portas de ${name}.`);
      continue;
    }

    const ports = container.ports ?? {};

    if (container.service === 'wordpress' && !ports['80/tcp']?.length) {
      errors.push('O WordPress não tem a porta HTTP publicada no ambiente selecionado.');
    }

    for (const [target, bindings] of Object.entries(ports)) {
      // null representa uma porta declarada na imagem, sem publicação no PC.
      if (bindings === null) {
        continue;
      }

      if (!Array.isArray(bindings)) {
        errors.push(`Não foi possível interpretar a publicação de ${name} ${target}.`);
        continue;
      }

      for (const binding of bindings) {
        if (!binding || typeof binding.HostIp !== 'string'
          || !/^\d+$/.test(String(binding.HostPort))
          || Number(binding.HostPort) < 1 || Number(binding.HostPort) > 65535) {
          errors.push(`Não foi possível interpretar a publicação de ${name} ${target}.`);
        } else if (!isLoopback(binding.HostIp)) {
          errors.push(`Porta publicada fora de localhost: ${name} ${binding.HostIp || '*'}:${binding.HostPort} -> ${target}`);
        }
      }
    }
  }

  return errors;
}

export function checkEnvironment({
  tests = false,
  run = (command, args) => spawnSync(command, args, { encoding: 'utf8' }),
  log = console.log,
  error = console.error,
} = {}) {
  const fail = (message) => {
    error(message);
    return 1;
  };
  const docker = run('docker', ['version', '--format', '{{.Server.Version}}']);

  if (docker.error || docker.status !== 0) {
    return fail('Docker não está disponível. Não foi possível confirmar o ambiente nem a publicação das portas.');
  }

  const configArgs = tests ? ['--tests'] : [];
  const status = run(process.execPath, [
    'scripts/wp-env.mjs', ...configArgs, 'status', '--json',
  ]);

  if (status.error || status.status !== 0) {
    return fail(status.stderr || 'wp-env status falhou.');
  }

  let parsed;

  try {
    parsed = JSON.parse(status.stdout);
  } catch {
    return fail('A resposta de wp-env status --json não é JSON válido.');
  }

  if (parsed?.status !== 'running') {
    return fail('O ambiente selecionado não está em execução.');
  }

  if (parsed.runtime !== 'docker' || typeof parsed.installPath !== 'string' || !isAbsolute(parsed.installPath)) {
    return fail('Não foi possível identificar o projeto Docker do ambiente selecionado.');
  }

  // O wp-env 11.17.0 fornece installPath e usa este ficheiro Compose.
  // Listar apenas os seus IDs evita aprovar ou reprovar contentores de outro projeto.
  const listed = run('docker', [
    'compose', '-f', join(parsed.installPath, 'docker-compose.yml'), 'ps', '--all', '--quiet',
  ]);

  if (listed.error || listed.status !== 0) {
    return fail(listed.stderr || 'Não foi possível listar os contentores do ambiente selecionado.');
  }

  const ids = String(listed.stdout).trim().split(/\s+/).filter(Boolean);

  if (ids.length === 0 || ids.some((id) => !/^[a-f0-9]{12,64}$/i.test(id))) {
    return fail('O Docker não devolveu IDs válidos para os contentores deste ambiente.');
  }

  const inspected = run('docker', ['inspect', '--format', inspectFormat, ...ids]);

  if (inspected.error || inspected.status !== 0) {
    return fail(inspected.stderr || 'Não foi possível inspecionar as portas do ambiente selecionado.');
  }

  let containers;

  try {
    containers = String(inspected.stdout).trim().split(/\r?\n/).filter(Boolean).map((line) => JSON.parse(line));
  } catch {
    return fail('A inspeção das portas Docker não é JSON válido.');
  }

  if (containers.length !== ids.length) {
    return fail('A inspeção Docker não abrangeu todos os contentores do ambiente selecionado.');
  }

  const errors = validateContainers(containers);

  if (errors.length > 0) {
    errors.forEach((message) => error(message));
    return 1;
  }

  log('WordPress e MariaDB em execução. As portas publicadas deste ambiente estão limitadas a loopback.');
  return 0;
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  process.exitCode = checkEnvironment({ tests: process.argv.includes('--tests') });
}
