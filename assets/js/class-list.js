(() => {
    const menus = [...document.querySelectorAll('.class-roster-actions')];
    const closeMenus = except => menus.forEach(menu => {
        if (menu !== except) menu.open = false;
    });
    menus.forEach(menu => menu.addEventListener('toggle', () => {
        if (menu.open) closeMenus(menu);
    }));
    document.addEventListener('click', event => {
        if (!event.target.closest('.class-roster-actions')) closeMenus();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeMenus();
    });
})();
