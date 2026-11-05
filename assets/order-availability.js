(function () {
  'use strict';

  let timeout;
  const maxDelay = 60000;

  function reschedule() {
    window.clearTimeout(timeout);
    const notices = Array.from(document.querySelectorAll('.p9od-order-deadline--pending[data-p9od-open-at]'));
    const starts = notices.map((node) => Number(node.dataset.p9odOpenAt))
      .filter((value) => Number.isSafeInteger(value) && value > 0);
    if (!starts.length) return;

    const start = Math.min(...starts);
    const remaining = start * 1000 - Date.now();
    if (remaining > 0) {
      timeout = window.setTimeout(reschedule, Math.min(remaining + 150, maxDelay));
      return;
    }

    // A client-side clock is never used to permit a purchase. Reload the
    // server-rendered form so WooCommerce can evaluate purchasability afresh.
    const key = 'p9od-open-refresh:' + window.location.pathname + ':' + start;
    try {
      if (window.sessionStorage.getItem(key)) return;
      window.sessionStorage.setItem(key, '1');
    } catch (error) {
      // Without session storage, don't risk a repeated reload on cached pages.
      return;
    }
    window.location.reload();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', reschedule, { once: true });
  } else {
    reschedule();
  }

  // WooCommerce updates the variation availability markup dynamically.
  if (window.jQuery) {
    window.jQuery(document.body).on('show_variation hide_variation', 'form.variations_form', function () {
      window.setTimeout(reschedule, 0);
    });
  }
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) reschedule();
  });
}());
