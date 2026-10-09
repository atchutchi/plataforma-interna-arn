import { spawnSync } from 'node:child_process';
import { readdirSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { checkFoundation } from './foundation-check.mjs';

const root = dirname(fileURLToPath(new URL('../package.json', import.meta.url)));

function walkScripts(directory, files) {
  for (const entry of readdirSync(directory)) {
    const path = join(directory, entry);
    const info = statSync(path);

    if (info.isDirectory()) {
      walkScripts(path, files);
      continue;
    }

    if (entry.endsWith('.mjs') || entry.endsWith('.js')) {
      files.push(path);
    }
  }
}

const scripts = [];
walkScripts(join(root, 'scripts'), scripts);
scripts.push(join(root, 'wp-content/themes/arn-intranet/assets/js/navigation.js'));

let failed = false;

for (const file of scripts) {
  const result = spawnSync(process.execPath, ['--check', file], { encoding: 'utf8' });

  if (result.status !== 0) {
    process.stderr.write(result.stderr || '');
    failed = true;
  }
}

const errors = checkFoundation(root);

if (errors.length > 0) {
  for (const error of errors) {
    console.error(error);
  }

  failed = true;
}

if (failed) {
  process.exit(1);
}

console.log('Lint da fundação concluído. O phpcs fica em npm run lint:php, dentro do wp-env.');
