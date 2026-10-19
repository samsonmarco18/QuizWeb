const {JSDOM} = require('../data/qa/node_modules/jsdom');
const fs = require('node:fs'), assert = require('node:assert/strict');
const fields = names => names.map(name => `<label><span>${name}</span><input name="${name}" ${['student_number','program','year_level'].includes(name)?'data-student-required':''}></label>`).join('');
const html = `<form id="signup-form"><span data-step-counter></span>${[0,1,2,3].map(i => `<span data-progress-step="${i}"></span>`).join('')}<section data-signup-step><h1 tabindex="-1">Account</h1>${fields(['name','email','password','confirm_password'])}<select name="role"><option>student</option><option>teacher</option></select><button type="button" data-password-toggle>Show</button></section><section data-signup-step><h1 tabindex="-1">Personal</h1>${fields(['student_number','birthdate','gender'])}</section><section data-signup-step><h1 tabindex="-1">Academic</h1>${fields(['program','year_level'])}</section><section data-signup-step><h1 tabindex="-1">Review</h1><dl data-signup-review></dl></section><button type="button" data-signup-back>Back</button><button type="button" data-signup-next>Next</button><button data-signup-submit>Create</button></form>`;
const dom = new JSDOM(html, {runScripts:'outside-only'}), w = dom.window, d = w.document;
w.eval(fs.readFileSync('assets/js/signup.js','utf8'));
const form=d.querySelector('form'), field=name=>form.elements.namedItem(name);
const input=(name,value)=>{field(name).value=value;field(name).dispatchEvent(new w.Event('input',{bubbles:true}));};
input('password','Password123');input('confirm_password','Password12');
assert.match(field('confirm_password').closest('label').textContent,/Passwords do not match/);
d.querySelector('[data-signup-next]').click();assert.equal(d.querySelector('[data-signup-step]').hidden,false);
input('confirm_password','Password123');assert.match(field('confirm_password').closest('label').textContent,/Passwords match/);
field('role').value='teacher';field('role').dispatchEvent(new w.Event('change'));
for(const name of ['student_number','year_level']) {assert.equal(field(name).disabled,true);assert.equal(field(name).closest('label').hidden,true);}
assert.equal(field('program').disabled,false);
for(let i=0;i<3;i++)d.querySelector('[data-signup-next]').click();
assert(!d.querySelector('[data-signup-review]').textContent.includes('Student Number'));
assert(!d.querySelector('[data-signup-review]').textContent.includes('Year Level'));
field('role').value='student';field('role').dispatchEvent(new w.Event('change'));
for(const name of ['student_number','year_level']) {assert.equal(field(name).disabled,false);assert.equal(field(name).required,true);}
dom.window.close();console.log('Password match feedback, blocked mismatch, teacher fields, review, and student requirements passed.');
