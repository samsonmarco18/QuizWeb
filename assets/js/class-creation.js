(() => {
    const form = document.querySelector('#class-creation');
    if (!form) return;
    const panels = [...form.querySelectorAll('[data-class-panel]')];
    const steps = [...form.querySelectorAll('[data-class-step]')];
    const back = form.querySelector('[data-class-back]');
    const next = form.querySelector('[data-class-next]');
    const gradeError = form.querySelector('[data-class-grade-error]');
    const submit = form.querySelector('[data-class-submit]');
    const completedSteps = new Set();
    let current = 0;
    const field = name => form.elements.namedItem(name);
    function validate(index, report = true) {
        for (const input of panels[index].querySelectorAll('input, select, textarea')) {
            if (!input.checkValidity()) { if (report) input.reportValidity(); return false; }
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
        if (report) { gradeError.textContent = error; gradeError.hidden = !error; }
        return !error;
    }
    function updateProgress() {
        completedSteps.forEach(index => { if (index < 3 && !validate(index, false)) completedSteps.delete(index); });
        steps.forEach((button, index) => {
            const complete = completedSteps.has(index);
            button.classList.toggle('is-complete', complete);
            button.querySelector('.class-step-number').textContent = complete ? '\u2713' : String(index + 1);
            button.setAttribute('aria-label', `Step ${index + 1}: ${button.dataset.classLabel}, ${complete ? 'completed' : index === current ? 'current' : 'not completed'}`);
            button.title = complete ? 'Completed' : '';
        });
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
            const heading = document.createElement('h4');
            const icon = panels[index].querySelector('.class-heading-icon svg, .class-field-icon svg');
            if (icon) heading.append(icon.cloneNode(true));
            heading.append(document.createTextNode(title));
            const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'button button-secondary'; edit.textContent = 'Edit'; edit.addEventListener('click', () => go(index));
            card.append(heading, edit);
            if (index < 2) {
                const list = document.createElement('dl');
                for (const input of panels[index].querySelectorAll('input, select, textarea')) {
                    if (input.type === 'hidden') continue;
                    const label = input.closest('label')?.querySelector('.class-field-label')?.textContent;
                    if (label) pair(list, label.replace(' (optional for semester mean)', '').replace('*', '').trim(), text(input));
                }
                card.append(list);
            } else {
                const grid = document.createElement('div'); grid.className = 'class-grading-review';
                for (const group of ['Categories', 'Grading periods', 'Rules', 'Grade scale']) {
                    const block = document.createElement('div'), title = document.createElement('h5'), list = document.createElement('dl'); title.textContent = group;
                    if (group === 'Categories' || group === 'Grading periods') {
                        const key = group === 'Categories' ? 'categories' : 'periods';
                        const total = [...panels[2].querySelectorAll(`input[name^="creation_config[${key}]"][name$="[weight]"]`)].reduce((sum, input) => sum + Math.round(Number(input.value) * 100), 0) / 100;
                        title.textContent = `${group === 'Categories' ? 'Category weights' : 'Grading periods'} (total ${total}%)`;
                        list.className = 'class-review-weights';
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
        form.dataset.currentStep = String(index);
        next.hidden = index === panels.length - 1;
        submit.hidden = index !== panels.length - 1;
        form.querySelector('[data-class-status]').textContent = `Step ${index + 1} of ${panels.length}: ${steps[index].dataset.classLabel}`;
        form.querySelector('[data-class-step-help]').textContent = ['Provide the basic information for your classroom.', 'Provide the academic information for this classroom.', 'Choose how grades will be computed for this classroom.', 'Check all information before creating your classroom.'][index];
        const subtitle = document.querySelector('[data-class-subtitle]');
        if (subtitle) subtitle.textContent = ['Set up your classroom details to get started.', 'Set up the academic details for your classroom.', 'Configure how grades will be computed for this classroom.', 'Review all details before creating your classroom.'][index];
        if (index === 3) review();
        updateProgress();
        if (focus) panels[index].querySelector('h3').focus();
    }
    function go(index) {
        if (index > current) {
            for (let i = 0; i < index; i++) {
                show(i, false);
                if (!validate(i)) { updateProgress(); return; }
                completedSteps.add(i);
            }
        }
        show(index);
    }
    form.noValidate = true;
    form.querySelectorAll('[data-class-navigation]').forEach(element => { element.hidden = false; });
    next.addEventListener('click', () => go(current + 1));
    back.addEventListener('click', () => { if (current === 0) window.location.assign('/QuizWeb/classes.php'); else go(current - 1); });
    steps.forEach((step, i) => step.addEventListener('click', () => go(i)));
    const rowPrototypes = {
        categories: form.querySelector('[data-class-category-row]').cloneNode(true),
        scale: form.querySelector('[data-class-scale-row]').cloneNode(true),
    };
    let categoryIndex = Math.max(...[...form.querySelectorAll('[data-class-category-row] input')].map(input => Number(input.name.match(/\[categories\]\[(\d+)\]/)?.[1] || 0))) + 1;
    let scaleIndex = Math.max(...[...form.querySelectorAll('[data-class-scale-row] input')].map(input => Number(input.name.match(/\[scale\]\[(\d+)\]/)?.[1] || 0))) + 1;
    function makeRow(group, index, entry) {
        const row = rowPrototypes[group].cloneNode(true);
        row.querySelectorAll('input').forEach(input => {
            input.name = input.name.replace(new RegExp(`\\[${group}\\]\\[\\d+\\]`), `[${group}][${index}]`);
            const property = input.name.match(/\[([^\]]+)\]$/)[1];
            input.value = entry[property] ?? '';
        });
        return row;
    }
    function updateTotals() {
        for (const [group, selector] of [['categories', '[data-class-category-total]'], ['periods', '[data-class-period-total]']]) {
            const total = [...form.querySelectorAll(`input[name^="creation_config[${group}]"][name$="[weight]"]`)].reduce((sum, input) => sum + Math.round(Number(input.value || 0) * 100), 0);
            const output = form.querySelector(selector);
            output.textContent = `${total / 100}%`;
            output.closest('.class-weight-total').classList.toggle('is-invalid', total !== 10000);
        }
        const categories = form.querySelectorAll('[data-class-category-row]');
        const scales = form.querySelectorAll('[data-class-scale-row]');
        form.querySelector('[data-class-add-category]').disabled = categories.length >= 8;
        form.querySelector('[data-class-add-scale]').disabled = scales.length >= 10;
        form.querySelectorAll('[data-class-remove-category]').forEach(button => { button.disabled = categories.length <= 1; });
        form.querySelectorAll('[data-class-remove-scale]').forEach(button => { button.disabled = scales.length <= 1; });
        updateProgress();
    }
    field('grading_template').addEventListener('change', event => {
        const config = window.classCreationTemplates[event.target.value]?.config;
        if (!config) return;
        for (const [key, value] of Object.entries(config)) {
            if (key === 'categories' || key === 'scale') {
                form.querySelector(key === 'categories' ? '[data-class-categories]' : '[data-class-scales]').replaceChildren(...value.map((entry, i) => makeRow(key, i, entry)));
                if (key === 'categories') categoryIndex = value.length;
                else scaleIndex = value.length;
            } else if (Array.isArray(value)) value.forEach((entry, i) => {
                for (const [property, contents] of Object.entries(entry)) {
                    const input = field(`creation_config[${key}][${i}][${property}]`);
                    if (input) input.value = contents;
                }
            });
            else if (field(`creation_config[${key}]`)) field(`creation_config[${key}]`).value = value;
        }
        gradeError.hidden = true;
        updateTotals();
    });
    panels[2].addEventListener('input', event => { if (event.target.name !== 'grading_template') field('grading_template').value = 'custom'; updateTotals(); });
    form.querySelector('[data-class-add-category]').addEventListener('click', () => {
        if (form.querySelectorAll('[data-class-category-row]').length >= 8) return;
        const row = makeRow('categories', categoryIndex++, {id: `category-${Date.now()}-${categoryIndex}`, name: `Category ${categoryIndex}`, weight: 0});
        form.querySelector('[data-class-categories]').append(row);
        field('grading_template').value = 'custom'; updateTotals(); row.querySelector('input:not([type="hidden"])').focus();
    });
    form.querySelector('[data-class-add-scale]').addEventListener('click', () => {
        if (form.querySelectorAll('[data-class-scale-row]').length >= 10) return;
        const used = [...form.querySelectorAll('[data-class-scale-row] input[type="number"]')].map(input => Number(input.value));
        let min = Math.min(100, Math.max(...used) + 5);
        if (used.includes(min)) min = Array.from({length: 101}, (_, i) => i).find(value => !used.includes(value));
        const row = makeRow('scale', scaleIndex++, {min, label: `Range ${scaleIndex}`});
        form.querySelector('[data-class-scales]').append(row);
        field('grading_template').value = 'custom'; updateTotals(); row.querySelector('input').focus();
    });
    panels[2].addEventListener('click', event => {
        const remove = event.target.closest('[data-class-remove-category], [data-class-remove-scale]');
        if (!remove || remove.disabled) return;
        const row = remove.closest('[data-class-category-row], [data-class-scale-row]');
        const parent = row.parentElement;
        row.remove(); field('grading_template').value = 'custom'; updateTotals();
        parent.querySelector('input:not([type="hidden"])')?.focus();
    });
    const description = field('description');
    const updateDescription = () => { form.querySelector('[data-class-description-count]').textContent = `${description.value.length}/500`; };
    description.addEventListener('input', updateDescription);
    form.addEventListener('input', updateProgress);
    form.addEventListener('change', updateProgress);
    updateDescription(); updateTotals();
    form.addEventListener('submit', event => {
        if (current !== 3) { event.preventDefault(); go(current + 1); return; }
        for (let i = 0; i < 3; i++) {
            show(i, false);
            if (!validate(i)) { event.preventDefault(); updateProgress(); return; }
            completedSteps.add(i);
        }
        show(3, false);
    });
    show(0, false);
})();
