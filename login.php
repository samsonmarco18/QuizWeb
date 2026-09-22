<?php

require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) {
    redirect('/QuizWeb/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = attempt_login($email, $password);

    if (!$user) {
        $errors[] = 'Invalid email or password.';
    } else {
        login_user($user);
        flash_set('success', 'Welcome back, ' . $user['name'] . '!');
        redirect('/QuizWeb/dashboard.php');
    }
}

render_header('Login', 'auth-page login-page');
?>

<section class="auth-layout">
    <aside class="auth-branding">
        <a class="brand auth-brand" href="/QuizWeb/login.php" aria-label="<?php echo esc(APP_NAME); ?> home">
            <span class="brand-badge brand-badge-minimal">CH</span>
            <span class="brand-copy">
                <strong><?php echo esc(APP_NAME); ?></strong>
                <small>Classroom quiz space</small>
            </span>
        </a>
    </aside>
    <form method="post" class="stack-form auth-card">
        <div class="auth-form-heading">
            <div class="auth-title-row">
                <h1>Sign in</h1>
                <span class="chalk-sticker" aria-hidden="true"></span>
            </div>
            <p>Welcome back.</p>
        </div>
        <?php foreach ($errors as $error): ?>
            <div class="inline-error"><?php echo esc($error); ?></div>
        <?php endforeach; ?>
        <label>
            <span>Email</span>
            <input type="email" name="email" required placeholder="you@example.com">
        </label>
        <label>
            <span>Password</span>
            <input type="password" name="password" required placeholder="Enter your password">
        </label>
        <button class="button button-primary" type="submit">Sign in</button>
        <p class="muted auth-switch">New here? <a href="/QuizWeb/register.php">Create an account</a></p>
    </form>
</section>

<?php render_footer(); ?>
