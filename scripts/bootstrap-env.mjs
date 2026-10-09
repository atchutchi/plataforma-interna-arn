import { spawnSync } from 'node:child_process';

const tests = process.argv.includes('--tests');
const configArgs = tests ? ['--config=.wp-env.test.json'] : [];
const blogname = tests ? 'Intranet ARN - testes' : 'Intranet ARN';
const description = tests
  ? 'Ambiente de testes, com dados separados do desenvolvimento.'
  : 'Ambiente local de desenvolvimento.';

function wp(args) {
  const result = spawnSync(
    process.execPath,
    [
      'node_modules/@wordpress/env/lib/cli.js',
      ...configArgs,
      'run',
      'cli',
      'wp',
      ...args,
    ],
    { stdio: 'inherit' }
  );

  if (result.status !== 0) {
    process.exit(result.status ?? 1);
  }
}

wp(['theme', 'activate', 'arn-intranet']);
wp(['language', 'core', 'install', 'pt_PT', '--activate']);
wp(['option', 'update', 'blogname', blogname]);
wp(['option', 'update', 'blogdescription', description]);
wp(['rewrite', 'structure', '/%postname%/', '--hard']);
