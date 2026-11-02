(function () {
  'use strict';
  const el = window.wp.element.createElement;
  const __ = window.wp.i18n.__;
  window.wp.blocks.registerBlockType('patsch9-orderdate/pickup-date', {
    apiVersion: 3, title: __('Abholdatum', 'patsch9-orderdate'), category: 'woocommerce',
    parent: ['woocommerce/checkout-fields-block'], supports: { html: false, multiple: false, reusable: false },
    edit: function () {
      return el('div', window.wp.blockEditor.useBlockProps(),
        el('strong', null, __('Abholdatum', 'patsch9-orderdate')),
        el('p', null, __('Erscheint für Artikel mit Terminauswahl an der Kasse. Wird im Shop automatisch ergänzt, sofern der Block fehlt.', 'patsch9-orderdate')));
    },
    save: function () { return el('div', window.wp.blockEditor.useBlockProps.save()); }
  });
}());
