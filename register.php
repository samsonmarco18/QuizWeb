<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/profile.php';
if (is_logged_in()) redirect('/QuizWeb/dashboard.php');
$_SESSION['signup_csrf'] ??= bin2hex(random_bytes(32));
$errors = [];
$createdName = $_SESSION['signup_created_name'] ?? null;
unset($_SESSION['signup_created_name']);
$role = is_string($_POST['role'] ?? null) ? $_POST['role'] : 'student';
$profile = profile_input($_POST);
$name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
$email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $createdName = null;
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['signup_csrf'], $_POST['csrf'])) $errors[] = 'Your form expired. Please try again.';
    if ($name === '' || strlen($name) > 150) $errors[] = 'Enter your full name (up to 150 characters).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) $errors[] = 'Use at least 8 characters with a letter and a number.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['teacher', 'student'], true)) $errors[] = 'Choose a valid role.';
    $errors = array_merge($errors, profile_errors($profile, $role));
    if (!$errors && find_user_by_email($email)) $errors[] = 'An account already exists for that email.';
    if (!$errors) {
        register_user($name, $email, $password, $role, $profile);
        $_SESSION['signup_created_name'] = $name;
        $_SESSION['signup_csrf'] = bin2hex(random_bytes(32));
        redirect('/QuizWeb/register.php?created=1');
    }
}
render_header('Register', 'auth-page signup-page');
?>
<section class="signup-layout">
    <aside class="signup-brand-panel">
        <a class="signup-brand" href="/QuizWeb/login.php"><span class="brand-badge">CH</span><strong>CHALK</strong></a>
    </aside>
    <?php if ($createdName !== null): ?>
        <section class="signup-card signup-success" aria-labelledby="signup-success-title">
            <span class="signup-success-check" aria-hidden="true">&#10003;</span>
            <h1 id="signup-success-title">Account created!</h1>
            <p>Welcome to CHALK, <?php echo esc($createdName); ?>!</p>
            <p>You're all set to start your learning journey.</p>
            <a class="button button-primary" href="/QuizWeb/login.php">Go to Login</a>
        </section>
    <?php else: ?>
    <form method="post" class="signup-card" id="signup-form">
        <input type="hidden" name="csrf" value="<?php echo esc($_SESSION['signup_csrf']); ?>">
        <div class="signup-card-top"><span class="signup-mini-brand"><span>CH</span> CHALK</span><small data-step-counter aria-live="polite">Step 1 of 4</small></div>
        <ol class="signup-progress" aria-label="Registration progress">
            <?php foreach (['Account', 'Personal Info', 'Academic Info', 'Review'] as $index => $label): ?><li data-progress-step="<?php echo $index; ?>"><span><?php echo $index + 1; ?></span><?php echo esc($label); ?></li><?php endforeach; ?>
        </ol>
        <?php if ($errors): ?><div class="inline-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo esc($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <section data-signup-step="0" class="signup-step">
            <h1 tabindex="-1">Create your account</h1>
            <label><span>Full Name</span><input name="name" autocomplete="name" maxlength="150" required value="<?php echo esc($name); ?>" placeholder="Enter your full name"></label>
            <label><span>Email Address</span><input type="email" name="email" autocomplete="email" maxlength="190" required value="<?php echo esc($email); ?>" placeholder="Enter your email address"></label>
            <label><span>Password</span><span class="signup-password"><input type="password" name="password" autocomplete="new-password" minlength="8" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}" required placeholder="Create a password" aria-describedby="password-guidance"><button type="button" data-password-toggle aria-label="Show password" aria-pressed="false">Show</button></span></label>
            <label><span>Confirm Password</span><input type="password" name="confirm_password" autocomplete="new-password" required placeholder="Repeat your password"></label>
            <small id="password-guidance">8+ characters, including a letter and a number.</small>
            <label><span>I am joining as</span><select name="role"><option value="student"<?php echo $role === 'student' ? ' selected' : ''; ?>>Student</option><option value="teacher"<?php echo $role === 'teacher' ? ' selected' : ''; ?>>Teacher</option></select></label>
        </section>
        <section data-signup-step="1" class="signup-step">
            <h1 tabindex="-1">Personal information</h1>
            <div class="signup-field-grid"><?php render_profile_fields($profile, ['student_number', 'birthdate'], $role); ?></div>
            <?php render_profile_fields($profile, ['gender'], $role); ?>
        </section>
        <section data-signup-step="2" class="signup-step">
            <h1 tabindex="-1">Academic information</h1>
            <?php render_profile_fields($profile, ['program', 'year_level'], $role); ?>
        </section>
        <section data-signup-step="3" class="signup-step">
            <h1 tabindex="-1">Review your information</h1>
            <dl class="signup-review" data-signup-review></dl>
        </section>
        <div class="signup-actions"><button class="button button-secondary" type="button" data-signup-back hidden>&lsaquo; Back</button><button class="button button-primary" type="button" data-signup-next hidden>Next &rsaquo;</button><button class="button button-primary" type="submit" data-signup-submit>Create Account</button></div>
        <p class="signup-login">Already have an account? <a href="/QuizWeb/login.php">Sign in</a></p>
    </form>
    <?php endif; ?>
</section>
<?php render_footer(['/QuizWeb/assets/js/signup.js']); ?>
