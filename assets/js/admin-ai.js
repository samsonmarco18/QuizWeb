(() => {
    document.querySelectorAll('[data-ai-settings-form]').forEach(form => {
        let pending = false;
        let approved = false;
        form.addEventListener('submit', async event => {
            if (pending) { event.preventDefault(); return; }
            if (form.hasAttribute('data-ai-remove') && !approved) {
                event.preventDefault();
                pending = true;
                const answer = window.chalkConfirm
                    ? await window.chalkConfirm('Remove Gemini API key?', 'Disable AI quiz generation and remove the configured key?', 'Remove Key')
                    : window.confirm('Remove the configured Gemini key and disable AI generation?');
                pending = false;
                if (!answer) return;
                approved = true;
                form.requestSubmit();
                return;
            }
            pending = true;
            form.querySelectorAll('[type=submit]').forEach(button => { button.disabled = true; });
            const action = form.querySelector('[name=action]').value;
            form.querySelector('[data-ai-form-status]').textContent = action === 'ai_test_connection' ? 'Checking the saved connection…' : 'Saving settings…';
        });
    });
})();
