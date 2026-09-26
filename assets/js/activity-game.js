(() => {
    const root = document.querySelector('[data-game-root]');
    if (!root) return;
    const quiz = JSON.parse(root.dataset.quiz || '{}');
    const questions = quiz.questions || [];
    const preview = root.dataset.isPreview === '1';
    const matching = quiz.game_type === 'flip_match';
    const stage = root.querySelector('[data-answer-grid]');
    const controls = root.querySelector('[data-game-controls]');
    const note = root.querySelector('[data-game-note]');
    const heading = root.querySelector('[data-question-text]');
    const helper = root.querySelector('[data-question-helper]');
    const points = root.querySelector('[data-question-points]');
    const answers = {};
    let started = 0, timer, index = 0, finished = false, moves = 0, matched = 0;
    function node(tag, text, className) {
        const element = document.createElement(tag);
        if (text !== undefined) element.textContent = text;
        if (className) element.className = className;
        return element;
    }
    function button(text, action, secondary = false) {
        const element = node('button', text, `button button-${secondary ? 'secondary' : 'primary'}`);
        element.type = 'button'; element.addEventListener('click', action); return element;
    }
    function progress(count) {
        document.querySelector('[data-progress-count]').textContent = `${count} / ${questions.length}`;
        root.querySelector('[data-progress-bar]').style.width = `${count / Math.max(questions.length, 1) * 100}%`;
    }
    function elapsed() { return started ? Math.round((Date.now() - started) / 1000) : 0; }
    document.querySelector('[data-score-value]').textContent = 'After submit';
    document.querySelector('[data-streak-value]').textContent = '—';
    heading.textContent = quiz.title;
    helper.textContent = matching ? 'Flip two cards to match each term with its definition.' : 'Answer each prompt. You can go back before submitting.';
    points.textContent = `${questions.length} ${matching ? 'pairs' : 'questions'}`;
    stage.replaceChildren();
    note.textContent = preview ? 'Teacher preview. No results will be saved.' : 'Your results are saved when you submit.';
    controls.replaceChildren(button(preview ? 'Start Preview' : 'Start Activity', () => {
        if (started) return;
        started = Date.now();
        timer = setInterval(() => { document.querySelector('[data-timer-value]').textContent = `${elapsed()}s`; }, 1000);
        if (matching) renderMatching(); else renderQuestion();
    }), button('Back to Classroom', () => { location.href = root.dataset.returnUrl; }, true));
    if (!questions.length) { controls.firstChild.disabled = true; note.textContent = 'No questions are available yet.'; }
    function renderQuestion() {
        const question = questions[index];
        heading.textContent = question.prompt;
        heading.classList.toggle('emoji-prompt', quiz.game_type === 'emoji_quiz');
        points.textContent = `${question.points} points`;
        helper.textContent = `Question ${index + 1} of ${questions.length}`;
        const label = node('label', undefined, 'activity-answer');
        const input = node('input');
        input.type = 'text'; input.maxLength = 500; input.autocomplete = 'off'; input.value = answers[index] || '';
        input.placeholder = 'Type your answer';
        label.append(node('span', 'Your answer'), input);
        input.addEventListener('input', () => { answers[index] = input.value; });
        stage.replaceChildren(label);
        if (question.hint) {
            const hint = node('details', undefined, 'activity-hint');
            hint.append(node('summary', 'Show hint'), node('p', question.hint)); stage.append(hint);
        }
        const advance = () => {
            answers[index] = input.value;
            if (index < questions.length - 1) { index++; renderQuestion(); } else review();
        };
        input.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); advance(); } });
        controls.replaceChildren();
        if (index > 0) controls.append(button('Previous', () => { answers[index] = input.value; index--; renderQuestion(); }, true));
        controls.append(button(index === questions.length - 1 ? 'Review Answers' : 'Next Question', advance));
        progress(index); input.focus();
    }
    function review() {
        heading.textContent = 'Review your answers'; helper.textContent = 'Return to any item to make changes before submitting.';
        stage.replaceChildren();
        questions.forEach((question, item) => {
            const row = node('article', undefined, 'activity-review-row');
            row.append(node('strong', question.prompt), node('p', answers[item] || 'Not answered'), button('Edit', () => { index = item; renderQuestion(); }, true)); stage.append(row);
        });
        controls.replaceChildren(button(preview ? 'Finish Preview' : 'Submit Activity', finish)); progress(questions.length);
    }
    function renderMatching() {
        heading.textContent = 'Find the matching pairs';
        helper.textContent = 'Choose two cards. A term matches its definition.';
        stage.replaceChildren(); stage.classList.add('match-board');
        const cards = questions.flatMap((q, i) => [{pair: i, side: 'term', text: q.prompt}, {pair: i, side: 'answer', text: q.answer}]);
        for (let i = cards.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [cards[i], cards[j]] = [cards[j], cards[i]]; }
        let selected = [], busy = false;
        function update() { points.textContent = `${matched} / ${questions.length} pairs · ${moves} moves`; progress(matched); }
        cards.forEach((card, position) => {
            const tile = node('button', '?', 'match-card'); tile.type = 'button';
            tile.setAttribute('aria-label', `Reveal card ${position + 1}`); tile.setAttribute('aria-pressed', 'false');
            tile.addEventListener('click', () => {
                if (busy || tile.disabled || selected.some(item => item.tile === tile)) return;
                tile.textContent = card.text; tile.classList.add('is-revealed'); tile.setAttribute('aria-pressed', 'true'); tile.setAttribute('aria-label', card.text);
                selected.push({card, tile, position});
                if (selected.length < 2) return;
                moves++; busy = true;
                const [first, second] = selected;
                if (first.card.pair === second.card.pair && first.card.side !== second.card.side) {
                    selected.forEach(item => { item.tile.disabled = true; item.tile.classList.add('is-matched'); });
                    answers[first.card.pair] = questions[first.card.pair].answer;
                    matched++; selected = []; busy = false;
                    note.textContent = 'Pair matched.';
                    if (matched === questions.length) controls.replaceChildren(button(preview ? 'Finish Preview' : 'Submit Matches', finish));
                } else {
                    note.textContent = 'Not a pair. Remember these cards, then continue.';
                    controls.replaceChildren(button('Turn Cards Back', () => {
                        selected.forEach(item => { item.tile.textContent = '?'; item.tile.classList.remove('is-revealed'); item.tile.setAttribute('aria-pressed', 'false'); item.tile.setAttribute('aria-label', `Reveal card ${item.position + 1}`); });
                        selected = []; busy = false; controls.replaceChildren();
                    }, true));
                }
                update();
            });
            stage.append(tile);
        });
        controls.replaceChildren(); update();
    }
    function finish() {
        if (finished) return;
        finished = true; clearInterval(timer);
        if (preview) {
            let score = 0;
            questions.forEach((q, i) => {
                const normalize = value => q.case_sensitive ? String(value).trim() : String(value).trim().toLocaleLowerCase();
                if ([q.answer, ...(q.accepted_answers || [])].some(value => normalize(value) === normalize(answers[i] || ''))) score += Number(q.points);
            });
            heading.textContent = 'Preview complete'; stage.replaceChildren();
            helper.textContent = `${score} / ${questions.reduce((total, q) => total + Number(q.points), 0)} points · ${elapsed()} seconds`;
            note.textContent = 'No score or attempt was saved.';
            controls.replaceChildren(button('Replay Preview', () => location.reload()), button('Back to Classroom', () => { location.href = root.dataset.returnUrl; }, true));
            return;
        }
        controls.querySelectorAll('button').forEach(element => { element.disabled = true; });
        note.textContent = 'Submitting your answers…';
        if (matching) answers._moves = moves;
        const form = node('form'); form.method = 'post'; form.action = root.dataset.submitUrl;
        const fields = {classroom_id: root.dataset.classroomId, quiz_id: quiz.id, elapsed_seconds: elapsed(), answers: JSON.stringify(answers), csrf: root.dataset.csrf};
        Object.entries(fields).forEach(([name, value]) => { const input = node('input'); input.type = 'hidden'; input.name = name; input.value = value; form.append(input); });
        document.body.append(form); form.submit();
    }
    window.addEventListener('beforeunload', event => { if (started && !finished) { event.preventDefault(); event.returnValue = ''; } });
})();
