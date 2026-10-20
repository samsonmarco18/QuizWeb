const { JSDOM } = require('../data/qa/node_modules/jsdom');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs'), assert = require('node:assert/strict');
const fixture = JSON.parse(execFileSync(process.env.PHP_BINARY || 'C:/xampppp/php/php.exe', ['tests/member_profile_fixture.php'], { encoding: 'utf8' }));
const tick = () => new Promise(resolve => setImmediate(resolve));
(async () => {
    const dom = new JSDOM(fixture.html, { url: 'http://localhost/QuizWeb/dashboard.php', runScripts: 'outside-only', pretendToBeVisual: true });
    const w = dom.window, d = w.document, requests = [], intervals = [];
    const reader = { id: 5, name: 'Teacher', avatar_url: null };
    const thread = fixture.threads[0];
    thread.messages = [{ id: 1, user_id: 5, user_name: 'Teacher', body: 'Hello', created_at: new Date().toISOString(), readers: [] }];
    w.matchMedia = () => ({ matches: false });
    w.setInterval = callback => { intervals.push(callback); return intervals.length; };
    let releaseSend;
    w.fetch = async (url, options) => {
        const action = options.body?.get('action'); requests.push(action || 'get');
        if (action === 'send') {
            await new Promise(resolve => { releaseSend = resolve; });
            thread.messages.push({ id: 2, user_id: 6, user_name: 'Student', body: 'My reply', created_at: new Date().toISOString(), readers: [] });
        }
        return { ok: true, json: async () => ({ threads: structuredClone(fixture.threads) }) };
    };
    w.eval(fs.readFileSync('assets/js/site.js', 'utf8'));
    await tick(); await tick();
    assert(!requests.includes('seen'), 'Closed conversations must not create receipts');
    d.querySelector('.messenger-launcher').click(); await tick(); await tick();
    assert(requests.includes('seen'), 'Opening a visible conversation records the read boundary');
    const form = d.querySelector('.messenger-thread.is-active .messenger-compose');
    form.querySelector('textarea').value = 'My reply';
    form.dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true }));
    assert.match(form.querySelector('[data-chat-status]').textContent, /Sending/);
    releaseSend(); await tick(); await tick();
    assert.equal(form.querySelector('[data-chat-status]').textContent, 'Sent');
    assert.equal(d.querySelector('.is-self .chat-message-receipt').textContent, 'Sent');
    thread.messages[1].readers = [reader];
    await intervals[0](); await tick();
    assert.match(d.querySelector('.is-self .chat-message-receipt').textContent, /Seen by/);
    assert.equal(d.querySelector('.chat-reader-avatar').title, 'Seen by Teacher');
    const seenCount = requests.filter(action => action === 'seen').length;
    await intervals[0](); await tick();
    assert.equal(requests.filter(action => action === 'seen').length, seenCount, 'Polling does not repeatedly write the same receipt');
    dom.window.close();
    console.log('Visible-chat receipts, inline Sending/Sent, viewer avatars, polling, and duplicate-write prevention passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
