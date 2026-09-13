(() => {
    const root = document.querySelector("[data-game-root]");
    if (!root) {
        return;
    }

    const quiz = JSON.parse(root.dataset.quiz || "{}");
    const submitUrl = root.dataset.submitUrl;
    const classroomId = root.dataset.classroomId;
    const isPreview = root.dataset.isPreview === "1";
    const practiceMode = root.dataset.practiceMode === "1";
    const returnUrl = root.dataset.returnUrl || "/QuizWeb/dashboard.php";
    const mode = quiz.game_type || "time_attack";
    const crosswordMode = mode === "crossword";
    const masteryMode = mode === "master_ladder";
    const masteryThreshold = Number(quiz.mastery_threshold || 75);
    const crosswordLayout = quiz.crossword_layout || null;
    const progressCount = document.querySelector("[data-progress-count]");
    const scoreValue = document.querySelector("[data-score-value]");
    const streakValue = document.querySelector("[data-streak-value]");
    const timerValue = document.querySelector("[data-timer-value]");
    const questionText = document.querySelector("[data-question-text]");
    const questionPoints = document.querySelector("[data-question-points]");
    const questionHelper = document.querySelector("[data-question-helper]");
    const answerGrid = document.querySelector("[data-answer-grid]");
    const controls = document.querySelector("[data-game-controls]");
    const note = document.querySelector("[data-game-note]");
    const questionStage = document.querySelector(".question-stage");
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
    const integrity = {
        active: false,
        warningCount: 0,
        maxWarnings: 3,
        lastViolationAt: 0,
        disqualifying: false,
        unloadSubmitted: false,
        dialog: null,
    };

    const modeNotes = {
        time_attack: "Fast fingers win here. Beat the timer on every question.",
        rocket_rush: "Boost through the stars and lock in the right launch code.",
        memory_flip: "Flip the cards and trust your memory under pressure.",
        treasure_dive: "Dive deep, gather treasure, and keep the streak alive.",
        boss_battle: "Every correct answer damages the boss. Wrong answers hit your shield.",
        crossword: "Solve each clue in the grid. Words cross through shared matching letters.",
        master_ladder: "Beat Easy, then earn Medium, Hard, and Master by scoring at least the mastery target on each level.",
    };

    const modeHelpers = {
        time_attack: "You only have 12 seconds per question in this mode.",
        rocket_rush: "Press 1 to 4 on the keyboard for fast answer boosts.",
        memory_flip: "Take a second to scan every option before you lock in.",
        treasure_dive: "Keep the streak going to make the run feel smoother.",
        boss_battle: "Protect your shield and bring the boss meter down to zero.",
        crossword: "Fill every white square, use intersections to check your spelling, then submit the completed puzzle.",
        master_ladder: `Each level needs at least ${masteryThreshold}% before the next one unlocks.`,
    };
    const integrityStartMessage = "Quiz rule: stay in fullscreen and keep this tab focused. Alt-tab, minimizing, or leaving fullscreen gives a warning. After 3 warnings, the next violation marks the quiz as 0. Reloading or closing the quiz while it is active also marks it as 0.";

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

    function escapeHtml(value) {
        return String(value ?? "").replace(/[&<>"']/g, (character) => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            "\"": "&quot;",
            "'": "&#039;",
        })[character]);
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

    function fullscreenElement() {
        return document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement || null;
    }

    function requestQuizFullscreen() {
        const target = document.documentElement;
        const request = target.requestFullscreen || target.webkitRequestFullscreen || target.msRequestFullscreen;

        if (isPreview || fullscreenElement() || !request) {
            return Promise.resolve();
        }

        return Promise.resolve(request.call(target)).catch(() => {
            setNote("Fullscreen could not start automatically. Keep this quiz tab focused.");
        });
    }

    function setIntegrityActive(active) {
        integrity.active = !isPreview && active;
    }

    function createHiddenInput(name, value) {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        input.value = String(value);

        return input;
    }

    function integrityStartNotice() {
        return isPreview ? "" : `<p><span>Quiz rule:</span> ${integrityStartMessage.replace("Quiz rule: ", "")}</p>`;
    }

    function submitAttempt(extraFields = {}) {
        const form = document.createElement("form");
        form.method = "post";
        form.action = submitUrl;
        form.appendChild(createHiddenInput("classroom_id", classroomId));
        form.appendChild(createHiddenInput("quiz_id", quiz.id));
        form.appendChild(createHiddenInput("elapsed_seconds", state.elapsedSeconds));
        form.appendChild(createHiddenInput("answers", JSON.stringify(state.answers)));

        Object.entries(extraFields).forEach(([name, value]) => {
            form.appendChild(createHiddenInput(name, value));
        });

        document.body.appendChild(form);

        window.setTimeout(() => {
            form.submit();
        }, 900);
    }

    function ensureIntegrityDialog() {
        if (integrity.dialog) {
            return integrity.dialog;
        }

        const dialog = document.createElement("div");
        dialog.className = "quiz-integrity-dialog";
        dialog.hidden = true;
        dialog.setAttribute("aria-hidden", "true");
        dialog.innerHTML = `
            <section class="quiz-integrity-panel" role="alertdialog" aria-modal="true" aria-labelledby="quiz-integrity-title">
                <span class="eyebrow">Quiz Warning</span>
                <h2 id="quiz-integrity-title">Stay in fullscreen</h2>
                <p data-integrity-message></p>
                <button class="button button-primary" type="button" data-integrity-continue>Continue Quiz</button>
            </section>
        `;

        dialog.querySelector("[data-integrity-continue]")?.addEventListener("click", () => {
            dialog.hidden = true;
            dialog.setAttribute("aria-hidden", "true");
            setIntegrityActive(false);
            requestQuizFullscreen().finally(() => {
                if (state.started && !state.finished && !integrity.disqualifying) {
                    setIntegrityActive(true);
                }
            });
        });

        document.body.appendChild(dialog);
        integrity.dialog = dialog;

        return dialog;
    }

    function showIntegrityWarning(reason) {
        const dialog = ensureIntegrityDialog();
        const message = dialog.querySelector("[data-integrity-message]");
        const remaining = integrity.maxWarnings - integrity.warningCount;
        const nextMessage = remaining > 0
            ? `${remaining} warning${remaining === 1 ? "" : "s"} left before your score is marked as 0.`
            : "This is your last warning. The next violation will mark your score as 0.";

        if (message) {
            message.textContent = `Warning ${integrity.warningCount} of ${integrity.maxWarnings}: ${reason}. ${nextMessage}`;
        }

        dialog.hidden = false;
        dialog.setAttribute("aria-hidden", "false");
        setNote(`Warning ${integrity.warningCount} of ${integrity.maxWarnings}. Return to fullscreen to continue.`);
    }

    function disqualifyAttempt(reason) {
        if (integrity.disqualifying || state.finished) {
            return;
        }

        integrity.disqualifying = true;
        setIntegrityActive(false);
        state.finished = true;
        state.awaitingNext = false;
        state.score = 0;
        state.answers = [];
        window.clearInterval(overallTimer);
        window.clearInterval(questionTimer);

        questionText.textContent = "Quiz marked as 0.";
        questionPoints.textContent = "0 total points";
        questionHelper.textContent = "The warning limit was exceeded.";
        answerGrid.innerHTML = `
            <div class="finish-card">
                <article class="finish-metric">
                    <strong>0</strong>
                    <span>Total score</span>
                </article>
                <article class="finish-metric">
                    <strong>${integrity.warningCount}</strong>
                    <span>Warnings</span>
                </article>
                <article class="finish-metric">
                    <strong>${state.elapsedSeconds}s</strong>
                    <span>Time used</span>
                </article>
            </div>
        `;
        setControls([]);
        setNote("Saving a zero score for this attempt...");
        updateHud();
        submitAttempt({
            disqualified: "1",
            violation_reason: reason,
            violation_count: integrity.warningCount,
        });
    }

    function appendAttemptField(formData, name, value) {
        formData.append(name, String(value));
    }

    function submitUnloadDisqualification(reason) {
        if (isPreview || integrity.unloadSubmitted || !state.started || state.finished) {
            return;
        }

        integrity.unloadSubmitted = true;
        const formData = new FormData();
        appendAttemptField(formData, "classroom_id", classroomId);
        appendAttemptField(formData, "quiz_id", quiz.id);
        appendAttemptField(formData, "elapsed_seconds", state.elapsedSeconds);
        appendAttemptField(formData, "answers", "[]");
        appendAttemptField(formData, "disqualified", "1");
        appendAttemptField(formData, "violation_reason", reason);
        appendAttemptField(formData, "violation_count", integrity.warningCount + 1);

        if (navigator.sendBeacon) {
            navigator.sendBeacon(submitUrl, formData);
            return;
        }

        fetch(submitUrl, {
            method: "POST",
            body: formData,
            keepalive: true,
        }).catch(() => {});
    }

    function registerIntegrityViolation(reason) {
        if (!integrity.active || !state.started || state.finished || integrity.disqualifying) {
            return;
        }

        const now = Date.now();
        if (now - integrity.lastViolationAt < 1500) {
            return;
        }

        integrity.lastViolationAt = now;
        integrity.warningCount += 1;

        if (integrity.warningCount > integrity.maxWarnings) {
            disqualifyAttempt(reason);
            return;
        }

        showIntegrityWarning(reason);
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

    function showFeedbackBurst(isCorrect, timedOut) {
        if (!questionStage) {
            return;
        }

        const burst = document.createElement("div");
        burst.className = `feedback-burst ${isCorrect ? "is-correct" : "is-wrong"}`;
        burst.textContent = isCorrect
            ? (state.streak >= 3 ? `${state.streak}x streak` : "Correct")
            : (timedOut ? "Time up" : "Review");
        questionStage.appendChild(burst);
        root.classList.toggle("is-hot-streak", state.streak >= 3);

        window.setTimeout(() => {
            burst.remove();
        }, 900);
    }

    function buildAnswerButton(answer, answerIndex) {
        const element = document.createElement("button");
        element.type = "button";
        element.className = "answer-button";
        element.dataset.answerIndex = String(answerIndex);

        const index = document.createElement("span");
        index.className = "answer-index";
        index.textContent = String(answerIndex + 1);

        const label = document.createElement("span");
        label.className = "answer-label";
        label.textContent = String(answer ?? "");

        element.append(index, label);

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

    function normalizeCrosswordWord(value) {
        return String(value || "").replace(/[^a-z]/gi, "").toUpperCase();
    }

    function crosswordPlacements() {
        const placements = Array.isArray(crosswordLayout?.placements) ? crosswordLayout.placements : [];

        return placements.map((placement) => ({
            ...placement,
            question: questions.find((question) => Number(question.id) === Number(placement.question_id)),
        })).filter((placement) => placement.question);
    }

    function crosswordStartLabels() {
        const labels = {};

        crosswordPlacements().forEach((placement) => {
            const key = `${placement.row}:${placement.col}`;
            const prefix = placement.direction === "across" ? "A" : "D";
            labels[key] = labels[key] ? `${labels[key]} ${prefix}${placement.number}` : `${prefix}${placement.number}`;
        });

        return labels;
    }

    function focusNextCrosswordCell(input) {
        const row = Number(input.dataset.row);
        const col = Number(input.dataset.col);
        const next = answerGrid.querySelector(`.crossword-cell-input[data-row="${row}"][data-col="${col + 1}"]`)
            || answerGrid.querySelector(`.crossword-cell-input[data-row="${row + 1}"]`);
        next?.focus();
    }

    function focusCrosswordCell(row, col) {
        const target = answerGrid.querySelector(`.crossword-cell-input[data-row="${row}"][data-col="${col}"]`);
        target?.focus();
    }

    function renderCrossword() {
        const placements = crosswordPlacements();

        if (!crosswordLayout || !placements.length) {
            questionText.textContent = "Crossword needs revision.";
            questionPoints.textContent = "0 words";
            questionHelper.textContent = "Ask the teacher to revise the word list so every word intersects correctly.";
            answerGrid.innerHTML = "";
            setControls([
                button("Back to Classroom", "button button-secondary", () => {
                    window.location.href = returnUrl;
                }),
            ]);
            setNote("The saved crossword layout is missing or invalid.");
            updateHud();
            return;
        }

        state.currentIndex = 0;
        questionText.textContent = quiz.title || "Crossword Puzzle";
        questionPoints.textContent = `${questions.length} words`;
        questionHelper.textContent = "Use the Across and Down clues. Shared letters help check spelling and accuracy.";
        const labels = crosswordStartLabels();
        const cells = Array.isArray(crosswordLayout.cells) ? crosswordLayout.cells : [];
        const acrossClues = placements.filter((placement) => placement.direction === "across").sort((a, b) => a.number - b.number);
        const downClues = placements.filter((placement) => placement.direction === "down").sort((a, b) => a.number - b.number);

        answerGrid.innerHTML = `
            <div class="crossword-play-shell">
                <div class="crossword-grid" style="--crossword-cols: ${Number(crosswordLayout.cols || 1)}">
                    ${cells.map((row, rowIndex) => row.map((letter, colIndex) => {
                        if (!letter) {
                            return '<div class="crossword-cell crossword-cell-black" aria-hidden="true"></div>';
                        }

                        const label = labels[`${rowIndex}:${colIndex}`] || "";
                        return `
                            <label class="crossword-cell">
                                ${label ? `<span class="crossword-cell-number">${label}</span>` : ""}
                                <input class="crossword-cell-input" maxlength="1" autocomplete="off" data-row="${rowIndex}" data-col="${colIndex}" aria-label="Crossword cell row ${rowIndex + 1} column ${colIndex + 1}">
                            </label>
                        `;
                    }).join("")).join("")}
                </div>
                <div class="crossword-clues">
                    <section>
                        <h3>Across</h3>
                        ${acrossClues.map((placement) => `<p><strong>${placement.number}.</strong> ${escapeHtml(placement.question.prompt)}</p>`).join("") || "<p>No across clues.</p>"}
                    </section>
                    <section>
                        <h3>Down</h3>
                        ${downClues.map((placement) => `<p><strong>${placement.number}.</strong> ${escapeHtml(placement.question.prompt)}</p>`).join("") || "<p>No down clues.</p>"}
                    </section>
                </div>
            </div>
        `;

        answerGrid.querySelectorAll(".crossword-cell-input").forEach((input) => {
            input.addEventListener("input", () => {
                input.value = normalizeCrosswordWord(input.value).slice(0, 1);
                if (input.value) {
                    focusNextCrosswordCell(input);
                }
            });

            input.addEventListener("keydown", (event) => {
                const row = Number(input.dataset.row);
                const col = Number(input.dataset.col);

                if (event.key === "ArrowRight") {
                    event.preventDefault();
                    focusCrosswordCell(row, col + 1);
                } else if (event.key === "ArrowLeft") {
                    event.preventDefault();
                    focusCrosswordCell(row, col - 1);
                } else if (event.key === "ArrowDown") {
                    event.preventDefault();
                    focusCrosswordCell(row + 1, col);
                } else if (event.key === "ArrowUp") {
                    event.preventDefault();
                    focusCrosswordCell(row - 1, col);
                } else if (event.key === "Backspace" && !input.value) {
                    focusCrosswordCell(row, col - 1);
                }
            });
        });

        setControls([
            button("Submit Crossword", "button button-primary", gradeCrossword),
        ]);
        setNote("Fill the grid, then submit your crossword.");
        updateHud();
    }

    function crosswordWordForPlacement(placement) {
        const answer = normalizeCrosswordWord(placement.question.answer || placement.question.options?.[0] || "");
        const letters = [];

        for (let index = 0; index < answer.length; index += 1) {
            const row = Number(placement.row) + (placement.direction === "down" ? index : 0);
            const col = Number(placement.col) + (placement.direction === "across" ? index : 0);
            const input = answerGrid.querySelector(`.crossword-cell-input[data-row="${row}"][data-col="${col}"]`);
            letters.push(input?.value || "");
        }

        return normalizeCrosswordWord(letters.join(""));
    }

    function gradeCrossword() {
        if (state.finished) {
            return;
        }

        const placements = crosswordPlacements();
        let correct = 0;
        state.score = 0;
        state.answers = [];

        placements.forEach((placement) => {
            const questionIndex = questions.findIndex((question) => Number(question.id) === Number(placement.question_id));
            const expected = normalizeCrosswordWord(placement.question.answer || placement.question.options?.[0] || "");
            const submitted = crosswordWordForPlacement(placement);
            const isCorrect = submitted === expected;
            const points = Number(placement.question.points || 10);

            state.answers[questionIndex] = submitted;
            if (isCorrect) {
                correct += 1;
                state.score += points;
            }

            for (let index = 0; index < expected.length; index += 1) {
                const row = Number(placement.row) + (placement.direction === "down" ? index : 0);
                const col = Number(placement.col) + (placement.direction === "across" ? index : 0);
                const input = answerGrid.querySelector(`.crossword-cell-input[data-row="${row}"][data-col="${col}"]`);
                input?.classList.toggle("is-correct", isCorrect);
                input?.classList.toggle("is-wrong", !isCorrect);
            }
        });

        state.correctCount = correct;
        finishGame();
    }

    function healthStep() {
        return Math.max(4, Math.ceil(100 / Math.max(questions.length, 1)));
    }

    function decorateAfterAnswer(isCorrect, timedOut) {
        if (mode === "boss_battle") {
            const damage = healthStep();
            if (isCorrect) {
                state.bossHealth = Math.max(0, state.bossHealth - damage);
                setNote(`Direct hit. Boss health is now ${state.bossHealth}%.`);
            } else {
                state.playerHealth = Math.max(0, state.playerHealth - damage);
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
        questionPoints.textContent = crosswordMode ? `${questions.length} words` : `${questions.length} questions`;

        if (crosswordMode) {
            questionHelper.textContent = modeHelpers[mode];
            answerGrid.innerHTML = `
                <div class="start-card">
                    <strong>${modeNotes[mode]}</strong>
                    <p>Start with the longest clues, use crossing letters to confirm spelling, and fill every white square.</p>
                    <p>Black squares separate words. Across and Down clues are numbered separately.</p>
                    ${integrityStartNotice()}
                </div>
            `;
        } else if (masteryMode) {
            const ladderText = masteryStages.map((stage) => stage.label).join(" -> ");
            questionHelper.textContent = `${modeHelpers[mode]} Levels: ${ladderText}.`;
            answerGrid.innerHTML = `
                <div class="start-card">
                    <strong>${modeNotes[mode]}</strong>
                    <p>You begin at Easy and must reach at least <span>${masteryThreshold}% accuracy</span> on each level before the next one unlocks.</p>
                    <p>Level order: <span>${ladderText}</span>.</p>
                    ${integrityStartNotice()}
                </div>
            `;
        } else {
            questionHelper.textContent = modeHelpers[mode] || "Use the buttons or keys 1 to 4 to answer.";
            answerGrid.innerHTML = `
                <div class="start-card">
                    <strong>${modeNotes[mode] || "Choose the best answer."}</strong>
                    <p>${practiceMode ? "This focus round is for practice only, so it will not be saved to the scoreboard." : (isPreview ? "This is a teacher preview, so your run will not be saved." : "Your score, time, and accuracy will be saved when you finish.")}</p>
                    <p>Keyboard shortcuts: <span>1-4 to answer</span>, <span>Enter to continue</span>.</p>
                    ${integrityStartNotice()}
                </div>
            `;
        }

        setControls([
            button(practiceMode ? "Start Practice" : (isPreview ? "Start Preview" : "Start Game"), "button button-primary", startGame),
            button(practiceMode ? "Back to Dashboard" : "Back to Classroom", "button button-secondary", () => {
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
        setNote(isPreview ? (practiceMode ? "Practice started." : "Game on.") : "Entering fullscreen...");
        clearControls();
        requestQuizFullscreen().finally(() => {
            if (!state.started || state.finished) {
                return;
            }

            setIntegrityActive(true);
            startTimers();
            if (crosswordMode) {
                renderCrossword();
                return;
            }
            renderQuestion();
        });
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

        showFeedbackBurst(isCorrect, timedOut);
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
        setIntegrityActive(false);
        state.awaitingNext = false;
        window.clearInterval(overallTimer);
        window.clearInterval(questionTimer);

        if (progressCount) {
            progressCount.textContent = `${questions.length} / ${questions.length}`;
        }

        if (progressBar) {
            progressBar.style.width = "100%";
        }

        if (scoreValue) {
            scoreValue.textContent = String(state.score);
        }

        if (streakValue) {
            streakValue.textContent = `${state.streak}x`;
        }

        const runAccuracy = accuracy();
        const ladderSummary = masteryMode
            ? (state.masteredLevels.length
                ? `Levels cleared: ${state.masteredLevels.join(", ")}`
                : `Unlocked up to: ${state.failedLevelLabel || "Easy"}`)
            : `Best streak ${state.bestStreak}`;

        questionText.textContent = practiceMode ? "Practice complete." : (isPreview ? "Preview complete." : "Run complete.");
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
            setNote(practiceMode ? "Practice complete. This run was not saved to the scoreboard." : "Preview complete. Teacher previews are not saved to the scoreboard.");
            setControls([
                button(practiceMode ? "Replay Practice" : "Replay Preview", "button button-primary", () => window.location.reload()),
                button(practiceMode ? "Back to Dashboard" : "Back to Classroom", "button button-secondary", () => {
                    window.location.href = returnUrl;
                }),
            ]);
            return;
        }

        setNote("Saving your results...");
        submitAttempt();
    }

    document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
            registerIntegrityViolation("leaving the quiz tab or minimizing the browser");
        }
    });

    window.addEventListener("beforeunload", (event) => {
        if (!integrity.active || !state.started || state.finished || isPreview) {
            return;
        }

        event.preventDefault();
        event.returnValue = integrityStartMessage;
    });

    window.addEventListener("pagehide", () => {
        if (integrity.active && state.started && !state.finished && !isPreview) {
            submitUnloadDisqualification("reloading or closing the quiz page");
        }
    });

    window.addEventListener("blur", () => {
        registerIntegrityViolation("moving focus away from the quiz");
    });

    ["fullscreenchange", "webkitfullscreenchange", "MSFullscreenChange"].forEach((eventName) => {
        document.addEventListener(eventName, () => {
            if (integrity.active && !fullscreenElement()) {
                registerIntegrityViolation("exiting fullscreen");
            }
        });
    });

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
