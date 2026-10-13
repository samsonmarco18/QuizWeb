(() => {
    const panel = document.getElementById('ai-quiz-panel');
    const form = document.getElementById('quiz-builder-form');
    const api = window.quizBuilder;
    if (!panel || !form || !api) return;
    const get = name => document.getElementById(`ai-quiz-${name}`);
    const mode = () => form.elements.game_type.value;
    const supported = () => Boolean(window.quizBuilderModes?.[mode()]);
    const textMode = value => ['crossword', 'fill_blank', 'emoji_quiz', 'flip_match'].includes(value);
    const countLimits = () => ({min: mode() === 'master_ladder' ? 4 : (mode() === 'flip_match' && get('difficulty').value !== 'mixed' ? 2 : 3), max: mode() === 'flip_match' ? 12 : 20});
    let busy = false;
    let draft = null;
    let draftMode = '';
    let progressTimer = null;
    let progressStartedAt = 0;
    const initialRemaining = get('remaining').dataset.remaining;
    let remaining = /^(?:[0-9]|10)$/.test(initialRemaining) ? Number(initialRemaining) : null;
    function setRemaining(value) {
        if (!Number.isInteger(value) || value < 0 || value > 10) return;
        remaining = value;
        get('remaining').dataset.remaining = String(value);
        get('remaining').textContent = `${value} / 10 attempts left today`;
    }
    function update() {
        const notes = {
            crossword: 'Lesson words are checked in a real grid before review. Each draft has the requested number of connected words (3–15 letters). Preview again when combining with existing words.',
            fill_blank: 'Generate sentences with a blank, an expected answer, optional alternatives, and a hint.',
            emoji_quiz: 'Generate emoji clues, expected answers, optional alternatives, and explanations. Review each clue for clarity.',
            flip_match: 'Generate distinct term-and-definition pairs. An activity supports up to 12 pairs; you can add your own images after importing.',
            master_ladder: 'Mixed difficulty includes Easy, Medium, Hard, and Master. Generate at least 4 questions.'
        };
        get('mode-note').textContent = supported() ? (notes[mode()] || '') : 'Choose an activity type to generate a draft.';
        get('mode-note').hidden = supported() && !notes[mode()];
        get('description').textContent = textMode(mode()) ? 'Create activity drafts from your lesson. Review the clues, answers, and explanations before adding them.' : 'Create multiple-choice drafts from your lesson. Review the answers before adding them to your quiz.';
        get('count-label').textContent = mode() === 'crossword' ? 'Words' : (mode() === 'flip_match' ? 'Matching pairs' : 'Questions');
        if (mode() === 'master_ladder') get('difficulty').value = 'mixed';
        [...get('difficulty').options].forEach(option => { option.disabled = mode() === 'master_ladder' && option.value !== 'mixed'; });
        const limits = countLimits();
        get('count').min = limits.min;
        get('count').max = limits.max;
        if (get('count').value !== '') get('count').value = Math.max(limits.min, Math.min(limits.max, Number(get('count').value)));
        get('generate').disabled = busy || remaining === 0 || !supported() || panel.dataset.ready !== 'true';
        get('generate').textContent = busy ? 'Generating…' : 'Generate draft';
        get('add').disabled = busy || !draft || draftMode !== mode();
        get('discard').disabled = busy;
        panel.setAttribute('aria-busy', String(busy));
    }
    function progressStage(stage) {
        get('progress').querySelectorAll('[data-ai-stage]').forEach((item, index) => {
            item.classList.toggle('is-complete', index < stage);
            if (index === stage && stage < 2) item.setAttribute('aria-current', 'step');
            else item.removeAttribute('aria-current');
        });
    }
    function progressElapsed() {
        const seconds = Math.max(0, Math.floor((Date.now() - progressStartedAt) / 1000));
        get('elapsed').textContent = `${seconds}s elapsed`;
        return seconds;
    }
    function beginProgress() {
        clearInterval(progressTimer);
        progressStartedAt = Date.now();
        get('progress').hidden = false;
        get('progress').dataset.state = 'loading';
        get('progress-label').textContent = 'Generating your draft…';
        get('progress-note').textContent = 'You can keep editing while the generator works.';
        get('progress-bar').hidden = false;
        get('progress-bar').removeAttribute('aria-valuenow');
        get('progress-bar').setAttribute('aria-valuetext', 'Waiting for the generator');
        progressStage(0);
        progressElapsed();
        progressTimer = setInterval(() => {
            if (progressElapsed() >= 30 && get('progress').querySelector('[data-ai-stage="0"]')?.hasAttribute('aria-current')) {
                get('progress-label').textContent = 'Still waiting for your draft…';
                get('progress-note').textContent = 'Google may be busy. Keep this page open while we wait for the result.';
            }
        }, 1000);
    }
    function finishProgress(success) {
        clearInterval(progressTimer);
        progressTimer = null;
        progressElapsed();
        get('progress').dataset.state = success ? 'complete' : 'error';
        get('progress-label').textContent = success ? 'Draft ready for review' : 'Generation stopped';
        get('progress-note').textContent = success ? 'Review the questions before adding them to your quiz.' : 'Your existing quiz questions are unchanged.';
        if (success) {
            progressStage(3);
            get('progress-bar').setAttribute('aria-valuemin', '0');
            get('progress-bar').setAttribute('aria-valuemax', '100');
            get('progress-bar').setAttribute('aria-valuenow', '100');
            get('progress-bar').setAttribute('aria-valuetext', 'Draft ready for review');
        } else {
            get('progress-bar').hidden = true;
            get('progress').querySelectorAll('[aria-current]').forEach(item => item.removeAttribute('aria-current'));
        }
    }
    function element(tag, text) {
        const el = document.createElement(tag);
        el.textContent = text;
        return el;
    }
    function preview(questions, activityMode) {
        get('preview').replaceChildren();
        questions.forEach((q, index) => {
            const row = document.createElement('details');
            row.append(element('summary', `${index + 1}. ${q.prompt} (${q.level})`));
            if (textMode(activityMode)) {
                row.append(element('p', `${activityMode === 'flip_match' ? 'Matching answer' : 'Answer'}: ${q.answer}`));
                if (q.accepted_answers?.length) row.append(element('p', `Also accepted: ${q.accepted_answers.join(', ')}`));
                if (q.hint) row.append(element('p', `Hint: ${q.hint}`));
            } else {
                const choices = document.createElement('ol');
                choices.type = 'A';
                q.options.forEach((value, choice) => {
                    const item = element('li', value + (choice === q.correct_index ? ' — Correct answer' : ''));
                    if (choice === q.correct_index) item.className = 'ai-correct';
                    choices.append(item);
                });
                row.append(choices);
            }
            row.append(element('p', q.explanation));
            get('preview').append(row);
        });
        get('draft').hidden = false;
    }
    get('generate').addEventListener('click', async () => {
        if (busy || remaining === 0 || !supported() || panel.dataset.ready !== 'true') return;
        const count = Number(get('count').value);
        const limits = countLimits();
        if (!Number.isInteger(count) || count < limits.min || count > limits.max) { get('status').textContent = `Choose ${limits.min}–${limits.max} ${mode() === 'flip_match' ? 'matching pairs' : 'items'}.`; return; }
        if (mode() === 'master_ladder' && (count < 4 || get('difficulty').value !== 'mixed')) { get('status').textContent = 'Master Ladder needs at least 4 questions and mixed difficulty.'; return; }
        const text = get('source').value.trim();
        const file = get('file').files?.[0];
        if (!text && !file) { get('status').textContent = 'Paste lesson text or attach a PDF/TXT file first.'; get('source').focus(); return; }
        if (new TextEncoder().encode(text).length > 60000 || (file && file.size > 5 * 1024 * 1024)) { get('status').textContent = 'Use text up to 60 KB and a PDF up to 5 MB.'; return; }
        const requestedMode = mode();
        const data = new FormData();
        data.set('csrf', form.elements.csrf.value);
        data.set('classroom_id', panel.dataset.classroomId);
        data.set('game_type', requestedMode);
        data.set('source_text', text);
        data.set('count', String(count));
        data.set('difficulty', get('difficulty').value);
        data.set('language', get('language').value);
        data.set('topic', get('topic').value);
        if (file) data.set('lesson', file);
        busy = true;
        update();
        beginProgress();
        get('status').textContent = '';
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 80000);
        try {
            const response = await fetch('/QuizWeb/ai_quiz_generate.php', {method: 'POST', body: data, signal: controller.signal});
            progressStage(1);
            get('progress-label').textContent = 'Reading the generator response…';
            get('progress-bar').setAttribute('aria-valuetext', 'Reading the generator response');
            if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Your session may have expired. Reload the builder and sign in again.');
            const result = await response.json();
            setRemaining(result.remaining);
            if (!response.ok) {
                const quota = Number.isInteger(result.remaining) ? ` ${result.remaining} application requests left today.` : '';
                throw new Error((result.error || 'Could not generate questions. Try again later.') + quota);
            }
            if (!Array.isArray(result.questions) || !result.questions.length) throw new Error('No questions were generated. Try more lesson content.');
            draft = result.questions;
            draftMode = requestedMode;
            preview(draft, requestedMode);
            finishProgress(true);
            get('status').textContent = `${draft.length} questions ready for review. ${result.remaining} requests left today.` + (result.used_backup && result.model ? ` Generated with backup model ${result.model}.` : '') + (result.warning ? ` ${result.warning}` : '') + (mode() !== draftMode ? ' Return to the original game mode to add these questions.' : '');
        } catch (error) {
            finishProgress(false);
            get('status').textContent = error.name === 'AbortError' ? 'Generation timed out. Your quiz is unchanged. Try fewer questions; this request may have used quota.' : error.message;
        } finally {
            clearTimeout(timer);
            busy = false;
            update();
        }
    });
    get('add').addEventListener('click', () => {
        if (!draft || busy || mode() !== draftMode) return;
        const existing = api.collect();
        const blank = existing.length === 1 && !existing[0].prompt && existing[0].options?.every(value => !value) && !existing[0].answer && !existing[0].topic && !existing[0].explanation && !existing[0].hint && !existing[0].accepted_answers?.length && !existing[0].prompt_image && !existing[0].answer_image;
        const kept = blank ? 0 : existing.length;
        const maximum = mode() === 'flip_match' ? 12 : 40;
        if (kept + draft.length > maximum) { get('status').textContent = `An activity supports up to ${maximum} ${mode() === 'flip_match' ? 'matching pairs' : 'questions'}. Remove some existing items before adding this draft.`; return; }
        if (!blank && ['crossword', 'flip_match'].includes(mode())) {
            const normalize = value => mode() === 'crossword' ? (value || '').replace(/[^a-z]/gi, '').toUpperCase() : (value || '').trim().toLowerCase();
            const answers = new Set(existing.filter(q => !q.answer_image).map(q => normalize(q.answer)));
            const prompts = new Set(existing.filter(q => !q.prompt_image).map(q => normalize(q.prompt)));
            if (draft.some(q => answers.has(normalize(q.answer)) || (mode() === 'flip_match' && prompts.has(normalize(q.prompt))))) {
                get('status').textContent = 'Some generated words or matching pairs already exist in your activity. Remove the overlapping items before adding this draft.';
                return;
            }
        }
        if (blank) document.getElementById('question-list').replaceChildren();
        const added = draft.length;
        draft.forEach(question => api.add(question));
        api.refresh();
        form.dispatchEvent(new Event('activity:changed'));
        draft = null;
        get('progress').hidden = true;
        get('draft').hidden = true;
        get('preview').replaceChildren();
        get('status').textContent = `${added} questions added to your unsaved quiz. Review, edit, and save through the normal builder steps.`;
        document.querySelectorAll('#question-navigator button')[kept]?.click();
        update();
    });
    get('discard').addEventListener('click', () => {
        draft = null;
        get('progress').hidden = true;
        get('draft').hidden = true;
        get('preview').replaceChildren();
        get('status').textContent = 'AI draft discarded. Your quiz questions are unchanged.';
        update();
    });
    form.elements.game_type.addEventListener('change', update);
    get('difficulty').addEventListener('change', update);
    panel.addEventListener('keydown', event => {
        if (event.key === 'Enter' && event.target.tagName === 'INPUT' && !['file', 'button', 'submit'].includes(event.target.type)) event.preventDefault();
    });
    update();
})();
