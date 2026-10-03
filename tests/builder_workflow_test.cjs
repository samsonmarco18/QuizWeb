// Run after: npm install --prefix data/qa jsdom --no-save --no-package-lock --ignore-scripts
const {JSDOM} = require('../data/qa/node_modules/jsdom');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const source = fs.readFileSync('app/pages/quizzes/quiz_builder.php', 'utf8');
const html = source.slice(source.indexOf('<section class="glass panel builder-panel">'), source.indexOf('<script>\nwindow.quizBuilderSeed'))
    .replace(/<\?php[\s\S]*?\?>/g, '');
function setup(seed = [], mode = 'standard') {
    const dom = new JSDOM(`<!doctype html><body class="ui-refined builder-page">${html}<dialog id="activity-preview"><h2 id="preview-title"></h2><p id="preview-status"></p><div id="preview-content"></div></dialog>`, {url: 'http://localhost/QuizWeb/quiz_builder.php?classroom_id=1', runScripts: 'outside-only'});
    const w = dom.window;
    w.matchMedia = () => ({matches: true});
    w.confirm = () => true;
    w.alert = message => { throw new Error('Unexpected generic alert: ' + message); };
    w.quizBuilderSeed = seed; w.quizBuilderMode = mode;
    w.quizBuilderThreshold = 75;
    w.quizBuilderModes = Object.fromEntries(['standard', 'fill_blank', 'crossword', 'flip_match', 'master_ladder'].map(key => [key, {label: key, description: 'Game rules'}]));
    w.document.querySelector('[name="game_type"]').innerHTML = Object.keys(w.quizBuilderModes).map(key => `<option value="${key}">${key}</option>`).join('');
    w.document.querySelector('[name="game_type"]').value = mode;
    w.document.querySelector('[name="mastery_threshold"]').value = '75';
    w.document.querySelector('[name="grade_category_id"]').innerHTML = '<option value="">Practice</option><option value="quiz">Quizzes</option>';
    w.document.querySelector('[name="grade_attempt_policy"]').innerHTML = ['highest', 'latest', 'first', 'average'].map(key => `<option value="${key}">${key}</option>`).join('');
    w.document.getElementById('question-template').content.querySelector('[data-field="level"]').innerHTML = ['easy', 'medium', 'hard', 'master'].map(key => `<option>${key}</option>`).join('');
    w.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    w.eval(fs.readFileSync('assets/js/site.js', 'utf8'));
    w.eval(fs.readFileSync('assets/js/builder-workflow.js', 'utf8'));
    w.eval(fs.readFileSync('assets/js/builder-preview.js', 'utf8'));
    return {w, doc: w.document, click: id => w.document.getElementById(id).click(), step: index => w.document.querySelector(`[data-step="${index}"]`).click(), close: () => dom.window.close()};
}
const seed = [
    {prompt: 'First question', options: ['One', 'Two', 'Three', 'Four'], correct_index: 0, points: 10, level: 'easy'},
    {prompt: 'Second question', options: ['A', 'B', 'C', 'D'], correct_index: 2, points: 20, level: 'medium'}
];
const a = setup(seed);
assert.equal(a.doc.querySelectorAll('#question-list .question-card:not([hidden])').length, 1);
a.step(1);
a.doc.querySelectorAll('#question-navigator button')[1].click();
assert.equal(a.doc.querySelector('.question-card:not([hidden]) textarea').value, 'Second question');
a.click('duplicate-question');
assert.equal(a.w.quizBuilder.collect().length, 3);
a.click('move-question-up');
assert.equal(a.w.quizBuilder.collect()[1].prompt, 'Second question');
a.doc.querySelector('.question-card:not([hidden]) textarea').value = 'Modified duplicate';
a.doc.querySelector('.question-card:not([hidden]) textarea').dispatchEvent(new a.w.Event('input', {bubbles: true}));
assert.match(a.doc.getElementById('live-preview-content').textContent, /Modified duplicate/);
a.step(2); a.step(0); a.step(1);
assert.equal(a.w.quizBuilder.collect()[1].prompt, 'Modified duplicate');
a.doc.querySelector('[name="game_type"]').value = 'fill_blank';
a.doc.querySelector('[name="game_type"]').dispatchEvent(new a.w.Event('change', {bubbles: true}));
assert.equal(a.w.quizBuilder.collect()[1].answer, 'C');
a.doc.querySelector('[name="game_type"]').value = 'standard';
a.doc.querySelector('[name="game_type"]').dispatchEvent(new a.w.Event('change', {bubbles: true}));
assert.deepEqual(Array.from(a.w.quizBuilder.collect()[1].options), ['A', 'B', 'C', 'D']);
const leave = new a.w.Event('beforeunload', {cancelable: true}); a.w.dispatchEvent(leave);
assert(leave.defaultPrevented, 'Dirty drafts must warn before leaving');
a.doc.querySelector('.question-card:not([hidden]) .remove-question').click();
assert.equal(a.w.quizBuilder.collect().length, 2);
const gradeCategory = a.doc.querySelector('[name="grade_category_id"]');
gradeCategory.value = 'quiz'; gradeCategory.dispatchEvent(new a.w.Event('change', {bubbles: true}));
assert(!a.doc.querySelector('[data-quiz-graded]').hidden);
const gradeMaximum = a.doc.querySelector('[name="grade_max_score"]');
gradeMaximum.value = '50'; gradeMaximum.dispatchEvent(new a.w.Event('input', {bubbles: true}));
const gradePolicy = a.doc.querySelector('[name="grade_attempt_policy"]');
gradePolicy.value = 'latest'; gradePolicy.dispatchEvent(new a.w.Event('change', {bubbles: true}));
assert.match(a.doc.getElementById('builder-review').textContent, /Grade category: Quizzes.*Gradebook maximum: 50.*latest/);
gradeCategory.value = ''; gradeCategory.dispatchEvent(new a.w.Event('change', {bubbles: true}));
assert(a.doc.querySelector('[data-quiz-graded]').hidden);
assert.match(a.doc.getElementById('builder-review').textContent, /Practice activity/);
a.close();
const conversion = setup(seed);
const convertedCard = conversion.doc.querySelectorAll('.question-card')[1];
convertedCard.querySelector('[data-field="correct_index"]').value = '3';
convertedCard.querySelector('[data-field="option-3"]').value = 'Updated D';
const changeMode = mode => {
    conversion.doc.querySelector('[name="game_type"]').value = mode;
    conversion.doc.querySelector('[name="game_type"]').dispatchEvent(new conversion.w.Event('change', {bubbles: true}));
};
changeMode('fill_blank');
assert.equal(conversion.w.quizBuilder.collect()[1].answer, 'Updated D', 'Mode conversion uses the current selected answer');
const explicitAnswer = convertedCard.querySelector('[data-field="answer"]');
explicitAnswer.value = 'Teacher-written answer';
explicitAnswer.dispatchEvent(new conversion.w.Event('input', {bubbles: true}));
changeMode('standard'); changeMode('fill_blank');
assert.equal(conversion.w.quizBuilder.collect()[1].answer, 'Teacher-written answer', 'Explicit text answers survive mode round trips');
conversion.close();
const b = setup(seed);
const cleanLeave = new b.w.Event('beforeunload', {cancelable: true}); b.w.dispatchEvent(cleanLeave);
assert(!cleanLeave.defaultPrevented, 'Unchanged editing must not warn');
b.doc.querySelector('[name="title"]').value = 'Valid quiz';
b.doc.querySelectorAll('.question-card')[1].querySelector('[data-field="prompt"]').value = '';
b.doc.querySelector('form').dispatchEvent(new b.w.Event('submit', {bubbles: true, cancelable: true}));
assert.match(b.doc.getElementById('builder-status').textContent, /Q2: Missing question text/);
assert.equal(b.doc.querySelector('.question-card:not([hidden]) [data-question-label]').textContent, 'Question 2');
b.close();
(async () => {
    const c = setup(seed);
    c.doc.querySelector('[name="title"]').value = 'Play test draft';
    let submitted = 0, requests = 0;
    c.w.HTMLFormElement.prototype.submit = function () { submitted++; };
    c.w.fetch = async (url, request) => {
        requests++;
        assert.equal(request.body.get('action'), 'preview');
        return {ok: true, headers: {get: () => 'application/json'}, json: async () => ({title: 'Play test draft', game_type: 'standard', questions: seed, errors: []})};
    };
    c.click('play-test');
    await new Promise(resolve => setTimeout(resolve, 10));
    const iframe = c.doc.querySelector('#preview-content iframe');
    assert(iframe, 'Play test must load an isolated game renderer');
    assert.equal(iframe.getAttribute('sandbox'), 'allow-scripts');
    assert.match(iframe.srcdoc, /data-is-preview="1"/);
    assert.match(iframe.srcdoc, /connect-src 'none'/);
    assert.match(iframe.srcdoc, /form-action 'none'/);
    assert.match(iframe.srcdoc, /assets\/js\/game.js/);
    assert.equal(submitted, 0, 'Preview must never submit a save');
    // Exercise the real gameplay renderer using the isolated preview markup.
    for (const mode of ['standard', 'time_attack', 'rocket_rush', 'memory_flip', 'treasure_dive', 'boss_battle', 'master_ladder', 'crossword', 'fill_blank', 'emoji_quiz', 'flip_match']) {
        const game = new JSDOM(iframe.srcdoc, {url: 'http://localhost/QuizWeb/', runScripts: 'outside-only', pretendToBeVisual: true});
        const gw = game.window;
        const root = gw.document.querySelector('[data-game-root]');
        let quiz = {title: 'Preview', game_type: mode, questions: [{id: 1, ...seed[0]}]};
        if (mode === 'master_ladder') quiz.questions = ['easy', 'medium', 'hard', 'master'].map((level, index) => ({...seed[0], id: index + 1, level}));
        if (mode === 'crossword') {
            const php = process.env.PHP_BINARY || (fs.existsSync('C:/xampppp/php/php.exe') ? 'C:/xampppp/php/php.exe' : 'php');
            quiz = JSON.parse(require('node:child_process').execFileSync(php, ['tests/preview_fixture.php'], {encoding: 'utf8'}));
        }
        const textMode = ['fill_blank', 'emoji_quiz', 'flip_match'].includes(mode);
        if (textMode) quiz.questions = [{id: 1, prompt: 'Language', answer: 'CSS', points: 10, accepted_answers: ['Styles'], options: []}];
        root.dataset.quiz = JSON.stringify(quiz);
        gw.HTMLFormElement.prototype.submit = () => { throw new Error('Preview submitted a result'); };
        gw.fetch = () => { throw new Error('Preview wrote to the network'); };
        gw.eval(fs.readFileSync(`assets/js/${textMode ? 'activity-game.js' : 'game.js'}`, 'utf8'));
        gw.document.querySelector('[data-game-controls] .button-primary').click();
        await new Promise(resolve => setTimeout(resolve, 5));
        if (mode === 'crossword') {
            for (const input of gw.document.querySelectorAll('.crossword-cell-input')) input.value = quiz.crossword_layout.cells[Number(input.dataset.row)][Number(input.dataset.col)];
            gw.document.querySelector('[data-game-controls] .button-primary').click();
        } else if (mode === 'flip_match') {
            gw.document.querySelectorAll('.match-card').forEach(card => card.click());
            gw.document.querySelector('[data-game-controls] .button-primary').click();
        } else if (textMode) {
            gw.document.querySelector('.activity-answer input').value = 'Styles';
            gw.document.querySelector('[data-game-controls] .button-primary').click();
            gw.document.querySelector('[data-game-controls] .button-primary').click();
        } else {
            assert.equal(gw.document.querySelector('[data-question-text]').textContent, 'First question', `${mode} must render the saved question model`);
            for (let index = 0; index < quiz.questions.length; index++) {
                gw.document.querySelector('[data-answer-grid] button').click();
                gw.document.querySelector('[data-game-controls] .button-primary').click();
            }
        }
        assert.match(gw.document.querySelector('[data-game-note]').textContent, textMode ? /No score or attempt was saved/ : /previews are not saved/);
        game.window.close();
    }
    c.doc.querySelector('form').dispatchEvent(new c.w.Event('submit', {bubbles: true, cancelable: true}));
    await new Promise(resolve => setTimeout(resolve, 10));
    assert.equal(submitted, 1, 'Validated save must retain the existing POST behavior');
    assert.equal(requests, 2);
    c.close();
    const d = setup(seed);
    d.doc.querySelector('[name="title"]').value = 'Draft being validated';
    let release;
    let delayedSaves = 0;
    d.w.HTMLFormElement.prototype.submit = () => { delayedSaves++; };
    d.w.fetch = () => new Promise(resolve => { release = resolve; });
    d.doc.querySelector('form').dispatchEvent(new d.w.Event('submit', {bubbles: true, cancelable: true}));
    const duringValidation = new d.w.Event('beforeunload', {cancelable: true}); d.w.dispatchEvent(duringValidation);
    assert(duringValidation.defaultPrevented, 'Validation in flight must not suppress unsaved warnings');
    d.doc.querySelector('[name="title"]').value = 'Changed while checking';
    release({ok: true, headers: {get: () => 'application/json'}, json: async () => ({errors: []})});
    await new Promise(resolve => setTimeout(resolve, 5));
    assert.equal(delayedSaves, 0, 'Changes after validation started must never submit an outdated payload');
    assert.match(d.doc.getElementById('builder-status').textContent, /draft changed during validation/);
    assert.equal(d.doc.querySelector('[name="title"]').value, 'Changed while checking');
    d.close();
    console.log('Builder editing, steps, duplication, reorder, mode changes, validation, unsaved guards, isolated play test, and save checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
