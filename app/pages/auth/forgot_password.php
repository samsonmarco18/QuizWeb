<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/password_reset.php';
if (is_logged_in()) redirect('/QuizWeb/dashboard.php');
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_form_csrf(); $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    if ($email !== '' && strlen($email) <= 190) password_reset_request($email);
    $sent = true;
}
render_header('Forgot Password', 'auth-page login-page');
?>
<section class="auth-layout"><aside class="auth-branding"><a class="brand auth-brand" href="/QuizWeb/login.php"><span class="brand-badge brand-badge-logo"><img class="brand-logo" src="/QuizWeb/assets/images/chalklogo.png" alt=""></span><span class="brand-copy"><strong>CHALK</strong><small>Classroom quiz space</small></span></a></aside>
<?php if ($sent): ?><section class="auth-card"><div class="auth-form-heading"><h1>Check your email</h1><p>If an active CHALK account matches that address, a one-time temporary password has been sent. It expires in 30 minutes.</p></div><a class="button button-primary" href="/QuizWeb/login.php">Return to sign in</a></section>
<?php else: ?><form method="post" class="stack-form auth-card"><input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>"><div class="auth-form-heading"><h1>Forgot your password?</h1><p>Enter your account email and we’ll send a one-time temporary password.</p></div><label><span>Email</span><input type="email" name="email" maxlength="190" autocomplete="email" required></label><button class="button button-primary">Email temporary password</button><a class="button button-secondary" href="/QuizWeb/login.php">Back to sign in</a></form><?php endif; ?></section>
<?php render_footer(); ?>
