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
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('button.single_add_to_cart_button').click()]);
    assert.equal(await page.locator('.woocommerce-error').count(), 0);
    // Standard WooCommerce POST product form also adds checkout-mode products.
    await page.goto(`${base}/?p=${fixtures.checkout}`);
    await page.locator('button.single_add_to_cart_button').click();
    await page.waitForLoadState('networkidle');
    await page.goto(`${base}/?page_id=${fixtures.checkout_page}`);
    await page.locator('#p9od-block-date').waitFor();
    assert.equal(await page.locator('#p9od-block-date').count(), 1, 'Injected block mounts exactly once');
    const options = await page.locator('#p9od-block-date-list option').evaluateAll((nodes) => nodes.map((node) => node.value));
    assert.deepEqual(options, ['', fixtures.date], 'Block exposes only eligible dates');
    await page.locator('#p9od-block-date-list').selectOption(fixtures.date);
    assert.equal(await page.locator('#p9od-block-date').inputValue(), fixtures.date);
    const apiDate = await page.evaluate(() => window.wp.data.select('wc/store/checkout').getExtensionData()['patsch9-orderdate'].date);
    assert.equal(apiDate, fixtures.date, 'Selection reaches the actual WooCommerce checkout store');
    const fieldBeforeButton = await page.locator('#p9od-block-date').evaluate((field) => {
      const button = document.querySelector('.wc-block-components-checkout-place-order-button');
      return button && !!(field.compareDocumentPosition(button) & Node.DOCUMENT_POSITION_FOLLOWING);
    });
    assert.ok(fieldBeforeButton, 'Date selector is before the place-order button');
    await page.goto(`${base}/?page_id=${fixtures.classic_page}`);
    await page.locator('input#p9od_checkout_date').waitFor();
    await page.locator('input#p9od_checkout_date').fill(fixtures.date);
    await page.locator('input#p9od_checkout_date').dispatchEvent('change');
    assert.equal(await page.locator('select[name=p9od_checkout_date]').inputValue(), fixtures.date, 'Classic calendar updates submitted select value');
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
