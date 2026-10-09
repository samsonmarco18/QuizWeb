(() => {
  'use strict';
  const notice = document.createElement('div');
  notice.className = 'chalk-loading'; notice.setAttribute('role', 'status'); notice.setAttribute('aria-live', 'polite');
  notice.innerHTML = '<div class="chalk-loading-content"><img class="chalk-loading-art" src="/QuizWeb/assets/images/academic-loading.svg" alt="" width="240" height="190"><strong data-loading-label>Loading...</strong><p data-loading-description>Please wait while we prepare your content.</p><div class="chalk-loading-track" role="progressbar" aria-label="Operation in progress"><span></span></div></div>';
  document.body.append(notice);
  function position() {
    const main = document.querySelector('.page-shell');
    const header = document.querySelector('.site-header');
    const left = main ? Math.max(0, main.getBoundingClientRect().left - 20) : 0;
    notice.style.left = `${left}px`;
    notice.style.top = `${Math.max(0, header?.getBoundingClientRect().bottom || 0)}px`;
  }
  const scenes = {
    grades: ['academic-loading.svg', 'your grades', 'academic records'],
    quiz: ['loading-quiz.svg', 'your quiz', 'questions and activities'],
    classroom: ['loading-classroom.svg', 'your classroom', 'classroom information'],
    upload: ['loading-upload.svg', 'your files', 'files and attachments'],
    message: ['loading-message.svg', 'your message', 'classroom messages'],
    profile: ['loading-profile.svg', 'your profile', 'account information'],
    save: ['loading-save.svg', 'your changes', 'updated information'],
    page: ['loading-page.svg', 'your page', 'the requested content']
  };
  function context(url = location.href, method = 'GET', body = null) {
    let action = '', upload = false;
    if (body && typeof body.entries === 'function') {
      for (const [key, value] of body.entries()) {
        if (value instanceof Blob && value.size > 0) upload = true;
        if (['action', 'view'].includes(key)) action += ' ' + value;
      }
    }
    let route;
    try { route = new URL(String(url), location.href).pathname.split('/').pop(); } catch (_) { route = String(url); }
    const target = route + ' ' + action;
    if (upload || /upload|attachment/i.test(target)) return 'upload';
    if (/chat|message|reminder/i.test(target)) return 'message';
    if (/grades|gradebook|grading|academic_settings/i.test(target)) return 'grades';
    if (/quiz|play|practice|submit_game|attempt|activity/i.test(target)) return 'quiz';
    if (/profile|login|signup|register|logout|password/i.test(target)) return 'profile';
    if (/classroom|participants|join/i.test(target)) return 'classroom';
    return method.toUpperCase() === 'GET' ? 'page' : 'save';
  }
  function describe(label, scene) {
    const [image, subject, description] = scenes[scene] || scenes.page;
    const verb = /^Loading/.test(label) ? 'Loading' : /^Submitting/.test(label) ? 'Submitting' : scene === 'upload' ? 'Uploading' : scene === 'message' ? 'Sending' : 'Saving';
    notice.dataset.scene = scene;
    notice.querySelector('.chalk-loading-art').src = '/QuizWeb/assets/images/' + image;
    notice.querySelector('[data-loading-label]').textContent = `${verb} ${subject}...`;
    notice.querySelector('[data-loading-description]').textContent = `Please wait while we ${verb === 'Loading' ? 'fetch' : verb === 'Sending' ? 'send' : verb === 'Submitting' ? 'submit' : 'save'} ${description}.`;
    if (scene === 'profile' && document.body.classList.contains('login-page') && verb !== 'Loading') {
      notice.querySelector('[data-loading-label]').textContent = 'Signing you in...';
      notice.querySelector('[data-loading-description]').textContent = 'Please wait while we verify your account.';
    }
    position();
  }
  window.addEventListener('resize', position);
  const pending = new Map(); let sequence = 0, hideTimer;
  function begin(label = 'Loading...', delay = 180, scene = context()) {
    const id = ++sequence;
    const entry = {label, scene, visible: false, timer: null}; pending.set(id, entry);
    entry.timer = setTimeout(() => { if (!pending.has(id)) return; entry.visible = true; clearTimeout(hideTimer); describe(label, scene); notice.classList.add('is-visible'); }, delay);
    return () => { clearTimeout(entry.timer); pending.delete(id); const remaining = [...pending.values()].filter(item => item.visible); if (remaining.length) { const current = remaining[remaining.length - 1]; describe(current.label, current.scene); } else hideTimer = setTimeout(() => notice.classList.remove('is-visible'), 120); };
  }
  function reset() { pending.forEach(entry => clearTimeout(entry.timer)); pending.clear(); clearTimeout(hideTimer); notice.classList.remove('is-visible'); document.documentElement.classList.remove('is-navigating'); }
  window.chalkLoading = {begin, reset, context};
  const background = (url, method) => method === 'GET' && /chat_api\.php/.test(url) || /quiz_progress\.php|quiz_integrity\.php/.test(url);
  if (window.fetch) {
    const original = window.fetch;
    window.fetch = async function(input, options) {
      const url = typeof input === 'string' ? input : input?.url || String(input);
      const method = (options?.method || input?.method || 'GET').toUpperCase();
      const end = background(url, method) ? () => {} : begin(method === 'GET' ? 'Loading...' : 'Saving...', method === 'GET' ? 500 : 160, context(url, method, options?.body));
      try { return await original.apply(this, arguments); } finally { end(); }
    };
  }
  if (window.XMLHttpRequest) {
    const proto = XMLHttpRequest.prototype, open = proto.open, send = proto.send;
    proto.open = function(method, url) { this.chalkRequest = {method: String(method).toUpperCase(), url: String(url)}; return open.apply(this, arguments); };
    proto.send = function() { const info = this.chalkRequest || {}; const end = background(info.url || '', info.method) ? () => {} : begin(info.method === 'GET' ? 'Loading...' : 'Saving...', 180, context(info.url, info.method || 'GET', arguments[0])); this.addEventListener('loadend', end, {once: true}); try { return send.apply(this, arguments); } catch (error) { end(); throw error; } };
  }
  document.addEventListener('submit', event => {
    const form = event.target;
    queueMicrotask(() => {
      if (event.defaultPrevented || form.method === 'dialog' || form.target && form.target !== '_self') return;
      const end = begin(form.method.toLowerCase() === 'post' ? 'Submitting...' : 'Loading...', 0, context(form.action, form.method, new FormData(form)));
      setTimeout(end, 20000);
    });
  });
  document.addEventListener('click', event => {
    const link = event.target.closest?.('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.hasAttribute('download') || link.target && link.target !== '_self') return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || !/^https?:$/.test(url.protocol) || url.hash && url.pathname === location.pathname && url.search === location.search || /upload\.php|export=|download=/.test(url.href)) return;
    queueMicrotask(() => { if (!event.defaultPrevented) { document.documentElement.classList.add('is-navigating'); const end = begin('Loading page...', 0, context(url.href)); setTimeout(() => { end(); document.documentElement.classList.remove('is-navigating'); }, 20000); } });
  });
  window.addEventListener('pageshow', reset);
  window.addEventListener('pagehide', reset);
})();
