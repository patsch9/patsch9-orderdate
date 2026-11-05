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
    await input.click();
    const datepicker = page.locator('#ui-datepicker-div');
    await page.locator('#ui-datepicker-div.p9od-datepicker').waitFor({ state: 'visible' });
    await page.evaluate(() => window.jQuery('#p9od_date').datepicker('hide'));
    assert.equal(await datepicker.isHidden(), true, 'Calendar closes immediately without a visible unstyled tail');
    assert.equal(await datepicker.evaluate((element) => element.classList.contains('p9od-datepicker')), false, 'Calendar styling remains scoped to Orderdate while open');
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
    // Ordering deadline notices: simple products and the selected variation.
    await page.goto(base + '/?p=' + fixtures.deadline_future);
    const futureNotice = page.locator('.p9od-order-deadline');
    await futureNotice.waitFor();
    assert.match(await futureNotice.innerText(), /Bestellbar bis:/, 'Simple product announces its deadline');
    assert.equal(await page.locator('button.single_add_to_cart_button').isVisible(), true, 'Future deadline keeps add-to-cart enabled');
    await page.goto(base + '/?p=' + fixtures.deadline_pending);
    const startNotice = page.locator('.p9od-order-deadline--pending');
    await startNotice.waitFor();
    assert.match(await startNotice.innerText(), /Bestellbar ab:/, 'Upcoming sale start is shown on product page');
    const openAt = Number(await startNotice.getAttribute('data-p9od-open-at'));
    assert.ok(openAt > Math.floor(Date.now() / 1000), 'Upcoming sale start is scheduled in UTC');
    assert.equal(await page.locator('button.single_add_to_cart_button').count(), 0, 'Not-yet-orderable product cannot be added');
    await page.goto(base + '/?p=' + fixtures.deadline_expired);
    const expiredNotice = page.locator('.p9od-order-deadline--expired');
    await expiredNotice.waitFor();
    assert.match(await expiredNotice.innerText(), /nicht mehr bestellt/, 'Expired product explains it cannot be ordered');
    assert.equal(await page.locator('button.single_add_to_cart_button').count(), 0, 'Expired simple product cannot be added');
    await page.goto(base + '/?p=' + fixtures.deadline_variable);
    const variationSelect = page.locator('form.variations_form .variations select').first();
    await variationSelect.waitFor();
    await variationSelect.selectOption({ label: 'Expired' });
    await page.locator('.woocommerce-variation-availability .p9od-order-deadline--expired').waitFor();
    assert.equal(await page.locator('.woocommerce-variation-add-to-cart-enabled').count(), 0, 'Expired variation is not purchasable');
    await variationSelect.selectOption({ label: 'Available' });
    await page.locator('.woocommerce-variation-availability .p9od-order-deadline:not(.p9od-order-deadline--expired)').waitFor();
    assert.match(await page.locator('.woocommerce-variation-availability .p9od-order-deadline').innerText(), /Bestellbar bis:/);
    assert.equal(await page.locator('.woocommerce-variation-add-to-cart-enabled').count(), 1, 'Future variation is still purchasable');
    await variationSelect.selectOption({ label: 'Pending' });
    const pendingVariation = page.locator('.woocommerce-variation-availability .p9od-order-deadline--pending');
    await pendingVariation.waitFor();
    assert.match(await pendingVariation.innerText(), /Bestellbar ab:/, 'Selected variation shows its independent sale start');
    assert.equal(await page.locator('.woocommerce-variation-add-to-cart-enabled').count(), 0, 'Pending variation remains unpurchasable');
    // Admin fields are rendered in their appropriate WooCommerce panels.
    await page.goto(base + '/wp-admin/post.php?post=' + fixtures.deadline_future + '&action=edit');
    if (await page.locator('#user_login').count()) {
      await page.locator('#user_login').fill('admin');
      await page.locator('#user_pass').fill('password');
      await Promise.all([page.waitForNavigation(), page.locator('#wp-submit').click()]);
    }
    await page.locator('#general_product_data #p9od_order_available_from').waitFor();
    assert.equal(await page.locator('#general_product_data .pricing #p9od_order_deadline').count(), 1, 'Deadline lives in pricing rather than below warranty');
    assert.equal(await page.locator('#general_product_data .pricing #p9od_order_available_from').count(), 1, 'Sale start lives next to sale deadline in pricing');
    assert.equal(await page.locator('#general_product_data #p9od_pay_on_pickup').count(), 0, 'Payment selection was removed from general product settings');
    assert.equal(await page.locator('#advanced_product_data #p9od_pay_on_pickup').count(), 1, 'Payment selection was moved to Advanced');

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
