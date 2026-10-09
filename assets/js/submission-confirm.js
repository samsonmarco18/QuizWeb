(() => {
  'use strict';
  let pending = false;
  window.chalkConfirm = (title, message, label = 'Confirm') => {
    if (pending) return Promise.resolve(false);
    pending = true;
    return new Promise(resolve => {
      const previous = document.activeElement;
      const dialog = document.createElement('dialog'); dialog.className = 'submission-confirm';
      dialog.setAttribute('aria-labelledby', 'submission-confirm-title');
      dialog.setAttribute('aria-describedby', 'submission-confirm-message');
      dialog.innerHTML = '<span class="submission-confirm-icon" aria-hidden="true">?</span><h2 id="submission-confirm-title"></h2><p id="submission-confirm-message"></p><div class="submission-confirm-actions"><button type="button" data-cancel>Cancel</button><button type="button" class="button button-primary" data-confirm></button></div>';
      dialog.querySelector('h2').textContent = title;
      dialog.querySelector('p').textContent = message;
      dialog.querySelector('[data-confirm]').textContent = label;
      let settled = false;
      const finish = answer => { if (settled) return; settled = true; pending = false; dialog.close(); dialog.remove(); previous?.focus?.({preventScroll:true}); resolve(answer); };
      dialog.querySelector('[data-cancel]').onclick = () => finish(false);
      dialog.querySelector('[data-confirm]').onclick = () => finish(true);
      dialog.addEventListener('cancel', event => { event.preventDefault(); finish(false); });
      document.body.append(dialog); dialog.showModal(); dialog.querySelector('[data-cancel]').focus();
    });
  };
  const approved = new WeakSet(), waiting = new WeakSet();
  const messages = {
    scores: ['Save grades?', 'Save these assessment scores? Students will see them only after you release grades.', 'Save Grades'],
    publish: ['Release grades?', 'Publish this grade release to all enrolled students? It replaces the previous release.', 'Release Grades'],
    lock: ['Finalize grades?', 'Finalize this release and lock grade editing? Reopening it will require a correction reason.', 'Finalize Grades'],
    unpublish: ['Hide grades?', 'Hide the published grade release from students?', 'Hide Grades'],
    unlock: ['Unlock grades?', 'Reopen this gradebook for corrections using the reason you entered?', 'Unlock Grades'],
    override: ['Save grade correction?', 'Save this assessment correction and its reason in the grade history?', 'Save Correction'],
    restore: ['Restore calculated grade?', 'Remove this adjustment and return to the calculated assessment grade?', 'Restore Grade']
  };
  document.addEventListener('submit', event => {
    const form = event.target;
    if (form.method.toLowerCase() !== 'post' || !document.body.classList.contains('gradebook-page') || document.body.classList.contains('grades-page') || form.id === 'quiz-builder-form' || form.closest('.messenger-dock')) return;
    if (approved.has(form)) { approved.delete(form); return; }
    if (!form.noValidate && !form.checkValidity()) return;
    const action = event.submitter?.name === 'action' ? event.submitter.value : form.querySelector('[name="action"]')?.value;
    if (!action) return;
    event.preventDefault(); event.stopImmediatePropagation();
    if (waiting.has(form)) return;
    waiting.add(form); const button = event.submitter;
    const info = messages[action] || ['Save grading changes?', 'Save these changes to the classroom gradebook?', 'Save Changes'];
    window.chalkConfirm(...info).then(answer => { waiting.delete(form); if (!answer) return; approved.add(form); form.requestSubmit(button || undefined); });
  }, true);
})();
