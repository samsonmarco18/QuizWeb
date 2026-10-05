(() => {
    const rule = 'Stay in fullscreen and keep this quiz tab focused. Leaving fullscreen, switching tabs, losing focus, or a detected screenshot shortcut gives a warning. After 3 warnings, the next violation saves a zero score. Reloading or leaving an active quiz also saves zero. Screenshot detection is limited to shortcuts and focus changes received by the browser.';
    const labels = { fullscreen_exit: 'leaving fullscreen', focus_loss: 'moving focus away from the quiz', tab_hidden: 'switching tabs or minimizing', screenshot_shortcut: 'using a screenshot shortcut', page_exit: 'leaving or reloading the active quiz' };
    function create(root, callbacks = {}) {
        const enabled = root.dataset.isPreview !== '1' && root.dataset.practiceMode !== '1';
        let started = false, starting = false, active = false, paused = false, done = false, warnings = 0;
        let queue = Promise.resolve(), pending = 0, leaveSent = false, warningEvent = null;
        const fullscreen = () => document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
        const id = () => [...crypto.getRandomValues(new Uint8Array(16))].map(v => v.toString(16).padStart(2, '0')).join('');
        const notice = document.createElement('p'); notice.className = 'quiz-security-notice'; notice.textContent = rule;
        const counter = document.createElement('p'); counter.className = 'quiz-warning-count'; counter.setAttribute('role', 'status');
        if (enabled) { root.prepend(notice, counter); counter.textContent = 'Security active after Start · Warnings 0 / 3'; }
        const dialog = document.createElement('div'); dialog.className = 'quiz-integrity-dialog'; dialog.hidden = true;
        dialog.innerHTML = '<section class="quiz-integrity-panel" role="alertdialog" aria-modal="true" aria-labelledby="quiz-integrity-title"><span class="eyebrow">Quiz warning</span><h2 id="quiz-integrity-title">Return to fullscreen</h2><p data-integrity-message></p><p data-integrity-sync role="status"></p><button class="button button-primary" type="button" data-integrity-continue>Continue Quiz</button></section>';
        const message = dialog.querySelector('[data-integrity-message]');
        const sync = dialog.querySelector('[data-integrity-sync]');
        const resume = dialog.querySelector('[data-integrity-continue]');
        if (enabled) document.body.append(dialog);
        function fields(event) {
            return {classroom_id: root.dataset.classroomId, quiz_id: JSON.parse(root.dataset.quiz).id,
                run_token: root.dataset.runToken, csrf: root.dataset.csrf, ...event};
        }
        async function send(event) {
            if (!root.dataset.integrityUrl) throw new Error('Quiz security is unavailable. Reload from your classroom.');
            const response = await fetch(root.dataset.integrityUrl, {
                method: 'POST', body: new URLSearchParams(fields(event)), credentials: 'same-origin', keepalive: true,
                signal: AbortSignal.timeout(15000),
            });
            const status = await response.json();
            if (!response.ok) throw new Error(status.error || 'Could not save the quiz warning.');
            warnings = Math.max(warnings, Number(status.warnings || 0));
            counter.textContent = `Warnings ${warnings} / 3`;
            if (status.disqualified && !done) disqualify(status.results_url);
            return status;
        }
        async function requestFullscreen() {
            if (fullscreen()) return;
            const target = document.documentElement;
            const request = target.requestFullscreen || target.webkitRequestFullscreen || target.msRequestFullscreen;
            if (!request) throw new Error('This browser does not support quiz fullscreen. Open the quiz in a browser that supports fullscreen.');
            await request.call(target);
            if (!fullscreen()) throw new Error('Fullscreen is required. Allow fullscreen, then press Start again.');
        }
        function disqualify(url) {
            done = true; active = false; paused = false; root.inert = true;
            dialog.hidden = true;
            counter.textContent = 'Quiz marked as zero · Warning limit exceeded';
            callbacks.onDisqualify?.(labels[warningEvent?.reason] || 'quiz integrity violation', warnings, url);
        }
        function violation(reason) {
            // One incident can emit blur, visibility, and fullscreen changes.
            // Pausing until acknowledgement counts the incident once.
            if (!enabled || !started || !active || paused || done) return;
            paused = true; active = false; root.inert = true; warnings++;
            warningEvent = {action: 'violation', reason, event_id: id()};
            message.textContent = `Warning ${warnings} of 3: ${labels[reason]}. ${warnings <= 3 ? (warnings === 3 ? 'The next violation saves a zero score.' : 'Return to fullscreen to continue.') : 'This attempt will be saved as zero.'}`;
            counter.textContent = `Warnings ${warnings} / 3`;
            dialog.hidden = false; sync.textContent = 'Saving warning…'; resume.disabled = true; pending++;
            queue = queue.then(() => send(warningEvent)).then(() => {
                sync.textContent = 'Warning saved.'; warningEvent = null;
            }).catch(error => { sync.textContent = error.message + ' Press Continue Quiz to retry.'; })
                .finally(() => { pending--; resume.disabled = false; if (!done) resume.focus(); });
        }
        resume.addEventListener('click', async () => {
            if (pending || done) return;
            resume.disabled = true;
            try {
                // Fullscreen must be requested directly from this click.
                await requestFullscreen();
                if (warningEvent) { await send(warningEvent); warningEvent = null; }
                if (done) return;
                if (document.hidden || !fullscreen()) throw new Error('Keep the quiz visible in fullscreen to continue.');
                dialog.hidden = true; root.inert = false; paused = false; active = true;
                callbacks.onResume?.();
            } catch (error) { sync.textContent = error.message; }
            finally { resume.disabled = false; }
        });
        if (enabled) {
            document.addEventListener('visibilitychange', () => { if (document.hidden) violation('tab_hidden'); });
            window.addEventListener('blur', () => violation('focus_loss'));
            ['fullscreenchange', 'webkitfullscreenchange', 'MSFullscreenChange'].forEach(name => document.addEventListener(name, () => { if (!fullscreen()) violation('fullscreen_exit'); }));
            function screenshot(event) {
                const key = String(event.key).toLowerCase();
                const mac = /Mac|iPhone|iPad/i.test(navigator.userAgentData?.platform || navigator.platform || '');
                const screenshotKeys = mac ? ['3', '4', '5'] : ['s'];
                const detected = key === 'printscreen' || event.code === 'PrintScreen'
                    || (event.metaKey && event.shiftKey && screenshotKeys.includes(key));
                if (detected && active && !event.repeat) { event.preventDefault(); violation('screenshot_shortcut'); }
            }
            document.addEventListener('keydown', screenshot, true);
            document.addEventListener('keyup', screenshot, true);
            window.addEventListener('beforeunload', event => {
                if (started && !done) { event.preventDefault(); event.returnValue = ''; }
            });
            window.addEventListener('pagehide', () => {
                if (!started || done || leaveSent) return;
                leaveSent = true;
                const body = new URLSearchParams(fields({action: 'abandon', reason: 'page_exit', event_id: id()}));
                if (!navigator.sendBeacon?.(root.dataset.integrityUrl, body)) {
                    fetch(root.dataset.integrityUrl, {method: 'POST', body, credentials: 'same-origin', keepalive: true}).catch(() => {});
                }
            });
        }
        return {
            enabled, rule,
            get paused() { return paused; },
            get warnings() { return warnings; },
            get canInteract() { return !enabled || (started && active && !paused && !done); },
            async start() {
                if (!enabled) return true;
                if (starting || started || done) return false;
                starting = true;
                try {
                    await requestFullscreen();
                    const status = await send({action: 'start', event_id: id()});
                    if (status.disqualified || status.results_url) { if (!done) disqualify(status.results_url); return false; }
                    if (document.hidden || !fullscreen()) throw new Error('Fullscreen was interrupted. Press Start to try again.');
                    started = true; active = true; counter.textContent = `Warnings ${warnings} / 3 · Fullscreen required`;
                    return true;
                } catch (error) {
                    root.querySelector('[data-game-note]').textContent = error.message || 'Fullscreen could not start. Allow fullscreen and try again.';
                    return false;
                } finally { starting = false; }
            },
            complete() { active = false; done = true; paused = false; root.inert = false; dialog.hidden = true; },
        };
    }
    window.chalkQuizIntegrity = {create};
})();
