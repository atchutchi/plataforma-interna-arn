import { runWpEnv } from './wp-env.mjs';

const tests = process.argv.includes('--tests');
const configArgs = tests ? ['--tests'] : [];
const blogname = tests ? 'Intranet ARN - testes' : 'Intranet ARN';
const description = tests
  ? 'Ambiente de testes, com dados separados do desenvolvimento.'
  : 'Ambiente local de desenvolvimento.';

async function wp(args) {
  const status = await runWpEnv([...configArgs, 'run', 'cli', 'wp', ...args]);

  if (status !== 0) {
    process.exit(status);
  }
}

await wp(['theme', 'activate', 'arn-intranet']);
await wp(['language', 'core', 'install', 'pt_PT', '--activate']);
await wp(['option', 'update', 'blogname', blogname]);
await wp(['option', 'update', 'blogdescription', description]);
await wp(['rewrite', 'structure', '/%postname%/', '--hard']);
