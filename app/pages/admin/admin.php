<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
$user = require_role('admin');
$views = ['overview' => 'Overview', 'users' => 'Users', 'classes' => 'Classes', 'security' => 'Security', 'logs' => 'Audit logs', 'grade_logs' => 'Grade audit'];
$view = is_string($_GET['tab'] ?? null) && isset($views[$_GET['tab']]) ? $_GET['tab'] : 'overview';
$_SESSION['admin_csrf'] ??= bin2hex(random_bytes(32));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $view = 'users';
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['admin_csrf'], $_POST['csrf'])) {
        $error = 'Your form expired. Reload and try again.';
    } elseif (($_POST['confirm'] ?? '') !== 'yes') {
        $error = 'Confirm the account status change.';
    } else {
        try {
            admin_set_account_status(db(), (int) $user['id'], (int) ($_POST['user_id'] ?? 0), is_string($_POST['status'] ?? null) ? $_POST['status'] : '');
            flash_set('success', 'Account status updated.');
            redirect('/QuizWeb/admin.php?tab=users');
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Admin account action failed: ' . $exception->getMessage());
            $error = 'Could not update this account. Please retry.';
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
} elseif (in_array($view, ['security', 'logs', 'classes', 'grade_logs'], true)) {
    $table = $view === 'classes' ? 'classrooms' : ($view === 'grade_logs' ? 'grade_changes' : 'audit_logs');
    $where = $view === 'security' ? " WHERE action = 'login_failed'" : '';
    $total = (int) db()->query('SELECT COUNT(*) FROM ' . $table . $where)->fetchColumn();
    $page = min($page, max(1, (int) ceil($total / $limit)));
    $fields = $view === 'classes' ? 'id, name, subject, teacher_id, created_at' : ($view === 'grade_logs' ? '*' : 'id, actor_id, target_id, action, occurred_at');
    $records = db()->query('SELECT ' . $fields . ' FROM ' . $table . $where . ' ORDER BY id DESC LIMIT 15 OFFSET ' . (($page - 1) * $limit))->fetchAll();
}
render_header('Administration', 'admin-page');
?>
<header class="page-heading"><div><span class="eyebrow">System administration</span><h1><?php echo esc($views[$view]); ?></h1><p>Manage accounts and review system activity.</p></div><a class="button button-secondary" href="/QuizWeb/admin.php">Back to Overview</a></header>
<nav class="classroom-tabs" aria-label="Administration"><?php foreach ($views as $key => $label): ?><a href="/QuizWeb/admin.php?tab=<?php echo esc($key); ?>" <?php echo $view === $key ? 'class="is-active" aria-current="page"' : ''; ?>><?php echo esc($label); ?></a><?php endforeach; ?></nav>
<?php if ($error): ?><p class="inline-error" role="alert"><?php echo esc($error); ?></p><?php endif; ?>
<section class="glass panel">
<?php if ($view === 'overview'): ?>
    <div class="learning-summary-grid"><?php foreach (['users' => 'Accounts', 'classrooms' => 'Classes', 'attempts' => 'Student attempts'] as $table => $label): ?><article class="stat-card"><strong><?php echo (int) db()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(); ?></strong><span><?php echo esc($label); ?></span></article><?php endforeach; ?></div>
    <div class="action-row"><a class="button button-primary" href="/QuizWeb/admin.php?tab=users">Manage users</a><a class="button button-secondary" href="/QuizWeb/admin.php?tab=security">Review failed sign-ins</a></div>
<?php else: ?>
    <?php if ($view === 'users'): ?>
    <form method="get" class="action-row"><input type="hidden" name="tab" value="users"><label><span>Search name or email</span><input type="search" name="q" value="<?php echo esc($query); ?>"></label><label><span>Role</span><select name="role"><option value="">All roles</option><?php foreach (['student', 'teacher', 'admin'] as $filter): ?><option value="<?php echo esc($filter); ?>" <?php echo $role === $filter ? 'selected' : ''; ?>><?php echo esc(ucfirst($filter)); ?></option><?php endforeach; ?></select></label><button class="button button-primary">Search</button></form>
    <?php endif; ?>
    <?php if ($view === 'security'): ?><p>Failed sign-ins recorded since security logging was enabled. No passwords, tokens, or attempted credentials are stored here.</p><?php endif; ?>
    <div class="admin-records">
    <?php foreach ($records as $record): ?>
        <article class="recent-item">
        <?php if ($view === 'users'): ?>
            <div><strong><?php echo esc($record['name']); ?></strong><span><?php echo esc($record['email']); ?></span><small><?php echo esc(ucfirst($record['role']) . ' · ' . ucfirst($record['account_status'])); ?></small></div>
            <details><summary>View account / Manage</summary><p>Account #<?php echo (int) $record['id']; ?> · Joined <?php echo esc(format_date($record['created_at'])); ?></p>
            <?php if ($record['role'] !== 'admin'): $nextStatus = $record['account_status'] === 'active' ? 'suspended' : 'active'; ?>
            <form method="post" class="stack-form" data-account-action>
                <input type="hidden" name="csrf" value="<?php echo esc($_SESSION['admin_csrf']); ?>"><input type="hidden" name="user_id" value="<?php echo (int) $record['id']; ?>"><input type="hidden" name="status" value="<?php echo esc($nextStatus); ?>">
                <p><?php echo $nextStatus === 'suspended' ? 'Suspending blocks sign-in and access from existing sessions. Classroom work and results remain stored.' : 'Reactivating restores sign-in and access to existing work.'; ?></p>
                <label><input type="checkbox" name="confirm" value="yes" required> Confirm this account change</label>
                <button class="button button-secondary" type="submit"><?php echo $nextStatus === 'suspended' ? 'Suspend account' : 'Reactivate account'; ?></button>
            </form><?php else: ?><p>Administrator accounts are protected from status changes.</p><?php endif; ?></details>
        <?php elseif ($view === 'grade_logs'): ?>
            <details><summary><?php echo esc(ucwords(str_replace('_', ' ', $record['action'])) . ' · ' . format_date($record['created_at'])); ?></summary><p>Class #<?php echo (int) $record['classroom_id']; ?> · Teacher #<?php echo (int) $record['actor_id']; ?> · Student <?php echo $record['student_id'] === null ? '—' : '#' . (int) $record['student_id']; ?> · Item <?php echo esc($record['item_id'] ?? 'Class grading'); ?></p><pre class="grade-audit-data"><?php echo esc('Previous: ' . $record['previous_value'] . "\nNew: " . $record['new_value']); ?></pre><p>Read-only audit. Academic changes belong to the classroom teacher.</p></details>
        <?php elseif ($view === 'classes'): ?>
            <strong><?php echo esc($record['name']); ?></strong><span><?php echo esc($record['subject']); ?></span><small>Teacher #<?php echo (int) $record['teacher_id']; ?> · <?php echo esc(format_date($record['created_at'])); ?></small>
        <?php else: ?>
            <strong><?php echo esc(ucwords(str_replace('_', ' ', $record['action']))); ?></strong><span><?php echo esc(format_date($record['occurred_at'])); ?></span><small>Actor <?php echo $record['actor_id'] === null ? 'Anonymous' : '#' . (int) $record['actor_id']; ?> · Target <?php echo $record['target_id'] === null ? 'Unknown account' : '#' . (int) $record['target_id']; ?></small>
        <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if (!$records): ?><div class="empty-state"><h2><?php echo $query ? 'No matching accounts' : 'No records yet'; ?></h2><p><?php echo $query ? 'Try another name or email.' : 'New activity will appear here.'; ?></p></div><?php endif; ?>
    </div>
    <nav class="action-row" aria-label="Pagination"><span>Page <?php echo $page; ?> of <?php echo max(1, (int) ceil($total / $limit)); ?> · <?php echo $total; ?> records</span><?php foreach ([-1 => 'Previous', 1 => 'Next'] as $delta => $label): $targetPage = $page + $delta; if ($targetPage < 1 || $targetPage > ceil($total / $limit)) continue; ?><a class="button button-secondary" href="/QuizWeb/admin.php?<?php echo esc(http_build_query(['tab' => $view, 'page' => $targetPage, 'q' => $query, 'role' => $role])); ?>"><?php echo esc($label); ?></a><?php endforeach; ?></nav>
<?php endif; ?>
</section>
<?php render_footer(); ?>
