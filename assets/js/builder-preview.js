(() => {
    const form = document.getElementById('quiz-builder-form');
    const trigger = document.getElementById('preview-quiz');
    const dialog = document.getElementById('activity-preview');
    if (!form || !trigger || !dialog) return;
    const status = document.getElementById('preview-status');
    const content = document.getElementById('preview-content');
    dialog.querySelectorAll('[data-preview-width]').forEach(button => button.addEventListener('click', () => {
        dialog.style.setProperty('--preview-width', button.dataset.previewWidth);
        dialog.querySelectorAll('[data-preview-width]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    }));
    document.getElementById('play-test')?.addEventListener('click', () => trigger.click());
    dialog.addEventListener('close', () => content.replaceChildren());
    window.addEventListener('message', event => {
        const frame = content.querySelector('iframe');
        if (frame && event.source === frame.contentWindow && event.data?.type === 'quizweb-preview-close') dialog.close();
    });
    function node(tag, text, className) {
        const element = document.createElement(tag);
        if (text !== undefined) element.textContent = text;
        if (className) element.className = className;
        return element;
    }
    function playTest(quiz) {
        quiz.mastery_threshold = quiz.mastery_threshold ?? (Number(form.elements.mastery_threshold.value) || 75);
        const frame = node('iframe');
        frame.title = 'Isolated activity play test';
        frame.addEventListener('load', () => { frame.dataset.previewReady = '1'; });
        // No same-origin permission, forms, popups, or network writes. Even a
        // renderer regression cannot submit a student attempt from this frame.
        frame.setAttribute('sandbox', 'allow-scripts');
        const doc = document.implementation.createHTMLDocument('Activity play test');
        const csp = doc.createElement('meta'); csp.httpEquiv = 'Content-Security-Policy';
        csp.content = `default-src 'none'; script-src ${location.origin}; style-src ${location.origin} 'unsafe-inline'; img-src ${location.origin} data:; connect-src 'none'; form-action 'none';`;
        doc.head.append(csp);
        const viewport = doc.createElement('meta'); viewport.name = 'viewport'; viewport.content = 'width=device-width,initial-scale=1'; doc.head.append(viewport);
        for (const path of ['site.css', 'refinements.css', 'visual-polish.css', 'game-experience.css']) {
            const link = doc.createElement('link'); link.rel = 'stylesheet'; link.href = `${location.origin}/QuizWeb/assets/css/${path}`; doc.head.append(link);
        }
        doc.body.className = `ui-refined game-page mode-${quiz.game_type}${document.body.classList.contains('theme-dark') ? ' theme-dark' : ''}`;
        doc.body.innerHTML = `<main class="preview-play-test"><div class="card-meta"><span data-progress-count></span><span>Score <b data-score-value>0</b></span><span>Streak <b data-streak-value>0</b></span><span>Time <b data-timer-value>0</b></span></div><div class="game-board" data-game-root data-is-preview="1" data-return-url="#"><div class="game-progress"><div class="game-progress-bar" data-progress-bar></div></div><div class="battle-strip" data-battle-strip hidden><div data-boss-health></div><div data-player-health></div></div><div class="mode-stage"><article class="question-stage glass"><span data-question-points></span><h2 data-question-text></h2><p data-question-helper></p><div class="answers-grid" data-answer-grid></div><div class="game-controls" data-game-controls></div></article></div><div class="game-note" data-game-note></div></div></main>`;
        doc.querySelector('[data-game-root]').dataset.quiz = JSON.stringify(quiz);
        doc.querySelector('[data-game-root]').dataset.embeddedPreview = '1';
        const experience = doc.createElement('script'); experience.src = `${location.origin}/QuizWeb/assets/js/game-experience.js`; doc.body.append(experience);
        const security = doc.createElement('script'); security.src = `${location.origin}/QuizWeb/assets/js/quiz-integrity.js`; doc.body.append(security);
        const script = doc.createElement('script');
        script.src = `${location.origin}/QuizWeb/assets/js/${['fill_blank', 'emoji_quiz', 'flip_match'].includes(quiz.game_type) ? 'activity-game.js' : 'game.js'}`;
        doc.body.append(script);
        frame.srcdoc = '<!doctype html>' + doc.documentElement.outerHTML;
        content.append(frame);
    }
    trigger.addEventListener('click', async () => {
        if (window.quizBuilder?.imagesPending?.()) { dialog.showModal(); content.replaceChildren(); status.textContent = 'Wait for the image upload to finish, then preview again.'; return; }
        form.dispatchEvent(new Event('activity:collect'));
        const data = new FormData(form);
        data.set('action', 'preview');
        dialog.showModal();
        status.textContent = 'Building your preview…';
        content.replaceChildren();
        trigger.disabled = true;
        dialog.setAttribute('aria-busy', 'true');
        const abort = new AbortController();
        const timeout = setTimeout(() => abort.abort(), 15000);
        try {
            const response = await fetch(form.action || location.href, {method: 'POST', body: data, signal: abort.signal, headers: {'Accept': 'application/json'}});
            if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Your session may have expired. Sign in again, then retry.');
            const quiz = await response.json();
            if (!response.ok || quiz.errors?.length) {
                status.textContent = 'Update these items, then preview again:';
                const list = node('ul');
                (quiz.errors || ['Preview could not be generated.']).forEach(error => list.append(node('li', error)));
                content.append(list); return;
            }
            document.getElementById('preview-title').textContent = quiz.title;
            status.textContent = `${quiz.questions.length} items · Play test responses are never saved.`;
            playTest(quiz);
        } catch (error) {
            status.textContent = error.name === 'AbortError' ? 'Preview timed out. Close and retry.' : (error.message || 'Could not load the preview. Please retry.');
        } finally {
            clearTimeout(timeout); trigger.disabled = false; dialog.removeAttribute('aria-busy');
        }
    });
})();
