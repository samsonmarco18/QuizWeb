(() => {
  'use strict';
  document.querySelectorAll('.student-portal-page form[method="post"]').forEach(form => {
    form.addEventListener('submit', event => {
      if (event.defaultPrevented || !form.checkValidity()) return;
      if (form.dataset.saving) { event.preventDefault(); return; }
      form.dataset.saving = '1'; form.setAttribute('aria-busy', 'true');
    });
  });
  window.addEventListener('pageshow', () => document.querySelectorAll('[data-saving]').forEach(form => { delete form.dataset.saving; form.removeAttribute('aria-busy'); }));
})();
