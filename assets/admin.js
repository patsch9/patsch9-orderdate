(function () {
  'use strict';
  document.addEventListener('click', function (event) {
    if (!event.target.matches('.p9od-list-add')) return;
    const row = event.target.closest('.p9od-date-list');
    const picker = row.querySelector('.p9od-list-picker');
    const list = row.querySelector('textarea');
    if (!picker.value || !picker.checkValidity()) { picker.reportValidity(); return; }
    const dates = new Set(list.value.trim().split(/[\s,;]+/).filter(Boolean));
    dates.add(picker.value);
    list.value = Array.from(dates).sort().join('\n');
    list.dispatchEvent(new Event('change', { bubbles: true }));
    picker.value = '';
  });
}());
