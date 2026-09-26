<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/profile.php';
$user = require_login();
$_SESSION['profile_csrf'] ??= bin2hex(random_bytes(32));
$profile = profile_input($user['profile'] ?? []);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profile = profile_input($_POST);
    $errors = profile_errors($profile, $user['role']);
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['profile_csrf'], $_POST['csrf'])) $errors[] = 'Your form expired. Please try again.';
    if (!$errors) {
        $statement = db()->prepare('UPDATE users SET profile = :profile WHERE id = :id');
        $statement->execute(['profile' => json_encode($profile), 'id' => $user['id']]);
        flash_set('success', 'Profile updated.');
        redirect('/QuizWeb/profile.php');
    }
}
render_header('Profile Settings', 'profile-page');
?>
<section class="glass panel profile-settings">
    <h1>Profile Settings</h1>
    <p class="muted"><?php echo esc($user['name']); ?> &middot; <?php echo esc($user['email']); ?></p>
    <form method="post" class="stack-form">
        <input type="hidden" name="csrf" value="<?php echo esc($_SESSION['profile_csrf']); ?>">
        <?php foreach ($errors as $error): ?><div class="inline-error" role="alert"><?php echo esc($error); ?></div><?php endforeach; ?>
        <?php render_profile_fields($profile, array_keys(profile_labels()), $user['role']); ?>
        <button class="button button-primary" type="submit">Save Changes</button>
    </form>
</section>
<?php render_footer(); ?>
