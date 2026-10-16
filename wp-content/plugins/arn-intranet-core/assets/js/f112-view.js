/* Native page marker only. Editorial data is never fetched in the editor. */
((blocks, element) => {
  blocks.registerBlockType('arn/f112-view', {
    apiVersion: 3,
    title: 'Vista privada de comunicados e calendário',
    category: 'widgets',
    attributes: { module: { type: 'string', enum: ['communications', 'calendar'] } },
    supports: { html: false, inserter: false, multiple: false },
    edit: () => element.createElement('p', {}, 'Vista privada. Gere os conteúdos nas páginas Comunicados e Calendário do painel.'),
    save: () => null,
  });
})(window.wp.blocks, window.wp.element);
