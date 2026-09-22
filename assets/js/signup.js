(() => {
    const form = document.getElementById('signup-form');
    if (!form) return;
    const steps = [...form.querySelectorAll('[data-signup-step]')];
    const progress = [...form.querySelectorAll('[data-progress-step]')];
    const back = form.querySelector('[data-signup-back]');
    const next = form.querySelector('[data-signup-next]');
    const submit = form.querySelector('[data-signup-submit]');
    const password = form.elements.namedItem('password');
    const confirm = form.elements.namedItem('confirm_password');
    let current = 0;
    form.noValidate = true;
    function updateRole() {
        const student = form.elements.namedItem('role').value === 'student';
        form.querySelectorAll('[data-student-required]').forEach(field => { field.required = student; });
        form.querySelectorAll('[data-optional-label]').forEach(label => { label.hidden = student; });
    }
    function review() {
        const container = form.querySelector('[data-signup-review]');
        container.replaceChildren();
        const labels = { name: 'Full Name', email: 'Email Address', role: 'Role', student_number: 'Student Number', birthdate: 'Birthdate', gender: 'Gender', program: 'Program / Course', year_level: 'Year Level' };
        Object.entries(labels).forEach(([name, label]) => {
            const term = document.createElement('dt');
            const value = document.createElement('dd');
            term.textContent = label;
            value.textContent = form.elements.namedItem(name).value || 'Not provided';
            container.append(term, value);
        });
    }
    function showStep(index, focus = true) {
        current = index;
        steps.forEach((step, i) => { step.hidden = i !== index; });
        progress.forEach((item, i) => {
            item.classList.toggle('is-complete', i < index);
            item.classList.toggle('is-current', i === index);
            if (i === index) item.setAttribute('aria-current', 'step');
            else item.removeAttribute('aria-current');
        });
        form.querySelector('[data-step-counter]').textContent = `Step ${index + 1} of 4`;
        back.hidden = index === 0;
        next.hidden = index === 3;
        submit.hidden = index !== 3;
        if (index === 3) review();
        if (focus) steps[index].querySelector('h1').focus();
    }
    function validateStep(index) {
        confirm.setCustomValidity(password.value === confirm.value ? '' : 'Passwords do not match.');
        const invalid = [...steps[index].querySelectorAll('input, select')].find(field => !field.checkValidity());
        if (invalid) {
            showStep(index, false);
            invalid.reportValidity();
            return false;
        }
        return true;
    }
    next.addEventListener('click', () => { if (validateStep(current)) showStep(current + 1); });
    back.addEventListener('click', () => showStep(current - 1));
    form.elements.namedItem('role').addEventListener('change', updateRole);
    form.addEventListener('input', () => confirm.setCustomValidity(''));
    form.querySelector('[data-password-toggle]').addEventListener('click', event => {
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        event.currentTarget.textContent = visible ? 'Hide' : 'Show';
        event.currentTarget.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        event.currentTarget.setAttribute('aria-pressed', String(visible));
    });
    form.addEventListener('submit', event => {
        if (current < 3) {
            event.preventDefault();
            if (validateStep(current)) showStep(current + 1);
            return;
        }
        for (let i = 0; i < 3; i++) {
            if (!validateStep(i)) { event.preventDefault(); return; }
        }
        submit.disabled = true;
        submit.textContent = 'Creating account...';
    });
    updateRole();
    showStep(0, false);
})();
