(function () {
  'use strict';
  document.addEventListener('click', function (event) {
    if (event.target.matches('.p9od-window-add')) {
      const row = event.target.closest('.p9od-window-list');
      const start = row.querySelector('.p9od-window-start');
      const end = row.querySelector('.p9od-window-end');
      if (!start.value || !end.value || start.value >= end.value) {
        start.focus();
        return;
      }
      const list = row.querySelector('textarea');
      const windows = new Set(list.value.trim().split(/\r?\n/).filter(Boolean));
      windows.add(row.querySelector('.p9od-window-day').value + ':' + start.value + '-' + end.value);
      list.value = Array.from(windows).sort().join('\n');
      list.dispatchEvent(new Event('change', { bubbles: true }));
      return;
    }
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
