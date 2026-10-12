(() => {
  'use strict';
  const assignment = document.querySelector('[data-term-assignment]');
  if (assignment) {
    const rows = [...assignment.querySelectorAll('[data-assign-row]')];
    const component = assignment.querySelector('[data-assign-component]');
    const period = assignment.querySelector('[data-assign-period]');
    const all = assignment.querySelector('[data-assign-all]');
    const update = () => {
      rows.forEach(row => { row.hidden = Boolean((component.value && row.dataset.component !== component.value) || (period.value && (row.dataset.period || 'unassigned') !== period.value)); });
      const selected = rows.filter(row => row.querySelector('input').checked);
      const target = assignment.elements.period_id;
      const names = selected.map(row => row.querySelector('th').textContent).join(', ');
      assignment.querySelector('[data-assignment-preview]').textContent = selected.length && target.value ? `${selected.length} activities → ${target.selectedOptions[0].textContent}: ${names}` : 'Select activities and a destination term.';
      assignment.querySelector('[data-assign-save]').disabled = !selected.length || !target.value;
      const visible = rows.filter(row => !row.hidden);
      all.checked = visible.length > 0 && visible.every(row => row.querySelector('input').checked);
      all.indeterminate = !all.checked && visible.some(row => row.querySelector('input').checked);
    };
    all.addEventListener('change', () => { rows.filter(row => !row.hidden).forEach(row => { row.querySelector('input').checked = all.checked; }); update(); });
    assignment.addEventListener('change', update); update();
  }
  const monitor = document.querySelector('[data-grade-monitor]');
  const monitorStatus = document.querySelector('[data-monitor-status]');
  let refreshing = false;
  async function refreshMonitor() {
    if (!monitor || refreshing || document.hidden) return;
    refreshing = true;
    try {
      const url = new URL(window.location.href); url.searchParams.set('refresh', '1');
      const response = await fetch(url, {cache: 'no-store', credentials: 'same-origin'});
      if (!response.ok || response.redirected) throw new Error('Refresh failed');
      const html = await response.text();
      if (!html.includes('academic-monitor-term')) throw new Error('Monitoring unavailable');
      // Preserve keyboard focus while a teacher is using activity links.
      if (!monitor.contains(document.activeElement)) monitor.innerHTML = html;
      monitorStatus.textContent = `Live · updated ${new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'})}`;
    } catch {
      monitorStatus.textContent = 'Refresh unavailable · retrying in 15 seconds';
    } finally { refreshing = false; }
  }
  if (monitor) {
    setInterval(refreshMonitor, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshMonitor(); });
  }
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
