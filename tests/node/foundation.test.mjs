import assert from 'node:assert/strict';
import { dirname } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { checkFoundation } from '../../scripts/foundation-check.mjs';

const root = dirname(fileURLToPath(new URL('../../package.json', import.meta.url)));

test('a fundação local cumpre o contrato de configuração e de dados fictícios', () => {
  assert.deepEqual(checkFoundation(root), []);
});
