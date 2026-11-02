(function ($) {
  'use strict';
  function enhance() {
    $('select.p9od-date:not([data-enhanced])').each(function () {
      const select = this;
      const dates = Array.from(select.options).map(function (option) { return option.value; }).filter(Boolean);
      if (!dates.length) return;
      select.dataset.enhanced = 'true';
      const originalId = select.id;
      select.id = originalId + '-value';
      const input = $('<input>', { type: 'text', id: originalId, autocomplete: 'off', placeholder: window.p9odCalendar.choose, required: true, class: 'input-text p9od-calendar' });
      input.val(select.value);
      $(select).hide().prop('required', false).after(input);
      const allowed = new Set(dates);
      const options = Object.assign({}, window.p9odCalendar, {
        dateFormat: 'yy-mm-dd', minDate: dates[0], maxDate: dates[dates.length - 1],
        prevText: window.p9odCalendar.previous, nextText: window.p9odCalendar.next,
        beforeShowDay: function (date) { return [allowed.has($.datepicker.formatDate('yy-mm-dd', date)), '']; },
        beforeShow: function () { $('#ui-datepicker-div').addClass('p9od-datepicker'); },
        onClose: function () { $('#ui-datepicker-div').removeClass('p9od-datepicker'); },
        onSelect: function (date) { input[0].setCustomValidity(''); $(select).val(date).trigger('change'); }
      });
      input.datepicker(options).on('change', function () {
        select.value = allowed.has(input.val()) ? input.val() : '';
        this.setCustomValidity(select.value ? '' : window.p9odCalendar.choose);
      });
    });
  }
  $(enhance);
  $(document.body).on('updated_checkout', enhance);
}(jQuery));
