(() => {
    const form = document.getElementById('quiz-builder-form');
    const trigger = document.getElementById('preview-quiz');
    const dialog = document.getElementById('activity-preview');
    if (!form || !trigger || !dialog) return;
    const status = document.getElementById('preview-status');
    const content = document.getElementById('preview-content');
    function node(tag, text, className) {
        const element = document.createElement(tag);
        if (text !== undefined) element.textContent = text;
        if (className) element.className = className;
        return element;
    }
    function crosswordPreview(quiz) {
        const layout = quiz.crossword_layout;
        const wrap = node('div', undefined, 'preview-crossword');
        const scroll = node('div', undefined, 'preview-grid-scroll');
        const grid = node('div', undefined, 'preview-grid');
        grid.style.setProperty('--cols', layout.cols);
        grid.setAttribute('aria-label', 'Crossword grid');
        const labels = new Map();
        layout.placements.forEach(p => {
            const key = `${p.row}:${p.col}`;
            labels.set(key, [...(labels.get(key) || []), `${p.number}${p.direction === 'across' ? 'A' : 'D'}`]);
        });
        layout.cells.forEach((row, y) => row.forEach((letter, x) => {
            const cell = node('div', undefined, letter === null ? 'preview-cell is-block' : 'preview-cell');
            if (letter !== null) {
                const input = node('input');
                input.maxLength = 1;
                input.autocomplete = 'off';
                input.setAttribute('aria-label', `Row ${y + 1}, column ${x + 1}`);
                input.dataset.answer = letter;
                const label = labels.get(`${y}:${x}`);
                if (label) cell.append(node('small', label.join('/')));
                cell.append(input);
            }
            grid.append(cell);
        }));
        scroll.append(grid);
        wrap.append(scroll);
        const clues = node('div', undefined, 'preview-clues');
        for (const direction of ['across', 'down']) {
            clues.append(node('h3', direction === 'across' ? 'Across' : 'Down'));
            layout.placements.filter(p => p.direction === direction).forEach(p => {
                const question = quiz.questions.find(q => Number(q.id) === Number(p.question_id));
                clues.append(node('p', `${p.number}. ${question.prompt} (${question.answer.length})`));
            });
        }
        wrap.append(clues);
        let showingAnswers = false;
        const toggle = node('button', 'Show answer key', 'button button-secondary');
        toggle.type = 'button';
        toggle.setAttribute('aria-pressed', 'false');
        toggle.addEventListener('click', () => {
            showingAnswers = !showingAnswers;
            grid.querySelectorAll('input').forEach(input => {
                if (showingAnswers) input.dataset.draft = input.value;
                input.value = showingAnswers ? input.dataset.answer : (input.dataset.draft || '');
                input.readOnly = showingAnswers;
            });
            toggle.textContent = showingAnswers ? 'Hide answer key' : 'Show answer key';
            toggle.setAttribute('aria-pressed', String(showingAnswers));
        });
        content.append(toggle, wrap);
    }
    function questionPreview(quiz) {
        if (quiz.game_type === 'flip_match') {
            content.append(node('p', 'Pair review: each term becomes one card and its definition becomes another. Cards are shuffled during play.'));
        }
        quiz.questions.forEach((question, index) => {
            const card = node('article', undefined, 'preview-question');
            card.append(node('small', `Item ${index + 1} · ${question.points} points`), node('h3', question.prompt));
            if (quiz.game_type === 'flip_match') {
                card.append(node('p', question.answer));
            } else if (['fill_blank', 'emoji_quiz'].includes(quiz.game_type)) {
                const input = node('input');
                input.placeholder = 'Type your answer';
                input.setAttribute('aria-label', `Answer for item ${index + 1}`);
                card.append(input);
            } else {
                const choices = node('div', undefined, 'preview-choices');
                question.options.forEach((option, optionIndex) => {
                    const label = node('label');
                    const input = node('input');
                    input.type = 'radio'; input.name = `preview-${index}`; input.value = optionIndex;
                    label.append(input, node('span', option)); choices.append(label);
                });
                card.append(choices);
            }
            if (question.hint) card.append(node('p', `Hint: ${question.hint}`));
            const key = node('details');
            key.append(node('summary', 'Answer key'), node('p', question.answer || question.options[question.correct_index]));
            if (question.explanation) key.append(node('p', question.explanation));
            card.append(key); content.append(card);
        });
    }
    trigger.addEventListener('click', async () => {
        if (!form.reportValidity()) return;
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
            status.textContent = quiz.game_type === 'crossword' ? 'This is the grid that will be saved. Try the cells or show the answer key.' : `${quiz.questions.length} items · Nothing has been saved yet.`;
            if (quiz.game_type === 'crossword') crosswordPreview(quiz); else questionPreview(quiz);
        } catch (error) {
            status.textContent = error.name === 'AbortError' ? 'Preview timed out. Close and retry.' : (error.message || 'Could not load the preview. Please retry.');
        } finally {
            clearTimeout(timeout); trigger.disabled = false; dialog.removeAttribute('aria-busy');
        }
    });
})();
