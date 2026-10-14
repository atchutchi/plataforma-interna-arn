import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { runInNewContext } from 'node:vm';

const root = dirname(dirname(dirname(fileURLToPath(import.meta.url))));
const script = readFileSync(join(root, 'wp-content/plugins/arn-intranet-core/assets/js/editorial-preview.js'), 'utf8');

test('bloco editorial edita a secção sem solicitar dados privados', () => {
  let name;
  let settings;
  const changes = [];
  const wp = {
    blocks: { registerBlockType: (blockName, blockSettings) => {
      name = blockName;
      settings = blockSettings;
    } },
    element: { createElement: (type, props, ...children) => ({ type, props, children }) },
    components: { Placeholder: 'Placeholder', SelectControl: 'SelectControl' },
    i18n: { __: (text) => text },
  };

  runInNewContext(script, { window: { wp } });

  assert.equal(name, 'arn/portal-view');
  assert.equal(settings.apiVersion, 3);
  assert.equal(settings.title, 'Vista do portal ARN');
  assert.equal(settings.save(), null);

  const editor = settings.edit({ attributes: { view: 'documents' }, setAttributes: (value) => changes.push(value) });
  assert.equal(editor.type, 'Placeholder');
  assert.equal(editor.props.instructions, 'Usa Ver página para consultar o resultado.');
  assert.equal(editor.children[0].type, 'SelectControl');
  assert.equal(editor.children[0].props.label, 'Secção');
  assert.equal(editor.children[0].props.value, 'documents');
  assert.deepEqual(Array.from(editor.children[0].props.options, ({ value }) => value), ['home', 'news', 'documents', 'regulations', 'services']);
  editor.children[0].props.onChange('news');
  assert.equal(changes.length, 1);
  assert.equal(changes[0].view, 'news');
});
