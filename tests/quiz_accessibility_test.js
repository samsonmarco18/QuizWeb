const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('assets/js/quiz-integrity.js', 'utf8');
function fixture(supportsFullscreen) {
  const events = {}, sent = [];
  const element = () => ({dataset: {}, append() {}, prepend() {}, setAttribute() {}, addEventListener() {}, focus() {}, querySelector() {return element();}});
  const document = {documentElement: {}, createElement: element, body: element(), addEventListener(name, fn) {events[name] = fn;}, hidden: false};
  if (supportsFullscreen) document.documentElement.requestFullscreen = async () => { throw new Error('Permission denied'); };
  const window = {addEventListener(name, fn) {events[name] = fn;}};
  const root = element(); root.dataset = {isPreview: '0', practiceMode: '0', classroomId: '1', quiz: '{"id":1}', runToken: 'run', csrf: 'csrf', integrityUrl: '/quiz_integrity.php'};
  vm.runInNewContext(source, {document, window, navigator: {platform: 'test'}, crypto: require('node:crypto').webcrypto, URLSearchParams, AbortSignal,
    fetch: async (url, options) => {sent.push(Object.fromEntries(options.body)); return {ok: true, json: async () => ({warnings: 0})};}});
  return {guard: window.chalkQuizIntegrity.create(root), events, sent};
}
(async () => {
  const mobile = fixture(false);
  assert.equal(await mobile.guard.start(), true, 'Unsupported fullscreen must allow focus-monitored start');
  assert.equal(mobile.sent[0].action, 'start');
  mobile.events.fullscreenchange();
  assert.equal(mobile.sent.length, 1, 'Unsupported fullscreen must not create false violations');
  mobile.events.blur();
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(mobile.sent[1].reason, 'focus_loss', 'Focus monitoring must remain active');
  const desktop = fixture(true);
  assert.equal(await desktop.guard.start(), false, 'Supported fullscreen permission denial must still block start');
  assert.equal(desktop.sent.length, 0);
  console.log('Mobile fullscreen fallback, focus monitoring, and desktop fullscreen enforcement passed.');
})().catch(error => {console.error(error); process.exitCode = 1;});
