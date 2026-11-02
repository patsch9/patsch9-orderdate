// Run against a disposable wp-env instance, after browser-fixtures.php.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const fixtures = JSON.parse(fs.readFileSync(process.argv[2], 'utf8').match(/P9OD_FIXTURES:(\{[^\n]+\})/)[1]);
const base = process.env.P9OD_TEST_URL || 'http://localhost:8888';

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  try {
    await page.goto(`${base}/?p=${fixtures.product}`);
    const input = page.locator('input#p9od_date');
    await input.waitFor();
    await input.fill(fixtures.date);
    await input.dispatchEvent('change');
    await page.locator('#p9od_time').selectOption('13:00-15:00');
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('button.single_add_to_cart_button').click()]);
    assert.equal(await page.locator('.woocommerce-error').count(), 0);
    // Standard WooCommerce POST product form also adds checkout-mode products.
    await page.goto(`${base}/?p=${fixtures.checkout}`);
    await page.locator('button.single_add_to_cart_button').click();
    await page.waitForLoadState('networkidle');
    await page.goto(`${base}/?p=${fixtures.checkout_other}`);
    await page.locator('button.single_add_to_cart_button').click();
    await page.waitForLoadState('networkidle');
    await page.goto(`${base}/?page_id=${fixtures.checkout_page}`);
    const blockDate = page.locator('#p9od-block-date');
    await blockDate.waitFor();
    assert.equal(await blockDate.count(), 1, 'Injected block mounts exactly once');
    assert.equal(await page.locator('#p9od-block-date-list').count(), 0, 'Checkout Block exposes only one date control');
    await blockDate.fill(fixtures.date);
    await blockDate.dispatchEvent('change');
    assert.equal(await blockDate.inputValue(), fixtures.date);
    const apiDate = await page.evaluate(() => window.wp.data.select('wc/store/checkout').getExtensionData()['patsch9-orderdate'].date);
    assert.equal(apiDate, fixtures.date, 'Selection reaches the actual WooCommerce checkout store');
    const timeOptions = await page.locator('#p9od-block-time option').evaluateAll((nodes) => nodes.map((node) => node.value));
    assert.deepEqual(timeOptions, ['', '14:00-15:00'], 'Only the common overlapping time window is offered');
    await page.locator('#p9od-block-time').selectOption('14:00-15:00');
    const apiTime = await page.evaluate(() => window.wp.data.select('wc/store/checkout').getExtensionData()['patsch9-orderdate'].time);
    assert.equal(apiTime, '14:00-15:00', 'Window reaches the actual WooCommerce checkout store');
    const fieldBeforeButton = await blockDate.evaluate((field) => {
      const button = document.querySelector('.wc-block-components-checkout-place-order-button');
      return button && !!(field.compareDocumentPosition(button) & Node.DOCUMENT_POSITION_FOLLOWING);
    });
    assert.ok(fieldBeforeButton, 'Date selector is before the place-order button');
    await page.goto(`${base}/?page_id=${fixtures.classic_page}`);
    await page.locator('input#p9od_checkout_date').waitFor();
    await page.locator('input#p9od_checkout_date').fill(fixtures.date);
    await page.locator('input#p9od_checkout_date').dispatchEvent('change');
    assert.equal(await page.locator('select[name=p9od_checkout_date]').inputValue(), fixtures.date, 'Classic calendar updates submitted select value');
    await page.locator('#p9od_checkout_time').selectOption('14:00-15:00');
    assert.equal(await page.locator('#p9od_checkout_time').inputValue(), '14:00-15:00');
    assert.deepEqual(errors, [], 'No uncaught frontend exceptions');
    console.log('Product, classic checkout and Checkout Block browser assertions passed.');
  } catch (error) {
    console.error('Browser page:', page.url());
    console.error((await page.locator('body').innerText()).slice(-5000));
    console.error('Frontend errors:', errors);
    await page.screenshot({ path: '/tmp/p9od-checkout-failure.png', fullPage: true });
    throw error;
  } finally {
    await browser.close();
  }
})().catch((error) => { console.error(error); process.exitCode = 1; });
