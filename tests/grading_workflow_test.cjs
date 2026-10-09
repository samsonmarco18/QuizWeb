const {JSDOM} = require('../data/qa/node_modules/jsdom');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const source = fs.readFileSync('app/pages/grading/gradebook.php', 'utf8');
const start = source.indexOf('<form method="post" id="grading-setup"');
let html = source.slice(start, source.indexOf('</form>', start) + 7).replace(/<\?php[\s\S]*?\?>/g, '');
html = html.replace(/<nav class="builder-steps"[\s\S]*?<\/nav>/, '<nav>' + [0, 1, 2, 3].map(step => `<button type="button" data-grading-step="${step}">${step}</button>`).join('') + '</nav>');
const dom = new JSDOM(html + '<a href="/elsewhere">Leave</a>', {url: 'http://localhost/QuizWeb/gradebook.php', runScripts: 'outside-only'});
const w = dom.window, d = w.document;
w.structuredClone = structuredClone;
w.gradingConfigSeed = {categories: [{id: 'quiz', name: 'Quizzes', weight: 30}, {id: 'work', name: 'Assignments', weight: 20}, {id: 'exam', name: 'Exams', weight: 50}], scale: [{min: 0, label: 'Below passing'}, {min: 75, label: 'Passed'}], passing: 75, missing_policy: 'exclude'};
w.confirm = () => false;
w.gradingTemplates = Object.fromEntries([
  ['balanced', 'Balanced', [30, 20, 50]],
  ['coursework', 'Coursework Focus', [20, 50, 30]],
  ['exams', 'Exam Focus', [20, 20, 60]],
].map(([id, name, weights]) => [id, {name, description: name + ' description', config: {
  ...structuredClone(w.gradingConfigSeed), categories: w.gradingConfigSeed.categories.map((category, index) => ({...category, weight: weights[index]}))
}}]));
w.eval(fs.readFileSync('assets/js/grading.js', 'utf8'));
const click = id => d.getElementById(id).click();
const input = (element, value) => { element.value = value; element.dispatchEvent(new w.Event('input', {bubbles: true})); };
const chooseTemplate = value => {
  const select = d.getElementById('grading-template');
  select.value = value; select.dispatchEvent(new w.Event('change'));
  click('apply-grading-template');
};
// The PHP option loop is stripped by this fixture; add its three choices.
for (const [id, template] of Object.entries(w.gradingTemplates)) {
  const option = d.createElement('option'); option.value = id; option.textContent = template.name;
  d.getElementById('grading-template').append(option);
}
chooseTemplate('exams');
assert.deepEqual([...d.querySelectorAll('#grading-weights input')].map(element => element.value), ['20', '20', '60']);
assert.match(d.getElementById('grading-template-summary').textContent, /Exams: 60%/);
let templateUnload = new w.Event('beforeunload', {cancelable: true}); w.dispatchEvent(templateUnload);
assert(templateUnload.defaultPrevented, 'Applied template must be saved');
chooseTemplate('coursework');
assert.equal(d.querySelectorAll('#grading-weights input')[1].value, '50');
w.gradingUsedCategories = ['quiz'];
chooseTemplate('custom');
assert.match(d.getElementById('grading-setup-status').textContent, /used by active activities/);
assert.equal(d.querySelectorAll('#grading-categories input').length, 3, 'Blocked template preserved categories');
w.gradingUsedCategories = [];
chooseTemplate('custom');
assert.equal(d.querySelectorAll('#grading-categories input').length, 0, 'Manual grading starts empty');
chooseTemplate('balanced');
let unload = new w.Event('beforeunload', {cancelable: true}); w.dispatchEvent(unload); assert(!unload.defaultPrevented, 'Unchanged setup has no false unsaved warning');
assert.match(d.getElementById('grading-weight-total').textContent, /100.00%.*Ready/);
input(d.querySelector('#grading-categories input'), 'Knowledge checks');
assert.match(d.querySelector('#grading-weights label').textContent, /Knowledge checks/);
unload = new w.Event('beforeunload', {cancelable: true}); w.dispatchEvent(unload); assert(unload.defaultPrevented, 'Rename tracked as unsaved');
let navigate = new w.MouseEvent('click', {bubbles: true, cancelable: true}); d.querySelector('a').dispatchEvent(navigate); assert(navigate.defaultPrevented, 'Discard prompt cancels navigation');
click('grading-next'); assert(!d.querySelector('[data-grading-panel="1"]').hidden);
input(d.querySelector('#grading-weights input'), '25'); assert.match(d.getElementById('grading-weight-total').textContent, /95.00%.*5.00% remaining/);
const form = d.getElementById('grading-setup');
let submit = new w.Event('submit', {cancelable: true}); form.dispatchEvent(submit); assert(submit.defaultPrevented); assert.match(d.getElementById('grading-setup-status').textContent, /exactly 100/);
input(d.querySelector('#grading-weights input'), '30');
d.querySelector('[data-grading-step="0"]').click();
d.querySelector('#grading-categories .grade-editor-row').querySelectorAll('button')[1].click();
assert.equal(d.querySelector('#grading-categories input').value, 'Assignments');
assert.equal(d.querySelectorAll('#grading-weights input')[1].value, '30', 'Reordering retains ID/weight');
click('add-grading-category'); const extra = d.querySelector('#grading-categories .grade-editor-row:last-child'); input(extra.querySelector('input'), 'Projects'); extra.querySelectorAll('button')[2].click(); assert.equal(d.querySelectorAll('#grading-categories input').length, 3);
click('add-grading-band'); const band = d.querySelector('#grading-scale .grade-editor-row:last-child'); input(band.querySelector('input[type="number"]'), '90'); input(band.querySelector('input[type="text"]'), 'Excellent');
input(form.elements.passing, '80'); form.elements.missing_policy.value = 'zero'; form.elements.missing_policy.dispatchEvent(new w.Event('change'));
d.querySelector('[data-grading-step="3"]').click(); assert.match(d.getElementById('grading-setup-review').textContent, /Passing: 80%.*count as zero/); assert.match(d.getElementById('grading-setup-review').textContent, /90% and above: Excellent/);
submit = new w.Event('submit', {cancelable: true}); form.dispatchEvent(submit); assert(!submit.defaultPrevented);
const payload = JSON.parse(d.getElementById('grading-config-payload').value); assert.equal(payload.categories[1].id, 'quiz'); assert.equal(payload.categories[1].name, 'Knowledge checks'); assert.equal(payload.scale.length, 3);
submit = new w.Event('submit', {cancelable: true}); form.dispatchEvent(submit); assert(submit.defaultPrevented, 'Duplicate submission rejected');
dom.window.close();

const scores = new JSDOM('<form method="post" data-grade-dirty><input type="number" name="scores[1]" min="0" max="20" step="0.01"><button>Save</button></form>', {runScripts: 'outside-only'});
scores.window.eval(fs.readFileSync('assets/js/grading.js', 'utf8'));
input(scores.window.document.querySelector('input'), '0');
const changed = new scores.window.Event('beforeunload', {cancelable: true}); scores.window.dispatchEvent(changed); assert(changed.defaultPrevented, 'Explicit zero differs from blank');
scores.window.close();
console.log('Grading wizard, weights, rename/reorder, scale, review, unsaved guards, score input, and duplicate-save checks passed.');
