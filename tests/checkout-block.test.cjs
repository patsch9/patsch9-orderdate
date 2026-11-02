const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

// Exercise the actual component lifecycle without a DOM or WordPress server.
function harness() {
  const state = [];
  const effects = [];
  const refs = [];
  let cursor, scheduled = [], dirty = false, component, tree;
  let props = { extensions: {}, checkoutExtensionData: { setExtensionData: (_ns, key, value) => { api[key === 'date' ? 'value' : 'time'] = value; } } };
  const api = { errors: {}, value: null, tree: null };
  Object.defineProperty(api, 'error', { get: () => Object.values(api.errors)[0] || null });
  const validation = { setValidationErrors: (errors) => { Object.assign(api.errors, errors); }, clearValidationError: (key) => { delete api.errors[key]; } };
  const element = {
    createElement: (type, attributes, ...children) => ({ type, props: attributes || {}, children }),
    useState: (initial) => {
      const i = cursor++;
      if (!(i in state)) state[i] = initial;
      return [state[i], (value) => { if (state[i] !== value) { state[i] = value; dirty = true; } }];
    },
    useRef: (initial) => { const i = cursor++; return refs[i] || (refs[i] = { current: initial }); },
    useEffect: (callback, dependencies) => {
      const i = cursor++;
      const old = effects[i];
      if (!old || dependencies.some((value, index) => value !== old.dependencies[index])) {
        scheduled.push(() => { if (old && old.cleanup) old.cleanup(); effects[i] = { dependencies, cleanup: callback() }; });
      }
    }
  };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/checkout-block.js'), 'utf8'), {
    window: {
      wp: { element, i18n: { __: (text) => text }, data: { dispatch: () => validation } },
      wc: { wcBlocksData: { VALIDATION_STORE_KEY: 'validation' }, blocksCheckout: { registerCheckoutBlock: (data) => { component = data.component; } } }
    }
  });
  function render() {
    let count = 0;
    do {
      if (++count > 10) throw new Error('Render loop');
      cursor = 0; scheduled = []; dirty = false;
      tree = component(props);
      scheduled.forEach((effect) => effect());
    } while (dirty);
    api.tree = tree;
  }
  api.update = (required, dates, timeRequired = false, windows = {}) => {
    props = { ...props, extensions: { 'patsch9-orderdate': { required, time_required: timeRequired, dates: dates.map((value) => ({ value, label: value, windows: windows[value] || [] })) } } };
    render();
  };
  api.choose = (date) => {
    tree.children.find((child) => child && child.type === 'select').props.onChange({ target: { value: date } });
    render();
  };
  api.chooseTime = (time) => {
    tree.children.find((child) => child && child.props.id === 'p9od-block-time').props.onChange({ target: { value: time } });
    render();
  };
  render();
  return api;
}

test('only active checkout products require an explicit valid selection', () => {
  const ui = harness();
  assert.equal(ui.tree, null);
  assert.equal(ui.error, null);
  ui.update(true, ['2026-11-13', '2026-11-14']);
  assert.ok(ui.error);
  assert.equal(ui.value, '');
  ui.choose('2026-11-13');
  assert.equal(ui.error, null);
  assert.equal(ui.value, '2026-11-13');
  ui.choose('2026-11-15');
  assert.ok(ui.error, 'Forged/outdated selections block client validation too');
});

test('cart changes discard a selection that is no longer available', () => {
  const ui = harness();
  ui.update(true, ['2026-11-13', '2026-11-14']);
  ui.choose('2026-11-13');
  ui.update(true, ['2026-11-14']);
  assert.equal(ui.value, '');
  assert.ok(ui.error);
  ui.choose('2026-11-14');
  ui.update(false, []);
  assert.equal(ui.value, '');
  assert.equal(ui.tree, null);
  assert.equal(ui.error, null);
});

test('no common date is visible and prevents an empty checkout', () => {
  const ui = harness();
  ui.update(true, []);
  assert.equal(ui.tree.props.role, 'alert');
  assert.ok(ui.error);
});

test('time is explicit, validated and discarded after availability changes', () => {
  const ui = harness();
  ui.update(true, ['2026-11-13'], true, { '2026-11-13': ['13:00-14:00', '14:00-15:00'] });
  ui.choose('2026-11-13');
  assert.ok(ui.errors.p9od_time);
  ui.chooseTime('13:00-14:00');
  assert.equal(ui.error, null);
  assert.equal(ui.time, '13:00-14:00');
  ui.chooseTime('12:00-13:00');
  assert.ok(ui.errors.p9od_time, 'Tampered time is not accepted');
  ui.chooseTime('13:00-14:00');
  ui.update(true, ['2026-11-13'], true, { '2026-11-13': ['14:00-15:00'] });
  assert.equal(ui.time, '');
  assert.ok(ui.errors.p9od_time);
  ui.update(false, []);
  assert.equal(ui.error, null);
  assert.equal(ui.time, '');
});
