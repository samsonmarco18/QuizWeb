<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/profile.php';
$user = require_login();
$_SESSION['profile_csrf'] ??= bin2hex(random_bytes(32));
$profile = profile_input($user['profile'] ?? []);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : 'details';
    if ($action === 'details') {
        $profile = profile_input($_POST);
        $errors = profile_errors($profile, $user['role']);
    } elseif (!in_array($action, ['photo_upload', 'photo_remove'], true)) $errors[] = 'Choose a valid profile action.';
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['profile_csrf'], $_POST['csrf'])) $errors[] = 'Your form expired. Please try again.';
    if (!$errors) {
        $newPhoto = null;
        try {
            if ($action === 'photo_upload') {
                $newPhoto = profile_store_photo($_FILES['profile_photo'] ?? []);
                $previous = profile_save_changes(db(), (int) $user['id'], ['avatar' => $newPhoto]);
            } elseif ($action === 'photo_remove') {
                $previous = profile_save_changes(db(), (int) $user['id'], [], true);
            } else {
                $previous = profile_save_changes(db(), (int) $user['id'], $profile);
            }
            if ($action !== 'details') profile_delete_photo($previous);
            flash_set('success', $action === 'photo_remove' ? 'Profile photo removed.' : 'Profile updated.');
            redirect('/QuizWeb/profile.php');
        } catch (Throwable $error) {
            if ($newPhoto) profile_delete_photo(['avatar' => $newPhoto]);
            $errors[] = $error instanceof InvalidArgumentException ? $error->getMessage() : 'Your profile could not be saved. Please try again.';
        }
    }
}
render_header('Profile Settings', 'profile-page', ['/QuizWeb/assets/css/profile.css']);
?>
<section class="glass panel profile-settings">
    <header class="profile-page-heading"><span class="eyebrow"><?php echo esc(ucfirst($user['role'])); ?> portal</span><h1>Profile Settings</h1><p>Manage your personal information and account details.</p></header>
    <?php foreach ($errors as $error): ?><div class="inline-error" role="alert"><?php echo esc($error); ?></div><?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="profile-photo-settings" data-profile-photo-form>
        <input type="hidden" name="csrf" value="<?php echo esc($_SESSION['profile_csrf']); ?>">
        <div class="profile-photo-portrait"><div data-profile-photo-preview><?php render_profile_avatar($user, 'profile-photo-preview'); ?></div><button class="profile-camera-button" type="button" data-profile-photo-choose aria-label="Change profile photo"><?php echo nav_icon('camera'); ?></button></div>
        <div class="profile-photo-controls">
            <h2>Profile photo</h2>
            <p>Choose a photo to personalize your account.</p>
            <label class="profile-photo-file">Choose a photo<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-profile-photo-input></label>
            <div class="profile-photo-actions">
                <button class="button button-primary" type="button" data-profile-photo-choose><?php echo nav_icon('upload'); ?>Upload photo</button>
                <button class="button button-primary" type="submit" name="action" value="photo_upload" data-profile-photo-save hidden><?php echo nav_icon('save'); ?>Save photo</button>
                <button class="button button-secondary" type="submit" name="action" value="photo_remove" formnovalidate <?php echo profile_photo_metadata($user) ? '' : 'disabled'; ?>><?php echo nav_icon('trash'); ?>Remove</button>
            </div>
            <p class="profile-photo-guidance">JPG, PNG, or WebP. Maximum 2 MB. Visible to members of your classrooms.</p>
            <p data-profile-photo-status role="status"></p>
        </div>
    </form>
    <form method="post" class="profile-details-form">
        <input type="hidden" name="action" value="details">
        <input type="hidden" name="csrf" value="<?php echo esc($_SESSION['profile_csrf']); ?>">
        <section class="profile-personal-panel" aria-labelledby="profile-personal-title">
            <div class="profile-section-heading"><span class="profile-section-icon"><?php echo nav_icon('user'); ?></span><div><h2 id="profile-personal-title">Personal information</h2><p>Update your personal details. Keep your information accurate and up to date.</p></div></div>
            <div class="profile-personal-grid"><?php render_profile_fields($profile, ['student_number', 'birthdate', 'gender', 'year_level', 'program'], $user['role'], true); ?></div>
        </section>
        <button class="button button-primary profile-save-button" type="submit"><?php echo nav_icon('save'); ?>Save Changes</button>
    </form>
</section>
<?php render_footer(); ?>
