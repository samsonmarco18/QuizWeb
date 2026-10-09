(() => {
  'use strict';
  document.querySelectorAll('[data-open-grade]').forEach(button => button.addEventListener('click', () => {
    const dialog = document.getElementById(button.dataset.openGrade);
    if (dialog && !dialog.open) dialog.showModal();
  }));
  document.querySelectorAll('[data-close-grade]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
  document.querySelectorAll('.academic-drawer').forEach(dialog => dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
  }));
  document.querySelectorAll('form[data-auto-filter] select').forEach(select => select.addEventListener('change', () => select.form.requestSubmit()));
  document.querySelectorAll('.academic-page form').forEach(form => form.addEventListener('submit', event => {
    if (event.defaultPrevented || (!form.noValidate && !form.checkValidity())) return;
    form.setAttribute('aria-busy', 'true');
    const button = event.submitter || form.querySelector('button:not([type]), button[type="submit"]');
    if (button) { button.dataset.originalLabel ||= button.textContent; button.textContent = form.method.toLowerCase() === 'post' ? 'Saving…' : 'Loading…'; }
  }));
  window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[aria-busy]').forEach(form => form.removeAttribute('aria-busy'));
    document.querySelectorAll('[data-original-label]').forEach(button => { button.textContent = button.dataset.originalLabel; delete button.dataset.originalLabel; });
  });
})();
