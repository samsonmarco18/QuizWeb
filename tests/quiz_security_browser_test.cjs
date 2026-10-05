const {JSDOM} = require('../data/qa/node_modules/jsdom');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const tick = () => new Promise(resolve => setImmediate(resolve));
const modes = ['standard','time_attack','rocket_rush','memory_flip','treasure_dive','boss_battle','crossword','master_ladder','fill_blank','emoji_quiz','flip_match'];
function setup(mode, preview = false, practice = false) {
    const html = `<div class="game-hud"><div class="hud-stats">${['progress-count','score-value','streak-value','timer-value'].map(k => `<div class="hud-pill"><span>${k}</span><b data-${k}></b></div>`).join('')}</div></div><div class="game-board" data-game-root data-submit-url="/QuizWeb/submit_game.php" data-integrity-url="/QuizWeb/quiz_integrity.php" data-run-token="run" data-classroom-id="1" data-csrf="csrf"><div data-progress-bar></div><div data-battle-strip hidden><div data-boss-health></div><div data-player-health></div></div><article class="question-stage"><b data-question-points></b><h2 data-question-text></h2><p data-question-helper></p><div data-answer-grid></div><div data-game-controls></div></article><p data-game-note></p></div>`;
    const dom = new JSDOM(html, {url:'https://example.com/QuizWeb/',runScripts:'outside-only',pretendToBeVisual:true});
    const w=dom.window, d=w.document, root=d.querySelector('[data-game-root]');
    const questions = mode === 'flip_match' ? [{id:1,prompt:'One',answer:'First',points:10},{id:2,prompt:'Two',answer:'Second',points:10}] : [{id:1,prompt:'Question',answer:'Yes',options:['Yes','No','Other','Unknown'],correct_index:0,points:10,level:'easy'}];
    root.dataset.quiz=JSON.stringify({id:1,title:'Security fixture',game_type:mode,questions});
    root.dataset.isPreview=preview?'1':'0'; root.dataset.practiceMode=practice?'1':'0';
    let rejectFullscreen=false, fullscreenCalls=0, failWarning=false, warnings=0;
    const requests=[], submits=[], beacons=[], events=new Set(), intervals=new Map(); let timerId=0;
    Object.defineProperty(d,'hidden',{value:false,configurable:true});
    Object.defineProperty(d,'fullscreenElement',{value:null,writable:true,configurable:true});
    d.documentElement.requestFullscreen=async()=>{ fullscreenCalls++; if(rejectFullscreen) throw new Error('Denied fullscreen'); d.fullscreenElement=d.documentElement; d.dispatchEvent(new w.Event('fullscreenchange')); };
    w.setInterval=fn=>{intervals.set(++timerId,fn);return timerId;}; w.clearInterval=id=>intervals.delete(id);
    w.HTMLFormElement.prototype.submit=function(){submits.push(Object.fromEntries(new w.FormData(this)));};
    w.navigator.sendBeacon=(url,body)=>{beacons.push({url,fields:Object.fromEntries(body)});return true;};
    w.fetch=async(url,options)=>{
        const fields=Object.fromEntries(options.body); requests.push(fields);
        assert.equal(fields.csrf,'csrf'); assert.equal(fields.run_token,'run');
        if(failWarning && fields.action==='violation'){failWarning=false;throw new Error('Network unavailable');}
        if(fields.action==='violation'&&!events.has(fields.event_id)){events.add(fields.event_id);warnings++;}
        return {ok:true,json:async()=>({warnings,disqualified:warnings>3,results_url:warnings>3?'/QuizWeb/results.php?id=1':null})};
    };
    for(const name of ['game-experience','quiz-integrity',['fill_blank','emoji_quiz','flip_match'].includes(mode)?'activity-game':'game']) w.eval(fs.readFileSync(`assets/js/${name}.js`,'utf8'));
    const controls=d.querySelector('[data-game-controls]');
    return {w,d,root,requests,submits,beacons,intervals,controls,
        async start(){controls.querySelector('.button-primary').click();await tick();},
        async resume(){d.querySelector('[data-integrity-continue]').click();await tick();},
        deny(value){rejectFullscreen=value;}, failNextWarning(){failWarning=true;},
        fullscreenCalls:()=>fullscreenCalls,close:()=>w.close()};
}
(async()=>{
    for(const mode of modes){
        const a=setup(mode);a.deny(true);await a.start();
        assert.equal(a.requests.length,0,'Denied fullscreen must not start a server run');
        assert.equal(a.intervals.size,0,'Denied fullscreen must not start timers');
        a.deny(false);await a.start();assert.equal(a.requests[0].action,'start',mode+' starts security');
        a.w.dispatchEvent(new a.w.Event('blur'));
        Object.defineProperty(a.d,'hidden',{value:true,configurable:true});a.d.dispatchEvent(new a.w.Event('visibilitychange'));
        a.d.fullscreenElement=null;a.d.dispatchEvent(new a.w.Event('fullscreenchange'));await tick();
        assert.match(a.d.querySelector('[data-integrity-message]').textContent,/Warning 1 of 3/);
        assert.equal(a.requests.filter(r=>r.action==='violation').length,1,'One focus incident counts once');
        assert(a.root.inert,'Game is paused beneath warning');
        a.deny(true);await a.resume();assert(!a.d.querySelector('.quiz-integrity-dialog').hidden,'Denied fullscreen cannot dismiss warning');
        a.deny(false);Object.defineProperty(a.d,'hidden',{value:false,configurable:true});await a.resume();
        assert(a.d.querySelector('.quiz-integrity-dialog').hidden);assert(!a.root.inert);
        a.d.dispatchEvent(new a.w.KeyboardEvent('keyup',{key:'PrintScreen',code:'PrintScreen',bubbles:true}));await tick();
        assert.equal(a.requests.at(-1).reason,'screenshot_shortcut');await a.resume();
        a.d.dispatchEvent(new a.w.KeyboardEvent('keydown',{key:'s',metaKey:true,shiftKey:true,bubbles:true}));await tick();
        assert.match(a.d.querySelector('[data-integrity-message]').textContent,/Warning 3 of 3/);await a.resume();
        a.d.fullscreenElement=null;a.d.dispatchEvent(new a.w.Event('fullscreenchange'));await tick();
        assert.equal(a.submits.length,1,mode+' fourth violation submits zero');
        assert.equal(a.submits[0].disqualified,'1');assert.equal(a.submits[0].violation_count,'4');
        assert.equal(Object.keys(JSON.parse(a.submits[0].answers)).length,0,'Disqualified run submits no answers');a.close();
    }
    const retry=setup('standard');await retry.start();retry.failNextWarning();retry.w.dispatchEvent(new retry.w.Event('blur'));await tick();
    assert(!retry.d.querySelector('.quiz-integrity-dialog').hidden);await retry.resume();
    const violations=retry.requests.filter(r=>r.action==='violation');assert.equal(violations.length,2);assert.equal(violations[0].event_id,violations[1].event_id,'Retry uses the same event ID');retry.close();
    const closed=setup('fill_blank');await closed.start();closed.w.dispatchEvent(new closed.w.Event('blur'));await tick();closed.w.dispatchEvent(new closed.w.Event('pagehide'));
    assert.equal(closed.beacons[0].fields.action,'abandon','Leaving while a warning is shown still saves zero');closed.close();
    for(const [preview,practice] of [[true,false],[false,true]]){
        const exempt=setup('standard',preview,practice);await exempt.start();exempt.w.dispatchEvent(new exempt.w.Event('blur'));exempt.d.dispatchEvent(new exempt.w.KeyboardEvent('keyup',{key:'PrintScreen'}));
        assert.equal(exempt.fullscreenCalls(),0);assert.equal(exempt.requests.length,0);assert(!exempt.d.querySelector('.quiz-integrity-dialog'));exempt.close();
    }
    console.log('All 11 modes: fullscreen gate/retry, shared warnings, incident deduplication, screenshot shortcuts, fourth-warning zero, warning persistence retry, abandonment, and preview/practice exemptions passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
