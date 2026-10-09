<?php
function profile_photo_metadata(array $user): ?array {
    $photo = $user['profile']['avatar'] ?? null;
    if (!is_array($photo) || !is_string($photo['file'] ?? null) || !preg_match('/^[a-f0-9]{48}$/D', $photo['file'])
        || !in_array($photo['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) return null;
    return ['file' => $photo['file'], 'mime' => $photo['mime']];
}
function profile_photo_url(array $user): ?string {
    $photo = profile_photo_metadata($user);
    return $photo ? '/QuizWeb/profile_photo.php?id=' . (int) $user['id'] . '&v=' . $photo['file'] : null;
}
function render_profile_avatar(array $user, string $class = 'account-avatar'): void {
    $name = trim((string) ($user['name'] ?? 'Member'));
    $initial = function_exists('mb_substr') ? mb_substr($name, 0, 1) : substr($name, 0, 1);
    echo '<span class="' . esc($class) . ' profile-photo-avatar" aria-hidden="true">' . esc(strtoupper($initial) ?: 'M');
    if ($url = profile_photo_url($user)) echo '<img class="profile-avatar-image" src="' . esc($url) . '" alt="">';
    echo '</span>';
}
function can_view_profile_photo(array $viewer, array $target, array $classrooms): bool {
    if ((int) $viewer['id'] === (int) $target['id'] || ($viewer['role'] ?? '') === 'admin') return true;
    foreach ($classrooms as $classroom) {
        if (classroom_belongs_to_user($classroom, $viewer) && classroom_belongs_to_user($classroom, $target)) return true;
    }
    return false;
}
function profile_validate_photo(string $path): array {
    $size = is_file($path) ? filesize($path) : 0;
    if (!$size || $size > 2 * 1024 * 1024) throw new InvalidArgumentException('Choose a profile photo smaller than 2 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $image = @getimagesize($path);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !$image || ($image['mime'] ?? '') !== $mime) {
        throw new InvalidArgumentException('Choose a valid JPG, PNG, or WebP image.');
    }
    if ($image[0] > 4096 || $image[1] > 4096 || $image[0] * $image[1] > 12000000) {
        throw new InvalidArgumentException('Use a photo up to 4096 pixels per side and 12 megapixels.');
    }
    return ['mime' => $mime];
}
function profile_store_photo(array $file): array {
    if (!is_int($file['error'] ?? null) || $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Choose a photo and try uploading it again (maximum 2 MB).');
    }
    $photo = profile_validate_photo($file['tmp_name']);
    $photo['file'] = bin2hex(random_bytes(24));
    $bytes = file_get_contents($file['tmp_name']);
    if ($bytes === false) throw new RuntimeException('The profile photo could not be saved.');
    // Stored with users.profile so the image survives replacement of the web container.
    $photo['data'] = base64_encode($bytes);
    return $photo;
}
function profile_photo_bytes(array $user): ?string {
    $photo = profile_photo_metadata($user);
    if (!$photo) return null;
    $avatar = $user['profile']['avatar'];
    if (array_key_exists('data', $avatar)) {
        if (!is_string($avatar['data']) || strlen($avatar['data']) > 2796204) return null;
        $bytes = base64_decode($avatar['data'], true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > 2 * 1024 * 1024) return null;
        $image = @getimagesizefromstring($bytes);
        if (!$image || ($image['mime'] ?? '') !== $photo['mime']
            || (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $photo['mime']
            || $image[0] > 4096 || $image[1] > 4096 || $image[0] * $image[1] > 12000000) return null;
        return $bytes;
    }
    // Compatibility for uploads made before database photo storage.
    $path = UPLOADS_DIR . '/profiles/' . $photo['file'];
    if (!is_file($path)) return null;
    try { profile_validate_photo($path); } catch (InvalidArgumentException $error) { return null; }
    $bytes = file_get_contents($path);
    return $bytes === false ? null : $bytes;
}
function profile_delete_photo(array $profile): void {
    $photo = profile_photo_metadata(['profile' => $profile]);
    if ($photo && !isset($profile['avatar']['data'])) @unlink(UPLOADS_DIR . '/profiles/' . $photo['file']);
}
function profile_save_changes(PDO $pdo, int $userId, array $changes, bool $removePhoto = false): array {
    $pdo->beginTransaction();
    try {
        $select = $pdo->prepare('SELECT profile FROM users WHERE id = ? FOR UPDATE');
        $select->execute([$userId]);
        $row = $select->fetch();
        if (!$row) throw new RuntimeException('Account not found.');
        $previous = db_json_decode($row['profile']);
        $next = array_replace($previous, $changes);
        if ($removePhoto) unset($next['avatar']);
        if (is_array($next['avatar'] ?? null) && !array_key_exists('data', $next['avatar'])) {
            $bytes = profile_photo_bytes(['profile' => $next]);
            if ($bytes !== null) $next['avatar']['data'] = base64_encode($bytes);
        }
        $pdo->prepare('UPDATE users SET profile = ? WHERE id = ?')->execute([json_encode((object) $next, JSON_UNESCAPED_SLASHES), $userId]);
        $pdo->commit();
        return $previous;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

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
