// Generate source-derived UI fixtures without using a classroom database.
const fs = require('node:fs');
const {execFileSync} = require('node:child_process');
const php = process.env.PHP_BINARY || 'C:/xampppp/php/php.exe';
const crossword = JSON.parse(execFileSync(php, ['tests/preview_fixture.php'], {encoding:'utf8'}));
const source = fs.readFileSync('app/pages/quizzes/quiz_builder.php','utf8');
const builder = source.slice(source.indexOf('<section class="glass panel builder-panel">'),source.search(/<script>\s*window.quizBuilderSeed/)).replace(/<\?php[\s\S]*?\?>/g,'').replace(/<div class="inline-error">\s*<\/div>/g,'');
const dialog = source.slice(source.indexOf('<dialog id="activity-preview"'), source.indexOf('</dialog>') + 9).replace(/<\?php[\s\S]*?\?>/g,'');
const hud = `<div class="game-hud"><div><span class="eyebrow">Classroom activity</span><h1>A sufficiently long activity title to verify wrapping on a narrow phone screen</h1><p>Teacher instructions: choose your response and review your work.</p></div><div class="game-intro-info"><span>40 possible points</span><span>Graded · Highest attempt</span><span>2 completed attempts · Retry available</span><span>Due October 12 · submissions remain open</span></div><div class="hud-stats">${['progress-count','score-value','streak-value','timer-value'].map(key=>`<div class="hud-pill"><span>${key.replace('-value','')}</span><strong data-${key}></strong></div>`).join('')}</div></div>`;
const game = `<section class="game-shell glass">${hud}<div data-game-root class="game-board" data-is-preview="1" data-return-url="#"><div class="game-progress"><div data-progress-bar class="game-progress-bar"></div></div><div data-battle-strip class="battle-strip" hidden><div class="battle-meter"><span>Boss HP</span><div class="battle-meter-track"><div data-boss-health class="battle-meter-fill battle-meter-fill-boss"></div></div></div><div class="battle-meter"><span>Shield</span><div class="battle-meter-track"><div data-player-health class="battle-meter-fill battle-meter-fill-player"></div></div></div></div><div class="mode-stage"><article class="question-stage glass"><span data-question-points></span><h2 data-question-text></h2><p data-question-helper></p><div data-answer-grid class="answers-grid"></div><div data-game-controls class="game-controls"></div></article></div><p data-game-note class="game-note"></p></div></section>`;
const results = `<section class="results-shell glass"><span class="eyebrow">Activity Complete</span><h1>A longer result title that must wrap safely</h1><div class="result-score-ring"><div><strong>85%</strong><span>34 / 40 points</span></div></div><div class="stat-grid"><article class="stat-card"><strong>4</strong><span>Correct</span></article><article class="stat-card"><strong>36s</strong><span>Time used</span></article></div><div class="result-learning-panel"><h2>Learning review</h2><article class="review-card"><h3>A long question prompt with a detailed explanation of the expected answer and what to practice next.</h3><div class="review-answer-grid"><p>Your answer: an answer that wraps across lines</p><p>Correct answer: the teacher's expected response</p></div></article></div><div class="action-row centered"><a class="button button-primary" href="#">Review Answers</a><a class="button button-secondary" href="#">Return to Classroom</a><a class="button button-secondary" href="#">Try Again</a><a class="button button-secondary" href="#">Focus Practice</a></div><p>This game score is one attempt. Classroom grades use category weights and the configured attempt policy.</p></section>`;
const names = {standard:'Standard Quiz',time_attack:'Time Attack',rocket_rush:'Rocket Rush',treasure_dive:'Treasure Dive',boss_battle:'Boss Battle',memory_flip:'Memory Flip',master_ladder:'Mastery Ladder',crossword:'Crossword',flip_match:'Flip Match',fill_blank:'Fill in the Blank',emoji_quiz:'Emoji Quiz'};
const setup = `
const params=new URLSearchParams(location.search), view=params.get('view')||'standard';
localStorage.setItem('quizweb-theme',params.get('theme')==='dark'?'dark':'default');
const names=${JSON.stringify(names)};
const seed=['easy','medium','hard','master'].map((level,i)=>({id:i+1,prompt:'Choose the correct answer to this sufficiently long question about responsible classroom learning.',options:['A well explained answer that can safely wrap over several lines','A second plausible response','Another response','None of these'],correct_index:0,points:10,level}));
window.quizBuilderModes=Object.fromEntries(Object.entries(names).map(([key,label])=>[key,{label,description:'An interactive classroom activity with teacher-configured question points.'}]));
window.quizBuilderMode='standard';window.quizBuilderSeed=seed;window.quizBuilderThreshold=75;
window.fetch=async()=>({ok:true,headers:{get:()=> 'application/json'},json:async()=>({title:'Unsaved Activity Preview',game_type:'standard',questions:seed,errors:[]})});
if(['builder','picker','preview'].includes(view)){
 document.querySelector('main').innerHTML=${JSON.stringify(builder+dialog)};
 document.querySelector('[name=game_type]').innerHTML=Object.entries(names).map(([key,label])=>'<option value="'+key+'">'+label+'</option>').join('');
 document.querySelector('[name=title]').value='Teacher activity';
 document.querySelector('[name=mastery_threshold]').value='75';
 document.querySelector('[name=grade_category_id]').innerHTML='<option value="">Practice</option><option value="quiz">Quizzes</option>';
 document.querySelector('[name=grade_attempt_policy]').innerHTML='<option value="highest">Highest attempt</option>';
 document.querySelector('#question-template').content.querySelector('[data-field=level]').innerHTML=['easy','medium','hard','master'].map(level=>'<option>'+level+'</option>').join('');
 document.body.classList.add('builder-page');
}else if(view==='results'){document.querySelector('main').innerHTML=${JSON.stringify(results)}}
else{
 document.body.classList.add('game-page','mode-'+view);document.querySelector('main').innerHTML=${JSON.stringify(game)};
 let quiz={id:1,title:names[view],game_type:view,mastery_threshold:75,questions:seed};
 if(view==='crossword')quiz=${JSON.stringify(crossword)};
 if(['flip_match','fill_blank','emoji_quiz'].includes(view))quiz.questions=seed.map((q,i)=>({...q,prompt:view==='emoji_quiz'?'🌧️ + 🌈':view==='flip_match'?'Term '+(i+1):q.prompt,answer:'A definition that safely wraps inside the matching card for pair '+(i+1),options:[]}));
 document.querySelector('[data-game-root]').dataset.quiz=JSON.stringify(quiz);
}
`;
const checks = `
window.addEventListener('load',async()=>{try{
if(view==='builder')document.querySelector('[data-step="'+(params.get('step')||'2')+'"]').click();
if(view==='preview'){document.querySelector('#preview-quiz').click();for(let i=0;i<60&&!document.querySelector('#preview-content iframe[data-preview-ready]');i++)await new Promise(r=>setTimeout(r,100));if(!document.querySelector('#preview-content iframe[data-preview-ready]'))throw new Error('Preview renderer did not load');}
if(!['builder','picker','preview','results'].includes(view)){document.querySelector('[data-game-controls] .button-primary').click();await new Promise(r=>setTimeout(r,100));}
if(params.get('feedback')==='1')document.querySelector('[data-answer-index="0"]')?.click();
const bad=[...document.querySelectorAll('button,input,textarea,select,.quiz-card,.question-stage,.review-card,dialog[open]')].filter(e=>!e.closest('[hidden]')&&!e.closest('#question-navigator,.crossword-board-scroll')&&e.getBoundingClientRect().width>0).filter(e=>{const r=e.getBoundingClientRect();return r.left< -2||r.right>innerWidth+2;}).map(e=>e.tagName+'.'+e.className+': '+e.textContent.slice(0,40));
document.querySelector('#checks').textContent=(document.documentElement.scrollWidth>innerWidth+2||bad.length)?'FAIL '+JSON.stringify(bad):'PASS: '+view+' responsive layout';
}catch(e){document.querySelector('#checks').textContent='FAIL '+e.message}});
`;
const html = `<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CHALK source-based responsive QA</title>${['site','refinements','visual-polish','game-experience'].map(name=>`<link rel="stylesheet" href="/QuizWeb/assets/css/${name}.css">`).join('')}<body class="ui-refined"><main class="page-shell"></main><output id="checks">PENDING</output><script>${setup}</script><script src="/QuizWeb/assets/js/site.js"></script><script src="/QuizWeb/assets/js/game-experience.js"></script><script src="/QuizWeb/assets/js/builder-workflow.js"></script><script src="/QuizWeb/assets/js/builder-preview.js"></script><script>if(!['builder','picker','preview','results'].includes(view)){const s=document.createElement('script');s.src='/QuizWeb/assets/js/'+(['flip_match','fill_blank','emoji_quiz'].includes(view)?'activity-game':'game')+'.js';document.body.append(s);}</script><script>${checks}</script></body></html>`;
fs.mkdirSync('data/qa',{recursive:true});fs.writeFileSync('data/qa/experience.html',html);console.log('Generated data/qa/experience.html');
