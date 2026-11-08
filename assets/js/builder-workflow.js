(() => {
    const form = document.getElementById('quiz-builder-form');
    if (!form || !window.quizBuilder) return;
    const api = window.quizBuilder;
    const list = document.getElementById('question-list');
    const navigator = document.getElementById('question-navigator');
    const status = document.getElementById('builder-status');
    const live = document.getElementById('live-preview-content');
    const mode = () => form.elements.game_type.value;
    const cards = () => [...list.querySelectorAll('.question-card')];
    let step = 0, active = 0, submitting = false, leaving = false;
    const snapshot = () => JSON.stringify([form.elements.activity_type?.value, form.elements.title.value, form.elements.description.value, form.elements.due_at.value, form.elements.mastery_threshold.value, form.elements.grade_category_id?.value, form.elements.grade_period_id?.value, form.elements.grade_max_score?.value, form.elements.grade_attempt_policy?.value, mode(), api.collect()]);
    const original = window.quizBuilderUnsaved ? null : snapshot();
    const stepNames = ['Details', 'Questions', 'Settings', 'Grading', 'Review'];
    const lastStep = stepNames.length - 1;
    const visitedSteps = new Set();
    function updateStepCompletion(questions) {
        const validFields = index => [...form.querySelectorAll(`[data-builder-step="${index}"] input, [data-builder-step="${index}"] select, [data-builder-step="${index}"] textarea`)]
            .filter(input => input.name && !input.disabled).every(input => input.checkValidity());
        const category = form.elements.grade_category_id?.value;
        const period = form.elements.grade_period_id;
        const maximum = form.elements.grade_max_score;
        const maxValue = Number(maximum?.value);
        const gradingReady = !category || ((!period || Boolean(period.value))
            && ['highest', 'latest', 'first', 'average'].includes(form.elements.grade_attempt_policy?.value)
            && (!maximum?.value || (Number.isFinite(maxValue) && maxValue >= .01 && maxValue <= 100000 && Math.abs(maxValue * 100 - Math.round(maxValue * 100)) <= .00001)));
        const ready = [
            Boolean(window.quizBuilderModes[mode()]) && Boolean(form.elements.title.value.trim()) && (!form.elements.activity_type || Boolean(form.elements.activity_type.value.trim())) && validFields(0),
            questions.length > 0 && questions.length <= 100 && questions.every(q => !problem(q)),
            validFields(2) && configurationProblems(questions).filter(issue => !issue.startsWith('Gradebook maximum')).length === 0,
            gradingReady,
        ];
        form.querySelectorAll('[data-step]').forEach(button => {
            const index = Number(button.dataset.step);
            const complete = index < lastStep && visitedSteps.has(index) && ready[index];
            button.classList.toggle('is-complete', Boolean(complete));
            const number = button.querySelector('.builder-step-number');
            if (number) number.textContent = complete ? '\u2713' : String(index + 1);
            button.setAttribute('aria-label', `Step ${index + 1}: ${stepNames[index]}, ${complete ? 'completed' : index === step ? 'current' : 'not completed'}`);
            button.title = complete ? 'Completed' : '';
        });
    }
    const draftKey = `chalk-builder:${location.pathname}${location.search}`;
    let recoveredDraft = null, draftTimer;
    try { recoveredDraft = JSON.parse(localStorage.getItem(draftKey)); } catch (_) { /* Storage is optional. */ }
    function saveLocalDraft() {
        if (leaving) return;
        const fields = {};
        ['title', 'activity_type', 'description', 'due_at', 'mastery_threshold', 'grade_category_id', 'grade_period_id', 'grade_max_score', 'grade_attempt_policy', 'game_type'].forEach(name => { fields[name] = form.elements[name]?.value || ''; });
        try { localStorage.setItem(draftKey, JSON.stringify({version: 2, fields, questions: api.collect(), step})); } catch (_) { /* Private mode or full storage must not prevent saving. */ }
    }
    const node = (tag, text, cls) => {
        const el = document.createElement(tag);
        if (text !== undefined) el.textContent = text;
        if (cls) el.className = cls;
        return el;
    };
    const action = (text, callback) => {
        const button = node('button', text, 'button button-secondary');
        button.type = 'button'; button.addEventListener('click', callback); return button;
    };
    function problem(q) {
        if (!q.prompt) return 'Missing question text';
        if (q.prompt.length > 2000) return 'Question text is too long';
        if (mode() === 'crossword') {
            const answer = (q.answer || '').replace(/[^a-z]/gi, '');
            if (answer.length < 3 || answer.length > 15) return 'Answer needs 3–15 letters';
        } else if (['fill_blank', 'emoji_quiz', 'flip_match'].includes(mode())) {
            if (!q.answer) return 'Missing answer';
            if (q.answer.length > 250) return 'Answer is too long';
            if (q.accepted_answers.length > 20) return 'Use up to 20 alternative answers';
        } else {
            if (q.options.some(value => !value)) return 'Missing answer option';
            if (!Number.isInteger(q.correct_index) || q.correct_index < 0 || q.correct_index > 3) return 'Select a correct answer';
        }
        if (!Number.isFinite(q.points) || q.points < 5 || q.points > 1000) return 'Points must be 5–1000';
        return '';
    }
    function showStep(value, focus = true) {
        if (Math.max(0, Math.min(lastStep, value)) !== step) visitedSteps.add(step);
        step = Math.max(0, Math.min(lastStep, value));
        form.querySelectorAll('[data-builder-step]').forEach(el => { el.hidden = Number(el.dataset.builderStep) !== step; });
        form.querySelectorAll('[data-step]').forEach(el => {
            const selected = Number(el.dataset.step) === step;
            el.setAttribute('aria-current', selected ? 'step' : 'false');
        });
        document.getElementById('builder-previous').disabled = step === 0;
        document.getElementById('builder-next').hidden = step === lastStep;
        document.getElementById('builder-save').hidden = step !== lastStep;
        document.getElementById('builder-step-summary').textContent = `Step ${step + 1} of ${stepNames.length} · ${stepNames[step]}`;
        refresh();
        if (focus) form.querySelector(`[data-step="${step}"]`).focus();
    }
    function configurationProblems(questions) {
        const issues = [];
        if (form.elements.grade_category_id?.value && form.elements.grade_max_score.value !== '') {
            const maximum = Number(form.elements.grade_max_score.value);
            if (!Number.isFinite(maximum) || maximum < .01 || maximum > 100000 || Math.abs(maximum * 100 - Math.round(maximum * 100)) > .00001) issues.push('Gradebook maximum must be 0.01–100000 with up to two decimal places.');
        }
        const target = Number(form.elements.mastery_threshold.value);
        if (target < 50 || target > 100 || !Number.isInteger(target)) issues.push('Mastery target must be 50–100%.');
        if (questions.length > 100) issues.push('Use up to 100 items.');
        if (mode() === 'master_ladder') {
            ['easy', 'medium', 'hard', 'master'].forEach(level => {
                if (!questions.some(q => q.level === level)) issues.push(`Add a ${level} question.`);
            });
        }
        if (mode() === 'crossword' || mode() === 'flip_match') {
            const minimum = mode() === 'crossword' ? 3 : 2;
            if (questions.length < minimum) issues.push(`Add at least ${minimum} items.`);
            if (mode() === 'flip_match' && questions.length > 100) issues.push('Use up to 100 matching pairs.');
            const answers = questions.map(q => mode() === 'crossword' ? (q.answer || '').replace(/[^a-z]/gi, '').toUpperCase() : q.answer_image || (q.answer || '').trim().toLowerCase());
            if (new Set(answers).size !== answers.length) issues.push('Use unique answers for every item.');
        }
        return issues;
    }
    function selectQuestion(index) {
        active = Math.max(0, Math.min(index, cards().length - 1));
        refresh();
        if (step === 1) cards()[active]?.querySelector('[data-field="prompt"]')?.focus();
    }
    function renderLive(q) {
        live.replaceChildren();
        live.append(node('small', window.quizBuilderModes[mode()]?.label || mode()));
        if (!q || !q.prompt) { live.append(node('p', 'Add a question to preview this activity.')); return; }
        const card = node('article', undefined, `preview-question mode-${mode()}`);
        card.append(node('small', `Question ${active + 1} · ${q.points} points`), node('h3', q.prompt));
        const feedback = node('p'); feedback.setAttribute('role', 'status');
        if (q.options?.length === 4 && mode() !== 'crossword') {
            const choices = node('div', undefined, 'preview-choices');
            q.options.forEach((option, index) => choices.append(action(option || `Option ${index + 1}`, () => {
                feedback.textContent = index === q.correct_index ? 'Correct — preview only.' : 'Try again — preview only.';
            })));
            card.append(choices);
        } else if (mode() === 'flip_match') {
            if (q.prompt_image && /^data:image\/(png|jpeg|webp);base64,/.test(q.prompt_image)) {
                const image = node('img', undefined, 'match-card-image'); image.src = q.prompt_image; image.alt = q.prompt; card.append(image);
            }
            card.append(action('Reveal matching card', () => {
                feedback.replaceChildren(node('span', q.answer || 'Add a matching label.'));
                if (q.answer_image && /^data:image\/(png|jpeg|webp);base64,/.test(q.answer_image)) {
                    const image = node('img', undefined, 'match-card-image'); image.src = q.answer_image; image.alt = q.answer; feedback.append(image);
                }
            }));
        } else {
            const label = node('label', mode() === 'crossword' ? 'Answer to this clue' : 'Your answer');
            const input = node('input'); input.type = 'text'; label.append(input);
            card.append(label, action('Check answer', () => {
                const normalize = value => q.case_sensitive ? value.trim() : value.trim().toLowerCase();
                const accepted = [q.answer || '', ...(q.accepted_answers || [])];
                feedback.textContent = input.value.trim() && accepted.some(answer => normalize(answer) === normalize(input.value)) ? 'Correct — preview only.' : 'Try again — preview only.';
            }));
        }
        card.append(feedback); live.append(card);
    }
    function refresh() {
        const questions = api.collect();
        updateStepCompletion(questions);
        active = Math.max(0, Math.min(active, questions.length - 1));
        cards().forEach((card, index) => { card.hidden = index !== active; });
        navigator.replaceChildren();
        questions.forEach((q, index) => {
            const issue = problem(q);
            const button = action(`Q${index + 1} · ${issue || 'Complete'}`, () => selectQuestion(index));
            button.setAttribute('aria-current', index === active ? 'true' : 'false');
            button.classList.toggle('is-incomplete', Boolean(issue)); navigator.append(button);
        });
        document.getElementById('duplicate-question').disabled = !questions.length || questions.length >= 100;
        document.getElementById('move-question-up').disabled = active === 0;
        document.getElementById('move-question-down').disabled = active >= questions.length - 1;
        document.getElementById('add-question-button').disabled = questions.length >= 100;
        renderLive(questions[active]);
        const settings = document.getElementById('builder-settings');
        const categorySelect = form.elements.grade_category_id;
        form.querySelector('[data-quiz-graded]')?.toggleAttribute('hidden', !categorySelect?.value);
        document.getElementById('mastery-target-setting').hidden = mode() !== 'master_ladder';
        window.quizBuilderThreshold = Number(form.elements.mastery_threshold.value);
        settings.replaceChildren(node('h3', window.quizBuilderModes[mode()]?.label), node('p', window.quizBuilderModes[mode()]?.description));
        if (mode() === 'master_ladder') settings.append(node('p', `Each difficulty needs at least one question. Level unlock target: ${window.quizBuilderThreshold}%.`));
        if (mode() === 'crossword') settings.append(node('p', 'At least three unique words must intersect. Preview Activity checks the complete grid.'));
        if (mode() === 'time_attack') settings.append(node('p', 'Each question allows 12 seconds. A timeout earns zero for that item.'));
        if (mode() === 'boss_battle') settings.append(node('p', 'Boss and shield start at 100. The run can end early when either reaches zero. Health never subtracts academic points.'));
        const review = document.getElementById('builder-review'); review.replaceChildren();
        const summary = node('table'); summary.className = 'formal-review-table';
        summary.append(node('caption', 'Activity summary'));
        const body = node('tbody');
        const rows = [
            ['Title', form.elements.title.value.trim() || 'Untitled activity'],
            ['Activity type', form.elements.activity_type?.value || 'Quiz'],
            ['Game mode', window.quizBuilderModes[mode()]?.label || mode()],
            ['Questions', String(questions.length)],
            ['Total points', String(questions.reduce((sum, q) => sum + q.points, 0))],
            ['Deadline', form.elements.due_at.value ? form.elements.due_at.value.replace('T', ' ') : 'No deadline'],
            ['Grade category', categorySelect?.value ? categorySelect.selectedOptions[0].textContent : 'Practice activity: excluded from classroom grades'],
            ['Gradebook maximum', categorySelect?.value ? String(form.elements.grade_max_score.value || questions.reduce((sum, q) => sum + q.points, 0)) : 'Not applicable'],
            ['Attempt policy', categorySelect?.value ? form.elements.grade_attempt_policy.selectedOptions[0].textContent : 'Not applicable']
        ];
        rows.forEach(([label, value]) => { const row = node('tr'), heading = node('th', label); heading.scope = 'row'; row.append(heading, node('td', value)); body.append(row); });
        summary.append(body); review.append(summary);
        review.append(action(form.elements.title.value.trim() ? '✓ Quiz information complete' : '! Quiz title is required', () => showStep(0)));
        const incomplete = questions.findIndex(q => problem(q));
        review.append(action(!questions.length ? '! Add a question' : incomplete < 0 ? '✓ Questions and answers complete' : `! Q${incomplete + 1}: ${problem(questions[incomplete])}`, () => { showStep(1); selectQuestion(Math.max(0, incomplete)); }));
        const issues = configurationProblems(questions);
        review.append(action(issues.length ? `! ${issues.join(' ')}` : '✓ Settings valid', () => showStep(2)));

        review.append(node('p', 'Preview validates game content without recording an attempt. Saving makes the activity available in this classroom. Grades are published separately.'));
        cards().forEach((card, index) => {
            let error = card.querySelector('.question-error');
            if (!error) { error = node('p', '', 'question-error'); error.setAttribute('role', 'status'); card.append(error); }
            error.textContent = problem(questions[index]);
        });
    }
    form.querySelectorAll('[data-step]').forEach(button => button.addEventListener('click', () => showStep(Number(button.dataset.step))));
    document.getElementById('builder-previous').addEventListener('click', () => showStep(step - 1));
    document.getElementById('builder-next').addEventListener('click', () => showStep(step + 1));
    document.getElementById('duplicate-question').addEventListener('click', () => {
        const q = api.collect()[active]; if (!q || cards().length >= 100) return;
        api.add(q); selectQuestion(cards().length - 1);
    });
    function move(delta) {
        const all = cards(), target = active + delta;
        if (target < 0 || target >= all.length) return;
        if (delta < 0) list.insertBefore(all[active], all[target]);
        else list.insertBefore(all[target], all[active]);
        active = target; api.refresh(); refresh(); saveLocalDraft();
    }
    document.getElementById('move-question-up').addEventListener('click', () => move(-1));
    document.getElementById('move-question-down').addEventListener('click', () => move(1));
    document.getElementById('add-question-button').addEventListener('click', () => selectQuestion(cards().length - 1));
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    form.addEventListener('activity:changed', refresh);
    ['input', 'change', 'activity:changed'].forEach(type => form.addEventListener(type, () => { clearTimeout(draftTimer); draftTimer = setTimeout(saveLocalDraft, 300); }));
    document.getElementById('builder-recovery').hidden = !recoveredDraft || ![1, 2].includes(recoveredDraft.version);
    document.getElementById('restore-builder-draft').addEventListener('click', () => {
        if (!recoveredDraft || !Array.isArray(recoveredDraft.questions) || recoveredDraft.questions.length > 100) return;
        Object.entries(recoveredDraft.fields).forEach(([name, value]) => { if (name !== 'game_type' && form.elements[name] && typeof value === 'string') form.elements[name].value = value; });
        list.replaceChildren();
        if (api.addMany) api.addMany(recoveredDraft.questions);
        else recoveredDraft.questions.forEach(q => api.add(q));
        form.elements.game_type.dispatchEvent(new Event('change', {bubbles: true}));
        const restoredStep = recoveredDraft.version === 1 ? Math.max(0, Number(recoveredDraft.step || 0) - 1) : Number(recoveredDraft.step || 0);
        document.getElementById('builder-recovery').hidden = true; showStep(restoredStep);
        status.textContent = 'Local draft restored. Preview and save when ready.';
    });
    document.getElementById('discard-builder-draft').addEventListener('click', () => {
        try { localStorage.removeItem(draftKey); } catch (_) { /* Storage may be disabled. */ }
        recoveredDraft = null; document.getElementById('builder-recovery').hidden = true;
    });
    document.getElementById('toggle-live-preview').addEventListener('click', event => {
        const panel = document.getElementById('builder-live-preview');
        const visible = panel.classList.toggle('preview-open');
        event.currentTarget.setAttribute('aria-expanded', String(visible));
        document.querySelector('.builder-panel').classList.toggle('preview-collapsed', !visible);
    });
    window.addEventListener('beforeunload', event => {
        if (!leaving && snapshot() !== original) { saveLocalDraft(); event.preventDefault(); event.returnValue = ''; }
    });
    window.addEventListener('pageshow', event => { if (event.persisted) { submitting = false; leaving = false; } });
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (link && !link.target && !link.getAttribute('href').startsWith('#') && snapshot() !== original && !leaving) {
            if (!confirm("You have changes that haven't been saved. Leave anyway?")) event.preventDefault();
            else { saveLocalDraft(); leaving = true; }
        }
    });
    window.quizBuilderWorkflow = true;
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submitting) return;
        if (api.imagesPending?.()) { status.textContent = 'Wait for the image upload to finish, then save again.'; return; }
        const questions = api.collect();
        if (!form.elements.title.value.trim() || form.elements.title.value.length > 255) {
            showStep(0); status.textContent = 'Quiz title is required (up to 255 characters).'; form.elements.title.focus(); return;
        }
        const invalid = questions.findIndex(q => problem(q));
        if (!questions.length || invalid >= 0) {
            showStep(1); selectQuestion(Math.max(0, invalid)); status.textContent = questions.length ? `Q${invalid + 1}: ${problem(questions[invalid])}.` : 'Add a question.'; return;
        }
        if (configurationProblems(questions).length) {
            showStep(lastStep); status.textContent = configurationProblems(questions).join(' '); return;
        }
        if (window.chalkConfirm && !await window.chalkConfirm('Save quiz?', 'Save this quiz and its questions to the classroom?', 'Save Quiz')) return;
        if (submitting) return;
        submitting = true;
        const buttons = [...form.querySelectorAll('[type="submit"]')]; buttons.forEach(button => { button.disabled = true; });
        status.textContent = 'Checking activity…';
        try {
            form.dispatchEvent(new Event('activity:collect'));
            const checkedDraft = snapshot();
            const data = new FormData(form); data.set('action', 'preview');
            const response = await fetch(form.action || location.href, {method: 'POST', body: data, signal: AbortSignal.timeout(15000)});
            if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Your session expired. Sign in again before saving.');
            const result = await response.json();
            if (snapshot() !== checkedDraft) {
                status.textContent = 'Your draft changed during validation. Review and save again.';
                submitting = false; buttons.forEach(button => { button.disabled = false; }); return;
            }
            if (!response.ok || result.errors?.length) {
                showStep(lastStep); status.textContent = (result.errors || ['Could not validate this activity.']).join(' ');
                const match = status.textContent.match(/(?:Question|Item|word) (\d+)/i);
                if (match) { showStep(1); selectQuestion(Number(match[1]) - 1); }
                submitting = false; buttons.forEach(button => { button.disabled = false; }); return;
            }
            status.textContent = 'Saving activity…'; clearTimeout(draftTimer); saveLocalDraft(); leaving = true; window.chalkLoading?.carry(); window.chalkLoading?.begin('Saving...', 0, 'quiz'); HTMLFormElement.prototype.submit.call(form);
        } catch (error) {
            status.textContent = error.name === 'TimeoutError' ? 'Validation timed out. Your draft is still here. Try again.' : error.message;
            submitting = false; leaving = false; buttons.forEach(button => { button.disabled = false; });
        }
    });
    const wide = window.matchMedia('(min-width: 1101px)').matches;
    document.getElementById('builder-live-preview').classList.toggle('preview-open', wide);
    document.getElementById('toggle-live-preview').setAttribute('aria-expanded', String(wide));
    showStep(window.quizBuilderUnsaved ? lastStep : 0, false);
})();
