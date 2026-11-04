<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/profile.php';
require_once dirname(__DIR__, 3) . '/includes/google_oauth.php';
if (is_logged_in()) redirect('/QuizWeb/dashboard.php');
$google = $_SESSION['google_onboarding'] ?? null;
if (!is_array($google) || (int) ($google['expires'] ?? 0) < time()) { unset($_SESSION['google_onboarding']); flash_set('warning', 'Google registration expired. Please sign in with Google again.'); redirect('/QuizWeb/login.php'); }
$errors = []; $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : 'student'; $profile = profile_input($_POST);
if ($role === 'teacher') { $profile['student_number'] = ''; $profile['year_level'] = ''; }
$firstName = is_string($_POST['first_name'] ?? null) ? trim($_POST['first_name']) : (string) ($google['given_name'] ?? '');
$surname = is_string($_POST['surname'] ?? null) ? trim($_POST['surname']) : (string) ($google['family_name'] ?? '');
$middleName = is_string($_POST['middle_name'] ?? null) ? trim($_POST['middle_name']) : '';
$name = implode(' ', array_filter([$firstName, $middleName, $surname], static fn($part) => $part !== ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_form_csrf();
    if ($firstName === '' || strlen($firstName) > 50) $errors[] = 'First name is required and must be up to 50 characters.';
    if ($surname === '' || strlen($surname) > 50) $errors[] = 'Surname is required and must be up to 50 characters.';
    if (strlen($middleName) > 50 || strlen($name) > 150) $errors[] = 'Enter a valid complete name.';
    if (!in_array($role, ['teacher', 'student'], true)) $errors[] = 'Choose a valid role.';
    $errors = array_merge($errors, profile_errors($profile, $role));
    if (!$errors && find_user_by_email((string) $google['email'])) $errors[] = 'An account already exists for this Google email. Return to sign in and try again.';
    $created = null;
    if (!$errors) try {
        $created = register_user($name, (string) $google['email'], bin2hex(random_bytes(32)), $role, array_merge($profile, ['first_name' => $firstName, 'middle_name' => $middleName, 'surname' => $surname]));
        google_oauth_link(db(), (int) $created['id'], (string) $google['subject']); unset($_SESSION['google_onboarding']); login_user($created); record_audit('google_account_created', (int) $created['id'], (int) $created['id']);
        flash_set('success', 'Your CHALK account is ready.'); redirect('/QuizWeb/dashboard.php');
    } catch (Throwable $exception) {
        if (is_array($created) && isset($created['id'])) { try { db()->prepare('DELETE FROM users WHERE id = ?')->execute([(int) $created['id']]); } catch (Throwable $cleanupError) { error_log('Google onboarding cleanup failed: ' . $cleanupError->getMessage()); } }
        error_log('Google onboarding failed: ' . $exception->getMessage()); $errors[] = 'Could not create your account. Please try again.';
    }
}
render_header('Complete Google Registration', 'auth-page signup-page');
?>
<section class="signup-layout"><aside class="signup-brand-panel"><a class="signup-brand" href="/QuizWeb/login.php"><span class="brand-badge brand-badge-logo"><img class="brand-logo" src="/QuizWeb/assets/images/chalklogo.png" alt=""></span><span class="brand-copy"><strong>CHALK</strong><small>Classroom quiz space</small></span></a></aside>
<form method="post" class="signup-card stack-form" data-google-onboarding><input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>"><div class="auth-form-heading"><h1>Complete your account</h1><p>Google verified <strong><?php echo esc($google['email']); ?></strong>. Add the required CHALK information.</p></div>
<?php if ($errors): ?><div class="inline-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo esc($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="signup-field-grid"><label><span>First Name</span><input name="first_name" maxlength="50" required value="<?php echo esc($firstName); ?>"></label><label><span>Surname</span><input name="surname" maxlength="50" required value="<?php echo esc($surname); ?>"></label></div><label><span>Middle Name (optional)</span><input name="middle_name" maxlength="50" value="<?php echo esc($middleName); ?>"></label>
<label><span>I am joining as</span><select name="role" data-google-role><option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student</option><option value="teacher" <?php echo $role === 'teacher' ? 'selected' : ''; ?>>Teacher</option></select></label>
<div class="signup-field-grid"><?php render_profile_fields($profile, ['student_number', 'birthdate', 'gender', 'program', 'year_level'], $role); ?></div>
<div class="signup-actions"><a class="button button-secondary" href="/QuizWeb/login.php">Cancel</a><button class="button button-primary" type="submit">Create account and continue</button></div></form></section>
<script>const googleRole=document.querySelector('[data-google-role]');const syncGoogleRole=()=>{const student=googleRole.value==='student';document.querySelectorAll('[data-student-required]').forEach(input=>{if(['student_number','year_level'].includes(input.name)){input.closest('label').hidden=!student;input.disabled=!student;input.required=student;}});};googleRole?.addEventListener('change',syncGoogleRole);syncGoogleRole();</script>
<?php render_footer(); ?>
