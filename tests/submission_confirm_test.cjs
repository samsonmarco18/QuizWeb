const {JSDOM} = require('../data/qa/node_modules/jsdom');
const fs = require('node:fs'), assert = require('node:assert/strict');
(async () => {
  const dom = new JSDOM('<body class="gradebook-page"><form method="post"><button name="action" value="publish">Release</button></form></body>', {runScripts:'outside-only',url:'http://localhost/QuizWeb/gradebook.php'});
  const w=dom.window,d=w.document;
  w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};
  w.HTMLDialogElement.prototype.close=function(){this.open=false;};
  w.eval(fs.readFileSync('assets/js/submission-confirm.js','utf8'));
  const form=d.querySelector('form'),button=d.querySelector('button'); let submissions=0, submitter=null;
  form.addEventListener('submit',event=>{event.preventDefault();submissions++;submitter=event.submitter;});
  form.requestSubmit=target=>form.dispatchEvent(new w.SubmitEvent('submit',{bubbles:true,cancelable:true,submitter:target}));
  form.requestSubmit(button); assert.equal(submissions,0); assert(d.querySelector('dialog').textContent.includes('Release grades?'));
  d.querySelector('[data-cancel]').click(); await Promise.resolve(); assert.equal(submissions,0); assert(!d.querySelector('dialog'));
  form.requestSubmit(button); d.querySelector('[data-confirm]').click(); await Promise.resolve(); assert.equal(submissions,1); assert.equal(submitter,button);
  const quiz=w.chalkConfirm('Submit quiz?','Save this attempt?','Submit Quiz');
  d.querySelector('dialog').dispatchEvent(new w.Event('cancel',{cancelable:true})); assert.equal(await quiz,false);
  const save=w.chalkConfirm('Save quiz?','Save changes?','Save Quiz'); d.querySelector('[data-confirm]').click(); assert.equal(await save,true);
  dom.window.close(); console.log('Grade confirmation/cancellation, submitter preservation, quiz confirmation, and Escape cancellation passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
