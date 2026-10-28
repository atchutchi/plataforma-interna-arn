import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scopes = ['composer.json', 'composer.lock', 'wp-content/plugins/arn-intranet-core', 'wp-content/themes/arn-intranet'];
const forbidden = /(^|\/)(?:\.[^/]+|vendor|node_modules|uploads|private|cache|auth\.json|wp-config\.php)(\/|$)|\.(?:pem|key|sql|sqlite|log)$/i;

/** Inventory immutable Git blobs, never the working tree or a live installation. */
export function inventory(root, commit) {
  if (!/^[a-f0-9]{40}$/.test(commit)) throw new Error('É necessário o SHA completo de um commit.');
  const git = (...args) => execFileSync('git', ['--no-replace-objects', '-C', root, ...args], {
    maxBuffer: 32 * 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'],
  });
  if (git('cat-file', '-t', commit).toString().trim() !== 'commit') throw new Error('A referência não é um commit.');
  const tree = git('rev-parse', `${commit}^{tree}`).toString().trim();
  const entries = git('ls-tree', '-r', '-z', commit, '--', ...scopes).toString('utf8').split('\0').filter(Boolean);
  const files = entries.map((entry) => {
    const match = /^(\d+) (\w+) ([a-f0-9]+)\t([^\r\n\t]+)$/.exec(entry);
    if (!match || !['100644', '100755'].includes(match[1]) || match[2] !== 'blob' || forbidden.test(match[4])) {
      throw new Error('O âmbito contém uma entrada não admitida.');
    }
    const bytes = git('cat-file', 'blob', match[3]);
    return { path: match[4], mode: match[1], bytes: bytes.length, sha256: createHash('sha256').update(bytes).digest('hex') };
  });
  for (const required of ['composer.json', 'composer.lock', 'wp-content/plugins/arn-intranet-core/arn-intranet-core.php', 'wp-content/themes/arn-intranet/style.css']) {
    if (!files.some((file) => file.path === required)) throw new Error('Faltam fontes obrigatórias.');
  }
  return { schema: 1, kind: 'source-inventory', deployReady: false, commit, tree, files };
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    if (process.argv.length !== 3) throw new Error('Indica exactamente um SHA completo.');
    process.stdout.write(`${JSON.stringify(inventory(process.cwd(), process.argv[2]), null, 2)}\n`);
  } catch {
    console.error('Inventário recusado. Verifica o commit, as fontes obrigatórias e as entradas admitidas.');
    process.exitCode = 1;
  }
}
