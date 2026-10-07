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
        crosswordFilled: 0,
    };
    const guard = window.chalkQuizIntegrity?.create(root, {onDisqualify: disqualifyAttempt});
    let startingGame = false;
    let crosswordSurface = null;
    window.chalkGameUI?.init(root, quiz);

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
    const integrityStartMessage = guard?.rule || '';

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
        window.chalkGameUI?.update(root, mode, state.correctCount, questions.length, state.currentLevelIndex, state.masteredLevels);
        if (mode === 'time_attack') window.chalkGameUI?.timer(root, questionTimeLeft);
        const activeQuestion = currentQuestion();
        const progressIndex = activeQuestion ? activeQuestion.sequenceIndex + 1 : questions.length;

        if (progressCount) {
            progressCount.textContent = `${Math.min(progressIndex, Math.max(questions.length, 1))} / ${questions.length}`;
        }

        if (scoreValue) {
            scoreValue.textContent = crosswordMode && !isPreview ? 'After submit' : String(state.score);
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

        if (crosswordMode && !state.finished) {
            if (progressCount) progressCount.textContent = `${state.crosswordFilled} / ${questions.length}`;
            if (progressBar) progressBar.style.width = `${state.crosswordFilled / Math.max(1, questions.length) * 100}%`;
        }
        updateBattleMeters();
    }

    function clearControls() {
        if (controls) {
            controls.innerHTML = "";
        }
    }

    function button(label, className, onClick) {
        if (isPreview && root.dataset.embeddedPreview === '1' && label.startsWith('Back to')) {
            label = 'Close play test'; onClick = () => window.parent.postMessage({type: 'quizweb-preview-close'}, '*');
        }
        const element = document.createElement("button");
        element.type = "button";
        element.className = className;
        element.textContent = label;
        element.addEventListener("click", onClick);

        return element;
    }

    function createHiddenInput(name, value) {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        input.value = String(value);

        return input;
    }

    function integrityStartNotice() {
        return isPreview || practiceMode ? "" : `<p><span>Quiz rule:</span> ${integrityStartMessage.replace("Quiz rule: ", "")}</p>`;
    }

    function submitAttempt(extraFields = {}) {
        const form = document.createElement("form");
        form.method = "post";
        form.action = submitUrl;
        form.appendChild(createHiddenInput("classroom_id", classroomId));
        form.appendChild(createHiddenInput("quiz_id", quiz.id));
        form.appendChild(createHiddenInput("elapsed_seconds", state.elapsedSeconds));
        form.appendChild(createHiddenInput("answers", JSON.stringify(state.answers)));
        form.appendChild(createHiddenInput("csrf", root.dataset.csrf || ''));
        form.appendChild(createHiddenInput("run_token", root.dataset.runToken || ''));

        Object.entries(extraFields).forEach(([name, value]) => {
            form.appendChild(createHiddenInput(name, value));
        });

        document.body.appendChild(form);

        form.submit();
    }

    function disqualifyAttempt(reason, warningCount) {
        if (state.finished) return;
        resetCrosswordSurface();
        state.finished = true;
        state.score = 0;
        state.answers = [];
        window.clearInterval(overallTimer);
        window.clearInterval(questionTimer);
        questionText.textContent = 'Quiz marked as 0.';
        questionPoints.textContent = '0 total points';
        questionHelper.textContent = `Security violation: ${reason}. ${warningCount} warnings recorded.`;
        answerGrid.replaceChildren(); setControls([]); updateHud();
        setNote('Saving the zero score for this attempt?');
        submitAttempt({disqualified: '1', violation_reason: reason, violation_count: warningCount});
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
            if (guard?.paused) return;
            questionTimeLeft -= 1;
            updateHud();

            if (questionTimeLeft <= 0) {
                window.clearInterval(questionTimer);
                handleAnswer(null, false, true);
            }
        }, 1000);
    }

    function showFeedbackBurst(isCorrect, timedOut) {
        if (window.chalkGameUI) { root.classList.toggle("is-hot-streak", state.streak >= 3); return; }
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
            const number = String(Number(placement.number));
            labels[key] = labels[key] && labels[key] !== number ? `${labels[key]}/${number}` : number;
        });

        return labels;
    }

    // Keep shared controls and security instructions alive when leaving the puzzle surface.
    function resetCrosswordSurface() {
        if (!crosswordSurface) return;
        crosswordSurface.resize?.disconnect();
        crosswordSurface.originals.slice().reverse().forEach(({element, parent, next}) => {
            parent.insertBefore(element, next?.parentNode === parent ? next : null);
        });
        crosswordSurface.footer.remove();
        root.classList.remove('crossword-running');
        questionStage?.classList.remove('crossword-stage-active');
        crosswordSurface = null;
    }

    function mountCrosswordSurface(placements) {
        const footer = document.createElement('div');
        footer.className = 'crossword-footer';
        footer.innerHTML = `<div class="crossword-progress"><span>Progress</span><div class="crossword-progress-track" role="progressbar" aria-label="Words filled" aria-valuemin="0" aria-valuemax="${placements.length}" aria-valuenow="0"><div class="crossword-progress-dots" aria-hidden="true">${placements.map(placement => `<span data-word-dot="${Number(placement.question_id)}"></span>`).join('')}</div></div><strong data-crossword-filled>0 / ${placements.length} words</strong></div>`;
        const originals = [];
        const move = (element, destination) => {
            if (!element) return;
            originals.push({element, parent: element.parentNode, next: element.nextSibling});
            destination.append(element);
        };
        move(progressBar?.closest('.game-progress'), footer.querySelector('.crossword-progress-track'));
        move(controls, footer);
        move(root.querySelector('[data-game-help]') || root.querySelector('.game-instructions'), answerGrid.querySelector('.crossword-help'));
        root.append(footer);
        move(note, footer);
        root.classList.add('crossword-running');
        questionStage?.classList.add('crossword-stage-active');
        crosswordSurface = {footer, originals};

        const board = answerGrid.querySelector('.crossword-board-scroll');
        const grid = board.querySelector('.crossword-grid');
        const fit = () => {
            const bounds = board.getBoundingClientRect();
            if (!bounds.width || !bounds.height) return;
            const cols = Number(crosswordLayout.cols) || 1;
            const rows = crosswordLayout.cells.length || 1;
            const size = Math.floor(Math.min((bounds.width - 32) / cols, (bounds.height - 24) / rows));
            const cellSize = Math.max(36, Math.min(64, size));
            grid.style.setProperty('--crossword-cell-size', `${cellSize}px`);
            board.classList.toggle('crossword-board-overflow', cols * cellSize > bounds.width - 32 || rows * cellSize > bounds.height - 24);
        };
        if (window.ResizeObserver) {
            crosswordSurface.resize = new ResizeObserver(fit);
            crosswordSurface.resize.observe(board);
        }
        fit();
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
        const lengthOf = placement => placement.question.word_length || normalizeCrosswordWord(placement.question.answer || placement.question.options?.[0] || '').length;
        const clueMarkup = list => list.map(placement => `<button type="button" class="clue-button" data-clue-id="${Number(placement.question_id)}" aria-pressed="false"><span class="crossword-clue-number">${Number(placement.number)}.</span><span class="crossword-clue-prompt">${escapeHtml(placement.question.prompt)}</span><span class="crossword-clue-length">${lengthOf(placement)} letters</span></button>`).join('');

        answerGrid.innerHTML = `
            <div class="crossword-play-shell">
                <div class="crossword-board-scroll" tabindex="0" aria-label="Scrollable crossword board"><div class="crossword-grid" style="--crossword-cols: ${Number(crosswordLayout.cols || 1)}">
                    ${cells.map((row, rowIndex) => row.map((letter, colIndex) => {
                        if (!letter) {
                            return '<div class="crossword-cell crossword-cell-black" aria-hidden="true"></div>';
                        }

                        const label = labels[`${rowIndex}:${colIndex}`] || "";
                        return `
                            <label class="crossword-cell">
                                ${label ? `<span class="crossword-cell-number">${label}</span>` : ""}
                                <input class="crossword-cell-input" maxlength="1" autocomplete="off" autocapitalize="characters" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}" aria-label="Crossword cell row ${rowIndex + 1} column ${colIndex + 1}">
                            </label>
                        `;
                    }).join("")).join("")}
                </div></div>
                <div class="crossword-clues">
                    <div class="crossword-clue-tabs" role="group" aria-label="Clue direction"><button type="button" class="button button-secondary" data-clue-tab="across" aria-pressed="true">Across</button><button type="button" class="button button-secondary" data-clue-tab="down" aria-pressed="false">Down</button></div>
                    <p data-current-clue role="status"></p>
                    <section data-clue-section="across">
                        <h3>Across</h3>
                        ${clueMarkup(acrossClues) || '<p class="crossword-empty-clues">No across clues in this puzzle.</p>'}
                    </section>
                    <section data-clue-section="down" hidden>
                        <h3>Down</h3>
                        ${clueMarkup(downClues) || '<p class="crossword-empty-clues">No down clues in this puzzle.</p>'}
                    </section>
                    <div class="crossword-help"></div>
                </div>
            </div>
        `;

        let activeWord = placements[0];
        const contains = (placement, row, col) => Array.from({length: lengthOf(placement)}, (_, index) => [Number(placement.row) + (placement.direction === 'down' ? index : 0), Number(placement.col) + (placement.direction === 'across' ? index : 0)]).some(cell => cell[0] === row && cell[1] === col);
        function activate(placement, focus = false) {
            activeWord = placement;
            answerGrid.querySelectorAll('[data-clue-section]').forEach(section => { section.hidden = section.dataset.clueSection !== placement.direction; });
            answerGrid.querySelectorAll('[data-clue-tab]').forEach(tab => tab.setAttribute('aria-pressed', String(tab.dataset.clueTab === placement.direction)));
            answerGrid.querySelectorAll('[data-clue-id]').forEach(clue => clue.setAttribute('aria-pressed', String(Number(clue.dataset.clueId) === Number(placement.question_id))));
            answerGrid.querySelector('[data-current-clue]').textContent = `${placement.number} ${placement.direction}: ${placement.question.prompt}`;
            answerGrid.querySelectorAll('.crossword-cell-input').forEach(input => input.closest('.crossword-cell').classList.toggle('active-word', contains(placement, Number(input.dataset.row), Number(input.dataset.col))));
            if (focus) focusCrosswordCell(Number(placement.row), Number(placement.col));
        }
        answerGrid.querySelectorAll('[data-clue-id]').forEach(clue => clue.addEventListener('click', () => activate(placements.find(placement => Number(placement.question_id) === Number(clue.dataset.clueId)), true)));
        answerGrid.querySelectorAll('[data-clue-tab]').forEach(tab => tab.addEventListener('click', () => {
            const firstWord = placements.find(placement => placement.direction === tab.dataset.clueTab);
            if (firstWord) { activate(firstWord, true); return; }
            answerGrid.querySelectorAll('[data-clue-section]').forEach(section => { section.hidden = section.dataset.clueSection !== tab.dataset.clueTab; });
            answerGrid.querySelectorAll('[data-clue-tab]').forEach(item => item.setAttribute('aria-pressed', String(item === tab)));
            answerGrid.querySelector('[data-current-clue]').textContent = `No ${tab.dataset.clueTab} clues in this puzzle.`;
        }));
        function completion() {
            const filled = placements.filter(placement => {
                for (let index = 0; index < lengthOf(placement); index++) {
                    const row = Number(placement.row) + (placement.direction === 'down' ? index : 0), col = Number(placement.col) + (placement.direction === 'across' ? index : 0);
                    if (!answerGrid.querySelector(`[data-row="${row}"][data-col="${col}"]`)?.value) return false;
                }
                return true;
            });
            const count = filled.length;
            questionPoints.textContent = `${count} / ${placements.length} words filled`;
            state.crosswordFilled = count;
            if (progressCount) progressCount.textContent = `${count} / ${placements.length}`;
            if (progressBar) progressBar.style.width = `${count / placements.length * 100}%`;
            const footer = crosswordSurface?.footer;
            if (footer) {
                footer.querySelector('[data-crossword-filled]').textContent = `${count} / ${placements.length} words`;
                footer.querySelector('[role="progressbar"]').setAttribute('aria-valuenow', String(count));
                footer.querySelectorAll('[data-word-dot]').forEach(dot => dot.classList.toggle('is-filled', filled.some(placement => Number(placement.question_id) === Number(dot.dataset.wordDot))));
            }
        }
        answerGrid.querySelectorAll(".crossword-cell-input").forEach((input) => {
            input.addEventListener('focus', () => {
                input.select();
                const row = Number(input.dataset.row), col = Number(input.dataset.col);
                if (!contains(activeWord, row, col)) activate(placements.find(placement => contains(placement, row, col)) || activeWord);
                else if (answerGrid.querySelector('[data-clue-tab][aria-pressed="true"]')?.dataset.clueTab !== activeWord.direction) activate(activeWord);
            });
            input.addEventListener("input", () => {
                input.value = normalizeCrosswordWord(input.value).slice(0, 1);
                completion();
                if (input.value) {
                    const row = Number(input.dataset.row) + (activeWord.direction === 'down' ? 1 : 0), col = Number(input.dataset.col) + (activeWord.direction === 'across' ? 1 : 0);
                    if (contains(activeWord, row, col)) focusCrosswordCell(row, col);
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
                    event.preventDefault();
                    const previousRow = row - (activeWord.direction === 'down' ? 1 : 0);
                    const previousCol = col - (activeWord.direction === 'across' ? 1 : 0);
                    if (contains(activeWord, previousRow, previousCol)) focusCrosswordCell(previousRow, previousCol);
                }
            });
        });

        setControls([
            button("Submit Answer", "button button-primary crossword-submit", gradeCrossword),
        ]);
        const sendIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        sendIcon.setAttribute('viewBox', '0 0 24 24'); sendIcon.setAttribute('aria-hidden', 'true');
        sendIcon.innerHTML = '<path d="m22 2-7 20-4-9-9-4 20-7ZM22 2 11 13"/>';
        controls.firstChild.prepend(sendIcon);
        mountCrosswordSurface(placements);
        setNote("");
        updateHud();
        activate(activeWord, true); completion();
        if (isPreview) {
            let showingKey = false;
            const savedValues = new Map();
            controls.append(button('Show Answer Key', 'button button-secondary', () => {
                showingKey = !showingKey;
                answerGrid.querySelectorAll('.crossword-cell-input').forEach(input => {
                    if (showingKey) { savedValues.set(input, input.value); input.value = cells[Number(input.dataset.row)][Number(input.dataset.col)]; }
                    else input.value = savedValues.get(input) || '';
                    input.readOnly = showingKey;
                });
                controls.lastChild.textContent = showingKey ? 'Hide Answer Key' : 'Show Answer Key'; completion();
            }));
        }
    }

    function crosswordWordForPlacement(placement) {
        const length = placement.question.word_length || normalizeCrosswordWord(placement.question.answer || placement.question.options?.[0] || "").length;
        const letters = [];

        for (let index = 0; index < length; index += 1) {
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
            const isCorrect = isPreview && submitted === expected;
            const points = Number(placement.question.points || 10);

            state.answers[questionIndex] = submitted;
            if (isCorrect) {
                correct += 1;
                state.score += points;
            }

            for (let index = 0; isPreview && index < expected.length; index += 1) {
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
            <p>${practiceMode ? "This focus round will be recorded in your training history without affecting the class leaderboard." : (isPreview ? "This is a teacher preview, so your run will not be saved." : "Your score, time, and accuracy will be saved when you finish.")}</p>
                    <p>Keyboard shortcuts: <span>1-4 to answer</span>, <span>Enter to continue</span>.</p>
                    ${integrityStartNotice()}
                </div>
            `;
        }

        setControls([
            button(practiceMode ? "Start Practice" : (isPreview ? "Start Preview" : "Start Activity"), "button button-primary", startGame),
            button(practiceMode ? "Back to Dashboard" : "Back to Classroom", "button button-secondary", () => {
                window.location.href = returnUrl;
            }),
        ]);
        setNote("Press Start when you are ready.");
        updateHud();
    }

    async function startGame() {
        if (state.started || startingGame) return;
        if (!guard && !isPreview && !practiceMode) { setNote('Quiz security failed to load. Reload from your classroom.'); return; }
        startingGame = true;
        const ready = guard ? await guard.start() : true;
        startingGame = false;
        if (!ready) return;
        state.started = true;
        window.chalkGameUI?.start(root);
        clearControls(); startTimers();
        if (crosswordMode) renderCrossword(); else renderQuestion();
    }

    function renderQuestion() {
        window.chalkGameUI?.clear(root);
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
        if (guard?.enabled && !guard.canInteract) return;
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
        window.chalkGameUI?.feedback(root, question, isCorrect, timedOut, mode, healthStep());
        updateHud();

        if (masteryMode) {
            const stage = currentMasteryStage();
            const finishedLevel = state.currentQuestionInLevel >= stage.questions.length - 1;

            if (finishedLevel) {
                const levelPercent = Math.round((state.levelCorrect / stage.questions.length) * 100);

                if (levelPercent >= masteryThreshold) {
                    state.masteredLevels.push(stage.label);
                    window.chalkGameUI?.update(root, mode, state.correctCount, questions.length, state.currentLevelIndex, state.masteredLevels);

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

        resetCrosswordSurface();
        state.finished = true;
        guard?.complete();
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
            setNote(practiceMode ? "Practice complete. This training run was recorded outside the class leaderboard." : "Preview complete. Teacher previews are not saved to the scoreboard.");
            setControls([
                button(practiceMode ? "Replay Practice" : "Replay Preview", "button button-primary", () => window.location.reload()),
                button(practiceMode ? "Back to Dashboard" : "Back to Classroom", "button button-secondary", () => {
                    window.location.href = returnUrl;
                }),
            ]);
            return;
        }
        if (crosswordMode) {
            questionPoints.textContent = 'Score calculated after submission';
            questionHelper.textContent = `${state.elapsedSeconds}s elapsed · Your words are ready to submit.`;
            answerGrid.replaceChildren(); scoreValue.textContent = 'After submit';
        }
        setNote("Activity complete. Continue to save your answers and view results.");
        let submittingResults = false;
        setControls([button('View Results', 'button button-primary', () => {
            if (submittingResults) return;
            submittingResults = true; controls.querySelectorAll('button').forEach(item => { item.disabled = true; });
            setNote('Submitting your answers…'); submitAttempt();
        })]);
    }

    document.addEventListener("keydown", (event) => {
        if (guard?.paused) return;
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
