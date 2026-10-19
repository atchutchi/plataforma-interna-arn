(function (wp) {
  'use strict';

  const el = wp.element.createElement;
  const __ = wp.i18n.__;
  const views = [
    { label: __('Início', 'arn-intranet-core'), value: 'home' },
    { label: __('Notícias', 'arn-intranet-core'), value: 'news' },
    { label: __('Documentos', 'arn-intranet-core'), value: 'documents' },
    { label: __('Regulamentos', 'arn-intranet-core'), value: 'regulations' },
    { label: __('Serviços', 'arn-intranet-core'), value: 'services' },
    { label: __('Pesquisa integrada', 'arn-intranet-core'), value: 'search' },
  ];

  wp.blocks.registerBlockType('arn/portal-view', {
    apiVersion: 3,
    title: __('Vista do portal ARN', 'arn-intranet-core'),
    category: 'widgets',
    icon: 'layout',
    attributes: {
      view: {
        type: 'string',
        default: 'home',
        enum: ['home', 'news', 'documents', 'regulations', 'services', 'search'],
      },
    },
    supports: { html: false },
    edit: function (props) {
      return el(
        wp.components.Placeholder,
        {
          label: __('Vista do portal ARN', 'arn-intranet-core'),
          instructions: __('Usa Ver página para consultar o resultado.', 'arn-intranet-core'),
        },
        el(wp.components.SelectControl, {
          label: __('Secção', 'arn-intranet-core'),
          value: props.attributes.view || 'home',
          options: views,
          onChange: function (view) {
            props.setAttributes({ view: view });
          },
        })
      );
    },
    save: function () {
      return null;
    },
  });
})(window.wp);
