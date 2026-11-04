<?php

// Administrative records contain no credentials or session identifiers.
function ensure_admin_schema(PDO $pdo): void
{
    ensure_database_schema($pdo);
}

function record_audit(string $action, ?int $actorId = null, ?int $targetId = null): void
{
    $statement = db()->prepare('INSERT INTO audit_logs (actor_id, target_id, action, occurred_at) VALUES (?, ?, ?, ?)');
    $statement->execute([$actorId, $targetId, $action, now_iso()]);
}

function admin_set_account_status(PDO $pdo, int $actorId, int $targetId, string $status): void
{
    if (!in_array($status, ['active', 'suspended'], true) || $actorId === $targetId) {
        throw new InvalidArgumentException('Choose another account and a valid status.');
    }
    $pdo->beginTransaction();
    try {
        $actorQuery = $pdo->prepare('SELECT role, account_status FROM users WHERE id = ? FOR UPDATE');
        $actorQuery->execute([$actorId]);
        $actor = $actorQuery->fetch();
        if (!$actor || $actor['role'] !== 'admin' || $actor['account_status'] !== 'active') {
            throw new InvalidArgumentException('Administrator access is required.');
        }
        $query = $pdo->prepare('SELECT role FROM users WHERE id = ? FOR UPDATE');
        $query->execute([$targetId]);
        $target = $query->fetch();
        if (!$target || $target['role'] === 'admin') {
            throw new InvalidArgumentException('Administrator accounts cannot be changed here.');
        }
        $update = $pdo->prepare('UPDATE users SET account_status = ? WHERE id = ?');
        $update->execute([$status, $targetId]);
        $audit = $pdo->prepare('INSERT INTO audit_logs (actor_id, target_id, action, occurred_at) VALUES (?, ?, ?, ?)');
        $audit->execute([$actorId, $targetId, 'account_' . $status, now_iso()]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function admin_update_account(PDO $pdo, int $actorId, int $targetId, string $name, string $email, string $role): void
{
    $name = trim($name); $email = strtolower(trim($email));
    if ($actorId === $targetId || $name === '' || strlen($name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || !in_array($role, ['student', 'teacher'], true)) {
        throw new InvalidArgumentException('Enter a valid name, email, and non-administrator role.');
    }
    $pdo->beginTransaction();
    try {
        $actor = $pdo->prepare('SELECT role, account_status FROM users WHERE id = ? FOR UPDATE'); $actor->execute([$actorId]);
        $target = $pdo->prepare('SELECT role FROM users WHERE id = ? FOR UPDATE'); $target->execute([$targetId]);
        $actorRow = $actor->fetch(); $targetRow = $target->fetch();
        if (!$actorRow || $actorRow['role'] !== 'admin' || $actorRow['account_status'] !== 'active' || !$targetRow || $targetRow['role'] === 'admin') throw new InvalidArgumentException('This account cannot be edited.');
        $duplicate = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id <> ?'); $duplicate->execute([$email, $targetId]);
        if ($duplicate->fetchColumn()) throw new InvalidArgumentException('Another account already uses that email address.');
        $update = $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?'); $update->execute([$name, $email, $role, $targetId]);
        $audit = $pdo->prepare('INSERT INTO audit_logs (actor_id, target_id, action, occurred_at) VALUES (?, ?, ?, ?)'); $audit->execute([$actorId, $targetId, 'account_edited', now_iso()]);
        $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

function admin_delete_account(PDO $pdo, int $actorId, int $targetId): void
{
    if ($actorId === $targetId) throw new InvalidArgumentException('You cannot delete your own account.');
    $pdo->beginTransaction();
    try {
        $actor = $pdo->prepare('SELECT role, account_status FROM users WHERE id = ? FOR UPDATE'); $actor->execute([$actorId]);
        $target = $pdo->prepare('SELECT role FROM users WHERE id = ? FOR UPDATE'); $target->execute([$targetId]);
        $actorRow = $actor->fetch(); $targetRow = $target->fetch();
        if (!$actorRow || $actorRow['role'] !== 'admin' || $actorRow['account_status'] !== 'active' || !$targetRow || $targetRow['role'] === 'admin') throw new InvalidArgumentException('This account cannot be deleted.');
        foreach ([['classrooms', 'teacher_id'], ['attempts', 'student_id'], ['grade_changes', 'student_id'], ['grade_changes', 'actor_id']] as [$table, $column]) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?"); $check->execute([$targetId]);
            if ((int) $check->fetchColumn() > 0) throw new InvalidArgumentException('This account has retained classroom or grading records. Suspend it instead of deleting it.');
        }
        $delete = $pdo->prepare('DELETE FROM users WHERE id = ?'); $delete->execute([$targetId]);
        if ($delete->rowCount() !== 1) throw new InvalidArgumentException('Account not found.');
        $audit = $pdo->prepare('INSERT INTO audit_logs (actor_id, target_id, action, occurred_at) VALUES (?, ?, ?, ?)'); $audit->execute([$actorId, null, 'account_deleted', now_iso()]);
        $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

function admin_grade_value_summary($raw): string
{
    if ($raw === null || $raw === '' || $raw === 'null') return 'None';
    $value = is_string($raw) ? json_decode($raw, true) : $raw;
    if (is_string($raw) && json_last_error() !== JSON_ERROR_NONE) return $raw;
    $parts = [];
    $walk = function ($item, string $prefix = '') use (&$walk, &$parts): void {
        if (is_array($item)) { foreach ($item as $key => $child) $walk($child, $prefix === '' ? (string) $key : $prefix . ' / ' . $key); return; }
        $label = ucwords(str_replace(['_', '-'], ' ', $prefix));
        $display = is_bool($item) ? ($item ? 'Yes' : 'No') : ($item === null ? 'None' : (string) $item);
        $parts[] = ($label !== '' ? $label . ': ' : '') . $display;
    };
    $walk($value);
    return $parts ? implode(' · ', array_slice($parts, 0, 12)) : 'None';
}
