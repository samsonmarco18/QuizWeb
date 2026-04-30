(() => {
    const body = document.body;
    const themeToggle = document.getElementById("theme-toggle");
    const questionList = document.getElementById("question-list");
    const questionTemplate = document.getElementById("question-template");
    const addQuestionButton = document.getElementById("add-question-button");
    const builderForm = document.getElementById("quiz-builder-form");
    const payloadInput = document.getElementById("questions_payload");
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

    document.addEventListener("pointermove", (event) => {
        const x = (event.clientX / window.innerWidth) * 100;
        const y = (event.clientY / window.innerHeight) * 100;
        body?.style.setProperty("--mouse-x", `${x}%`);
        body?.style.setProperty("--mouse-y", `${y}%`);
    });
})();
