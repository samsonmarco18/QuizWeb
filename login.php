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

render_header('Login', 'auth-page');
?>

<section class="auth-wrap glass">
    <div class="auth-copy">
        <span class="eyebrow">Welcome back</span>
        <h1>Log in to your quiz arena</h1>
        <p class="lead">Teachers can manage classrooms and games. Students can jump back into live quiz action.</p>
        <div class="auth-badges">
            <span>Classroom newsfeed</span>
            <span>Quiz dashboards</span>
            <span>Mobile friendly play</span>
        </div>
    </div>
    <form method="post" class="stack-form">
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
        <button class="button button-primary" type="submit">Login</button>
        <p class="muted">Need an account? <a href="/QuizWeb/register.php">Create one here</a>.</p>
    </form>
    <aside class="auth-side">
        <article class="auth-side-card">
            <strong>Teacher view</strong>
            <p>Create classrooms, publish announcements, attach modules, and manage game-based assessments.</p>
        </article>
        <article class="auth-side-card">
            <strong>Student view</strong>
            <p>See updates, open classroom files, and jump into quizzes from one clean classroom space.</p>
        </article>
    </aside>
</section>

<?php render_footer(); ?>
