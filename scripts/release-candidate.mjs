import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, lstatSync, mkdirSync, readFileSync, readdirSync, realpathSync, writeFileSync } from 'node:fs';
import { dirname, isAbsolute, join, parse, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { isDeepStrictEqual } from 'node:util';
import { inventory } from './release-inventory.mjs';

const hash = (bytes) => createHash('sha256').update(bytes).digest('hex');
const fail = () => { throw new Error('Candidato recusado.'); };
const blockers = [
  'institutional-adapters-and-migrations', 'persistent-media-operations',
  'google-calendar', 'server-https-identity', 'restore-and-rollback', 'pilot-acceptance',
];

/** Portable paths must have identical meaning on Windows and POSIX. */
export function assertPortablePath(path) {
  if (typeof path !== 'string' || path.length > 220 || path.split('/').some((part) =>
    !part || part === '.' || part === '..' || /[<>:"\\|?*\x00-\x1f\x7f]/.test(part) || /[. ]$/.test(part) ||
    /^(con|prn|aux|nul|com[0-9¹²³]|lpt[0-9¹²³])(?:\.|$)/i.test(part))) fail();
}

function assertParents(path) {
  for (let cursor = resolve(path); ; cursor = dirname(cursor)) {
    const info = lstatSync(cursor);
    if (!info.isDirectory() || info.isSymbolicLink()) fail();
    if (cursor === parse(cursor).root) break;
  }
}

function destinationPath(destination, fresh = false) {
  if (!isAbsolute(destination)) fail();
  const target = resolve(destination);
  assertParents(dirname(target));
  // A private build must not sit in this or any other checkout, even an ignored directory.
  for (let cursor = dirname(target); ; cursor = dirname(cursor)) {
    if (existsSync(join(cursor, '.git'))) fail();
    if (cursor === parse(cursor).root) break;
  }
  if (fresh) {
    if (existsSync(target)) fail();
  } else {
    assertParents(target);
    if (existsSync(join(target, '.git'))) fail();
  }
  return target;
}

function bytesAt(path, max = 32 * 1024 * 1024) {
  const info = lstatSync(path);
  if (!info.isFile() || info.isSymbolicLink() || info.nlink !== 1 || info.size > max) fail();
  return readFileSync(path);
}

function jsonAt(path) { return JSON.parse(bytesAt(path, 8 * 1024 * 1024).toString('utf8')); }
function writeJson(path, value) { writeFileSync(path, `${JSON.stringify(value, null, 2)}\n`, { flag: 'wx' }); }

function portableSet(paths) {
  const known = new Set();
  for (const path of paths) {
    assertPortablePath(path);
    const key = path.toLowerCase();
    if (known.has(key)) fail();
    known.add(key);
  }
  // Also refuse file/directory aliases, including differently cased parent directories.
  const spelling = new Map();
  for (const path of paths) {
    const parts = path.split('/');
    for (let n = 1; n <= parts.length; n++) {
      const prefix = parts.slice(0, n).join('/');
      const key = prefix.toLowerCase();
      if (spelling.has(key) && spelling.get(key) !== prefix) fail();
      if (n < parts.length && known.has(key)) fail();
      spelling.set(key, prefix);
    }
  }
}

function treeFiles(root) {
  assertParents(root);
  const files = [];
  let entries = 0;
  function walk(dir, prefix = '', depth = 0) {
    if (depth > 30) fail();
    for (const name of readdirSync(dir).sort()) {
      if (++entries > 20000) fail();
      const path = prefix ? `${prefix}/${name}` : name;
      assertPortablePath(path);
      const full = join(dir, name), info = lstatSync(full);
      if (info.isSymbolicLink()) fail();
      if (info.isDirectory()) walk(full, path, depth + 1);
      else {
        const bytes = bytesAt(full);
        files.push({ path, bytes: bytes.length, sha256: hash(bytes) });
      }
    }
  }
  walk(root);
  portableSet(files.map((file) => file.path));
  return files.sort((a, b) => a.path < b.path ? -1 : a.path > b.path ? 1 : 0);
}

function sourceState(root, commit, destination) {
  const source = inventory(root, commit);
  portableSet(source.files.map((file) => file.path));
  if (!isDeepStrictEqual(jsonAt(join(destination, 'source-inventory.json')), source)) fail();
  const files = treeFiles(join(destination, 'app'));
  const expected = source.files.map(({ path, bytes, sha256 }) => ({ path, bytes, sha256 }));
  if (!isDeepStrictEqual(files.filter((file) => !file.path.startsWith('vendor/')), expected)) fail();
  return { source, files };
}

function productionDependencies(app) {
  const lock = jsonAt(join(app, 'composer.lock'));
  const installed = jsonAt(join(app, 'vendor/composer/installed.json'));
  if (!Array.isArray(lock.packages) || !Array.isArray(installed.packages) || installed.dev !== false ||
    !Array.isArray(installed['dev-package-names']) || installed['dev-package-names'].length) fail();
  const normalize = (packages) => packages.map((pkg) => {
    if (typeof pkg.name !== 'string' || typeof pkg.version !== 'string') fail();
    return { name: pkg.name, version: pkg.version, source: pkg.source?.reference ?? null, dist: pkg.dist?.reference ?? null };
  }).sort((a, b) => a.name < b.name ? -1 : a.name > b.name ? 1 : 0);
  const expected = normalize(lock.packages), actual = normalize(installed.packages);
  if (new Set(expected.map((pkg) => pkg.name)).size !== expected.length || !isDeepStrictEqual(actual, expected)) fail();
  bytesAt(join(app, 'vendor/autoload.php'));
  bytesAt(join(app, 'vendor/composer/installed.php'));
  return actual;
}

function assertEnvelope(destination, sealed) {
  const expected = ['app', 'source-inventory.json', ...(sealed ? ['candidate.json'] : [])].sort();
  if (!isDeepStrictEqual(readdirSync(destination).sort(), expected)) fail();
}

/** Stage only committed application sources. Never copy the working tree or local data. */
export function stageCandidate(root, commit, destination) {
  const source = inventory(root, commit);
  portableSet(source.files.map((file) => file.path));
  const target = destinationPath(destination, true);
  mkdirSync(target);
  mkdirSync(join(target, 'app'));
  for (const file of source.files) {
    const bytes = execFileSync('git', ['--no-replace-objects', '-C', root, 'show', `${commit}:${file.path}`], {
      maxBuffer: 32 * 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'],
    });
    if (bytes.length !== file.bytes || hash(bytes) !== file.sha256) fail();
    const path = join(target, 'app', file.path);
    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, bytes, { flag: 'wx', mode: file.mode === '100755' ? 0o755 : 0o644 });
  }
  writeJson(join(target, 'source-inventory.json'), source);
  return { commit, sourceFiles: source.files.length, deployReady: false };
}

/** Seal after an isolated composer install --no-dev. This is integrity, not deployment approval. */
export function sealCandidate(root, commit, destination) {
  const target = destinationPath(destination);
  assertEnvelope(target, false);
  const { source, files } = sourceState(root, commit, target);
  const packages = productionDependencies(join(target, 'app'));
  const candidate = {
    schema: 1, kind: 'preparation-candidate', deployReady: false, commit, tree: source.tree,
    blockers, packages, files,
  };
  writeJson(join(target, 'candidate.json'), candidate);
  return { commit, files: files.length, packages: packages.length, deployReady: false,
    manifestSha256: hash(bytesAt(join(target, 'candidate.json'))) };
}

/** The expected manifest hash must come from a separately retained trusted receipt. */
export function verifyCandidate(root, commit, destination, manifestSha256) {
  if (!/^[a-f0-9]{64}$/.test(manifestSha256)) fail();
  const target = destinationPath(destination);
  assertEnvelope(target, true);
  const manifest = bytesAt(join(target, 'candidate.json'), 8 * 1024 * 1024);
  if (hash(manifest) !== manifestSha256) fail();
  const actual = JSON.parse(manifest.toString('utf8'));
  const { source, files } = sourceState(root, commit, target);
  const expected = { schema: 1, kind: 'preparation-candidate', deployReady: false, commit,
    tree: source.tree, blockers, packages: productionDependencies(join(target, 'app')), files };
  if (!isDeepStrictEqual(actual, expected)) fail();
  return { commit, files: files.length, packages: expected.packages.length, integrity: 'verified', deployReady: false };
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    const [action, commit, destination, digest] = process.argv.slice(2);
    const methods = { stage: stageCandidate, seal: sealCandidate, verify: verifyCandidate };
    if (!Object.hasOwn(methods, action) || process.argv.length !== (action === 'verify' ? 6 : 5)) fail();
    const result = methods[action](realpathSync(process.cwd()), commit, destination, digest);
    process.stdout.write(`${JSON.stringify(result)}\n`);
  } catch {
    console.error('Candidato recusado. Confere SHA, destino privado novo, fontes e dependências de produção.');
    process.exitCode = 1;
  }
}
