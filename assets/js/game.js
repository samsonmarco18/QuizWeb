(() => {
    const root = document.querySelector("[data-game-root]");
    if (!root) {
        return;
    }

    const quiz = JSON.parse(root.dataset.quiz || "{}");
    const submitUrl = root.dataset.submitUrl;
    const classroomId = root.dataset.classroomId;
    const isPreview = root.dataset.isPreview === "1";
    const returnUrl = root.dataset.returnUrl || "/QuizWeb/dashboard.php";
    const mode = quiz.game_type || "time_attack";
    const masteryMode = mode === "master_ladder";
    const masteryThreshold = Number(quiz.mastery_threshold || 75);
    const progressCount = document.querySelector("[data-progress-count]");
    const scoreValue = document.querySelector("[data-score-value]");
    const timerValue = document.querySelector("[data-timer-value]");
    const questionText = document.querySelector("[data-question-text]");
    const questionPoints = document.querySelector("[data-question-points]");
    const questionHelper = document.querySelector("[data-question-helper]");
    const answerGrid = document.querySelector("[data-answer-grid]");
    const controls = document.querySelector("[data-game-controls]");
    const note = document.querySelector("[data-game-note]");
    const progressBar = document.querySelector("[data-progress-bar]");
    const battleStrip = document.querySelector("[data-battle-strip]");
    const bossHealthFill = document.querySelector("[data-boss-health]");
    const playerHealthFill = document.querySelector("[data-player-health]");
    const rawQuestions = Array.isArray(quiz.questions) ? quiz.questions : [];
    const masteryOrder = [
        { key: "easy", label: "Easy" },
        { key: "medium", label: "Medium" },
        { key: "hard", label: "Hard" },
        { key: "master", label: "Master" },
    ];

    function buildMasteryStages() {
        let sequenceIndex = 0;

        return masteryOrder
            .map((level) => {
                const questions = rawQuestions
                    .filter((question) => (question.level || "easy") === level.key)
                    .map((question) => ({
                        ...question,
                        sequenceIndex: sequenceIndex++,
                    }));

                return {
                    ...level,
                    questions,
                };
            })
            .filter((stage) => stage.questions.length > 0);
    }

    const masteryStages = masteryMode ? buildMasteryStages() : [];
    const questions = masteryMode
        ? masteryStages.flatMap((stage) => stage.questions)
        : rawQuestions.map((question, index) => ({
            ...question,
            sequenceIndex: index,
        }));

    const state = {
        currentIndex: 0,
        score: 0,
        elapsedSeconds: 0,
        answers: [],
        awaitingNext: false,
        bossHealth: 100,
        playerHealth: 100,
        correctCount: 0,
        streak: 0,
        bestStreak: 0,
        started: false,
        finished: false,
        selectedIndex: null,
        currentLevelIndex: 0,
        currentQuestionInLevel: 0,
        levelCorrect: 0,
        masteredLevels: [],
        failedLevelLabel: "",
    };

    const modeNotes = {
        time_attack: "Fast fingers win here. Beat the timer on every question.",
        rocket_rush: "Boost through the stars and lock in the right launch code.",
        memory_flip: "Flip the cards and trust your memory under pressure.",
        treasure_dive: "Dive deep, gather treasure, and keep the streak alive.",
        boss_battle: "Every correct answer damages the boss. Wrong answers hit your shield.",
        master_ladder: "Beat Easy, then earn Medium, Hard, and Master by scoring at least the mastery target on each level.",
    };

    const modeHelpers = {
        time_attack: "You only have 12 seconds per question in this mode.",
        rocket_rush: "Press 1 to 4 on the keyboard for fast answer boosts.",
        memory_flip: "Take a second to scan every option before you lock in.",
        treasure_dive: "Keep the streak going to make the run feel smoother.",
        boss_battle: "Protect your shield and bring the boss meter down to zero.",
        master_ladder: `Each level needs at least ${masteryThreshold}% before the next one unlocks.`,
    };

    let startTime = null;
    let overallTimer = null;
    let questionTimer = null;
    let questionTimeLeft = 12;

    function countAttemptedQuestions() {
        return Object.keys(state.answers).length;
    }

    function accuracy() {
        const total = masteryMode ? Math.max(1, countAttemptedQuestions()) : Math.max(1, questions.length);
        return Math.round((state.correctCount / total) * 100);
    }

    function setNote(message) {
        if (note) {
            note.textContent = message;
        }
    }

    function currentMasteryStage() {
        return masteryStages[state.currentLevelIndex] || null;
    }

    function currentQuestion() {
        if (!masteryMode) {
            return questions[state.currentIndex] || null;
        }

        const stage = currentMasteryStage();
        if (!stage) {
            return null;
        }

        return stage.questions[state.currentQuestionInLevel] || null;
    }

    function updateBattleMeters() {
        if (!battleStrip || !bossHealthFill || !playerHealthFill) {
            return;
        }

        const isBossMode = mode === "boss_battle";
        battleStrip.hidden = !isBossMode;

        if (!isBossMode) {
            return;
        }

        bossHealthFill.style.width = `${state.bossHealth}%`;
        playerHealthFill.style.width = `${state.playerHealth}%`;
    }

    function updateHud() {
        const activeQuestion = currentQuestion();
        const progressIndex = activeQuestion ? activeQuestion.sequenceIndex + 1 : questions.length;

        if (progressCount) {
            progressCount.textContent = `${Math.min(progressIndex, Math.max(questions.length, 1))} / ${questions.length}`;
        }

        if (scoreValue) {
            scoreValue.textContent = String(state.score);
        }

        if (timerValue) {
            if (!state.started) {
                timerValue.textContent = mode === "time_attack" ? "0s | 12s left" : "0s";
            } else if (mode === "time_attack" && !state.finished) {
                timerValue.textContent = `${state.elapsedSeconds}s | ${questionTimeLeft}s left`;
            } else {
                timerValue.textContent = `${state.elapsedSeconds}s`;
            }
        }

        if (progressBar) {
            const percent = questions.length ? ((progressIndex - (activeQuestion ? 1 : 0)) / questions.length) * 100 : 0;
            progressBar.style.width = `${Math.min(Math.max(percent, 0), 100)}%`;
        }

        updateBattleMeters();
    }

    function clearControls() {
        if (controls) {
            controls.innerHTML = "";
        }
    }

    function button(label, className, onClick) {
        const element = document.createElement("button");
        element.type = "button";
        element.className = className;
        element.textContent = label;
        element.addEventListener("click", onClick);

        return element;
    }

    function setControls(elements) {
        if (!controls) {
            return;
        }

        clearControls();
        elements.forEach((element) => controls.appendChild(element));
    }

    function startTimers() {
        startTime = Date.now();
        window.clearInterval(overallTimer);
        overallTimer = window.setInterval(() => {
            state.elapsedSeconds = Math.floor((Date.now() - startTime) / 1000);
            updateHud();
        }, 1000);
    }

    function beginQuestionTimer() {
        window.clearInterval(questionTimer);
        questionTimeLeft = 12;

        if (mode !== "time_attack" || !state.started || state.finished) {
            updateHud();
            return;
        }

        updateHud();
        questionTimer = window.setInterval(() => {
            questionTimeLeft -= 1;
            updateHud();

            if (questionTimeLeft <= 0) {
                window.clearInterval(questionTimer);
                handleAnswer(null, false, true);
            }
        }, 1000);
    }

    function buildAnswerButton(answer, answerIndex) {
        const element = document.createElement("button");
        element.type = "button";
        element.className = "answer-button";
        element.innerHTML = `<span class="answer-index">${answerIndex + 1}</span><span>${answer}</span>`;
        element.dataset.answerIndex = String(answerIndex);

        if (mode === "memory_flip") {
            element.style.transform = `rotate(${(Math.random() - 0.5) * 4}deg)`;
        }

        if (mode === "treasure_dive") {
            element.style.background = `radial-gradient(circle at ${20 + answerIndex * 20}% 30%, rgba(124, 247, 255, 0.18), rgba(255,255,255,0.04))`;
        }

        element.addEventListener("click", () => {
            const question = currentQuestion();
            const correctIndex = Number(question?.correct_index ?? -1);
            handleAnswer(answerIndex, answerIndex === correctIndex, false);
        });

        return element;
    }

    function decorateAfterAnswer(isCorrect, timedOut) {
        if (mode === "boss_battle") {
            if (isCorrect) {
                state.bossHealth = Math.max(0, state.bossHealth - 20);
                setNote(`Direct hit. Boss health is now ${state.bossHealth}%.`);
            } else {
                state.playerHealth = Math.max(0, state.playerHealth - 20);
                setNote(timedOut ? `Time up. Your shield dropped to ${state.playerHealth}%.` : `The boss struck back. Shield at ${state.playerHealth}%.`);
            }
            updateBattleMeters();
            return;
        }

        if (mode === "rocket_rush") {
            setNote(isCorrect ? "Rocket boosted. Great answer." : timedOut ? "The launch window closed." : "Off course. Re-centering.");
            return;
        }

        if (mode === "memory_flip") {
            setNote(isCorrect ? "Memory lock confirmed." : timedOut ? "The cards faded before you answered." : "That card hid the wrong answer.");
            return;
        }

        if (mode === "treasure_dive") {
            setNote(isCorrect ? "Treasure found. Keep diving." : timedOut ? "You missed the treasure chest." : "A decoy chest. Try the next one.");
            return;
        }

        if (mode === "master_ladder") {
            setNote(isCorrect ? "Strong answer. Keep climbing." : timedOut ? "Time ran out. That can cost the level unlock." : "That one slipped. You may need a stronger score to unlock the next level.");
            return;
        }

        setNote(isCorrect ? "Correct. Keep the pace up." : timedOut ? "Time ran out for that question." : "Not quite. Move to the next one.");
    }

    function showStartScreen() {
        if (!questions.length) {
            questionText.textContent = "This quiz does not have any questions yet.";
            questionPoints.textContent = "0 pts";
            questionHelper.textContent = "Ask the teacher to add questions before trying to play.";
            answerGrid.innerHTML = "";
            setControls([
                button("Back to Classroom", "button button-secondary", () => {
                    window.location.href = returnUrl;
                }),
            ]);
            setNote("This classroom needs at least one question before the game can start.");
            updateHud();
            return;
        }

        questionText.textContent = quiz.title || "Ready to Play?";
        questionPoints.textContent = `${questions.length} questions`;

        if (masteryMode) {
            const ladderText = masteryStages.map((stage) => stage.label).join(" -> ");
            questionHelper.textContent = `${modeHelpers[mode]} Levels: ${ladderText}.`;
            answerGrid.innerHTML = `
                <div class="start-card">
                    <strong>${modeNotes[mode]}</strong>
                    <p>You begin at Easy and must reach at least <span>${masteryThreshold}% accuracy</span> on each level before the next one unlocks.</p>
                    <p>Level order: <span>${ladderText}</span>.</p>
                </div>
            `;
        } else {
            questionHelper.textContent = modeHelpers[mode] || "Use the buttons or keys 1 to 4 to answer.";
            answerGrid.innerHTML = `
                <div class="start-card">
                    <strong>${modeNotes[mode] || "Choose the best answer."}</strong>
                    <p>${isPreview ? "This is a teacher preview, so your run will not be saved." : "Your score, time, and accuracy will be saved when you finish."}</p>
                    <p>Keyboard shortcuts: <span>1-4 to answer</span>, <span>Enter to continue</span>.</p>
                </div>
            `;
        }

        setControls([
            button(isPreview ? "Start Preview" : "Start Game", "button button-primary", startGame),
            button("Back to Classroom", "button button-secondary", () => {
                window.location.href = returnUrl;
            }),
        ]);
        setNote("Press Start when you are ready.");
        updateHud();
    }

    function startGame() {
        if (state.started) {
            return;
        }

        state.started = true;
        setNote("Game on.");
        clearControls();
        startTimers();
        renderQuestion();
    }

    function renderQuestion() {
        const question = currentQuestion();

        if (!question || state.bossHealth <= 0 || state.playerHealth <= 0) {
            finishGame();
            return;
        }

        state.awaitingNext = false;
        state.selectedIndex = null;
        state.currentIndex = question.sequenceIndex;
        questionText.textContent = question.prompt;
        questionPoints.textContent = `${question.points} pts`;

        if (masteryMode) {
            const stage = currentMasteryStage();
            questionHelper.textContent = `${stage.label} level. Question ${state.currentQuestionInLevel + 1} of ${stage.questions.length}. Reach ${masteryThreshold}% to unlock the next level.`;
        } else {
            questionHelper.textContent = `Question ${state.currentIndex + 1} of ${questions.length}. ${modeHelpers[mode] || ""}`.trim();
        }

        answerGrid.innerHTML = "";
        question.options.forEach((option, index) => {
            answerGrid.appendChild(buildAnswerButton(option, index));
        });

        setControls([]);
        beginQuestionTimer();
        updateHud();
        setNote(modeNotes[mode] || "Choose the best answer.");
    }

    function unlockNextLevel() {
        state.currentLevelIndex += 1;
        state.currentQuestionInLevel = 0;
        state.levelCorrect = 0;
        renderQuestion();
    }

    function goNext() {
        if (!state.awaitingNext) {
            return;
        }

        if (masteryMode) {
            state.currentQuestionInLevel += 1;
        } else {
            state.currentIndex += 1;
        }

        state.awaitingNext = false;
        renderQuestion();
    }

    function handleAnswer(selectedIndex, isCorrect, timedOut) {
        if (state.awaitingNext || state.finished || !state.started) {
            return;
        }

        const question = currentQuestion();
        if (!question) {
            finishGame();
            return;
        }

        state.awaitingNext = true;
        state.selectedIndex = selectedIndex;
        window.clearInterval(questionTimer);

        const correctIndex = Number(question.correct_index ?? -1);
        state.answers[question.sequenceIndex] = selectedIndex;

        if (isCorrect) {
            state.score += Number(question.points || 0);
            state.correctCount += 1;
            state.streak += 1;
            state.bestStreak = Math.max(state.bestStreak, state.streak);
            if (masteryMode) {
                state.levelCorrect += 1;
            }
        } else {
            state.streak = 0;
        }

        [...answerGrid.querySelectorAll(".answer-button")].forEach((element, index) => {
            element.disabled = true;
            if (index === correctIndex) {
                element.classList.add("is-correct");
            } else if (index === selectedIndex) {
                element.classList.add("is-wrong");
            }
        });

        decorateAfterAnswer(isCorrect, timedOut);
        updateHud();

        if (masteryMode) {
            const stage = currentMasteryStage();
            const finishedLevel = state.currentQuestionInLevel >= stage.questions.length - 1;

            if (finishedLevel) {
                const levelPercent = Math.round((state.levelCorrect / stage.questions.length) * 100);

                if (levelPercent >= masteryThreshold) {
                    state.masteredLevels.push(stage.label);

                    if (state.currentLevelIndex >= masteryStages.length - 1) {
                        questionHelper.textContent = `${stage.label} cleared at ${levelPercent}%. You conquered the full ladder.`;
                        setNote("Mastery Ladder complete.");
                        setControls([
                            button("Finish Run", "button button-primary", finishGame),
                        ]);
                    } else {
                        const nextStage = masteryStages[state.currentLevelIndex + 1];
                        questionHelper.textContent = `${stage.label} cleared at ${levelPercent}%. ${nextStage.label} is now unlocked.`;
                        setNote(`${stage.label} mastered. ${nextStage.label} unlocked.`);
                        setControls([
                            button(`Unlock ${nextStage.label}`, "button button-primary", unlockNextLevel),
                        ]);
                    }
                } else {
                    state.failedLevelLabel = stage.label;
                    questionHelper.textContent = `${stage.label} ended at ${levelPercent}%. You need ${masteryThreshold}% to unlock the next level.`;
                    setNote(`${stage.label} was not mastered, so the next level stays locked.`);
                    setControls([
                        button("Finish Run", "button button-primary", finishGame),
                    ]);
                }

                return;
            }
        }

        if ((mode === "boss_battle" && state.playerHealth <= 0) || state.bossHealth <= 0 || (!masteryMode && state.currentIndex >= questions.length - 1)) {
            setControls([
                button("Finish Run", "button button-primary", finishGame),
            ]);
            return;
        }

        setControls([
            button("Next Question", "button button-primary", goNext),
        ]);
    }

    function finishGame() {
        if (state.finished) {
            return;
        }

        state.finished = true;
        state.awaitingNext = false;
        window.clearInterval(overallTimer);
        window.clearInterval(questionTimer);

        if (progressCount) {
            progressCount.textContent = `${questions.length} / ${questions.length}`;
        }

        if (progressBar) {
            progressBar.style.width = "100%";
        }

        const runAccuracy = accuracy();
        const ladderSummary = masteryMode
            ? (state.masteredLevels.length
                ? `Levels cleared: ${state.masteredLevels.join(", ")}`
                : `Unlocked up to: ${state.failedLevelLabel || "Easy"}`)
            : `Best streak ${state.bestStreak}`;

        questionText.textContent = isPreview ? "Preview complete." : "Run complete.";
        questionPoints.textContent = `${state.score} total points`;
        questionHelper.textContent = `Accuracy ${runAccuracy}% | ${ladderSummary} | ${state.elapsedSeconds}s elapsed`;
        answerGrid.innerHTML = `
            <div class="finish-card">
                <article class="finish-metric">
                    <strong>${state.score}</strong>
                    <span>Total score</span>
                </article>
                <article class="finish-metric">
                    <strong>${runAccuracy}%</strong>
                    <span>Accuracy</span>
                </article>
                <article class="finish-metric">
                    <strong>${masteryMode ? state.masteredLevels.length : state.bestStreak}</strong>
                    <span>${masteryMode ? "Levels cleared" : "Best streak"}</span>
                </article>
            </div>
        `;

        if (isPreview) {
            setNote("Preview complete. Teacher previews are not saved to the scoreboard.");
            setControls([
                button("Replay Preview", "button button-primary", () => window.location.reload()),
                button("Back to Classroom", "button button-secondary", () => {
                    window.location.href = returnUrl;
                }),
            ]);
            return;
        }

        setNote("Saving your results...");

        const form = document.createElement("form");
        form.method = "post";
        form.action = submitUrl;
        form.innerHTML = `
            <input type="hidden" name="classroom_id" value="${classroomId}">
            <input type="hidden" name="quiz_id" value="${quiz.id}">
            <input type="hidden" name="elapsed_seconds" value="${state.elapsedSeconds}">
            <input type="hidden" name="answers" value='${JSON.stringify(state.answers)}'>
        `;
        document.body.appendChild(form);

        window.setTimeout(() => {
            form.submit();
        }, 900);
    }

    document.addEventListener("keydown", (event) => {
        if (!state.started && event.key === "Enter") {
            const startButton = controls?.querySelector(".button-primary");
            startButton?.click();
            return;
        }

        if (state.finished && event.key === "Enter") {
            const finishButton = controls?.querySelector(".button-primary");
            finishButton?.click();
            return;
        }

        if (state.awaitingNext && event.key === "Enter") {
            const nextButton = controls?.querySelector(".button-primary");
            nextButton?.click();
            return;
        }

        if (!state.started || state.awaitingNext || state.finished) {
            return;
        }

        const keyNumber = Number(event.key);
        if (keyNumber >= 1 && keyNumber <= 4) {
            const target = answerGrid?.querySelector(`[data-answer-index="${keyNumber - 1}"]`);
            target?.click();
        }
    });

    updateHud();
    updateBattleMeters();
    showStartScreen();
})();
