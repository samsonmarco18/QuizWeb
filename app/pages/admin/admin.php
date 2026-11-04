<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/ai_quiz.php';
require_once dirname(__DIR__, 3) . '/includes/mailer_settings.php';
require_once dirname(__DIR__, 3) . '/includes/google_oauth.php';
$user = require_role('admin');
$views = ['overview' => 'Overview', 'users' => 'Users', 'classes' => 'Classes', 'ai' => 'AI settings', 'google_auth' => 'Google sign-in', 'mailer' => 'Mailer', 'security' => 'Security', 'logs' => 'Audit logs', 'grade_logs' => 'Grade audit', 'releases' => 'Grade releases'];
$view = is_string($_GET['tab'] ?? null) && isset($views[$_GET['tab']]) ? $_GET['tab'] : 'overview';
$_SESSION['admin_csrf'] ??= bin2hex(random_bytes(32));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminAction = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (str_starts_with($adminAction, 'ai_')) {
        $view = 'ai';
        try {
            ai_settings_csrf($_POST['csrf'] ?? null, $_SESSION['admin_csrf']);
            if (in_array($adminAction, ['ai_test_connection', 'ai_test_generation'], true)) {
                $lastTest = (int) ($_SESSION['ai_admin_last_test'] ?? 0);
                if (time() - $lastTest < 30) throw new InvalidArgumentException('Wait 30 seconds between AI tests.');
                $_SESSION['ai_admin_last_test'] = time();
                set_time_limit($adminAction === 'ai_test_generation' ? AI_QUIZ_EXECUTION_TIMEOUT_SECONDS : 30);
                // Persist the cooldown and let other tabs run during provider I/O.
                session_write_close();
                try {
                    if ($adminAction === 'ai_test_generation') {
                        $testedModel = ai_settings_test_generation(db(), (int) $user['id']);
                    } else {
                        ai_settings_test(db(), (int) $user['id']);
                    }
                } finally {
                    // Reload concurrent session changes before writing the flash.
                    session_start();
                }
                if ($adminAction === 'ai_test_generation') {
                    flash_set('success', 'Quiz generation succeeded: 3 sample questions passed validation using ' . $testedModel . '. No quiz was saved.');
                } else {
                    flash_set('success', 'Connected to Gemini. The saved key can access the configured model. Quiz generation has not been tested.');
                }
            } elseif (in_array($adminAction, ['ai_save_settings', 'ai_remove_key'], true)) {
                ai_settings_save(db(), (int) $user['id'], $_POST, $adminAction === 'ai_remove_key' ? 'remove' : 'save');
                flash_set('success', $adminAction === 'ai_remove_key' ? 'API key removed and AI generation disabled.' : 'AI settings saved.');
            } else {
                throw new InvalidArgumentException('Choose a valid AI settings action.');
            }
            redirect('/QuizWeb/admin.php?tab=ai');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            $error = $exception instanceof PDOException ? 'Could not save AI settings. Please retry.' : $exception->getMessage();
        } catch (Throwable $exception) {
            $error = 'Could not update AI settings. Please retry.';
        }
        // Never echo an entered key back into a failed form or flash message.
        unset($_POST['api_key']);
    } elseif (str_starts_with($adminAction, 'google_auth_')) {
        $view = 'google_auth';
        try {
            ai_settings_csrf($_POST['csrf'] ?? null, $_SESSION['admin_csrf']);
            google_oauth_settings_save(db(), (int) $user['id'], $_POST, $adminAction === 'google_auth_remove');
            flash_set('success', $adminAction === 'google_auth_remove' ? 'Google sign-in credentials removed and disabled.' : 'Google sign-in settings saved securely.');
            redirect('/QuizWeb/admin.php?tab=google_auth');
        } catch (InvalidArgumentException | RuntimeException $exception) { $error = $exception->getMessage(); }
        catch (Throwable $exception) { error_log('Google OAuth admin settings failed: ' . $exception->getMessage()); $error = 'Could not save Google sign-in settings.'; }
        unset($_POST['client_secret']);
    } elseif (str_starts_with($adminAction, 'mailer_')) {
        $view = 'mailer';
        try {
            ai_settings_csrf($_POST['csrf'] ?? null, $_SESSION['admin_csrf']);
            mailer_settings_save(db(), (int) $user['id'], $_POST, $adminAction === 'mailer_remove');
            flash_set('success', $adminAction === 'mailer_remove' ? 'Mailer credentials removed and delivery disabled.' : 'Mailer settings saved securely.');
            redirect('/QuizWeb/admin.php?tab=mailer');
        } catch (InvalidArgumentException | RuntimeException $exception) { $error = $exception->getMessage(); }
        catch (Throwable $exception) { error_log('Mailer settings failed: ' . $exception->getMessage()); $error = 'Could not save mailer settings.'; }
        foreach (['smtp_password', 'http_token', 'google_client_secret', 'google_refresh_token'] as $secretField) unset($_POST[$secretField]);
    } else {
    $view = 'users';
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['admin_csrf'], $_POST['csrf'])) {
        $error = 'Your form expired. Reload and try again.';
    } elseif (($adminAction === 'account_status' || $adminAction === 'account_delete') && ($_POST['confirm'] ?? '') !== 'yes') {
        $error = 'Confirm this account action.';
    } else {
        try {
            $targetId = (int) ($_POST['user_id'] ?? 0);
            if ($adminAction === 'account_status') admin_set_account_status(db(), (int) $user['id'], $targetId, is_string($_POST['status'] ?? null) ? $_POST['status'] : '');
            elseif ($adminAction === 'account_edit') admin_update_account(db(), (int) $user['id'], $targetId, (string) ($_POST['name'] ?? ''), (string) ($_POST['email'] ?? ''), (string) ($_POST['role'] ?? ''));
            elseif ($adminAction === 'account_delete') admin_delete_account(db(), (int) $user['id'], $targetId);
            else throw new InvalidArgumentException('Choose a valid account action.');
            flash_set('success', $adminAction === 'account_delete' ? 'Account deleted.' : ($adminAction === 'account_edit' ? 'Account updated.' : 'Account status updated.'));
            redirect('/QuizWeb/admin.php?tab=users');
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Admin account action failed: ' . $exception->getMessage());
            $error = 'Could not update this account. Please retry.';
        }
    }
    }
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$query = is_string($_GET['q'] ?? null) ? substr(trim($_GET['q']), 0, 150) : '';
$role = in_array($_GET['role'] ?? '', ['student', 'teacher', 'admin'], true) ? $_GET['role'] : '';
$records = []; $total = 0; $limit = 15;
if ($view === 'users') {
    $conditions = ['(name ILIKE ? OR email ILIKE ?)']; $params = ['%' . $query . '%', '%' . $query . '%'];
    if ($role) { $conditions[] = 'role = ?'; $params[] = $role; }
    $where = implode(' AND ', $conditions);
    $count = db()->prepare('SELECT COUNT(*) FROM users WHERE ' . $where); $count->execute($params); $total = (int) $count->fetchColumn();
    $page = min($page, max(1, (int) ceil($total / $limit)));
    $select = db()->prepare('SELECT id, name, email, role, account_status, created_at FROM users WHERE ' . $where . ' ORDER BY id DESC LIMIT 15 OFFSET ' . (($page - 1) * $limit));
    $select->execute($params); $records = $select->fetchAll();
} elseif (in_array($view, ['security', 'logs', 'classes', 'grade_logs', 'releases'], true)) {
    $table = $view === 'classes' ? 'classrooms' : (in_array($view, ['grade_logs', 'releases'], true) ? 'grade_changes' : 'audit_logs');
    $where = $view === 'security' ? " WHERE action = 'login_failed'" : ($view === 'releases' ? " WHERE action = 'publish'" : '');
    $total = (int) db()->query('SELECT COUNT(*) FROM ' . $table . $where)->fetchColumn();
    $page = min($page, max(1, (int) ceil($total / $limit)));
    $fields = $view === 'classes' ? 'id, name, subject, teacher_id, created_at' : (in_array($view, ['grade_logs', 'releases'], true) ? '*' : 'id, actor_id, target_id, action, occurred_at');
    if (in_array($view, ['grade_logs', 'releases'], true)) {
        $records = db()->query('SELECT gc.*, c.name AS classroom_name, actor.name AS actor_name, student.name AS student_name FROM grade_changes gc JOIN classrooms c ON c.id = gc.classroom_id JOIN users actor ON actor.id = gc.actor_id LEFT JOIN users student ON student.id = gc.student_id' . $where . ' ORDER BY gc.id DESC LIMIT 15 OFFSET ' . (($page - 1) * $limit))->fetchAll();
    } else {
        $records = db()->query('SELECT ' . $fields . ' FROM ' . $table . $where . ' ORDER BY id DESC LIMIT 15 OFFSET ' . (($page - 1) * $limit))->fetchAll();
    }
}
$mailerSettings = $view === 'mailer' ? mailer_settings_read(db()) : [];
$googleSettings = $view === 'google_auth' ? (google_oauth_settings_read(db()) ?? []) : [];
$googleConfig = $view === 'google_auth' ? google_oauth_config() : [];
header('Cache-Control: no-store');
render_header('Administration', 'admin-page', ['/QuizWeb/assets/css/admin-ai.css', '/QuizWeb/assets/css/admin.css']);
?>
<header class="page-heading"><div><span class="eyebrow">System administration</span><h1><?php echo esc($views[$view]); ?></h1><p>Manage accounts and review system activity.</p></div><a class="button button-secondary" href="/QuizWeb/admin.php">Back to Overview</a></header>
<nav class="classroom-tabs" aria-label="Administration"><?php foreach ($views as $key => $label): ?><a href="/QuizWeb/admin.php?tab=<?php echo esc($key); ?>" <?php echo $view === $key ? 'class="is-active" aria-current="page"' : ''; ?>><?php echo esc($label); ?></a><?php endforeach; ?></nav>
<?php if ($error): ?><p class="inline-error" role="alert"><?php echo esc($error); ?></p><?php endif; ?>
<section class="glass panel">
<?php if ($view === 'overview'): ?>
    <div class="learning-summary-grid"><?php foreach (['users' => 'Accounts', 'classrooms' => 'Classes', 'attempts' => 'Student attempts'] as $table => $label): ?><article class="stat-card"><strong><?php echo (int) db()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(); ?></strong><span><?php echo esc($label); ?></span></article><?php endforeach; ?></div>
    <div class="action-row"><a class="button button-primary" href="/QuizWeb/admin.php?tab=users">Manage users</a><a class="button button-secondary" href="/QuizWeb/admin.php?tab=ai">Configure AI quizzes</a><a class="button button-secondary" href="/QuizWeb/admin.php?tab=security">Review failed sign-ins</a></div>
<?php elseif ($view === 'ai'): ?>
    <?php require dirname(__DIR__, 3) . '/includes/admin_ai_view.php'; ?>
<?php elseif ($view === 'google_auth'): ?>
    <div class="section-heading"><div><span class="eyebrow">Account authentication</span><h2>Google sign-in configuration</h2><p>Connect a Google Cloud OAuth web client. First-time users will still complete CHALK's required registration information.</p></div><span class="status-chip <?php echo !empty($googleConfig['enabled']) ? 'is-active' : ''; ?>"><?php echo !empty($googleConfig['enabled']) ? 'Enabled' : 'Disabled / setup needed'; ?></span></div>
    <div class="admin-setup-steps"><h3>Google Cloud setup</h3><ol><li>Create or select a Google Cloud project.</li><li>Configure its OAuth consent screen.</li><li>Create an OAuth Client ID with application type <strong>Web application</strong>.</li><li>Add the exact redirect URI shown below to Authorized redirect URIs.</li><li>Paste the Client ID and Client Secret here, then enable Google sign-in.</li></ol></div>
    <form method="post" class="stack-form admin-google-auth-form"><input type="hidden" name="csrf" value="<?php echo esc($_SESSION['admin_csrf']); ?>"><input type="hidden" name="action" value="google_auth_save">
        <label class="grade-confirm"><input type="checkbox" name="enabled" value="yes" <?php echo !empty($googleSettings['enabled']) ? 'checked' : ''; ?>><span>Enable “Sign in with Google” on login and registration</span></label>
        <label><span>OAuth Client ID</span><input name="client_id" maxlength="500" required value="<?php echo esc($_POST['client_id'] ?? $googleSettings['client_id'] ?? ''); ?>" placeholder="123456789-example.apps.googleusercontent.com"></label>
        <label><span>OAuth Client Secret</span><input type="password" name="client_secret" autocomplete="new-password" value="" placeholder="<?php echo !empty($googleSettings['encrypted_client_secret']) ? 'Saved — leave blank to keep it' : 'Paste the client secret'; ?>"><small>The secret is encrypted and will never be displayed again.</small></label>
        <label><span>Authorized redirect URI</span><input type="url" name="redirect_uri" required value="<?php echo esc($_POST['redirect_uri'] ?? $googleSettings['redirect_uri'] ?? 'http://localhost/QuizWeb/google_auth.php'); ?>"><small>This must exactly match Google Cloud, including scheme, host, port, path, and trailing slash.</small></label>
        <div class="action-row"><button class="button button-primary" type="submit">Save Google sign-in settings</button><a class="button button-secondary" href="https://console.cloud.google.com/auth/clients" target="_blank" rel="noopener noreferrer">Open Google Cloud OAuth clients</a></div>
    </form>
    <?php if (!empty($googleSettings['encrypted_client_secret'])): ?><form method="post" data-confirm="Remove the saved Google OAuth credentials and disable Google sign-in?"><input type="hidden" name="csrf" value="<?php echo esc($_SESSION['admin_csrf']); ?>"><input type="hidden" name="action" value="google_auth_remove"><button class="button button-danger" type="submit">Remove Google credentials</button></form><?php endif; ?>
<?php elseif ($view === 'mailer'): ?>
    <div class="section-heading"><div><span class="eyebrow">Automatic email delivery</span><h2>Mailer configuration</h2><p>Credentials are encrypted and never displayed after saving.</p></div><span class="status-chip <?php echo !empty($mailerSettings['enabled']) ? 'is-active' : ''; ?>"><?php echo !empty($mailerSettings['enabled']) ? 'Enabled' : 'Disabled'; ?></span></div>
    <form method="post" class="stack-form admin-mailer-form">
        <input type="hidden" name="csrf" value="<?php echo esc($_SESSION['admin_csrf']); ?>"><input type="hidden" name="action" value="mailer_save">
        <label class="grade-confirm"><input type="checkbox" name="enabled" value="yes" <?php echo !empty($mailerSettings['enabled']) ? 'checked' : ''; ?>><span>Enable automatic mail delivery</span></label>
        <div class="admin-form-grid"><label><span>Provider</span><select name="provider" data-mailer-provider><?php foreach (['smtp' => 'SMTP server', 'http_api' => 'HTTPS mail API', 'google_oauth' => 'Google OAuth 2.0'] as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo ($mailerSettings['provider'] ?? 'smtp') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label><label><span>Sender name</span><input name="from_name" maxlength="150" value="<?php echo esc($mailerSettings['from_name'] ?? 'CHALK'); ?>"></label><label><span>Sender email</span><input type="email" name="from_email" required value="<?php echo esc($mailerSettings['from_email'] ?? ''); ?>"></label></div>
        <fieldset data-mailer-fields="smtp"><legend>SMTP connection</legend><div class="admin-form-grid"><label><span>SMTP host</span><input name="smtp_host" value="<?php echo esc($mailerSettings['smtp_host'] ?? ''); ?>" placeholder="smtp.example.com"></label><label><span>Port</span><input type="number" name="smtp_port" min="1" max="65535" value="<?php echo (int) ($mailerSettings['smtp_port'] ?? 587); ?>"></label><label><span>Security</span><select name="smtp_security"><?php foreach (['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'None'] as $key => $label): ?><option value="<?php echo $key; ?>" <?php echo ($mailerSettings['smtp_security'] ?? 'tls') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label><label><span>Username</span><input name="smtp_username" value="<?php echo esc($mailerSettings['smtp_username'] ?? ''); ?>"></label><label><span>Password / app password</span><input type="password" name="smtp_password" autocomplete="new-password" placeholder="<?php echo !empty($mailerSettings['encrypted_secrets']) ? 'Saved — enter to replace' : 'Required to enable'; ?>"></label></div></fieldset>
        <fieldset data-mailer-fields="http_api"><legend>HTTPS mail API</legend><div class="admin-form-grid"><label><span>API endpoint</span><input type="url" name="http_endpoint" value="<?php echo esc($mailerSettings['http_endpoint'] ?? ''); ?>" placeholder="https://api.provider.com/send"></label><label><span>Bearer / API token</span><input type="password" name="http_token" autocomplete="new-password" placeholder="<?php echo !empty($mailerSettings['encrypted_secrets']) ? 'Saved — enter to replace' : 'Required to enable'; ?>"></label></div></fieldset>
        <fieldset data-mailer-fields="google_oauth"><legend>Google OAuth 2.0</legend><p>Use a Google Cloud OAuth client with Gmail send permission and an authorized refresh token.</p><div class="admin-form-grid"><label><span>Google mailbox</span><input type="email" name="google_user" value="<?php echo esc($mailerSettings['google_user'] ?? ''); ?>"></label><label><span>Client ID</span><input name="google_client_id" value="<?php echo esc($mailerSettings['google_client_id'] ?? ''); ?>"></label><label><span>Client secret</span><input type="password" name="google_client_secret" autocomplete="new-password" placeholder="<?php echo !empty($mailerSettings['encrypted_secrets']) ? 'Saved — enter to replace' : 'Required'; ?>"></label><label><span>OAuth refresh token</span><input type="password" name="google_refresh_token" autocomplete="new-password" placeholder="<?php echo !empty($mailerSettings['encrypted_secrets']) ? 'Saved — enter to replace' : 'Required'; ?>"></label></div></fieldset>
        <div class="action-row"><button class="button button-primary" type="submit">Save mailer settings</button></div>
    </form>
    <?php if (!empty($mailerSettings['encrypted_secrets'])): ?><form method="post" class="action-row" data-confirm="Remove saved mail credentials and disable delivery?"><input type="hidden" name="csrf" value="<?php echo esc($_SESSION['admin_csrf']); ?>"><input type="hidden" name="action" value="mailer_remove"><button class="button button-secondary">Remove saved credentials</button></form><?php endif; ?>
<?php else: ?>
    <?php if ($view === 'users'): ?>
    <form method="get" class="action-row"><input type="hidden" name="tab" value="users"><label><span>Search name or email</span><input type="search" name="q" value="<?php echo esc($query); ?>"></label><label><span>Role</span><select name="role"><option value="">All roles</option><?php foreach (['student', 'teacher', 'admin'] as $filter): ?><option value="<?php echo esc($filter); ?>" <?php echo $role === $filter ? 'selected' : ''; ?>><?php echo esc(ucfirst($filter)); ?></option><?php endforeach; ?></select></label><button class="button button-primary">Search</button></form>
    <?php endif; ?>
    <?php if ($view === 'security'): ?><p>Failed sign-ins recorded since security logging was enabled. No passwords, tokens, or attempted credentials are stored here.</p><?php endif; ?>
    <?php if ($view === 'users'): ?>
    <div class="admin-table-wrap"><table class="admin-data-table"><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Role</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($records as $record): ?><tr><td><strong><?php echo esc($record['name']); ?></strong><small>Joined <?php echo esc(format_date($record['created_at'])); ?></small></td><td><?php echo esc($record['email']); ?></td><td><span class="status-chip <?php echo $record['account_status'] === 'active' ? 'is-active' : 'is-suspended'; ?>"><?php echo esc(ucfirst($record['account_status'])); ?></span></td><td><?php echo esc(ucfirst($record['role'])); ?></td><td><div class="admin-row-actions">
    <?php if ($record['role'] !== 'admin'): $nextStatus = $record['account_status'] === 'active' ? 'suspended' : 'active'; ?>
    <form method="post" data-account-action><input type="hidden" name="csrf" value="<?php echo esc($_SESSION['admin_csrf']); ?>"><input type="hidden" name="action" value="account_status"><input type="hidden" name="user_id" value="<?php echo (int) $record['id']; ?>"><input type="hidden" name="status" value="<?php echo esc($nextStatus); ?>"><input type="hidden" name="confirm" value="yes"><button class="button button-secondary"><?php echo $nextStatus === 'suspended' ? 'Suspend' : 'Reactivate'; ?></button></form>
    <details><summary class="button button-secondary">Edit</summary><form method="post" class="admin-popover-form"><input type="hidden" name="csrf" value="<?php echo esc($_SESSION['admin_csrf']); ?>"><input type="hidden" name="action" value="account_edit"><input type="hidden" name="user_id" value="<?php echo (int) $record['id']; ?>"><label><span>Name</span><input name="name" maxlength="150" required value="<?php echo esc($record['name']); ?>"></label><label><span>Email</span><input type="email" name="email" maxlength="190" required value="<?php echo esc($record['email']); ?>"></label><label><span>Role</span><select name="role"><option value="student" <?php echo $record['role'] === 'student' ? 'selected' : ''; ?>>Student</option><option value="teacher" <?php echo $record['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option></select></label><button class="button button-primary">Save</button></form></details>
    <form method="post" data-confirm="Permanently delete this account? Accounts with retained academic records cannot be deleted."><input type="hidden" name="csrf" value="<?php echo esc($_SESSION['admin_csrf']); ?>"><input type="hidden" name="action" value="account_delete"><input type="hidden" name="user_id" value="<?php echo (int) $record['id']; ?>"><input type="hidden" name="confirm" value="yes"><button class="button button-danger">Delete</button></form>
    <?php else: ?><span>Protected</span><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div>
    <?php elseif (in_array($view, ['grade_logs', 'releases'], true)): ?>
    <div class="admin-table-wrap"><table class="admin-data-table grade-audit-table"><thead><tr><th>Event</th><th>Class</th><th>Teacher</th><th>Student</th><th>Item</th><th>Previous information</th><th>New information</th><th>Date</th></tr></thead><tbody><?php foreach ($records as $record): ?><tr><td><?php echo esc(ucwords(str_replace('_', ' ', $record['action']))); ?></td><td><?php echo esc($record['classroom_name']); ?></td><td><?php echo esc($record['actor_name']); ?></td><td><?php echo esc($record['student_name'] ?? 'Whole class'); ?></td><td><?php echo esc($record['item_id'] ?: 'Class grading'); ?></td><td><?php echo esc(admin_grade_value_summary($record['previous_value'])); ?></td><td><?php echo esc(admin_grade_value_summary($record['new_value'])); ?></td><td><?php echo esc(format_date($record['created_at'])); ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><div class="admin-records"><?php foreach ($records as $record): ?><article class="recent-item">
        <?php if ($view === 'classes'): ?>
            <strong><?php echo esc($record['name']); ?></strong><span><?php echo esc($record['subject']); ?></span><small>Teacher #<?php echo (int) $record['teacher_id']; ?> · <?php echo esc(format_date($record['created_at'])); ?></small>
        <?php else: ?>
            <strong><?php echo esc(ucwords(str_replace('_', ' ', $record['action']))); ?></strong><span><?php echo esc(format_date($record['occurred_at'])); ?></span><small>Actor <?php echo $record['actor_id'] === null ? 'Anonymous' : '#' . (int) $record['actor_id']; ?> · Target <?php echo $record['target_id'] === null ? 'Unknown account' : '#' . (int) $record['target_id']; ?></small>
        <?php endif; ?>
        </article>
    <?php endforeach; ?></div><?php endif; ?>
    <?php if (!$records): ?><div class="empty-state"><h2><?php echo $query ? 'No matching accounts' : 'No records yet'; ?></h2><p><?php echo $query ? 'Try another name or email.' : 'New activity will appear here.'; ?></p></div><?php endif; ?>
    <nav class="action-row" aria-label="Pagination"><span>Page <?php echo $page; ?> of <?php echo max(1, (int) ceil($total / $limit)); ?> · <?php echo $total; ?> records</span><?php foreach ([-1 => 'Previous', 1 => 'Next'] as $delta => $label): $targetPage = $page + $delta; if ($targetPage < 1 || $targetPage > ceil($total / $limit)) continue; ?><a class="button button-secondary" href="/QuizWeb/admin.php?<?php echo esc(http_build_query(['tab' => $view, 'page' => $targetPage, 'q' => $query, 'role' => $role])); ?>"><?php echo esc($label); ?></a><?php endforeach; ?></nav>
<?php endif; ?>
</section>
<?php render_footer(['/QuizWeb/assets/js/admin-ai.js']); ?>
