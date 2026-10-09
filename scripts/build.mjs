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
console.log('Não há pacote de blocos para compilar nesta fundação.');
