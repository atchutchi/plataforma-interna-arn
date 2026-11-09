import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync, existsSync, symlinkSync, linkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname, resolve, basename } from 'node:path';
import { createHash } from 'node:crypto';
import { stageCandidate, sealCandidate, verifyCandidate, assertPortablePath } from '../../scripts/release-candidate.mjs';

function fixture(t) {
  const outer = mkdtempSync(join(tmpdir(), 'arn-candidate-test-'));
  t.after(() => {
    assert.equal(resolve(dirname(outer)), resolve(tmpdir()));
    assert.ok(basename(outer).startsWith('arn-candidate-test-'));
    rmSync(outer, { recursive: true, force: true });
  });
  const root = join(outer, 'repo'), target = join(outer, 'candidate');
  mkdirSync(root);
  const git = (...args) => execFileSync('git', ['-C', root, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
  const write = (base, path, bytes) => { mkdirSync(dirname(join(base, path)), { recursive: true }); writeFileSync(join(base, path), bytes); };
  git('init'); git('config', 'user.name', 'Fixture'); git('config', 'user.email', 'fixture@example.invalid');
  const packages = [{ name: 'fictional/runtime', version: '1.0.0', dist: { reference: 'a'.repeat(40) } }];
  write(root, 'composer.json', JSON.stringify({ require: { 'fictional/runtime': '1.0.0' } }));
  write(root, 'composer.lock', JSON.stringify({ packages, 'packages-dev': [{ name: 'fictional/test', version: '1.0.0' }] }));
  write(root, 'wp-content/plugins/arn-intranet-core/arn-intranet-core.php', '<?php // Synthetic only.\n');
  write(root, 'wp-content/themes/arn-intranet/style.css', '/* Synthetic only. */\n');
  const commit = () => { git('add', '.'); git('commit', '-m', 'Synthetic fixture'); return git('rev-parse', 'HEAD'); };
  const sha = commit();
  const vendor = (overrides = {}) => {
    write(join(target, 'app'), 'vendor/composer/installed.json', JSON.stringify({ dev: false, 'dev-package-names': [], packages, ...overrides }));
    write(join(target, 'app'), 'vendor/autoload.php', '<?php // Synthetic.');
    write(join(target, 'app'), 'vendor/composer/installed.php', '<?php return [];');
    write(join(target, 'app'), 'vendor/fictional/runtime/src.php', '<?php // Synthetic dependency.');
  };
  return { root, target, outer, sha, git, write, commit, vendor, packages };
}

test('candidate stages immutable Git sources only, seals production inventory and verifies', (t) => {
  const f = fixture(t);
  f.write(f.root, 'composer.json', 'dirty working tree');
  f.write(f.root, 'wp-config.php', 'synthetic secret');
  const result = stageCandidate(f.root, f.sha, f.target);
  assert.equal(result.sourceFiles, 4); assert.equal(result.deployReady, false);
  assert.match(readFileSync(join(f.target, 'app/composer.json'), 'utf8'), /fictional\/runtime/);
  assert.equal(existsSync(join(f.target, 'app/wp-config.php')), false);
  assert.throws(() => sealCandidate(f.root, f.sha, f.target));
  f.vendor();
  const sealed = sealCandidate(f.root, f.sha, f.target);
  assert.equal(sealed.packages, 1); assert.equal(sealed.files, 8);
  assert.equal(verifyCandidate(f.root, f.sha, f.target, sealed.manifestSha256).integrity, 'verified');
  assert.throws(() => sealCandidate(f.root, f.sha, f.target));
  assert.throws(() => verifyCandidate(f.root, f.sha, f.target, '0'.repeat(64)));
});

test('candidate refuses existing paths, relative destinations and any checkout', (t) => {
  const f = fixture(t);
  for (const dest of [f.root, f.outer, 'relative', join(f.root, 'ignored-candidate')]) {
    assert.throws(() => stageCandidate(f.root, f.sha, dest));
  }
  assert.equal(existsSync(join(f.root, 'ignored-candidate')), false);
  assert.throws(() => stageCandidate(f.root, 'HEAD', f.target));
  assert.equal(existsSync(f.target), false);
});

test('portable paths refuse traversal, ADS, Windows devices and trailing dots/spaces', () => {
  for (const path of ['/absolute', '../outside', 'a/../b', 'a\\b', 'a:stream', 'CON.php', 'x/LPT1',
    'x/nul.css', 'aux', 'a.', 'a ', 'a//b', 'a/./b', 'x\n.php', 'x/COM¹.txt']) {
    assert.throws(() => assertPortablePath(path), path);
  }
  assert.doesNotThrow(() => assertPortablePath('wp-content/plugins/arn-intranet-core/src/Autoloader.php'));
});

test('case and parent spelling collisions in committed Git paths fail before writes', (t) => {
  const f = fixture(t);
  const blob = f.git('rev-parse', `${f.sha}:composer.json`);
  f.git('config', 'core.ignorecase', 'false');
  f.git('update-index', '--add', '--cacheinfo', `100644,${blob},wp-content/themes/arn-intranet/assets/one.css`);
  f.git('update-index', '--add', '--cacheinfo', `100644,${blob},wp-content/themes/arn-intranet/Assets/two.css`);
  f.git('commit', '-m', 'Synthetic case collision');
  assert.throws(() => stageCandidate(f.root, f.git('rev-parse', 'HEAD'), f.target));
  assert.equal(existsSync(f.target), false);
});

test('production lock mismatches, dev packages and duplicated packages cannot be sealed', (t) => {
  const f = fixture(t); stageCandidate(f.root, f.sha, f.target);
  for (const override of [
    { dev: true }, { 'dev-package-names': ['fictional/test'] }, { packages: [] },
    { packages: [...f.packages, ...f.packages] },
    { packages: [{ ...f.packages[0], version: '2.0.0' }] },
    { packages: [{ ...f.packages[0], dist: { reference: 'b'.repeat(40) } }] },
  ]) {
    f.vendor(override); assert.throws(() => sealCandidate(f.root, f.sha, f.target));
    assert.equal(existsSync(join(f.target, 'candidate.json')), false);
  }
});

test('modified sources and extra root/application files are refused before sealing', (t) => {
  const f = fixture(t); stageCandidate(f.root, f.sha, f.target); f.vendor();
  f.write(f.target, 'app/wp-config.php', 'synthetic secret');
  assert.throws(() => sealCandidate(f.root, f.sha, f.target));
  rmSync(join(f.target, 'app/wp-config.php'));
  f.write(f.target, 'credentials.json', '{}');
  assert.throws(() => sealCandidate(f.root, f.sha, f.target));
  rmSync(join(f.target, 'credentials.json'));
  f.write(f.target, 'app/composer.json', '{}');
  assert.throws(() => sealCandidate(f.root, f.sha, f.target));
});

test('verification refuses missing, changed or extra files and altered manifests', (t) => {
  const f = fixture(t); stageCandidate(f.root, f.sha, f.target); f.vendor();
  const sealed = sealCandidate(f.root, f.sha, f.target);
  const original = readFileSync(join(f.target, 'app/vendor/autoload.php'));
  f.write(f.target, 'app/vendor/autoload.php', '<?php // changed');
  assert.throws(() => verifyCandidate(f.root, f.sha, f.target, sealed.manifestSha256));
  rmSync(join(f.target, 'app/vendor/autoload.php'));
  assert.throws(() => verifyCandidate(f.root, f.sha, f.target, sealed.manifestSha256));
  f.write(f.target, 'app/vendor/autoload.php', original);
  f.write(f.target, 'app/vendor/extra.txt', 'extra');
  assert.throws(() => verifyCandidate(f.root, f.sha, f.target, sealed.manifestSha256));
  rmSync(join(f.target, 'app/vendor/extra.txt'));
  const manifest = JSON.parse(readFileSync(join(f.target, 'candidate.json')));
  manifest.deployReady = true;
  f.write(f.target, 'candidate.json', JSON.stringify(manifest));
  assert.throws(() => verifyCandidate(f.root, f.sha, f.target, sealed.manifestSha256));
  const newHash = createHash('sha256').update(readFileSync(join(f.target, 'candidate.json'))).digest('hex');
  assert.throws(() => verifyCandidate(f.root, f.sha, f.target, newHash));
});

test('junctions or symlinks in destination ancestry and candidate trees are refused', (t) => {
  const f = fixture(t); const link = join(f.outer, 'alias');
  symlinkSync(f.root, link, process.platform === 'win32' ? 'junction' : 'dir');
  assert.throws(() => stageCandidate(f.root, f.sha, join(link, 'candidate')));
  stageCandidate(f.root, f.sha, f.target); f.vendor();
  symlinkSync(f.root, join(f.target, 'app/vendor/linked'), process.platform === 'win32' ? 'junction' : 'dir');
  assert.throws(() => sealCandidate(f.root, f.sha, f.target));
});

test('hard links cannot smuggle externally mutable bytes into the candidate', (t) => {
  const f = fixture(t); stageCandidate(f.root, f.sha, f.target); f.vendor();
  f.write(f.outer, 'external.txt', 'synthetic');
  linkSync(join(f.outer, 'external.txt'), join(f.target, 'app/vendor/linked.txt'));
  assert.throws(() => sealCandidate(f.root, f.sha, f.target));
});
