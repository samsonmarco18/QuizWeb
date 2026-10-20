(() => {
    const root = document.querySelector('[data-notifications]');
    if (!root) return;
    const toggle = root.querySelector('.notification-toggle');
    const panel = root.querySelector('.notification-panel');
    const badge = root.querySelector('[data-notification-count]');
    const markAll = root.querySelector('[data-notifications-read]');
    const items = [...root.querySelectorAll('[data-notification-id]')];
    const storageKey = `quizweb-notifications-read:${root.dataset.userId}`;
    const loadRead = () => {
        try {
            const value = JSON.parse(localStorage.getItem(storageKey) || '[]');
            return new Set(Array.isArray(value) ? value.filter(id => typeof id === 'string') : []);
        } catch { return new Set(); }
    };
    let read = loadRead();
    function refresh() {
        let count = 0;
        items.forEach(item => {
            const unread = !read.has(item.dataset.notificationId);
            item.classList.toggle('is-unread', unread);
            item.querySelector('.notification-unread-dot').hidden = !unread;
            if (unread) count++;
        });
        badge.textContent = String(count);
        badge.hidden = count === 0;
        toggle.setAttribute('aria-label', count ? `Notifications, ${count} unread` : 'Notifications');
        root.querySelector('[data-notification-summary]').textContent = count
            ? `${count} unread ${count === 1 ? 'notification' : 'notifications'}`
            : 'You’re all caught up';
        markAll.disabled = count === 0;
    }
    function save() {
        try { localStorage.setItem(storageKey, JSON.stringify([...read].slice(-200))); } catch { /* Keep read state for this page if storage is unavailable. */ }
        refresh();
    }
    function close(returnFocus = false) {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        if (returnFocus) toggle.focus();
    }
    function positionPanel() {
        if (window.innerWidth <= 760) {
            const headerBottom = root.closest('.site-header')?.getBoundingClientRect().bottom ?? toggle.getBoundingClientRect().bottom;
            const top = Math.min(headerBottom + 8, Math.max(12, window.innerHeight - 160));
            panel.style.top = `${top}px`;
            panel.style.maxHeight = `${Math.max(120, window.innerHeight - top - 12)}px`;
        } else {
            panel.style.removeProperty('top');
            panel.style.removeProperty('max-height');
        }
    }
    toggle.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', String(!panel.hidden));
        if (!panel.hidden) {
            positionPanel();
            const accountToggle = document.querySelector('.account-toggle');
            const accountPanel = document.getElementById('account-dropdown');
            if (accountPanel) accountPanel.hidden = true;
            accountToggle?.setAttribute('aria-expanded', 'false');
        }
    });
    markAll.addEventListener('click', () => {
        items.forEach(item => read.add(item.dataset.notificationId));
        save();
    });
    items.forEach(item => item.addEventListener('click', () => {
        read.add(item.dataset.notificationId);
        save();
    }));
    document.addEventListener('click', event => { if (!root.contains(event.target)) close(); });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); close(true); }
    });
    root.addEventListener('focusout', event => { if (!root.contains(event.relatedTarget)) close(); });
    window.addEventListener('resize', () => { if (!panel.hidden) positionPanel(); });
    window.addEventListener('storage', event => {
        if (event.key === storageKey || event.key === null) { read = loadRead(); refresh(); }
    });
    refresh();
})();
