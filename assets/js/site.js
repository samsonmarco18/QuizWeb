(() => {
    const body = document.body;
    const themeToggle = document.getElementById("theme-toggle");
    const questionList = document.getElementById("question-list");
    const questionTemplate = document.getElementById("question-template");
    const addQuestionButton = document.getElementById("add-question-button");
    const builderForm = document.getElementById("quiz-builder-form");
    const payloadInput = document.getElementById("questions_payload");
    const gameTypeSelect = builderForm?.querySelector('[name="game_type"]');
    const messengerDock = document.querySelector("[data-messenger-dock]");
    const sidebarToggle = document.querySelector(".sidebar-toggle");
    const classroomSidebar = document.getElementById("classroom-sidebar");
    const themeStorageKey = "quizweb-theme";

    document.querySelectorAll('.flash').forEach((notification) => {
        window.setTimeout(() => notification.remove(), 2000);
    });

    function setSidebarOpen(open) {
        body?.classList.toggle("sidebar-open", open);
        sidebarToggle?.setAttribute("aria-expanded", open ? "true" : "false");
        sidebarToggle?.setAttribute("aria-label", open ? "Close navigation" : "Open navigation");
    }

    sidebarToggle?.addEventListener("click", () => setSidebarOpen(!body.classList.contains("sidebar-open")));
    classroomSidebar?.addEventListener("click", (event) => {
        if (event.target.closest("a") && window.matchMedia("(max-width: 760px)").matches) setSidebarOpen(false);
    });
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") setSidebarOpen(false);
    });
    document.addEventListener("click", (event) => {
        if (body.classList.contains("sidebar-open") && !event.target.closest("#classroom-sidebar, .sidebar-toggle")) setSidebarOpen(false);
    });

    function setTheme(isDark) {
        if (!body) {
            return;
        }

        body.classList.toggle("theme-dark", isDark);

        if (themeToggle) {
            themeToggle.setAttribute("aria-pressed", isDark ? "true" : "false");
            themeToggle.querySelector(".theme-toggle-label").textContent = "Dark Mode";
            if (themeToggle.classList.contains("auth-theme-toggle")) {
                themeToggle.querySelector(".theme-toggle-label").textContent = isDark ? "Light" : "Dark";
                themeToggle.title = isDark ? "Switch to light mode" : "Switch to dark mode";
            }
        }

        try {
            window.localStorage.setItem(themeStorageKey, isDark ? "dark" : "default");
        } catch (error) {
            // Ignore storage failures and keep the theme only for the current page.
        }
    }

    {
        let savedTheme = "default";

        try {
            savedTheme = window.localStorage.getItem(themeStorageKey) || "default";
        } catch (error) {
            savedTheme = "default";
        }

        setTheme(savedTheme === "dark");

        themeToggle?.addEventListener("click", () => {
            setTheme(!body.classList.contains("theme-dark"));
        });
    }

    const accountToggle = document.querySelector(".account-toggle");
    const accountDropdown = document.getElementById("account-dropdown");
    function closeAccountMenu() {
        if (accountDropdown) accountDropdown.hidden = true;
        accountToggle?.setAttribute("aria-expanded", "false");
    }
    accountToggle?.addEventListener("click", () => {
        accountDropdown.hidden = !accountDropdown.hidden;
        accountToggle.setAttribute("aria-expanded", String(!accountDropdown.hidden));
    });
    document.addEventListener("click", (event) => {
        if (!event.target.closest(".account-menu")) closeAccountMenu();
    });
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && accountDropdown && !accountDropdown.hidden) {
            closeAccountMenu();
            accountToggle.focus();
        }
    });
    document.querySelector(".account-menu")?.addEventListener("focusout", (event) => {
        if (!event.currentTarget.contains(event.relatedTarget)) closeAccountMenu();
    });
    document.querySelector("[data-profile-open]")?.addEventListener("click", () => {
        closeAccountMenu();
        document.getElementById("profile-dialog")?.showModal();
    });

    function buildQuestionCard(seed = {}) {
        if (!questionTemplate || !questionList) {
            return null;
        }

        const fragment = questionTemplate.content.cloneNode(true);
        const card = fragment.querySelector(".question-card");

        card.querySelector('[data-field="prompt"]').value = seed.prompt || "";
        card.querySelector('[data-field="answer"]').value = seed.answer || seed.options?.[0] || "";
        card.querySelector('[data-field="preferred_direction"]').value = seed.preferred_direction || seed.crossword?.direction || "across";
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
        applyBuilderModeToCard(card);
        refreshQuestionLabels();

        return card;
    }

    function currentBuilderMode() {
        return gameTypeSelect?.value || window.quizBuilderMode || "time_attack";
    }

    function applyBuilderModeToCard(card) {
        const isCrossword = currentBuilderMode() === "crossword";
        card.querySelectorAll("[data-crossword-only]").forEach((element) => {
            element.hidden = !isCrossword;
        });
        card.querySelectorAll("[data-choice-only]").forEach((element) => {
            element.hidden = isCrossword;
        });

        const promptLabel = card.querySelector("[data-prompt-label]");
        const promptInput = card.querySelector('[data-field="prompt"]');
        if (promptLabel) {
            promptLabel.textContent = isCrossword ? "Clue" : "Prompt";
        }
        if (promptInput) {
            promptInput.placeholder = isCrossword ? "Write a clear clue for this word" : "Type the question here";
        }
    }

    function applyBuilderMode() {
        if (!questionList) {
            return;
        }

        const isCrossword = currentBuilderMode() === "crossword";
        if (addQuestionButton) {
            addQuestionButton.textContent = isCrossword ? "Add Word" : "Add Question";
        }
        [...questionList.querySelectorAll(".question-card")].forEach((card) => applyBuilderModeToCard(card));
        refreshQuestionLabels();
    }

    function refreshQuestionLabels() {
        if (!questionList) {
            return;
        }

        [...questionList.querySelectorAll(".question-card")].forEach((card, index) => {
            const label = card.querySelector("[data-question-label]");
            if (label) {
                label.textContent = `${currentBuilderMode() === "crossword" ? "Word" : "Question"} ${index + 1}`;
            }
        });
    }

    function collectQuestions() {
        if (!questionList) {
            return [];
        }

        const isCrossword = currentBuilderMode() === "crossword";

        return [...questionList.querySelectorAll(".question-card")].map((card) => {
            const prompt = card.querySelector('[data-field="prompt"]').value.trim();
            const answer = card.querySelector('[data-field="answer"]').value.trim();

            if (isCrossword) {
                return {
                    prompt,
                    answer,
                    preferred_direction: card.querySelector('[data-field="preferred_direction"]').value || "across",
                    options: [answer],
                    correct_index: 0,
                    points: Number(card.querySelector('[data-field="points"]').value || 10),
                    level: card.querySelector('[data-field="level"]').value || "easy",
                };
            }

            return {
                prompt,
                options: [
                    card.querySelector('[data-field="option-0"]').value.trim(),
                    card.querySelector('[data-field="option-1"]').value.trim(),
                    card.querySelector('[data-field="option-2"]').value.trim(),
                    card.querySelector('[data-field="option-3"]').value.trim(),
                ],
                correct_index: Number(card.querySelector('[data-field="correct_index"]').value || 0),
                points: Number(card.querySelector('[data-field="points"]').value || 10),
                level: card.querySelector('[data-field="level"]').value || "easy",
            };
        });
    }

    if (questionList && builderForm && payloadInput) {
        const seed = Array.isArray(window.quizBuilderSeed) ? window.quizBuilderSeed : [];
        if (seed.length) {
            seed.forEach((question) => buildQuestionCard(question));
        } else {
            buildQuestionCard();
        }

        addQuestionButton?.addEventListener("click", () => buildQuestionCard());
        gameTypeSelect?.addEventListener("change", applyBuilderMode);
        applyBuilderMode();

        builderForm.addEventListener("submit", (event) => {
            const questions = collectQuestions();
            const isCrossword = currentBuilderMode() === "crossword";
            const incomplete = isCrossword
                ? questions.some((question) => !question.prompt || !question.answer)
                : questions.some((question) => !question.prompt || question.options.some((option) => !option));

            if (!questions.length || incomplete) {
                event.preventDefault();
                alert(isCrossword
                    ? "Please complete every crossword word and clue before saving the puzzle."
                    : "Please complete every question and answer before saving the quiz.");
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
        const messengerSeenStorageKey = `quizweb-messenger-seen-${currentUserId}`;
        const messengerOpenStorageKey = `quizweb-messenger-open-${currentUserId}`;

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
            filterMessengerGroups();
        }

        function filterMessengerGroups() {
            const query = (messengerDock.querySelector('[data-messenger-search]')?.value || '').trim().toLowerCase();
            const unreadOnly = messengerDock.querySelector('[data-messenger-filter="unread"]')?.classList.contains('is-active');
            const seen = readMessengerSeenState();
            let visibleCount = 0;
            messengerGroupButtons.forEach((button) => {
                const thread = messengerThreads.find((item) => item.id === button.dataset.messengerTarget);
                const unread = threadLatestMs(thread) > seenMsForThread(thread, seen) && threadLatestSenderId(thread) !== currentUserId;
                button.classList.toggle('has-unread', unread);
                button.hidden = !button.querySelector('.messenger-group-copy').textContent.toLowerCase().includes(query) || (unreadOnly && !unread);
                if (!button.hidden) visibleCount++;
            });
            messengerDock.querySelector('[data-messenger-search-empty]').hidden = visibleCount > 0 || messengerGroupButtons.length === 0;
        }

        messengerDock.querySelector('[data-messenger-search]')?.addEventListener('input', filterMessengerGroups);
        messengerDock.querySelectorAll('[data-messenger-filter]').forEach((filter) => {
            filter.addEventListener('click', () => {
                messengerDock.querySelectorAll('[data-messenger-filter]').forEach((item) => {
                    item.classList.toggle('is-active', item === filter);
                    item.setAttribute('aria-pressed', String(item === filter));
                });
                filterMessengerGroups();
            });
        });

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

        function chatElement(tag, className, text) {
            const element = document.createElement(tag);
            if (className) element.className = className;
            if (text !== undefined) element.textContent = text;
            return element;
        }

        function renderChatMessage(message, thread) {
            const self = Number(message.user_id) === currentUserId;
            const row = chatElement('div', `messenger-message ${self ? 'is-self' : 'is-student'}`);
            row.append(chatElement('span', 'messenger-message-avatar', (message.user_name || 'M').slice(0, 1).toUpperCase()));
            const bubble = chatElement('div', 'messenger-message-bubble');
            const meta = chatElement('div', 'messenger-message-meta');
            meta.append(chatElement('strong', '', self ? 'You' : message.user_name));
            const time = chatElement('time', '', new Date(message.created_at).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'}));
            time.dateTime = message.created_at;
            time.title = new Date(message.created_at).toLocaleString();
            meta.append(time);
            bubble.append(meta);
            if (message.body) bubble.append(chatElement('p', 'chat-message-text', message.body));
            if (message.link && /^https?:\/\//i.test(message.link)) {
                const link = chatElement('a', 'chat-shared-link', message.link);
                link.href = message.link; link.target = '_blank'; link.rel = 'noopener noreferrer';
                bubble.append(link);
            }
            (message.attachments || []).forEach((file) => {
                const link = chatElement('a', 'chat-file-link', `${file.name} · ${Math.ceil(file.size / 1024)} KB`);
                link.href = file.url; link.target = '_blank'; link.rel = 'noopener';
                if (file.image) {
                    const img = chatElement('img', 'chat-image');
                    img.src = `${file.url}&view=1`; img.alt = file.name; img.loading = 'lazy';
                    link.prepend(img);
                }
                bubble.append(link);
            });
            if (message.poll) {
                const poll = chatElement('div', 'chat-poll');
                poll.append(chatElement('strong', '', message.poll.question));
                message.poll.options.forEach((option, index) => {
                    const votes = message.poll.counts[index];
                    const choice = chatElement('button', message.poll.selected === index ? 'is-selected' : '', `${option} — ${votes}`);
                    choice.type = 'button'; choice.setAttribute('aria-pressed', String(message.poll.selected === index));
                    choice.addEventListener('click', async () => {
                        poll.querySelectorAll('button').forEach((button) => button.disabled = true);
                        const data = new FormData();
                        data.set('action', 'vote'); data.set('classroom_id', thread.dataset.chatId);
                        data.set('message_id', message.id); data.set('option', index);
                        try { await chatRequest(data); }
                        catch (error) { thread.querySelector('[data-chat-status]').textContent = error.message; }
                        finally { poll.querySelectorAll('button').forEach((button) => button.disabled = false); }
                    });
                    poll.append(choice);
                });
                poll.append(chatElement('small', '', `${message.poll.total} vote${message.poll.total === 1 ? '' : 's'} · You can change your vote`));
                bubble.append(poll);
            }
            row.append(bubble);
            return row;
        }

        function applyChatThreads(threads) {
            threads.forEach((record) => {
                const thread = messengerThreads.find((item) => Number(item.dataset.chatId) === record.id);
                if (!thread) return;
                const signature = JSON.stringify(record.messages);
                if (thread.chatSignature === signature) return;
                thread.chatSignature = signature;
                const scroller = thread.querySelector('.messenger-thread-messages');
                const atBottom = scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 70;
                const oldTop = scroller.scrollTop;
                const fragment = document.createDocumentFragment();
                let lastDay = '';
                record.messages.forEach((message) => {
                    const day = new Date(message.created_at).toLocaleDateString();
                    if (day !== lastDay) {
                        fragment.append(chatElement('div', 'chat-date-divider', day === new Date().toLocaleDateString() ? 'Today' : day));
                        lastDay = day;
                    }
                    fragment.append(renderChatMessage(message, thread));
                });
                if (!record.messages.length) fragment.append(chatElement('p', 'messenger-thread-empty', 'Say hello. Start your classroom conversation.'));
                scroller.replaceChildren(fragment);
                const latest = record.messages.at(-1);
                thread.dataset.latestAt = latest?.created_at || '';
                thread.dataset.latestUserId = latest?.user_id || '0';
                const group = messengerGroupButtons.find((item) => item.dataset.chatId === String(record.id));
                if (group && latest) {
                    group.querySelector('.messenger-group-copy > span').textContent = latest.body || latest.poll?.question || latest.attachments?.[0]?.name || latest.link || 'New message';
                    group.querySelector('time').textContent = new Date(latest.created_at).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
                }
                if (atBottom || Number(latest?.user_id) === currentUserId) scrollThreadToBottom(thread);
                else scroller.scrollTop = oldTop;
                if (messengerDock.classList.contains('is-open') && thread.classList.contains('is-active') && !document.hidden && atBottom) markThreadSeen(thread);
            });
            updateMessengerBadges();
        }

        let requestSequence = 0;
        let appliedSequence = 0;
        async function chatRequest(data) {
            const sequence = ++requestSequence;
            if (data) data.set('csrf', messengerDock.dataset.chatCsrf);
            const response = await fetch('/QuizWeb/chat_api.php', {method: data ? 'POST' : 'GET', body: data, headers: {Accept: 'application/json'}, signal: AbortSignal.timeout(20000)});
            let result;
            try { result = await response.json(); } catch { throw new Error('Unable to connect. Your draft is still here; please try again.'); }
            if (!response.ok) throw new Error(result.error || 'Unable to send. Please try again.');
            if (sequence >= appliedSequence) { appliedSequence = sequence; applyChatThreads(result.threads); }
        }

        messengerDock.querySelectorAll('.messenger-compose').forEach((form) => {
            const thread = form.closest('[data-messenger-thread]');
            const textarea = form.querySelector('[name="chat_body"]');
            const status = form.querySelector('[data-chat-status]');
            const picker = form.querySelector('[data-chat-files]');
            const selected = form.querySelector('[data-chat-selected]');
            let files = [];
            let requestId = null;
            const displayFiles = () => {
                selected.replaceChildren(); selected.hidden = files.length === 0;
                files.forEach((file, index) => {
                    const remove = chatElement('button', '', `${file.name} ×`);
                    remove.type = 'button'; remove.setAttribute('aria-label', `Remove ${file.name}`);
                    remove.addEventListener('click', () => { files.splice(index, 1); displayFiles(); });
                    selected.append(remove);
                });
            };
            form.querySelectorAll('[data-chat-file]').forEach((button) => button.addEventListener('click', () => {
                picker.accept = button.dataset.chatFile === 'image' ? '.png,.jpg,.jpeg,.gif,.webp' : '.pdf,.txt,.csv,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.png,.jpg,.jpeg,.gif,.webp';
                picker.click();
            }));
            picker.addEventListener('change', () => {
                const incoming = [...picker.files]; picker.value = '';
                if (files.length + incoming.length > 4 || incoming.some((file) => file.size > 10 * 1024 * 1024 || file.size === 0)) {
                    status.textContent = 'Choose up to four nonempty files, at most 10 MB each.'; return;
                }
                files.push(...incoming); status.textContent = ''; displayFiles();
            });
            form.querySelectorAll('[data-chat-toggle]').forEach((button) => button.addEventListener('click', () => {
                const extra = form.querySelector(`[data-chat-extra="${button.dataset.chatToggle}"]`);
                extra.hidden = !extra.hidden; button.setAttribute('aria-expanded', String(!extra.hidden));
                if (extra.hidden) extra.querySelectorAll('input, textarea').forEach((input) => input.value = '');
                else extra.querySelector('input, textarea, button')?.focus();
            }));
            form.querySelectorAll('[data-chat-emoji]').forEach((button) => button.addEventListener('click', () => {
                if (textarea.value.length + button.dataset.chatEmoji.length > textarea.maxLength) return;
                textarea.setRangeText(button.dataset.chatEmoji, textarea.selectionStart, textarea.selectionEnd, 'end'); textarea.focus();
            }));
            textarea.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); form.requestSubmit(); }
            });
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (form.dataset.sending === '1') return;
                const data = new FormData(form);
                requestId ||= Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join('');
                data.set('request_id', requestId);
                data.set('action', 'send'); data.set('classroom_id', thread.dataset.chatId);
                files.forEach((file) => data.append('chat_files[]', file));
                if (!textarea.value.trim() && !files.length && !data.get('link')?.trim() && !data.get('poll_question')?.trim()) { status.textContent = 'Write a message or add a file, link, or poll.'; return; }
                form.dataset.sending = '1';
                form.querySelectorAll('button, input, textarea').forEach((input) => input.disabled = true);
                status.textContent = 'Sending…';
                try {
                    await chatRequest(data);
                    form.reset(); files = []; requestId = null; displayFiles();
                    form.querySelectorAll('[data-chat-extra]').forEach((extra) => extra.hidden = true);
                    form.querySelectorAll('[data-chat-toggle]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
                    status.textContent = 'Sent'; scrollThreadToBottom(thread); markThreadSeen(thread);
                } catch (error) { status.textContent = error.message || 'Send failed. Your draft is saved here; try again.'; }
                finally {
                    form.dataset.sending = '0';
                    form.querySelectorAll('button, input, textarea').forEach((input) => input.disabled = false);
                    textarea.focus();
                }
            });
            thread.querySelector('.messenger-thread-messages').addEventListener('scroll', (event) => {
                const box = event.currentTarget;
                if (messengerDock.classList.contains('is-open') && thread.classList.contains('is-active') && box.scrollHeight - box.scrollTop - box.clientHeight < 70) markThreadSeen(thread);
            });
        });

        let refreshing = false;
        async function refreshChat() {
            if (refreshing || document.hidden || messengerDock.closest('.game-page')) return;
            refreshing = true;
            try {
                await chatRequest();
                messengerDock.querySelectorAll('[data-chat-status]').forEach((status) => {
                    if (status.textContent === 'Connection interrupted. Retrying automatically…') status.textContent = '';
                });
            }
            catch (error) {
                const status = messengerDock.querySelector('.messenger-thread.is-active [data-chat-status]');
                if (status && !status.closest('form').dataset.sending?.includes('1')) status.textContent = 'Connection interrupted. Retrying automatically…';
            } finally { refreshing = false; }
        }
        refreshChat();
        window.setInterval(refreshChat, 5000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshChat(); });

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
