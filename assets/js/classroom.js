(() => {
    const page = document.querySelector('.classroom-redesign');
    if (!page) return;

    const copyButton = page.querySelector('[data-copy-classroom-code]');
    copyButton?.addEventListener('click', async () => {
        const code = page.querySelector('[data-classroom-code]');
        const status = page.querySelector('[data-copy-code-status]');
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(code.textContent.trim());
            status.textContent = 'Copied!';
        } catch {
            const selection = window.getSelection();
            const range = document.createRange();
            range.selectNodeContents(code);
            selection.removeAllRanges(); selection.addRange(range);
            status.textContent = 'Code selected. Press Ctrl+C or copy it.';
        }
    });

    page.querySelector('[data-classroom-message]')?.addEventListener('click', () => {
        const dock = document.querySelector('[data-messenger-dock]');
        if (!dock) return;
        const button = [...dock.querySelectorAll('[data-messenger-target]')].find(item => item.dataset.chatId === dock.dataset.defaultChatId);
        if (button) button.click();
        else if (!dock.classList.contains('is-open')) dock.querySelector('.messenger-launcher')?.click();
    });

    const panel = page.querySelector('[data-classroom-quizzes]');
    const form = panel?.querySelector('[data-quiz-controls]');
    if (!form) return;
    const filter = form.querySelector('[data-current-quiz-filter]');
    const search = form.querySelector('[name="q"]');
    const status = panel.querySelector('[data-quiz-filter-status]');
    const list = panel.querySelector('[data-quiz-list]');
    const pagination = panel.querySelector('[data-quiz-pagination]');
    let pending, sequence = 0, searchDelay;

    function updateFilters() {
        form.querySelectorAll('[data-quiz-filter]').forEach(button => {
            const selected = button.dataset.quizFilter === filter.value;
            button.classList.toggle('is-active', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        const more = form.querySelector('.quiz-more-filters');
        more?.querySelector('summary').classList.toggle('is-active', !!more.querySelector('[data-quiz-filter][aria-pressed="true"]'));
    }

    async function loadQuizzes(requestUrl) {
        clearTimeout(searchDelay);
        pending?.abort();
        pending = new AbortController();
        const requestController = pending;
        const current = ++sequence;
        const url = new URL(requestUrl || form.action, window.location.href);
        if (!requestUrl) url.search = new URLSearchParams(new FormData(form)).toString();
        if (url.origin !== window.location.origin) return;
        list.setAttribute('aria-busy', 'true');
        status.textContent = 'Loading quizzes...';
        updateFilters();
        form.querySelectorAll('details[open]').forEach(details => { details.open = false; });
        const timeout = setTimeout(() => requestController.abort(), 15000);
        try {
            const response = await fetch(url.href, {credentials: 'same-origin', signal: requestController.signal, headers: {'Accept': 'text/html'}});
            if (!response.ok) throw new Error('Quiz list could not load. Try again.');
            const html = await response.text();
            if (current !== sequence) return;
            const next = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-classroom-quizzes]');
            if (!next) { window.location.assign(url.href); return; }
            list.replaceChildren(...next.querySelector('[data-quiz-list]').childNodes);
            pagination.replaceChildren(...next.querySelector('[data-quiz-pagination]').childNodes);
            status.textContent = next.querySelector('[data-quiz-filter-status]').textContent;
            history.replaceState(null, '', url.pathname + url.search);
        } catch (error) {
            if (current !== sequence) return;
            status.textContent = error.name === 'AbortError' ? 'Search timed out. Press Enter to retry.' : error.message;
        } finally {
            clearTimeout(timeout);
            if (current === sequence) list.removeAttribute('aria-busy');
        }
    }

    form.addEventListener('submit', event => {
        event.preventDefault();
        if (event.submitter?.name === 'quiz_filter') filter.value = event.submitter.value;
        loadQuizzes();
    });
    search.addEventListener('input', () => {
        clearTimeout(searchDelay);
        // Invalidate an older request immediately, before the debounce fires.
        sequence++; pending?.abort();
        searchDelay = setTimeout(() => loadQuizzes(), 250);
    });
    search.addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); loadQuizzes(); }
    });
    form.querySelector('[name="quiz_sort"]').addEventListener('change', () => loadQuizzes());
    pagination.addEventListener('click', event => {
        const link = event.target.closest('a');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault(); loadQuizzes(link.href);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') page.querySelectorAll('.quiz-more-filters[open], .quiz-sort-menu[open], .quiz-card-menu[open]').forEach(details => { details.open = false; details.querySelector('summary')?.focus(); });
    });
})();
