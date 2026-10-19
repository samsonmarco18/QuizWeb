const {JSDOM} = require('../data/qa/node_modules/jsdom');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const php = process.env.PHP_BINARY || 'C:/xampppp/php/php.exe';
const html = execFileSync(php, ['-r', `
function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
function form_csrf() { return 'test'; }
function academic_settings() { return ['years'=>['2026-2027'], 'semesters'=>['First Semester']]; }
function academic_periods() { return [['id'=>'prelim','name'=>'Prelim','weight'=>30],['id'=>'midterm','name'=>'Midterm','weight'=>30],['id'=>'finals','name'=>'Finals','weight'=>40]]; }
require 'includes/grading.php'; require 'includes/academic_ui.php';
$user=['name'=>'Teacher']; $errors=[]; $_POST=[];
require 'includes/class_creation_form.php';
`], {encoding:'utf8'});
const dom = new JSDOM(html, {runScripts:'dangerously'});
const w = dom.window, d = w.document;
w.eval(fs.readFileSync('assets/js/class-creation.js', 'utf8'));
const form = d.querySelector('form');
const field = name => form.elements.namedItem(name);
const active = () => [...d.querySelectorAll('[data-class-panel]')].findIndex(p => !p.hidden);
const next = () => d.querySelector('[data-class-next]').click();
assert.equal(active(), 0);
next(); assert.equal(active(), 0, 'Required class details block progression');
field('name').value='Science'; field('subject').value='Biology'; next();
assert.equal(active(), 1);
next(); assert.equal(active(), 1, 'Required academic fields block progression');
for (const [key,value] of Object.entries({subject_code:'BIO101',section:'A',academic_year:'2026-2027',semester:'First Semester'})) field(key).value=value;
next(); assert.equal(active(), 2);
field('creation_config[categories][0][weight]').value=50;
next(); assert.equal(active(), 2, 'Invalid category total blocks review');
assert.match(d.querySelector('[data-class-grade-error]').textContent, /100%/);
field('grading_template').value='coursework';
field('grading_template').dispatchEvent(new w.Event('input', {bubbles:true}));
field('grading_template').dispatchEvent(new w.Event('change', {bubbles:true}));
assert.equal(field('grading_template').value, 'coursework', 'Template selection must not be reclassified as custom');
assert.equal(field('creation_config[categories][1][weight]').value, '50');
field('creation_config[periods][0][weight]').value=50;
next(); assert.equal(active(), 2, 'Invalid period total blocks review');
field('creation_config[periods][0][weight]').value=30;
next(); assert.equal(active(), 3);
assert.match(d.querySelector('[data-class-review]').textContent, /Science/);
assert.equal(d.querySelectorAll('.class-review-card').length, 3);
assert.match(d.querySelector('[data-class-review]').textContent, /Coursework Focus/);
d.querySelector('[data-class-back]').click(); assert.equal(active(), 2);
assert.equal(field('creation_config[categories][1][weight]').value, '50');
next();
assert.equal(form.dispatchEvent(new w.Event('submit', {cancelable:true})), true, 'Valid review allows submission');
if (process.env.CLASS_CREATION_PREVIEW) {
 d.head.innerHTML = '<meta name="viewport" content="width=device-width,initial-scale=1">' + ['site','refinements','visual-polish','game-experience','class-creation'].map(name => `<style>${fs.readFileSync(`assets/css/${name}.css`,'utf8')}</style>`).join('');
 d.body.className = 'ui-refined theme-dark classes-page';
 form.classList.add('glass','panel','class-creation-panel');
 form.style.margin = '24px auto';
 fs.writeFileSync(process.env.CLASS_CREATION_PREVIEW, dom.serialize());
}
console.log('Class creation steps, required fields, templates, weight validation, review, back navigation, and submission passed.');
