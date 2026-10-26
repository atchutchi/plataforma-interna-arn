(function (blocks, element) {
  'use strict';
  blocks.registerBlockType('arn/tickets-view', {
    title: 'Vista de tickets ARN',
    category: 'widgets',
    attributes: { view: { type: 'string', enum: ['own', 'team'] } },
    supports: { html: false, inserter: false, multiple: false },
    edit: function () {
      return element.createElement('p', null, 'Vista privada de tickets. Os dados são consultados no portal após autorização.');
    },
    save: function () { return null; }
  });
})(window.wp.blocks, window.wp.element);
