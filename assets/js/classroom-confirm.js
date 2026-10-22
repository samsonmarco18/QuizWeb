(() => {
    const input = document.querySelector('[data-delete-confirmation]');
    const button = document.querySelector('[data-delete-submit]');
    if (!input || !button) return;
    const update = () => { button.disabled = input.value !== input.dataset.expected; };
    input.addEventListener('input', update);
    window.addEventListener('pageshow', update);
    update();
})();
