(() => {
    const dialog = document.querySelector('[data-audit-dialog]');
    if (!dialog) return;
    const title = dialog.querySelector('[data-audit-dialog-title]');
    const content = dialog.querySelector('[data-audit-dialog-content]');

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-audit-detail]');
        if (!trigger) return;
        title.textContent = trigger.dataset.auditTitle || 'Audit details';
        content.textContent = trigger.dataset.auditDetail || 'None';
        dialog.showModal();
    });

    dialog.querySelector('[data-audit-dialog-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target === dialog) dialog.close();
    });
})();
