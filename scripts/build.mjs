import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { checkFoundation } from './foundation-check.mjs';

const root = dirname(fileURLToPath(new URL('../package.json', import.meta.url)));
const errors = checkFoundation(root);

if (errors.length > 0) {
  for (const error of errors) {
    console.error(error);
  }

  process.exit(1);
}

console.log('Construção verificada: tema, plugin e configuração local estão completos.');
await import('./build-panel.mjs');
console.log('Painel institucional compilado localmente, sem CDN.');
