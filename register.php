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

<section class="auth-wrap glass">
    <div class="auth-copy">
        <span class="eyebrow">Create your profile</span>
        <h1>Set up a teacher or student account</h1>
        <p class="lead">Teachers can create classes and custom games. Students can join using a classroom code.</p>
        <div class="auth-badges">
            <span>Quick signup</span>
            <span>Teacher tools</span>
            <span>Student join codes</span>
        </div>
    </div>
    <form method="post" class="stack-form">
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
    </form>
    <aside class="auth-side">
        <article class="auth-side-card">
            <strong>Built for teachers</strong>
            <p>Set up classrooms fast, share announcements, and keep quizzes, files, and activity in one place.</p>
        </article>
        <article class="auth-side-card">
            <strong>Easy for students</strong>
            <p>Join with a code, follow feed updates, and play through every challenge on desktop or phone.</p>
        </article>
    </aside>
</section>

<?php render_footer(); ?>
