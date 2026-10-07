const {JSDOM, VirtualConsole} = require('../data/qa/node_modules/jsdom');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const php = process.env.PHP_BINARY || (process.platform === 'win32' ? 'C:/xampppp/php/php.exe' : 'php');
const render = options => execFileSync(php, ['tests/classroom_layout_fixture.php'], {input: JSON.stringify(options || {}), encoding: 'utf8'});
const tick = () => new Promise(resolve => setImmediate(resolve));
const source = fs.readFileSync('assets/js/classroom.js', 'utf8');
function setup(options = {}) {
    const errors = [], output = new VirtualConsole();
    output.on('jsdomError', error => errors.push(error.message));
    const dom = new JSDOM(render(options), {url: 'http://localhost/QuizWeb/classroom.php?id=1&tab=quizzes', runScripts: 'outside-only', virtualConsole: output});
    const w = dom.window, d = w.document, requests = [], clipboard = [], timers = new Map(); let id = 0;
    w.setTimeout = (fn, ms) => { timers.set(++id, {fn, ms}); return id; }; w.clearTimeout = key => timers.delete(key);
    Object.defineProperty(w.navigator, 'clipboard', {value: {writeText: async text => { clipboard.push(text); }}, configurable: true});
    w.fetch = async (url, settings) => {
        requests.push({url, settings});
        return {ok: true, text: async () => render({...options, query: Object.fromEntries(new URL(url).searchParams)})};
    };
    d.body.insertAdjacentHTML('beforeend', '<div data-messenger-dock data-default-chat-id="1"><button data-messenger-target="messenger-thread-1" data-chat-id="1">Conversation</button></div>');
    let opened = 0; d.querySelector('[data-messenger-target]').addEventListener('click', () => opened++);
    w.eval(source);
    return {dom, w, d, requests, clipboard, timers, errors, opened: () => opened,
        fire(ms) { for (const [key, timer] of [...timers]) if (timer.ms === ms) { timers.delete(key); timer.fn(); } },
        close() { dom.window.close(); }};
}
(async () => {
    const a = setup();
    assert.equal(a.d.querySelectorAll('.assigned-game-card').length, 6, 'Pagination still limits the initial page');
    assert.match(a.d.querySelector('[data-quiz-filter-status]').textContent, /7 of 7/);
    assert.equal(a.d.querySelector('.quiz-best-score strong').textContent.trim(), '40 / 40', 'Displays best, not lower latest attempt');
    assert.equal(a.d.querySelector('[data-game-type="standard"] .quiz-best-score'), null, 'Another student’s score is not displayed');
    assert(a.d.querySelector('.classroom-banner') && a.d.querySelector('.classroom-aside') && a.d.querySelector('.classroom-progress-ring'));
    assert.equal(a.d.querySelector('.quiz-card-title img'), null, 'Quiz titles remain escaped');
    assert(!a.d.querySelector('a[href*="quiz_builder.php"]'), 'Student view has no teacher edit action');
    for (const image of a.d.querySelectorAll('img')) assert(fs.existsSync(image.getAttribute('src').replace('/QuizWeb/', '')), 'Referenced artwork exists');

    a.d.querySelector('[data-copy-classroom-code]').click(); await tick();
    assert.deepEqual(a.clipboard, ['DEMO1']); assert.match(a.d.querySelector('[data-copy-code-status]').textContent, /Copied/);
    Object.defineProperty(a.w.navigator, 'clipboard', {value: undefined, configurable: true});
    a.d.querySelector('[data-copy-classroom-code]').click(); await tick(); assert.equal(a.w.getSelection().toString(), 'DEMO1', 'Clipboard fallback selects the code');
    a.d.querySelector('[data-classroom-message]').click(); assert.equal(a.opened(), 1, 'Host message button selects this classroom’s conversation');

    a.d.querySelector('[data-quiz-filter="completed"]').click(); await tick(); await tick();
    assert.equal(a.d.querySelectorAll('.assigned-game-card').length, 2); assert.match(a.d.querySelector('[data-quiz-filter-status]').textContent, /2 of 7/);
    assert.equal(a.d.querySelector('[data-quiz-filter="completed"]').getAttribute('aria-pressed'), 'true');
    assert.equal(a.requests.at(-1).settings.credentials, 'same-origin');
    assert.equal(new URL(a.w.location.href).searchParams.get('quiz_filter'), 'completed');
    a.d.querySelector('[data-quiz-filter="all"]').click(); await tick(); await tick();
    const search = a.d.querySelector('[name="q"]'); search.value = 'Beyond First Page'; search.dispatchEvent(new a.w.Event('input'));
    a.fire(250); await tick(); await tick();
    assert.equal(a.d.querySelectorAll('.assigned-game-card').length, 1);
    assert.match(a.d.querySelector('.quiz-card-title').textContent, /Beyond First Page/, 'Search finds a quiz beyond the original page');
    assert.equal(search.value, 'Beyond First Page'); assert.equal(a.d.querySelector('[data-quiz-list]').hasAttribute('aria-busy'), false);
    search.value = 'no such classroom quiz'; search.dispatchEvent(new a.w.KeyboardEvent('keydown', {key: 'Enter', bubbles: true, cancelable: true})); await tick(); await tick();
    assert(a.d.querySelector('.classroom-quizzes-empty')); assert.match(a.d.querySelector('[data-quiz-filter-status]').textContent, /0 of 7/);
    search.value = ''; a.d.querySelector('[data-quiz-filter="rocket_rush"]').click(); await tick(); await tick();
    assert.equal(a.d.querySelector('.quiz-more-filters summary').classList.contains('is-active'), true);
    assert.equal(a.d.querySelector('.quiz-more-filters').open, false);
    a.d.querySelector('[data-quiz-filter="all"]').click(); await tick(); await tick();
    a.d.querySelector('[data-quiz-pagination] a').click(); await tick(); await tick();
    assert.match(a.d.querySelector('.quiz-card-title').textContent, /Beyond First Page/);
    const sort = a.d.querySelector('[name="quiz_sort"]'); sort.value = 'due'; sort.dispatchEvent(new a.w.Event('change')); await tick(); await tick();
    assert.match(a.d.querySelector('.quiz-card-title').textContent, /Key Terms Review/);
    const menu = a.d.querySelector('.quiz-card-menu'); menu.open = true;
    a.d.dispatchEvent(new a.w.KeyboardEvent('keydown', {key: 'Escape'})); assert(!menu.open); assert.equal(a.d.activeElement, menu.querySelector('summary'));
    a.w.fetch = async () => { throw new Error('Connection lost'); };
    a.d.querySelector('[data-quiz-filter="completed"]').click(); await tick(); await tick();
    assert.match(a.d.querySelector('[data-quiz-filter-status]').textContent, /Connection lost/);
    assert(!a.d.querySelector('[data-quiz-list]').hasAttribute('aria-busy'));
    assert.deepEqual(a.errors, []); a.close();

    const race = setup(); const pending = [];
    race.w.fetch = (url, settings) => new Promise(resolve => pending.push({url, settings, resolve}));
    const field = race.d.querySelector('[name="q"]');
    field.value = 'Science'; field.dispatchEvent(new race.w.KeyboardEvent('keydown', {key: 'Enter'}));
    field.value = 'Beyond'; field.dispatchEvent(new race.w.KeyboardEvent('keydown', {key: 'Enter'}));
    assert(pending[0].settings.signal.aborted, 'A newer query cancels the previous request');
    pending[1].resolve({ok: true, text: async () => render({query: {q: 'Beyond'}})}); await tick(); await tick();
    pending[0].resolve({ok: true, text: async () => render({query: {q: 'Science'}})}); await tick(); await tick();
    assert.match(race.d.querySelector('.quiz-card-title').textContent, /Beyond First Page/, 'A stale response cannot overwrite a newer search'); race.close();

    const teacher = setup({role: 'teacher'});
    assert(teacher.d.querySelector('a[href*="quiz_builder.php"]')); assert(teacher.d.querySelector('.classroom-roster-panel'));
    assert.equal(teacher.d.querySelectorAll('.quiz-best-score').length, 0); assert(!teacher.d.querySelector('[data-quiz-filter="completed"]'));
    assert.match(teacher.d.querySelector('.classroom-progress-content').textContent, /Students active/); teacher.close();
    const empty = setup({empty: true}); assert.match(empty.d.querySelector('.classroom-quizzes-empty').textContent, /No quizzes yet/); assert.match(empty.d.querySelector('.classroom-progress-ring').textContent, /0%/); empty.close();
    for (const role of ['student', 'teacher']) for (const view of ['overview', 'materials']) {
        const html = render({role, view}); assert(!/Warning:|Fatal error:/.test(html), 'Other classroom tabs still render');
        const d = new JSDOM(html).window.document; assert(d.querySelector('.classroom-aside')); assert.equal(d.querySelector('[aria-current="page"]').textContent.trim(), view === 'overview' ? 'Overview' : 'Materials');
    }
    const stylesheetErrors = [], output = new VirtualConsole(); output.on('jsdomError', error => stylesheetErrors.push(error.message));
    const css = ['site','refinements','visual-polish','game-experience','classroom'].map(name => '<style>' + fs.readFileSync('assets/css/' + name + '.css', 'utf8') + '</style>').join('');
    const styles = new JSDOM('<head>' + css + '</head>', {virtualConsole: output}); assert.equal(styles.window.document.styleSheets.length, 5); assert.deepEqual(stylesheetErrors, []); styles.window.close();
    console.log('Classroom templates and controls: private scores, teacher/student roles, catalog-wide search/filters/sort/pagination, stale response protection, failed fetch recovery, code copy/fallback, messaging, menus, empty classrooms, other tabs, artwork, and CSS parsing passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
