(() => {
    const form = document.querySelector('#class-creation');
    if (!form) return;
    const panels = [...form.querySelectorAll('[data-class-panel]')];
    const steps = [...form.querySelectorAll('[data-class-step]')];
    const back = form.querySelector('[data-class-back]');
    const next = form.querySelector('[data-class-next]');
    const gradeError = form.querySelector('[data-class-grade-error]');
    let current = 0;
    const field = name => form.elements.namedItem(name);
    function validate(index) {
        for (const input of panels[index].querySelectorAll('input, select, textarea')) {
            if (!input.checkValidity()) { input.reportValidity(); return false; }
        }
        if (index !== 2) return true;
        let error = '';
        for (const group of ['categories', 'periods']) {
            const weights = [...form.querySelectorAll(`input[name^="creation_config[${group}]"][name$="[weight]"]`)];
            if (weights.reduce((sum, input) => sum + Math.round(Number(input.value) * 100), 0) !== 10000) error = `${group === 'categories' ? 'Category' : 'Grading period'} weights must total exactly 100%.`;
        }
        const unique = (selector, normalize) => {
            const values = [...form.querySelectorAll(selector)].map(input => normalize(input.value));
            return new Set(values).size === values.length;
        };
        if (!unique('input[name^="creation_config[categories]"][name$="[name]"]', v => v.trim().toLowerCase())) error = 'Category names must be unique.';
        const minimums = [...form.querySelectorAll('input[name^="creation_config[scale]"][name$="[min]"]')].map(input => Number(input.value));
        if (!minimums.includes(0) || new Set(minimums).size !== minimums.length) error = 'Grade scale minimums must be unique and start at 0.';
        if (!unique('input[name^="creation_config[scale]"][name$="[label]"]', v => v.trim().toLowerCase())) error = 'Grade scale labels must be unique.';
        gradeError.textContent = error;
        gradeError.hidden = !error;
        return !error;
    }
    function review() {
        const container = form.querySelector('[data-class-review]'); container.replaceChildren();
        function pair(list, label, text) {
            const term = document.createElement('dt'), value = document.createElement('dd');
            term.textContent = label; value.textContent = text || 'Not specified'; list.append(term, value);
        }
        const text = input => input.tagName === 'SELECT' ? input.selectedOptions[0]?.textContent : input.value;
        for (const [index, title] of ['Class details', 'Academic details', 'Grade configuration'].entries()) {
            const card = document.createElement('section'); card.className = 'class-review-card';
            const heading = document.createElement('h4'); heading.textContent = title;
            const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'button button-secondary'; edit.textContent = 'Edit'; edit.addEventListener('click', () => go(index));
            card.append(heading, edit);
            if (index < 2) {
                const list = document.createElement('dl');
                for (const input of panels[index].querySelectorAll('input, select, textarea')) {
                    if (input.type === 'hidden') continue;
                    const label = input.closest('label')?.querySelector('span')?.textContent;
                    if (label) pair(list, label.replace(' (optional for semester mean)', ''), text(input));
                }
                card.append(list);
            } else {
                const grid = document.createElement('div'); grid.className = 'class-grading-review';
                for (const group of ['Categories', 'Grading periods', 'Rules', 'Grade scale']) {
                    const block = document.createElement('div'), title = document.createElement('h5'), list = document.createElement('dl'); title.textContent = group;
                    if (group === 'Categories' || group === 'Grading periods') {
                        const key = group === 'Categories' ? 'categories' : 'periods';
                        panels[2].querySelectorAll(`input[name^="creation_config[${key}]"][name$="[name]"]`).forEach(input => pair(list, input.value, `${field(input.name.replace('[name]', '[weight]')).value}%`));
                    } else if (group === 'Grade scale') {
                        const bands = [...panels[2].querySelectorAll('input[name^="creation_config[scale]"][name$="[min]"]')].sort((a,b) => Number(a.value)-Number(b.value));
                        bands.forEach((input,i) => pair(list, field(input.name.replace('[min]', '[label]')).value, `${input.value}%${i === bands.length - 1 ? ' to 100%' : ` to below ${bands[i+1].value}%`}`));
                    } else {
                        pair(list, 'Template', text(field('grading_template')));
                        pair(list, 'Passing grade', `${field('creation_config[passing]').value}%`);
                        pair(list, 'Missing scores', text(field('creation_config[missing_policy]')));
                    }
                    block.append(title, list); grid.append(block);
                }
                card.append(grid);
            }
            container.append(card);
        }
    }
    function show(index, focus = true) {
        current = index;
        panels.forEach((panel, i) => { panel.hidden = i !== index; });
        steps.forEach((step, i) => {
            if (i === index) step.setAttribute('aria-current', 'step');
            else step.removeAttribute('aria-current');
            step.classList.toggle('is-active', i === index);
        });
        back.disabled = index === 0;
        next.hidden = index === panels.length - 1;
        form.querySelector('[data-class-status]').textContent = `Step ${index + 1} of ${panels.length}: ${steps[index].textContent.replace(/^\d+\. /, '')}`;
        if (index === 3) review();
        if (focus) panels[index].querySelector('h3').focus();
    }
    function go(index) {
        if (index > current) {
            for (let i = 0; i < index; i++) {
                show(i, false);
                if (!validate(i)) return;
            }
        }
        show(index);
    }
    form.noValidate = true;
    form.querySelectorAll('[data-class-navigation]').forEach(element => { element.hidden = false; });
    next.addEventListener('click', () => go(current + 1));
    back.addEventListener('click', () => go(current - 1));
    steps.forEach((step, i) => step.addEventListener('click', () => go(i)));
    field('grading_template').addEventListener('change', event => {
        const config = window.classCreationTemplates[event.target.value]?.config;
        if (!config) return;
        for (const [key, value] of Object.entries(config)) {
            if (Array.isArray(value)) value.forEach((entry, i) => {
                for (const [property, contents] of Object.entries(entry)) {
                    const input = field(`creation_config[${key}][${i}][${property}]`);
                    if (input) input.value = contents;
                }
            });
            else if (field(`creation_config[${key}]`)) field(`creation_config[${key}]`).value = value;
        }
    });
    panels[2].addEventListener('input', event => { if (event.target.name !== 'grading_template') field('grading_template').value = 'custom'; });
    form.addEventListener('submit', event => {
        if (current !== 3) { event.preventDefault(); go(current + 1); return; }
        for (let i = 0; i < 3; i++) {
            show(i, false);
            if (!validate(i)) { event.preventDefault(); return; }
        }
        show(3, false);
    });
    show(0, false);
})();
