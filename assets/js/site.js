(() => {
    const body = document.body;
    const themeToggle = document.getElementById("theme-toggle");
    const questionList = document.getElementById("question-list");
    const questionTemplate = document.getElementById("question-template");
    const addQuestionButton = document.getElementById("add-question-button");
    const builderForm = document.getElementById("quiz-builder-form");
    const payloadInput = document.getElementById("questions_payload");
    const messengerDock = document.querySelector("[data-messenger-dock]");
    const themeStorageKey = "quizweb-theme";

    function setTheme(isDark) {
        if (!body) {
            return;
        }

        body.classList.toggle("theme-dark", isDark);

        if (themeToggle) {
            themeToggle.setAttribute("aria-pressed", isDark ? "true" : "false");
            themeToggle.querySelector(".theme-toggle-label").textContent = isDark ? "Light Mode" : "Dark Mode";
        }

        try {
            window.localStorage.setItem(themeStorageKey, isDark ? "dark" : "default");
        } catch (error) {
            // Ignore storage failures and keep the theme only for the current page.
        }
    }

    if (themeToggle) {
        let savedTheme = "default";

        try {
            savedTheme = window.localStorage.getItem(themeStorageKey) || "default";
        } catch (error) {
            savedTheme = "default";
        }

        setTheme(savedTheme === "dark");

        themeToggle.addEventListener("click", () => {
            setTheme(!body.classList.contains("theme-dark"));
        });
    }

    function buildQuestionCard(seed = {}) {
        if (!questionTemplate || !questionList) {
            return null;
        }

        const fragment = questionTemplate.content.cloneNode(true);
        const card = fragment.querySelector(".question-card");

        card.querySelector('[data-field="prompt"]').value = seed.prompt || "";
        card.querySelector('[data-field="option-0"]').value = seed.options?.[0] || "";
        card.querySelector('[data-field="option-1"]').value = seed.options?.[1] || "";
        card.querySelector('[data-field="option-2"]').value = seed.options?.[2] || "";
        card.querySelector('[data-field="option-3"]').value = seed.options?.[3] || "";
        card.querySelector('[data-field="correct_index"]').value = String(seed.correct_index ?? 0);
        card.querySelector('[data-field="points"]').value = String(seed.points ?? 10);
        card.querySelector('[data-field="level"]').value = seed.level || "easy";

        card.querySelector(".remove-question").addEventListener("click", () => {
            card.remove();
            refreshQuestionLabels();
        });

        questionList.appendChild(fragment);
        refreshQuestionLabels();

        return card;
    }

    function refreshQuestionLabels() {
        if (!questionList) {
            return;
        }

        [...questionList.querySelectorAll(".question-card")].forEach((card, index) => {
            const label = card.querySelector("[data-question-label]");
            if (label) {
                label.textContent = `Question ${index + 1}`;
            }
        });
    }

    function collectQuestions() {
        if (!questionList) {
            return [];
        }

        return [...questionList.querySelectorAll(".question-card")].map((card) => ({
            prompt: card.querySelector('[data-field="prompt"]').value.trim(),
            options: [
                card.querySelector('[data-field="option-0"]').value.trim(),
                card.querySelector('[data-field="option-1"]').value.trim(),
                card.querySelector('[data-field="option-2"]').value.trim(),
                card.querySelector('[data-field="option-3"]').value.trim(),
            ],
            correct_index: Number(card.querySelector('[data-field="correct_index"]').value || 0),
            points: Number(card.querySelector('[data-field="points"]').value || 10),
            level: card.querySelector('[data-field="level"]').value || "easy",
        }));
    }

    if (questionList && builderForm && payloadInput) {
        const seed = Array.isArray(window.quizBuilderSeed) ? window.quizBuilderSeed : [];
        if (seed.length) {
            seed.forEach((question) => buildQuestionCard(question));
        } else {
            buildQuestionCard();
        }

        addQuestionButton?.addEventListener("click", () => buildQuestionCard());

        builderForm.addEventListener("submit", (event) => {
            const questions = collectQuestions();
            const incomplete = questions.some((question) => !question.prompt || question.options.some((option) => !option));

            if (!questions.length || incomplete) {
                event.preventDefault();
                alert("Please complete every question and answer before saving the quiz.");
                return;
            }

            payloadInput.value = JSON.stringify(questions);
        });
    }

    if (messengerDock) {
        const messengerPanel = messengerDock.querySelector(".messenger-panel");
        const messengerLauncher = messengerDock.querySelector(".messenger-launcher");
        const messengerNavButton = document.querySelector("[data-messenger-nav-toggle]");
        const messengerClose = messengerDock.querySelector("[data-messenger-close]");
        const messengerGroupButtons = [...messengerDock.querySelectorAll("[data-messenger-target]")];
        const messengerThreads = [...messengerDock.querySelectorAll("[data-messenger-thread]")];
        const messengerBadgeElements = [...document.querySelectorAll("[data-messenger-badge]")];
        const defaultThreadId = messengerDock.dataset.defaultChatId || "";
        const currentUserId = Number(messengerDock.dataset.currentUserId || 0);
        const messengerSeenStorageKey = "quizweb-messenger-seen";
        const messengerOpenStorageKey = "quizweb-messenger-open";

        function readMessengerSeenState() {
            try {
                const storedState = window.localStorage.getItem(messengerSeenStorageKey);
                return storedState ? JSON.parse(storedState) : {};
            } catch (error) {
                return {};
            }
        }

        function writeMessengerSeenState(state) {
            try {
                window.localStorage.setItem(messengerSeenStorageKey, JSON.stringify(state));
            } catch (error) {
                // Ignore storage failures and keep the current-session indicator only.
            }
        }

        function threadChatId(thread) {
            return thread?.dataset.chatId || thread?.id?.replace(/^messenger-thread-/, "");
        }

        function threadLatestMs(thread) {
            const latestAt = thread?.dataset.latestAt || "";
            const latestMs = Date.parse(latestAt);
            return Number.isFinite(latestMs) ? latestMs : 0;
        }

        function threadLatestSenderId(thread) {
            return Number(thread?.dataset.latestUserId || 0);
        }

        function seenMsForThread(thread, state) {
            const chatId = threadChatId(thread);

            if (!chatId) {
                return 0;
            }

            return Number(state[chatId] || 0);
        }

        function updateMessengerBadges() {
            const state = readMessengerSeenState();
            const unreadCount = messengerThreads.reduce((count, thread) => {
                const latestMs = threadLatestMs(thread);
                const seenMs = seenMsForThread(thread, state);
                const latestSenderId = threadLatestSenderId(thread);

                if (latestMs > seenMs && latestSenderId !== currentUserId) {
                    return count + 1;
                }

                return count;
            }, 0);

            messengerBadgeElements.forEach((badge) => {
                badge.hidden = unreadCount === 0;
                badge.textContent = unreadCount > 99 ? "99+" : String(unreadCount);
            });

            messengerNavButton?.classList.toggle("has-unread", unreadCount > 0);
            messengerLauncher?.classList.toggle("has-unread", unreadCount > 0);
        }

        function readMessengerOpenState() {
            try {
                return window.localStorage.getItem(messengerOpenStorageKey) === "1";
            } catch (error) {
                return false;
            }
        }

        function writeMessengerOpenState(isOpen) {
            try {
                window.localStorage.setItem(messengerOpenStorageKey, isOpen ? "1" : "0");
            } catch (error) {
                // Ignore storage failures and fall back to session-only behavior.
            }
        }

        function markThreadSeen(thread) {
            const chatId = threadChatId(thread);
            const latestMs = threadLatestMs(thread);

            if (!chatId || !latestMs) {
                return;
            }

            const state = readMessengerSeenState();

            if ((Number(state[chatId] || 0)) < latestMs) {
                state[chatId] = latestMs;
                writeMessengerSeenState(state);
            }

            updateMessengerBadges();
        }

        function scrollThreadToBottom(thread) {
            const scroller = thread?.querySelector(".messenger-thread-messages");
            if (scroller) {
                scroller.scrollTop = scroller.scrollHeight;
            }
        }

        function setActiveThread(threadId) {
            const targetThread = messengerThreads.find((thread) => thread.id === threadId);

            if (!targetThread) {
                return;
            }

            messengerThreads.forEach((thread) => {
                thread.classList.toggle("is-active", thread === targetThread);
            });

            messengerGroupButtons.forEach((button) => {
                button.classList.toggle("is-active", button.dataset.messengerTarget === threadId);
            });

            scrollThreadToBottom(targetThread);
        }

        function openMessenger() {
            messengerPanel.hidden = false;
            messengerDock.classList.add("is-open");
            messengerPanel?.setAttribute("aria-hidden", "false");
            messengerLauncher?.setAttribute("aria-expanded", "true");
            messengerNavButton?.setAttribute("aria-expanded", "true");
            writeMessengerOpenState(true);
            const activeThread = messengerThreads.find((thread) => thread.classList.contains("is-active")) || messengerThreads[0];
            markThreadSeen(activeThread);
            scrollThreadToBottom(activeThread);
        }

        function closeMessenger() {
            messengerDock.classList.remove("is-open");
            messengerPanel?.setAttribute("aria-hidden", "true");
            messengerPanel.hidden = true;
            messengerLauncher?.setAttribute("aria-expanded", "false");
            messengerNavButton?.setAttribute("aria-expanded", "false");
            writeMessengerOpenState(false);
        }

        messengerLauncher?.addEventListener("click", () => {
            if (messengerDock.classList.contains("is-open")) {
                closeMessenger();
            } else {
                openMessenger();
            }
        });

        messengerNavButton?.addEventListener("click", (event) => {
            event.stopPropagation();
            if (messengerDock.classList.contains("is-open")) {
                closeMessenger();
            } else {
                openMessenger();
            }
        });

        messengerClose?.addEventListener("click", closeMessenger);

        messengerGroupButtons.forEach((button) => {
            button.addEventListener("click", () => {
                const targetThreadId = button.dataset.messengerTarget;
                setActiveThread(targetThreadId);
                openMessenger();
                const targetThread = messengerThreads.find((thread) => thread.id === targetThreadId);
                markThreadSeen(targetThread);
            });
        });

        messengerDock.querySelectorAll(".messenger-compose").forEach((form) => {
            form.addEventListener("submit", () => {
                const targetThread = form.closest("[data-messenger-thread]");
                markThreadSeen(targetThread);
            });
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && messengerDock.classList.contains("is-open")) {
                closeMessenger();
            }
        });

        document.addEventListener("click", (event) => {
            if (!messengerDock.classList.contains("is-open")) {
                return;
            }

            if (messengerDock.contains(event.target)) {
                return;
            }

            closeMessenger();
        });

        if (defaultThreadId) {
            setActiveThread(`messenger-thread-${defaultThreadId}`);
        } else if (messengerThreads[0]) {
            setActiveThread(messengerThreads[0].id);
        }

        if (readMessengerOpenState()) {
            openMessenger();
        } else {
            closeMessenger();
        }

        updateMessengerBadges();
    }

    document.addEventListener("pointermove", (event) => {
        const x = (event.clientX / window.innerWidth) * 100;
        const y = (event.clientY / window.innerHeight) * 100;
        body?.style.setProperty("--mouse-x", `${x}%`);
        body?.style.setProperty("--mouse-y", `${y}%`);
    });
})();
