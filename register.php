<?php

require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) {
    redirect('/QuizWeb/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';

    if ($name === '' || $email === '' || $password === '') {
        $errors[] = 'Please fill in all required fields.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if (!in_array($role, ['teacher', 'student'], true)) {
        $errors[] = 'Please choose a valid role.';
    }

    if (find_user_by_email($email)) {
        $errors[] = 'An account already exists for that email.';
    }

    if (!$errors) {
        $user = register_user($name, $email, $password, $role);
        login_user($user);
        flash_set('success', 'Account created successfully. Your quiz space is ready!');
        redirect('/QuizWeb/dashboard.php');
    }
}

render_header('Register', 'auth-page');
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
                <h1>Create account</h1>
                <span class="chalk-sticker" aria-hidden="true"></span>
            </div>
            <p>Start learning your way.</p>
        </div>
        <?php foreach ($errors as $error): ?>
            <div class="inline-error"><?php echo esc($error); ?></div>
        <?php endforeach; ?>
        <label>
            <span>Full Name</span>
            <input type="text" name="name" required value="<?php echo esc($_POST['name'] ?? ''); ?>" placeholder="Your name">
        </label>
        <label>
            <span>Email</span>
            <input type="email" name="email" required value="<?php echo esc($_POST['email'] ?? ''); ?>" placeholder="you@example.com">
        </label>
        <div class="split-fields">
            <label>
                <span>Password</span>
                <input type="password" name="password" required placeholder="Minimum 6 characters">
            </label>
            <label>
                <span>Confirm Password</span>
                <input type="password" name="confirm_password" required placeholder="Repeat your password">
            </label>
        </div>
        <label>
            <span>I am joining as</span>
            <select name="role">
                <option value="teacher" <?php echo (($_POST['role'] ?? '') === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                <option value="student" <?php echo (($_POST['role'] ?? 'student') === 'student') ? 'selected' : ''; ?>>Student</option>
            </select>
        </label>
        <button class="button button-primary" type="submit">Create Account</button>
        <p class="muted auth-switch">Already have an account? <a href="/QuizWeb/login.php">Sign in</a></p>
    </form>
</section>

<?php render_footer(); ?>
