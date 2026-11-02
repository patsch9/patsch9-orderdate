const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { runInNewContext } = require('node:vm');
test('offline payment only appears when all selected shipping packages use pickup', () => {
  let registration;
  runInNewContext(readFileSync(require('node:path').join(__dirname, '../assets/payment-block.js'), 'utf8'), { window: {
    wc: { wcSettings: { getSetting: () => ({ title: 'Vor Ort zahlen' }) }, wcBlocksRegistry: { registerPaymentMethod: x => { registration = x; } } },
    wp: { element: { createElement: (...args) => args }, htmlEntities: { decodeEntities: x => x } }
  } });
  const can = registration.canMakePayment;
  assert.equal(registration.name, 'p9od_pay_on_pickup');
  assert.equal(can({cartNeedsShipping: true}), false);
  assert.equal(can({cartNeedsShipping: true, selectedShippingMethods: {0: ['local_pickup:3']}}), true);
  assert.equal(can({cartNeedsShipping: true, selectedShippingMethods: {0: ['pickup_location:0']}}), true);
  assert.equal(can({cartNeedsShipping: true, selectedShippingMethods: {0: ['local_pickup:3'], 1: ['flat_rate:1']}}), false);
  assert.equal(can({cartNeedsShipping: true, selectedShippingMethods: {0: []}}), false);
  assert.equal(can({cartNeedsShipping: false}), true);
});
