import { spawnSync } from 'node:child_process';

function run(command, args) {
  return spawnSync(command, args, { encoding: 'utf8' });
}

const docker = run('docker', ['version', '--format', '{{.Server.Version}}']);

if (docker.error || docker.status !== 0) {
  console.error('Docker não está disponível neste computador.');
  console.error('Não foi possível confirmar WordPress, PHP, MariaDB nem a publicação das portas.');
  process.exit(1);
}

const status = spawnSync(
  process.execPath,
  ['node_modules/@wordpress/env/lib/cli.js', 'status', '--json'],
  { encoding: 'utf8' }
);

if (status.status !== 0) {
  console.error(status.stderr || 'wp-env status falhou.');
  process.exit(status.status ?? 1);
}

let parsed;

try {
  parsed = JSON.parse(status.stdout);
} catch (error) {
  console.error('A resposta de wp-env status --json não é JSON.');
  console.error(error instanceof Error ? error.message : error);
  process.exit(1);
}

const running = parsed?.status === 'running' || parsed?.running === true;

if (!running) {
  console.error('O ambiente local não está em execução.');
  process.exit(1);
}

const ports = run('docker', ['ps', '--format', '{{json .}}']);

if (ports.status !== 0) {
  console.error(ports.stderr || 'Não foi possível listar as portas Docker.');
  process.exit(ports.status ?? 1);
}

const published = ports.stdout
  .split(/\r?\n/)
  .filter(Boolean)
  .map((line) => JSON.parse(line));

let exposedBeyondLocalhost = false;

for (const container of published) {
  const mapping = String(container.Ports || '');

  if (mapping.includes('0.0.0.0:') || mapping.includes('[::]:') || mapping.includes(':::')) {
    exposedBeyondLocalhost = true;
    console.error(`Porta publicada fora de localhost: ${container.Names} ${mapping}`);
  }
}

if (exposedBeyondLocalhost) {
  process.exit(1);
}

console.log('Ambiente em execução e sem portas publicadas fora de localhost.');
