<?php
function profile_labels(): array {
    return ['student_number' => 'Student Number', 'birthdate' => 'Birthdate', 'gender' => 'Gender', 'program' => 'Program / Course', 'year_level' => 'Year Level'];
}
function profile_options(): array {
    return ['gender' => ['Female', 'Male', 'Non-binary', 'Prefer not to say', 'Other'], 'year_level' => ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', '6th Year', 'Graduate', 'Other']];
}
function profile_input(array $input): array {
    $profile = [];
    foreach (profile_labels() as $key => $label) $profile[$key] = is_string($input[$key] ?? null) ? trim($input[$key]) : '';
    return $profile;
}
function profile_errors(array $profile, string $role): array {
    $errors = [];
    foreach (profile_labels() as $key => $label) {
        $required = $role === 'student' || in_array($key, ['birthdate', 'gender'], true);
        if ($required && $profile[$key] === '') $errors[] = $label . ' is required.';
        if (strlen($profile[$key]) > 150) $errors[] = $label . ' must be 150 characters or fewer.';
    }
    if ($profile['birthdate'] !== '') {
        try {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $profile['birthdate']);
        } catch (ValueError $error) {
            $date = false;
        }
        if (!$date || $date->format('Y-m-d') !== $profile['birthdate'] || $date > new DateTimeImmutable('today') || $date < new DateTimeImmutable('1900-01-01')) $errors[] = 'Enter a valid birthdate between 1900 and today.';
    }
    foreach (profile_options() as $key => $options) {
        if ($profile[$key] !== '' && !in_array($profile[$key], $options, true)) $errors[] = 'Select a valid ' . strtolower(profile_labels()[$key]) . '.';
    }
    return $errors;
}
function render_profile_fields(array $values, array $keys, string $role = 'student'): void {
    foreach ($keys as $key) {
        $required = $role === 'student' || in_array($key, ['birthdate', 'gender'], true);
        $academic = in_array($key, ['student_number', 'program', 'year_level'], true);
        ?>
        <label>
            <span><?php echo esc(profile_labels()[$key]); ?><?php if ($academic): ?><small data-optional-label<?php echo $role === 'student' ? ' hidden' : ''; ?>> (optional for teachers)</small><?php endif; ?></span>
            <?php if (isset(profile_options()[$key])): ?>
                <select name="<?php echo esc($key); ?>" <?php echo $required ? 'required' : ''; ?> <?php echo $academic ? 'data-student-required' : ''; ?>>
                    <option value="">Select <?php echo esc(strtolower(profile_labels()[$key])); ?></option>
                    <?php foreach (profile_options()[$key] as $option): ?><option<?php echo ($values[$key] ?? '') === $option ? ' selected' : ''; ?>><?php echo esc($option); ?></option><?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="<?php echo $key === 'birthdate' ? 'date' : 'text'; ?>" name="<?php echo esc($key); ?>" value="<?php echo esc($values[$key] ?? ''); ?>" <?php echo $required ? 'required' : ''; ?> <?php echo $academic ? 'data-student-required' : ''; ?> <?php echo $key === 'birthdate' ? 'min="1900-01-01" max="' . date('Y-m-d') . '" autocomplete="bday"' : 'maxlength="150"'; ?> placeholder="<?php echo esc($key === 'program' ? 'e.g. BS Information Technology' : 'Enter your student number'); ?>">
            <?php endif; ?>
        </label>
        <?php
    }
}
