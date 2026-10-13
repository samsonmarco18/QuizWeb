(() => {
    const panel = document.getElementById('ai-quiz-panel');
    const form = document.getElementById('quiz-builder-form');
    const api = window.quizBuilder;
    if (!panel || !form || !api) return;
    const get = name => document.getElementById(`ai-quiz-${name}`);
    const mode = () => form.elements.game_type.value;
    const supported = () => !['crossword', 'fill_blank', 'emoji_quiz', 'flip_match'].includes(mode());
    let busy = false;
    let draft = null;
    let draftMode = '';
    function update() {
        get('mode-note').hidden = supported();
        get('generate').disabled = busy || !supported() || panel.dataset.ready !== 'true';
        get('add').disabled = busy || !draft || draftMode !== mode();
        get('discard').disabled = busy;
        panel.setAttribute('aria-busy', String(busy));
    }
    function element(tag, text) {
        const el = document.createElement(tag);
        el.textContent = text;
        return el;
    }
    function preview(questions) {
        get('preview').replaceChildren();
        questions.forEach((q, index) => {
            const row = document.createElement('details');
            row.append(element('summary', `${index + 1}. ${q.prompt} (${q.level})`));
            const choices = document.createElement('ol');
            choices.type = 'A';
            q.options.forEach((value, choice) => {
                const item = element('li', value + (choice === q.correct_index ? ' — Correct answer' : ''));
                if (choice === q.correct_index) item.className = 'ai-correct';
                choices.append(item);
            });
            row.append(choices, element('p', q.explanation));
            get('preview').append(row);
        });
        get('draft').hidden = false;
    }
    get('generate').addEventListener('click', async () => {
        if (busy || !supported() || panel.dataset.ready !== 'true') return;
        const count = Number(get('count').value);
        if (!Number.isInteger(count) || count < 3 || count > 20) { get('status').textContent = 'Choose 3–20 questions.'; return; }
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
        get('status').textContent = 'Generating your draft. You can continue editing your quiz…';
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 80000);
        try {
            const response = await fetch('/QuizWeb/ai_quiz_generate.php', {method: 'POST', body: data, signal: controller.signal});
            if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Your session may have expired. Reload the builder and sign in again.');
            const result = await response.json();
            if (!response.ok) {
                const quota = Number.isInteger(result.remaining) ? ` ${result.remaining} application requests left today.` : '';
                throw new Error((result.error || 'Could not generate questions. Try again later.') + quota);
            }
            if (!Array.isArray(result.questions) || !result.questions.length) throw new Error('No questions were generated. Try more lesson content.');
            draft = result.questions;
            draftMode = requestedMode;
            preview(draft);
            get('status').textContent = `${draft.length} questions ready for review. ${result.remaining} requests left today.` + (result.warning ? ` ${result.warning}` : '') + (mode() !== draftMode ? ' Return to the original game mode to add these questions.' : '');
        } catch (error) {
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
        const blank = existing.length === 1 && !existing[0].prompt && existing[0].options?.every(value => !value) && !existing[0].answer && !existing[0].topic && !existing[0].explanation;
        const kept = blank ? 0 : existing.length;
        if (kept + draft.length > 40) { get('status').textContent = 'A quiz supports up to 40 questions. Remove some existing questions before adding this draft.'; return; }
        if (blank) document.getElementById('question-list').replaceChildren();
        const added = draft.length;
        draft.forEach(question => api.add(question));
        api.refresh();
        form.dispatchEvent(new Event('activity:changed'));
        draft = null;
        get('draft').hidden = true;
        get('preview').replaceChildren();
        get('status').textContent = `${added} questions added to your unsaved quiz. Review, edit, and save through the normal builder steps.`;
        document.querySelectorAll('#question-navigator button')[kept]?.click();
        update();
    });
    get('discard').addEventListener('click', () => {
        draft = null;
        get('draft').hidden = true;
        get('preview').replaceChildren();
        get('status').textContent = 'AI draft discarded. Your quiz questions are unchanged.';
        update();
    });
    form.elements.game_type.addEventListener('change', update);
    panel.addEventListener('keydown', event => {
        if (event.key === 'Enter' && event.target.tagName === 'INPUT' && !['file', 'button', 'submit'].includes(event.target.type)) event.preventDefault();
    });
    update();
})();
