/* Local dynamic marker. Never render private leave data in the editor. */
(function (blocks, element) {
  blocks.registerBlockType('arn/leave-view', {
    apiVersion: 3, title: 'Férias locais', category: 'widgets',
    attributes: { view: { type: 'string', enum: ['own', 'team'] } },
    supports: { html: false, inserter: false, multiple: false },
    edit: function () { return element.createElement('p', {}, 'Os dados privados de férias são apresentados apenas no portal autorizado.'); },
    save: function () { return null; }
  });
})(window.wp.blocks, window.wp.element);
