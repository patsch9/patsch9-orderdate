(function () {
  'use strict';
  var settings = window.wc.wcSettings.getSetting('p9od_pay_on_pickup_data', {});
  var el = window.wp.element.createElement;
  var decode = window.wp.htmlEntities.decodeEntities;
  var content = el('span', null, decode(settings.description || ''));
  window.wc.wcBlocksRegistry.registerPaymentMethod({
    name: 'p9od_pay_on_pickup',
    label: decode(settings.title || 'Vor Ort zahlen'),
    ariaLabel: decode(settings.title || 'Vor Ort zahlen'),
    content: content,
    edit: content,
    canMakePayment: function (args) {
      if (!args.cartNeedsShipping) return true; // Server validates dated, non-shipping carts.
      var packages = Object.values(args.selectedShippingMethods || {});
      return packages.length > 0 && packages.every(function (methods) {
        var selected = Array.isArray(methods) ? methods : [methods];
        return selected.length > 0 && selected.every(function (method) {
          return ['local_pickup', 'pickup_location'].includes(String(method).split(':')[0]);
        });
      });
    },
    supports: { features: settings.supports || ['products'] }
  });
}());
