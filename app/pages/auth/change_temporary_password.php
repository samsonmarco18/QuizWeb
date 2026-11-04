<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
$user = current_user();
if (!$user) redirect('/QuizWeb/login.php');
if (empty($_SESSION['temporary_password_login'])) redirect('/QuizWeb/dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_form_csrf(); $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : ''; $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    if ($password !== $confirm) $error = 'Passwords do not match.';
    elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) $error = 'Use at least 8 characters with a letter and a number.';
    else {
        $pdo = db(); $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
            $pdo->prepare('UPDATE password_reset_tokens SET used_at = ? WHERE user_id = ? AND used_at IS NULL')->execute([time(), (int) $user['id']]);
            $audit = $pdo->prepare('INSERT INTO audit_logs (actor_id, target_id, action, occurred_at) VALUES (?, ?, ?, ?)'); $audit->execute([(int) $user['id'], (int) $user['id'], 'temporary_password_replaced', now_iso()]);
            $pdo->commit(); unset($_SESSION['temporary_password_login']); session_regenerate_id(true); flash_set('success', 'Your permanent password has been saved.'); redirect('/QuizWeb/dashboard.php');
        } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); $error = 'Could not save the new password. Please try again.'; }
    }
}
render_header('Create Permanent Password', 'auth-page login-page');
?>
<section class="auth-layout"><aside class="auth-branding"><a class="brand auth-brand" href="#"><span class="brand-badge brand-badge-logo"><img class="brand-logo" src="/QuizWeb/assets/images/chalklogo.png" alt=""></span><span class="brand-copy"><strong>CHALK</strong></span></a></aside><form method="post" class="stack-form auth-card"><input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>"><div class="auth-form-heading"><h1>Create your permanent password</h1><p>Your temporary password was accepted. Choose a permanent password before continuing.</p></div><?php if ($error): ?><p class="inline-error" role="alert"><?php echo esc($error); ?></p><?php endif; ?><label><span>New password</span><input type="password" name="password" minlength="8" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}" autocomplete="new-password" required></label><label><span>Confirm new password</span><input type="password" name="confirm_password" autocomplete="new-password" required></label><button class="button button-primary">Save permanent password</button><a class="button button-secondary" href="/QuizWeb/logout.php">Cancel and sign out</a></form></section>
<?php render_footer(); ?>
