(function (blocks, element) {
  'use strict';
  blocks.registerBlockType('arn/tasks-view', {
    title: 'Vista de tarefas ARN',
    category: 'widgets',
    attributes: { view: { type: 'string', enum: ['own', 'team'] } },
    supports: { html: false, inserter: false, multiple: false },
    edit: function () {
      return element.createElement('p', null, 'Vista privada de tarefas. Os dados são consultados no portal após autorização.');
    },
    save: function () { return null; }
  });
})(window.wp.blocks, window.wp.element);
