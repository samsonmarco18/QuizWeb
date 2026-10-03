(() => {
  'use strict';
  const setup = document.getElementById('grading-setup');
  let leaving = false;
  const tracked = [...document.querySelectorAll('[data-grade-dirty]')];
  const snapshot = form => JSON.stringify([...new FormData(form).entries()]);
  const originals = new Map(tracked.map(form => [form, snapshot(form)]));
  let setupDirty = false;
  if (setup) {
    const state = structuredClone(window.gradingConfigSeed);
    state.passing = String(state.passing);
    const initial = JSON.stringify(state);
    let step = 0;
    const byId = id => document.getElementById(id);
    const status = byId('grading-setup-status');
    const passing = setup.elements.passing, missing = setup.elements.missing_policy;
    passing.value = state.passing; missing.value = state.missing_policy;
    const node = (tag, text) => { const element = document.createElement(tag); if (text !== undefined) element.textContent = text; return element; };
    const field = (label, value, onInput, numeric = false) => {
      const wrapper = node('label'), title = node('span', label), input = node('input');
      input.type = numeric ? 'number' : 'text'; input.value = value; input.required = true;
      if (numeric) { input.min = '0'; input.max = '100'; input.step = '0.01'; } else input.maxLength = 50;
      input.addEventListener('input', () => { onInput(input.value); update(); });
      wrapper.append(title, input); return wrapper;
    };
    const button = (label, action) => { const element = node('button', label); element.type = 'button'; element.className = 'button button-secondary'; element.addEventListener('click', action); return element; };
    function update() {
      state.passing = passing.value; state.missing_policy = missing.value;
      const total = state.categories.reduce((sum, category) => sum + Math.round(Number(category.weight || 0) * 100), 0) / 100;
      byId('grading-weight-total').textContent = `Total: ${total.toFixed(2)}% · ${total === 100 ? 'Ready' : total < 100 ? `${(100 - total).toFixed(2)}% remaining` : `${(total - 100).toFixed(2)}% over`}`;
      byId('grading-weight-progress').value = Math.min(100, total);
      const review = byId('grading-setup-review'); review.replaceChildren();
      state.categories.forEach(category => review.append(node('p', `${category.name || 'Unnamed category'}: ${category.weight || 0}%`)));
      review.append(node('p', 'Category grade = earned points ÷ possible points × 100. Overall = sum of category grade × category weight, normalized over categories with available points.'));
      review.append(node('p', `Passing: ${state.passing}% · Missing scores: ${state.missing_policy === 'zero' ? 'count as zero' : 'excluded until graded'}`));
      [...state.scale].sort((a, b) => Number(a.min) - Number(b.min)).forEach(band => review.append(node('p', `${band.min}% and above: ${band.label}`)));
      setupDirty = Boolean(window.gradingPostedDraft) || JSON.stringify(state) !== initial;
    }
    function render() {
      const categories = byId('grading-categories'), weights = byId('grading-weights'), bands = byId('grading-scale');
      categories.replaceChildren(); weights.replaceChildren(); bands.replaceChildren();
      state.categories.forEach((category, index) => {
        const row = node('div'); row.className = 'grade-editor-row';
        row.append(field('Category name', category.name, value => { category.name = value; weights.children[index].querySelector('span').textContent = `${value} weight (%)`; }));
        const up = button('Move up', () => { [state.categories[index - 1], state.categories[index]] = [category, state.categories[index - 1]]; render(); }); up.disabled = index === 0;
        const down = button('Move down', () => { [state.categories[index + 1], state.categories[index]] = [category, state.categories[index + 1]]; render(); }); down.disabled = index === state.categories.length - 1;
        row.append(up, down, button('Remove', () => { state.categories.splice(index, 1); render(); })); categories.append(row);
        weights.append(field(`${category.name} weight (%)`, category.weight, value => { category.weight = value; }, true));
      });
      state.scale.forEach((band, index) => {
        const row = node('div'); row.className = 'grade-editor-row';
        row.append(field('Minimum (%)', band.min, value => { band.min = value; }, true), field('Grade label', band.label, value => { band.label = value; }), button('Remove band', () => { state.scale.splice(index, 1); render(); })); bands.append(row);
      });
      byId('add-grading-category').disabled = state.categories.length >= 8;
      byId('add-grading-band').disabled = state.scale.length >= 10;
      update();
    }
    function show(value) {
      step = Math.max(0, Math.min(3, value));
      setup.querySelectorAll('[data-grading-panel]').forEach(panel => { panel.hidden = Number(panel.dataset.gradingPanel) !== step; });
      setup.querySelectorAll('[data-grading-step]').forEach(button => { button.classList.toggle('is-active', Number(button.dataset.gradingStep) === step); button.setAttribute('aria-current', Number(button.dataset.gradingStep) === step ? 'step' : 'false'); });
      byId('grading-previous').disabled = step === 0; byId('grading-next').hidden = step === 3;
    }
    byId('add-grading-category').addEventListener('click', () => { state.categories.push({id: `cat-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`, name: '', weight: 0}); render(); });
    byId('add-grading-band').addEventListener('click', () => { state.scale.push({min: '', label: ''}); render(); });
    setup.querySelectorAll('[data-grading-step]').forEach(button => button.addEventListener('click', () => show(Number(button.dataset.gradingStep))));
    byId('grading-previous').addEventListener('click', () => show(step - 1)); byId('grading-next').addEventListener('click', () => show(step + 1));
    passing.addEventListener('input', update); missing.addEventListener('change', update);
    setup.addEventListener('submit', event => {
      update();
      const names = state.categories.map(category => category.name.trim().toLowerCase());
      let issue = '', target = 0;
      if (!names.length || names.some(name => !name) || new Set(names).size !== names.length) issue = 'Use unique, nonempty category names.';
      else if (state.categories.some(category => !Number.isFinite(Number(category.weight)) || Number(category.weight) < 0 || Number(category.weight) > 100) || state.categories.reduce((sum, category) => sum + Math.round(Number(category.weight) * 100), 0) !== 10000) { issue = 'Category weights must total exactly 100%.'; target = 1; }
      else if (!state.scale.length || state.scale.some(band => band.min === '' || !band.label.trim() || Number(band.min) < 0 || Number(band.min) > 100) || !state.scale.some(band => Number(band.min) === 0) || new Set(state.scale.map(band => Number(band.min))).size !== state.scale.length || new Set(state.scale.map(band => band.label.trim().toLowerCase())).size !== state.scale.length || passing.value === '' || Number(passing.value) < 0 || Number(passing.value) > 100) { issue = 'Set valid scale bands with unique labels and minimums starting at 0, and a passing grade from 0 to 100.'; target = 2; }
      if (issue) { event.preventDefault(); status.textContent = issue; show(target); return; }
      byId('grading-config-payload').value = JSON.stringify(state);
    });
    render(); show(0);
  }
  const dirty = () => setupDirty || Boolean(window.gradingPostedDraft) || tracked.some(form => snapshot(form) !== originals.get(form));
  window.addEventListener('beforeunload', event => { if (!leaving && dirty()) { event.preventDefault(); event.returnValue = ''; } });
  document.addEventListener('click', event => { const link = event.target.closest('a[href]'); if (link && !event.defaultPrevented && dirty() && !window.confirm('Leave this page and discard unsaved grading changes?')) event.preventDefault(); else if (link) leaving = true; });
  [...document.querySelectorAll('form[method="post"]')].forEach(form => form.addEventListener('submit', event => {
    if (event.defaultPrevented || (!form.noValidate && !form.checkValidity())) return;
    if (form.dataset.submitting) { event.preventDefault(); return; }
    form.dataset.submitting = 'true'; leaving = true;
  }));
  window.addEventListener('pageshow', () => { leaving = false; document.querySelectorAll('[data-submitting]').forEach(form => delete form.dataset.submitting); });
})();
