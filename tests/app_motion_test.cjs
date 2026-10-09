const {JSDOM} = require('../data/qa/node_modules/jsdom');
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async () => {
  const dom = new JSDOM('<form method="post"><button>Save</button></form><form method="dialog"></form>', {runScripts:'outside-only', url:'http://localhost/QuizWeb/dashboard.php'});
  const w = dom.window, d = w.document;
  let resolve, reject;
  w.fetch = () => new Promise((a,b) => { resolve=a; reject=b; });
  w.eval(fs.readFileSync('assets/js/app-motion.js','utf8'));
  const wait = ms => new Promise(r=>setTimeout(r,ms));
  const loading = () => d.querySelector('.chalk-loading').classList.contains('is-visible');
  await wait(20); // Allow the initial pageshow event to finish before starting a request.
  const contexts = {
    'grades.php': 'grades', 'gradebook.php': 'grades', 'play.php': 'quiz',
    'classroom.php': 'classroom', 'upload.php': 'upload', 'chat_api.php': 'message',
    'profile.php': 'profile', 'dashboard.php': 'page'
  };
  for (const [url, expected] of Object.entries(contexts)) assert.equal(w.chalkLoading.context(url), expected);
  assert.equal(w.chalkLoading.context('dashboard.php', 'POST'), 'save');
  assert.equal(w.chalkLoading.context('http://localhost/QuizWeb/login.php', 'POST'), 'profile', 'The app directory must not classify login as a quiz');
  assert.equal(w.chalkLoading.context('/QuizWeb/dashboard.php'), 'page');
  assert.equal(w.chalkLoading.context('/QuizWeb/gradebook.php'), 'grades');
  const files = new w.FormData(); files.append('file', new w.Blob(['picture']));
  assert.equal(w.chalkLoading.context('classroom.php', 'POST', files), 'upload');
  for (const scene of ['grades','quiz','classroom','upload','message','profile','save','page']) {
    const end = w.chalkLoading.begin('Loading...', 0, scene); await wait(10);
    assert.equal(d.querySelector('.chalk-loading').dataset.scene, scene);
    const filename = d.querySelector('.chalk-loading-art').getAttribute('src').split('/').pop();
    assert(fs.existsSync('assets/images/' + filename), `Missing illustration for ${scene}`);
    end();
  }
  w.chalkLoading.reset();
  d.body.className = 'auth-page login-page';
  const main = d.createElement('main'); main.className='page-shell'; main.getBoundingClientRect=()=>({left:283}); d.body.append(main);
  const loginEnd = w.chalkLoading.begin('Submitting...',0,'profile'); await wait(10);
  assert.equal(d.querySelector('.chalk-loading').style.left,'0px');
  assert.equal(d.querySelector('.chalk-loading').style.top,'0px');
  assert.equal(d.querySelector('[data-loading-label]').textContent,'Signing you in...');
  loginEnd(); w.chalkLoading.reset(); d.body.className=''; main.remove();
  const sidebar=d.createElement('aside'); sidebar.className='classroom-sidebar'; sidebar.getBoundingClientRect=()=>({left:0,right:212,width:212});
  const header=d.createElement('header'); header.className='site-header'; header.getBoundingClientRect=()=>({bottom:64,height:64}); d.body.append(sidebar,header);
  const contentEnd=w.chalkLoading.begin('Loading...',0,'grades'); await wait(10);
  assert.equal(d.querySelector('.chalk-loading').style.left,'212px','Blur must start at the sidebar edge');
  assert.equal(d.querySelector('.chalk-loading').style.top,'64px','Blur must start below the header');
  contentEnd(); w.chalkLoading.reset(); sidebar.remove(); header.remove();
  const saving = w.fetch('/save.php',{method:'POST'});
  await wait(200); assert(loading(), 'Pending save needs visible feedback');
  resolve({ok:true}); await saving; await wait(150); assert(loading(), 'Fast save should finish its loading animation'); await wait(850); assert(!loading(), 'Successful save must clear feedback');
  const failed = w.fetch('/save.php',{method:'POST'}); const caught = failed.catch(()=>{});
  await wait(200); reject(new Error('network')); await caught; await wait(1000); assert(!loading(), 'Failed save must clear feedback');
  const heartbeat = w.fetch('/quiz_progress.php',{method:'POST'});
  await wait(200); assert(!loading(), 'Background tracking must not flash a loader'); resolve({ok:true}); await heartbeat;
  const endFirst = w.chalkLoading.begin('First',0), endSecond=w.chalkLoading.begin('Second',0);
  await wait(10); endFirst(); await wait(150); assert(loading(), 'Concurrent request must retain loader'); endSecond(); await wait(1000); assert(!loading());
  d.querySelector('form').addEventListener('submit',event=>event.preventDefault());
  d.querySelector('form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true})); await wait(20); assert(!loading(), 'Canceled submit must stay usable');
  w.chalkLoading.begin('Navigation',0); await wait(10); w.dispatchEvent(new w.Event('pageshow')); assert(!loading(), 'Back navigation must reset loading');
  dom.window.close(); console.log('Loading lifecycle, failures, concurrency, background requests, canceled forms, and back navigation passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
