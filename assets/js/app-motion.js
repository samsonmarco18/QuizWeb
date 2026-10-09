(() => {
  'use strict';
  const notice = document.createElement('div');
  notice.className = 'chalk-loading'; notice.setAttribute('role', 'status'); notice.setAttribute('aria-live', 'polite');
  notice.innerHTML = '<span class="chalk-loading-track" aria-hidden="true"></span><span class="chalk-loading-spinner" aria-hidden="true"></span><span data-loading-label>Loading...</span>';
  document.body.append(notice);
  const pending = new Map(); let sequence = 0, hideTimer;
  function begin(label = 'Loading...', delay = 180) {
    const id = ++sequence;
    const entry = {label, visible: false, timer: null}; pending.set(id, entry);
    entry.timer = setTimeout(() => { if (!pending.has(id)) return; entry.visible = true; clearTimeout(hideTimer); notice.querySelector('[data-loading-label]').textContent = label; notice.classList.add('is-visible'); }, delay);
    return () => { clearTimeout(entry.timer); pending.delete(id); if (![...pending.values()].some(item => item.visible)) hideTimer = setTimeout(() => notice.classList.remove('is-visible'), 120); };
  }
  function reset() { pending.forEach(entry => clearTimeout(entry.timer)); pending.clear(); clearTimeout(hideTimer); notice.classList.remove('is-visible'); document.documentElement.classList.remove('is-navigating'); }
  window.chalkLoading = {begin, reset};
  const background = (url, method) => method === 'GET' && /chat_api\.php/.test(url) || /quiz_progress\.php|quiz_integrity\.php/.test(url);
  if (window.fetch) {
    const original = window.fetch;
    window.fetch = async function(input, options) {
      const url = typeof input === 'string' ? input : input?.url || String(input);
      const method = (options?.method || input?.method || 'GET').toUpperCase();
      const end = background(url, method) ? () => {} : begin(method === 'GET' ? 'Loading...' : 'Saving...', method === 'GET' ? 500 : 160);
      try { return await original.apply(this, arguments); } finally { end(); }
    };
  }
  if (window.XMLHttpRequest) {
    const proto = XMLHttpRequest.prototype, open = proto.open, send = proto.send;
    proto.open = function(method, url) { this.chalkRequest = {method: String(method).toUpperCase(), url: String(url)}; return open.apply(this, arguments); };
    proto.send = function() { const info = this.chalkRequest || {}; const end = background(info.url || '', info.method) ? () => {} : begin(info.method === 'GET' ? 'Loading...' : 'Uploading / saving...'); this.addEventListener('loadend', end, {once: true}); try { return send.apply(this, arguments); } catch (error) { end(); throw error; } };
  }
  document.addEventListener('submit', event => {
    const form = event.target;
    queueMicrotask(() => {
      if (event.defaultPrevented || form.method === 'dialog' || form.target && form.target !== '_self') return;
      const end = begin(form.method.toLowerCase() === 'post' ? 'Submitting...' : 'Loading...', 0);
      setTimeout(end, 20000);
    });
  });
  document.addEventListener('click', event => {
    const link = event.target.closest?.('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.hasAttribute('download') || link.target && link.target !== '_self') return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || !/^https?:$/.test(url.protocol) || url.hash && url.pathname === location.pathname && url.search === location.search || /upload\.php|export=|download=/.test(url.href)) return;
    queueMicrotask(() => { if (!event.defaultPrevented) { document.documentElement.classList.add('is-navigating'); const end = begin('Loading page...', 0); setTimeout(() => { end(); document.documentElement.classList.remove('is-navigating'); }, 20000); } });
  });
  window.addEventListener('pageshow', reset);
  window.addEventListener('pagehide', reset);
})();
