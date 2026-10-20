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
        const programLabel = form.querySelector('[data-program-label]');
        if (programLabel) programLabel.textContent = student ? 'Program / Course' : 'Department';
        const program = form.elements.namedItem('program');
        if (program) program.placeholder = student ? 'e.g. BS Information Technology' : 'e.g. Department of Information Technology';
        form.querySelectorAll('[data-student-required]').forEach(field => { field.required = student; });
        for (const name of ['student_number', 'year_level']) { const input = form.elements.namedItem(name); if (input) { input.disabled = !student; input.closest('label').hidden = !student; } }
        form.querySelectorAll('[data-optional-label]').forEach(label => { label.hidden = student; });
    }
    function review() {
        const container = form.querySelector('[data-signup-review]');
        container.replaceChildren();
        const labels = { name: 'Full Name', email: 'Email Address', role: 'Role', student_number: 'Student Number', birthdate: 'Birthdate', gender: 'Gender', program: form.elements.namedItem('role').value === 'teacher' ? 'Department' : 'Program / Course', year_level: 'Year Level' };
        Object.entries(labels).forEach(([name, label]) => {
            if (form.elements.namedItem(name).disabled) return;
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
    const passwordStatus = document.createElement('p'); passwordStatus.id = 'password-match-status'; confirm.setAttribute('aria-describedby', passwordStatus.id); passwordStatus.setAttribute('role', 'status'); passwordStatus.setAttribute('aria-live', 'polite'); confirm.closest('label').append(passwordStatus);
    const feedback = form.querySelector('[data-password-feedback]');
    function passwordFeedback() {
        const matches = password.value === confirm.value;
        confirm.setCustomValidity(confirm.value && !matches ? 'Passwords do not match.' : '');
        confirm.setAttribute('aria-invalid', String(Boolean(confirm.value && !matches)));
        passwordStatus.textContent = !confirm.value ? '' : (matches ? 'Passwords match.' : 'Passwords do not match.');
        passwordStatus.className = matches ? 'password-match-status is-match' : 'password-match-status is-mismatch';
        if (!feedback) return;
        const value = password.value;
        const rules = { length: value.length >= 8, letter: /[A-Za-z]/.test(value), number: /[0-9]/.test(value), match: Boolean(value && confirm.value && matches) };
        const valid = rules.length && rules.letter && rules.number;
        // Strength is guidance; the checklist mirrors the server's required rules.
        let score = !value ? 0 : !valid ? 1 : 2;
        if (valid && value.length >= 12) score++;
        if (valid && value.length >= 12 && /[^A-Za-z0-9]/.test(value)) score++;
        const strength = ['Enter a password', 'Weak', 'Fair', 'Good', 'Strong'][score];
        feedback.dataset.strength = String(score);
        feedback.querySelector('#password-strength-text').textContent = strength;
        const meter = feedback.querySelector('[role="meter"]');
        meter.setAttribute('aria-valuenow', String(score));
        meter.setAttribute('aria-valuetext', strength);
        [...meter.children].forEach((segment, index) => segment.classList.toggle('is-filled', index < score));
        Object.entries(rules).forEach(([rule, met]) => {
            const item = feedback.querySelector(`[data-password-rule="${rule}"]`);
            item.classList.toggle('is-met', met);
            item.querySelector('span').textContent = met ? '?' : '';
        });
        password.classList.toggle('password-valid', Boolean(valid));
        confirm.classList.toggle('password-valid', rules.match && valid);
        const ready = valid && rules.match;
        feedback.classList.toggle('is-ready', ready);
        feedback.querySelector('[data-password-ready]').textContent = ready
            ? 'All password requirements met. You?re ready to continue.'
            : valid ? 'Password requirements met. Confirm your password to continue.'
            : 'Use 8+ characters with a letter and a number. For more strength, try 12+ characters and a symbol.';
    }
    password.addEventListener('input', passwordFeedback); confirm.addEventListener('input', passwordFeedback);
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
    passwordFeedback();
    updateRole();
    showStep(0, false);
})();
