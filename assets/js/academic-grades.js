(() => {
  'use strict';
  const table = document.querySelector('.academic-gradebook');
  const panels = [...document.querySelectorAll('.academic-drawer')];
  let active = null, trigger = null, closeTimer;
  let stage, slot;
  if (table && panels.length) {
    stage = document.createElement('div'); stage.className = 'academic-detail-layout';
    slot = document.createElement('aside'); slot.className = 'academic-detail-slot';
    slot.setAttribute('aria-label', 'Grade breakdown');
    if (document.body.classList.contains('grades-page')) {
      const left = document.createElement('div'); left.className = 'academic-student-main';
      const heading = document.querySelector('.page-shell > .page-heading');
      const filter = document.querySelector('form[data-auto-filter]');
      (heading || table).before(stage); stage.append(left, slot);
      if (heading) left.append(heading); if (filter) left.append(filter); left.append(table);
    } else { table.before(stage); stage.append(table, slot); } panels.forEach(panel => slot.append(panel));
  }
  const closePanel = () => {
    if (!active) return;
    const panel = active; active = null;
    stage?.classList.remove('has-detail'); panel.classList.add('is-closing');
    clearTimeout(closeTimer);
    const finish = () => { panel.close(); panel.classList.remove('is-closing'); };
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) finish();
    else closeTimer = setTimeout(finish, 220);
    trigger?.setAttribute('aria-expanded', 'false'); trigger?.focus();
  };
  document.querySelectorAll('[data-open-grade]').forEach(button => {
    button.setAttribute('aria-expanded', 'false'); button.setAttribute('aria-controls', button.dataset.openGrade);
    button.addEventListener('click', () => {
      const panel = document.getElementById(button.dataset.openGrade);
      if (!panel) return;
      clearTimeout(closeTimer);
      panels.forEach(other => { if (other !== panel && other.open) other.close(); other.classList.remove('is-closing'); });
      trigger?.setAttribute('aria-expanded', 'false'); active = panel; trigger = button;
      if (!panel.open) panel.show();
      stage?.classList.add('has-detail'); button.setAttribute('aria-expanded', 'true');
      panel.querySelector('[data-close-grade]')?.focus({preventScroll: true});
      if (window.innerWidth < 800) panel.scrollIntoView?.({behavior: 'smooth', block: 'start'});
    });
  });
  document.querySelectorAll('[data-close-grade]').forEach(button => button.addEventListener('click', closePanel));
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && active) { event.preventDefault(); closePanel(); } });
  if (document.body.classList.contains('grades-page')) document.querySelector('[data-open-grade]')?.click();
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
