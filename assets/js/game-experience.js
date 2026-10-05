(() => {
    const mechanics = {
        standard: 'Choose one of four answers, see feedback, then continue. Points come from the configured question values.',
        time_attack: 'Answer each question within 12 seconds. A timeout earns zero for that question.',
        rocket_rush: 'Correct answers advance your rocket from Launch to Destination. Flight progress is separate from points. Use keys 1–4.',
        treasure_dive: 'Correct answers advance your dive from Surface to Treasure Site. Depth is separate from points.',
        memory_flip: 'Choose the correct answer from four cards. This mode uses multiple-choice scoring.',
        boss_battle: 'Correct answers damage the boss; wrong answers damage your shield. Either reaching zero may end the run early. Health does not subtract academic points.',
        master_ladder: 'Complete Easy, Medium, Hard, and Master. Meet the accuracy target at each level to unlock the next.',
        crossword: 'Fill words from Across and Down clues. Click a clue to change direction. Each whole correct word earns its points.',
        flip_match: 'Reveal two cards to match a term with its definition. Mismatches turn back after a short pause. Match every pair before submitting.',
        fill_blank: 'Type your answers, use hints when available, then review and edit before submitting.',
        emoji_quiz: 'Decode each emoji clue, type the concept, and review your responses before submitting.'
    };
    window.chalkMechanics = mechanics;
    window.chalkGameUI = {
        init(root, quiz) {
            if (!root) return;
            const mode = quiz.game_type;
            const stage = root.querySelector('.question-stage');
            const instructions = document.createElement('details'); instructions.className = 'game-instructions';
            const label = document.createElement('summary'); label.textContent = 'Game instructions';
            const text = document.createElement('p'); text.textContent = mechanics[mode] || mechanics.standard;
            instructions.append(label, text); stage?.before(instructions);
            if (quiz.description) { const teacher = document.createElement('p'); teacher.textContent = `Teacher instructions: ${quiz.description}`; instructions.append(teacher); }
            root.querySelector('[data-game-note]')?.setAttribute('role', 'status');
            const timer = document.querySelector('[data-timer-value]');
            timer?.setAttribute('aria-label', mode === 'time_attack' ? 'Elapsed time and question countdown' : 'Elapsed time');
            if (['flip_match', 'fill_blank', 'emoji_quiz', 'crossword'].includes(mode)) document.querySelector('[data-streak-value]')?.closest('.hud-pill')?.setAttribute('hidden', '');
            if (['rocket_rush', 'treasure_dive'].includes(mode)) {
                const journey = document.createElement('div'); journey.className = 'game-journey';
                journey.innerHTML = '<strong data-journey-label></strong><div class="journey-track"><span class="journey-marker" aria-hidden="true"></span><progress max="100" value="0" aria-label="Visual journey progress"></progress></div><small>Visual progress · points are scored separately</small>';
                journey.querySelector('.journey-marker').textContent = mode === 'rocket_rush' ? '🚀' : '🤿';
                instructions.after(journey);
            }
            if (mode === 'master_ladder') {
                const ladder = document.createElement('ol'); ladder.className = 'mastery-ladder';
                ['Easy', 'Medium', 'Hard', 'Master'].forEach((level, index) => { const item = document.createElement('li'); item.dataset.ladderLevel = index; item.textContent = `${level} · ${index ? 'Locked' : 'Current'}`; ladder.append(item); });
                instructions.after(ladder);
            }
            const feedback = document.createElement('div'); feedback.className = 'answer-feedback'; feedback.hidden = true; feedback.dataset.answerFeedback = ''; feedback.setAttribute('role', 'status');
            const feedbackControls = stage?.querySelector("[data-game-controls]");
            if (feedbackControls) feedbackControls.before(feedback); else stage?.append(feedback);
        },
        start(root) {
            document.querySelectorAll('.game-hud .game-intro-info, .game-hud .lead, .game-hud .feature-pills').forEach(element => { element.hidden = true; });
            root.dataset.playing = '1';
        },
        timer(root, left) {
            root.dataset.timerState = left <= 3 ? 'urgent' : left <= 6 ? 'warning' : 'normal';
            let bar = root.querySelector('.question-clock');
            if (!bar) { bar = document.createElement('progress'); bar.className = 'question-clock'; bar.max = 12; bar.setAttribute('aria-label', 'Seconds remaining for this question'); root.querySelector('.game-progress')?.after(bar); }
            bar.value = left;
        },
        update(root, mode, correct, total, level, cleared = []) {
            const journey = root.querySelector('.game-journey');
            if (journey) {
                const percent = correct / Math.max(1, total) * 100;
                const names = mode === 'rocket_rush' ? ['Launch', 'Atmosphere', 'Orbit', 'Destination'] : ['Surface', 'Coral Zone', 'Deep Water', 'Treasure Site'];
                journey.querySelector('progress').value = percent;
                journey.querySelector('[data-journey-label]').textContent = `${names[Math.min(3, Math.floor(percent / 25))]} · ${Math.round(percent)}%`;
                journey.style.setProperty('--journey-progress', `${percent}%`);
            }
            root.querySelectorAll('[data-ladder-level]').forEach((item, index) => {
                const name = ['Easy', 'Medium', 'Hard', 'Master'][index];
                item.textContent = `${name} · ${cleared.includes(name) ? 'Cleared' : index === level ? 'Current' : index === level + 1 && cleared.includes(['Easy', 'Medium', 'Hard', 'Master'][level]) ? 'Unlocked' : 'Locked'}`;
                item.setAttribute('aria-current', index === level ? 'step' : 'false');
            });
        },
        feedback(root, question, correct, timedOut, mode, damage) {
            const panel = root.querySelector('[data-answer-feedback]'); if (!panel) return;
            panel.replaceChildren(); panel.hidden = false;
            const headline = document.createElement('strong');
            headline.textContent = timedOut ? "Time’s Up · 0 points" : correct ? `Correct! +${question.points} points` : 'Not quite · 0 points';
            panel.append(headline);
            if (!correct) { const answer = document.createElement('p'); answer.textContent = `Correct answer: ${question.options?.[question.correct_index] || ''}`; panel.append(answer); }
            if (question.explanation) { const why = document.createElement('p'); why.textContent = `Why? ${question.explanation}`; panel.append(why); }
            if (mode === 'boss_battle') { const health = document.createElement('p'); health.textContent = `−${damage} ${correct ? 'Boss HP' : 'Shield'} · game health`; health.className = 'health-damage'; panel.append(health); }
        },
        clear(root) { const panel = root.querySelector('[data-answer-feedback]'); if (panel) panel.hidden = true; }
    };
})();
