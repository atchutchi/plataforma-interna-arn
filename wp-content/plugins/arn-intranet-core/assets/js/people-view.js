(function (wp) {
  'use strict';
  const el = wp.element.createElement;
  const __ = wp.i18n.__;
  const options = [
    { label: __('Funcionários', 'arn-intranet-core'), value: 'directory' },
    { label: __('Meu perfil', 'arn-intranet-core'), value: 'profile' },
    { label: __('A minha equipa', 'arn-intranet-core'), value: 'team' },
  ];
  wp.blocks.registerBlockType('arn/people-view', {
    apiVersion: 3,
    title: __('Vista de pessoas ARN', 'arn-intranet-core'),
    category: 'widgets',
    icon: 'groups',
    attributes: { view: { type: 'string', default: 'directory', enum: ['directory', 'profile', 'team'] } },
    supports: { html: false },
    edit: function (props) {
      return el(wp.components.Placeholder, {
        label: __('Vista de pessoas ARN', 'arn-intranet-core'),
        instructions: __('Consulta o resultado na página publicada.', 'arn-intranet-core'),
      }, el(wp.components.SelectControl, {
        label: __('Secção', 'arn-intranet-core'),
        value: props.attributes.view || 'directory',
        options: options,
        onChange: function (view) { props.setAttributes({ view: view }); },
      }));
    },
    save: function () { return null; },
  });
})(window.wp);
