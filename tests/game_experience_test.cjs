const {JSDOM} = require('../data/qa/node_modules/jsdom');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const source = name => fs.readFileSync(`assets/js/${name}.js`, 'utf8');
const question = (points = 10, level = 'easy') => ({id: 1, prompt: 'Which answer?', options: ['Yes', 'No', 'Maybe', 'Other'], correct_index: 0, points, level});
const markup = `<div class="game-hud"><div class="hud-stats">${['progress-count','score-value','streak-value','timer-value'].map(key => `<div class="hud-pill"><span>${key}</span><strong data-${key}></strong></div>`).join('')}</div></div><div data-game-root class="game-board" data-submit-url="/QuizWeb/submit_game.php" data-classroom-id="1" data-csrf="csrf" data-run-token="run"><div class="game-progress"><div data-progress-bar></div></div><div data-battle-strip hidden><div data-boss-health></div><div data-player-health></div></div><article class="question-stage"><span data-question-points></span><h2 data-question-text></h2><p data-question-helper></p><div data-answer-grid></div><div data-game-controls></div></article><p data-game-note></p></div>`;
function setup(quiz, preview = true, practice = false) {
    const dom = new JSDOM(markup, {url: 'http://localhost/QuizWeb/', runScripts: 'outside-only', pretendToBeVisual: true});
    const w = dom.window, doc = w.document, root = doc.querySelector('[data-game-root]');
    root.dataset.quiz = JSON.stringify(quiz); root.dataset.isPreview = preview ? '1' : '0'; root.dataset.practiceMode = practice ? '1' : '0';
    const submits = [], intervals = new Map(), delays = new Map(); let id = 0, fullscreen = 0;
    w.setInterval = fn => { intervals.set(++id, fn); return id; }; w.clearInterval = key => intervals.delete(key);
    w.setTimeout = fn => { delays.set(++id, fn); return id; }; w.clearTimeout = key => delays.delete(key);
    w.HTMLFormElement.prototype.submit = function() { submits.push(Object.fromEntries(new w.FormData(this))); };
    doc.documentElement.requestFullscreen = () => { fullscreen++; return Promise.resolve(); };
    w.fetch = () => { throw new Error('Unexpected network write'); };
    w.eval(source('game-experience'));
    w.eval(source(['flip_match','fill_blank','emoji_quiz'].includes(quiz.game_type) ? 'activity-game' : 'game'));
    const controls = doc.querySelector('[data-game-controls]');
    return {w, doc, root, submits, intervals, delays, controls, fullscreen: () => fullscreen, start: async () => { controls.querySelector('.button-primary').click(); await new Promise(resolve => setImmediate(resolve)); }, answer: index => doc.querySelectorAll('[data-answer-index]')[index].click(), next: () => controls.querySelector('.button-primary').click(), close: () => w.close()};
}
(async () => {
    for (const mode of ['standard','rocket_rush','treasure_dive','memory_flip','boss_battle']) {
        const a = setup({title: mode, game_type: mode, questions: [question(20), {...question(30), id: 2}]}, false);
        await a.start(); a.answer(0); a.answer(0);
        assert.equal(a.doc.querySelector('[data-score-value]').textContent, '20', 'Locked answers cannot award twice');
        assert.match(a.doc.querySelector('[data-answer-feedback]').textContent, /Correct! \+20 points/);
        if (['rocket_rush','treasure_dive'].includes(mode)) assert.equal(a.doc.querySelector('.game-journey progress').value, 50);
        if (mode === 'boss_battle') assert.equal(a.doc.querySelector('[data-boss-health]').style.width, '50%');
        a.next(); a.answer(1); assert.match(a.doc.querySelector('[data-answer-feedback]').textContent, /Correct answer: Yes/);
        assert.equal(a.doc.querySelector('[data-score-value]').textContent, '20');
        a.next(); assert.equal(a.submits.length, 0, 'Completion must precede submission');
        const view = a.controls.querySelector('.button-primary'); view.click(); view.click();
        assert.equal(a.submits.length, 1, 'Submit only once');
        assert(!('score' in a.submits[0]), 'Client does not send authoritative points');
        const answers = JSON.parse(a.submits[0].answers); assert.equal(answers[0], 0); assert.equal(answers[1], 1);
        a.close();
    }
    const time = setup({title: 'Timer', game_type: 'time_attack', questions: [question()]});
    await time.start(); const tick = [...time.intervals.values()][1];
    for (let i = 0; i < 6; i++) tick(); assert.equal(time.root.dataset.timerState, 'warning');
    for (let i = 0; i < 3; i++) tick(); assert.equal(time.root.dataset.timerState, 'urgent');
    for (let i = 0; i < 3; i++) tick(); assert.match(time.doc.querySelector('[data-answer-feedback]').textContent, /Time’s Up/);
    assert.equal(time.doc.querySelector('[data-score-value]').textContent, '0'); time.next(); assert.equal(time.submits.length, 0); time.close();
    const ladder = setup({title: 'Ladder', game_type: 'master_ladder', mastery_threshold: 75, questions: ['easy','medium','hard','master'].map((level, i) => ({...question(), id:i + 1, level}))});
    await ladder.start(); ladder.answer(0); assert.match(ladder.controls.textContent, /Unlock Medium/); assert.match(ladder.doc.querySelector('[data-ladder-level="1"]').textContent, /Unlocked/); ladder.next();
    assert.match(ladder.doc.querySelector('[data-ladder-level="1"]').textContent, /Current/);
    ladder.answer(1); assert.match(ladder.doc.querySelector('[data-question-helper]').textContent, /need 75%/); ladder.next(); assert.equal(ladder.submits.length, 0); ladder.close();
    for (const mode of ['fill_blank','emoji_quiz']) {
        const text = setup({title:'Text',game_type:mode,questions:[{id:1,prompt:'Clue',points:10}]}, false);
        await text.start(); text.next(); assert.match(text.doc.querySelector('.activity-review-row').textContent, /Unanswered/);
        text.doc.querySelector('.activity-review-row button').click(); text.doc.querySelector('input').value = 'Response'; text.next();
        assert.match(text.doc.querySelector('.activity-review-row').textContent, /Answered/);
        text.next(); assert.equal(text.submits.length,0); text.next(); text.next(); assert.equal(text.submits.length,1); text.close();
    }
    const match = setup({title:'Match',game_type:'flip_match',questions:[{id:1,prompt:'Term A',answer:'Definition A',points:10},{id:2,prompt:'Term B',answer:'Definition B',points:10}]});
    match.w.Math.random = () => .999; await match.start();
    const tiles = [...match.doc.querySelectorAll('.match-card')];
    tiles[0].click(); tiles[2].click(); tiles[1].click(); assert.equal(tiles[1].textContent, '?', 'Busy mismatch prevents third card');
    [...match.delays.values()].forEach(fn => fn()); assert.equal(tiles[0].textContent,'?');
    tiles[0].click(); tiles[1].click(); tiles[2].click(); tiles[3].click(); match.next();
    assert.equal(match.submits.length,0,'Matching preview never writes'); assert.match(match.doc.querySelector('[data-question-helper]').textContent,/20 \/ 20/); match.close();
    const crossword = setup({title:'Puzzle', game_type:'crossword', questions:[{id:1,prompt:'Animal',word_length:3,points:10}],crossword_layout:{cols:3,cells:[[1,1,1]],placements:[{question_id:1,row:0,col:0,direction:'across',number:1}]}},false);
    await crossword.start(); assert(!crossword.controls.textContent.includes('Answer Key'));
    crossword.doc.querySelector('[data-clue-id]').click(); assert.match(crossword.doc.querySelector('[data-current-clue]').textContent,/Animal/);
    const cells = [...crossword.doc.querySelectorAll('.crossword-cell-input')]; cells.forEach((cell,i) => { cell.value='CAT'[i]; cell.dispatchEvent(new crossword.w.Event('input')); });
    assert.match(crossword.doc.querySelector('[data-question-points]').textContent,/1 \/ 1 words filled/);
    crossword.next(); assert.match(crossword.doc.querySelector('[data-question-points]').textContent,/after submission/); crossword.next();
    assert.equal(JSON.parse(crossword.submits[0].answers)[0],'CAT'); crossword.close();
    const practice = setup({title:'Practice', game_type:'time_attack',questions:[question()]},false,true);
    await practice.start(); practice.w.dispatchEvent(new practice.w.Event('blur'));
    assert.equal(practice.fullscreen(),0); assert(!practice.doc.querySelector('.quiz-integrity-dialog')); practice.close();
    console.log('Enhanced games: locked answers, visual-only scoring, completion, duplicate submission, timer states, mastery, written review, matching lock/reset, crossword secrecy/progress, preview isolation, and practice focus exemption passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
