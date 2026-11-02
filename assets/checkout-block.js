(function () {
  'use strict';
  const { createElement: el, useEffect, useRef, useState } = window.wp.element;
  const { __ } = window.wp.i18n;
  const ns = 'patsch9-orderdate';
  function PickupDate(props) {
    const data = (props.extensions || {})[ns];
    const dates = data ? data.dates : [];
    const required = !!(data && data.required);
    const [date, setDate] = useState('');
    const [time, setTime] = useState('');
    const timeRequired = !!(data && data.time_required);
    const selectedDate = dates.find(function (entry) { return entry.value === date; });
    const windows = selectedDate ? (selectedDate.windows || []) : [];
    const windowSignature = windows.join(',');
    const input = useRef(null);
    const signature = dates.map(function (entry) { return entry.value; }).join(',');
    const { setExtensionData } = props.checkoutExtensionData;
    useEffect(function () {
      if (!required || !dates.some(function (entry) { return entry.value === date; })) setDate('');
    }, [signature, required]);
    useEffect(function () { setExtensionData(ns, 'date', date); }, [date, setExtensionData]);
    useEffect(function () {
      if (!timeRequired || !windows.includes(time)) setTime('');
    }, [date, timeRequired, windowSignature]);
    useEffect(function () { setExtensionData(ns, 'time', time); }, [time, setExtensionData]);
    useEffect(function () {
      if (!input.current || !dates.length || !window.p9odCalendar) return;
      const $ = window.jQuery;
      const allowed = new Set(dates.map(function (entry) { return entry.value; }));
      const field = $(input.current);
      field.datepicker(Object.assign({}, window.p9odCalendar, {
        dateFormat: 'yy-mm-dd', minDate: dates[0].value, maxDate: dates[dates.length - 1].value,
        prevText: window.p9odCalendar.previous, nextText: window.p9odCalendar.next,
        beforeShow: function () { $('#ui-datepicker-div').addClass('p9od-datepicker'); },
        onClose: function () { $('#ui-datepicker-div').removeClass('p9od-datepicker'); },
        beforeShowDay: function (day) { return [allowed.has($.datepicker.formatDate('yy-mm-dd', day)), '']; },
        onSelect: setDate
      }));
      return function () { field.datepicker('destroy'); };
    }, [signature, required]);
    useEffect(function () {
      const store = window.wp.data.dispatch(window.wc.wcBlocksData.VALIDATION_STORE_KEY);
      if (required && !dates.some(function (entry) { return entry.value === date; })) {
        store.setValidationErrors({ p9od_date: { message: __('Bitte ein gültiges Abholdatum wählen.', 'patsch9-orderdate'), hidden: true } });
      } else { store.clearValidationError('p9od_date'); }
      if (required && timeRequired && !windows.includes(time)) {
        store.setValidationErrors({ p9od_time: { message: __('Bitte ein gültiges Abholzeitfenster wählen.', 'patsch9-orderdate'), hidden: true } });
      } else { store.clearValidationError('p9od_time'); }
      return function () { store.clearValidationError('p9od_date'); store.clearValidationError('p9od_time'); };
    }, [required, date, signature, timeRequired, time, windowSignature]);
    if (!required) return null;
    if (!dates.length) return el('p', { role: 'alert' }, __('Kein gemeinsamer Abholtermin verfügbar. Bitte die Artikelauswahl ändern oder getrennt bestellen.', 'patsch9-orderdate'));
    return el('div', { className: 'p9od-block p9od-field' },
      el('label', { htmlFor: 'p9od-block-date' }, __('Abholdatum', 'patsch9-orderdate') + ' *'),
      el('input', { ref: input, id: 'p9od-block-date', type: 'text', value: date, required: true, autoComplete: 'off', placeholder: 'JJJJ-MM-TT',
        onChange: function (event) { setDate(event.target.value); } }),
      el('label', { htmlFor: 'p9od-block-date-list', className: 'screen-reader-text' }, __('Verfügbare Abholtermine', 'patsch9-orderdate')),
      el('select', { id: 'p9od-block-date-list', value: date, onChange: function (event) { setDate(event.target.value); } },
        el('option', { value: '' }, __('Bitte Abholdatum wählen', 'patsch9-orderdate')),
        dates.map(function (entry) { return el('option', { key: entry.value, value: entry.value }, entry.label); })),
      timeRequired ? el('label', { htmlFor: 'p9od-block-time' }, __('Abholzeitfenster', 'patsch9-orderdate') + ' *') : null,
      timeRequired ? el('select', { id: 'p9od-block-time', required: true, value: time, disabled: !date,
        onChange: function (event) { setTime(event.target.value); } },
        el('option', { value: '' }, __('Bitte Zeitfenster wählen', 'patsch9-orderdate')),
        windows.map(function (window) { return el('option', { key: window, value: window }, window.replace('-', '–')); })) : null,
      el('p', null, __('Gilt für Artikel mit Terminauswahl an der Kasse. Bereits am Produkt gewählte Termine bleiben separat bestehen.', 'patsch9-orderdate')));
  }
  window.wc.blocksCheckout.registerCheckoutBlock({
    metadata: { apiVersion: 3, name: ns + '/pickup-date', parent: ['woocommerce/checkout-fields-block'] }, component: PickupDate
  });
}());
