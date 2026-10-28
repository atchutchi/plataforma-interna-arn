import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname, resolve, basename } from 'node:path';
import { inventory } from '../../scripts/release-inventory.mjs';

function fixture(t) {
  const root = mkdtempSync(join(tmpdir(), 'arn-inventory-test-'));
  t.after(() => {
    assert.equal(resolve(dirname(root)), resolve(tmpdir()));
    assert.ok(basename(root).startsWith('arn-inventory-test-'));
    rmSync(root, { recursive: true, force: true });
  });
  const git = (...args) => execFileSync('git', ['-C', root, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
  const write = (path, text) => { mkdirSync(dirname(join(root, path)), { recursive: true }); writeFileSync(join(root, path), text); };
  git('init'); git('config', 'user.name', 'Fixture'); git('config', 'user.email', 'fixture@example.invalid');
  for (const path of ['composer.json', 'composer.lock', 'wp-content/plugins/arn-intranet-core/arn-intranet-core.php', 'wp-content/themes/arn-intranet/style.css']) write(path, 'fictional\n');
  const commit = () => { git('add', '.'); git('commit', '-m', 'Fixture'); return git('rev-parse', 'HEAD'); };
  return { root, git, write, commit };
}

test('inventory uses committed bytes, with deterministic hashes and no deployment claim', (t) => {
  const f = fixture(t); const sha = f.commit(); const first = inventory(f.root, sha);
  f.write('composer.json', 'modified locally'); f.write('untracked-secret.txt', 'fictional secret');
  assert.deepEqual(inventory(f.root, sha), first);
  assert.equal(first.files.length, 4); assert.equal(first.deployReady, false);
  assert.equal(first.files[0].sha256.length, 64); assert.equal(first.files[0].bytes, 10);
});

test('inventory excludes private data, vendor and WordPress outside source scope', (t) => {
  const f = fixture(t);
  for (const path of ['.env', 'vendor/autoload.php', 'wp-config.php', 'wp-content/uploads/private.txt']) f.write(path, 'fictional');
  assert.equal(inventory(f.root, f.commit()).files.length, 4);
});

test('inventory rejects ambiguous or nonexistent refs', (t) => {
  const f = fixture(t); const sha = f.commit();
  for (const ref of ['HEAD', sha.slice(0, 7), '0'.repeat(40), '--help']) assert.throws(() => inventory(f.root, ref));
  const blob = f.git('rev-parse', `${sha}:composer.json`); assert.throws(() => inventory(f.root, blob));
});

test('inventory refuses private entries within the application source', (t) => {
  const f = fixture(t); f.write('wp-content/plugins/arn-intranet-core/.env', 'fictional');
  assert.throws(() => inventory(f.root, f.commit()), /não admitida/);
});

test('inventory refuses symlinks without following their targets', (t) => {
  const f = fixture(t); const sha = f.commit(); const blob = f.git('rev-parse', `${sha}:composer.json`);
  f.git('update-index', '--add', '--cacheinfo', `120000,${blob},wp-content/themes/arn-intranet/link`);
  f.git('commit', '-m', 'Synthetic symlink');
  assert.throws(() => inventory(f.root, f.git('rev-parse', 'HEAD')), /não admitida/);
});

test('inventory refuses an incomplete source tree', (t) => {
  const f = fixture(t); f.commit(); f.git('rm', 'composer.lock'); f.git('commit', '-m', 'Incomplete');
  assert.throws(() => inventory(f.root, f.git('rev-parse', 'HEAD')), /obrigatórias/);
});
