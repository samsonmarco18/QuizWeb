(() => {
  'use strict';
  let running = false;
  window.chalkParticipation = {start(root) {
    if (running || root.dataset.isPreview === '1' || !root.dataset.runToken) return;
    running = true;
    const ping = () => {
      if (document.hidden) return;
      fetch('/QuizWeb/quiz_progress.php', {method: 'POST', credentials: 'same-origin', body: new URLSearchParams({csrf: root.dataset.csrf, run_token: root.dataset.runToken})}).catch(() => {});
    };
    ping(); setInterval(ping, 45000);
    document.addEventListener('visibilitychange', ping);
  }};
})();
