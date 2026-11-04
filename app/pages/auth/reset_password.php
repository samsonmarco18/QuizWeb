<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/password_reset.php';
if (is_logged_in()) redirect('/QuizWeb/dashboard.php');
$token = is_string($_GET['token'] ?? $_POST['token'] ?? null) ? ($_GET['token'] ?? $_POST['token']) : '';
$resetUser = password_reset_user($token); $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetUser) {
    require_form_csrf(); $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : ''; $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    if ($password !== $confirm) $error = 'Passwords do not match.';
    else try { password_reset_complete($token, $password); flash_set('success', 'Password updated. You can now sign in.'); redirect('/QuizWeb/login.php'); } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
}
render_header('Reset Password', 'auth-page login-page');
?>
<section class="auth-layout"><aside class="auth-branding"><a class="brand auth-brand" href="/QuizWeb/login.php"><span class="brand-badge brand-badge-logo"><img class="brand-logo" src="/QuizWeb/assets/images/chalklogo.png" alt=""></span><span class="brand-copy"><strong>CHALK</strong></span></a></aside>
<?php if (!$resetUser): ?><section class="auth-card"><div class="inline-error"><h1>Reset link unavailable</h1><p>This password reset link is invalid, expired, or already used.</p></div><a class="button button-primary" href="/QuizWeb/forgot_password.php">Request another link</a></section>
<?php else: ?><form method="post" class="stack-form auth-card"><input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>"><input type="hidden" name="token" value="<?php echo esc($token); ?>"><div class="auth-form-heading"><h1>Create a new password</h1><p>Use at least 8 characters with a letter and a number.</p></div><?php if ($error): ?><p class="inline-error" role="alert"><?php echo esc($error); ?></p><?php endif; ?><label><span>New password</span><input type="password" name="password" minlength="8" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}" autocomplete="new-password" required></label><label><span>Confirm new password</span><input type="password" name="confirm_password" autocomplete="new-password" required></label><button class="button button-primary">Reset password</button></form><?php endif; ?></section>
<?php render_footer(); ?>
