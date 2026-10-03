<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
// SQLite tests atomic writes; production PostgreSQL locking needs live QA.
class AdminTestPDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
function assert_admin(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$pdo = new AdminTestPDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT, account_status TEXT); CREATE TABLE audit_logs (id INTEGER PRIMARY KEY, actor_id INTEGER, target_id INTEGER, action TEXT, occurred_at TEXT)");
$pdo->exec("INSERT INTO users VALUES (1, 'student', 'active'), (2, 'teacher', 'active'), (5, 'admin', 'active'), (6, 'admin', 'active')");
admin_set_account_status($pdo, 5, 1, 'suspended');
assert_admin($pdo->query('SELECT account_status FROM users WHERE id = 1')->fetchColumn() === 'suspended', 'Suspension persists.');
assert_admin($pdo->query('SELECT action FROM audit_logs')->fetchColumn() === 'account_suspended', 'Status changes are audited.');
admin_set_account_status($pdo, 5, 1, 'active');
foreach ([[5, 5, 'suspended'], [5, 6, 'suspended'], [2, 1, 'suspended'], [5, 900, 'suspended'], [5, 1, 'invalid']] as [$actor, $target, $status]) {
    try { admin_set_account_status($pdo, $actor, $target, $status); throw new RuntimeException('Forbidden account mutation succeeded.'); }
    catch (InvalidArgumentException $expected) {}
    assert_admin(!$pdo->inTransaction(), 'Rejected changes must close their transaction.');
}
assert_admin((int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === 2, 'Rejected actions cannot create successful audit entries.');
$pdo->exec("CREATE TRIGGER reject_log BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'Audit storage unavailable'); END");
try { admin_set_account_status($pdo, 5, 1, 'suspended'); throw new RuntimeException('Audit failure should abort the change.'); }
catch (PDOException $expected) {}
assert_admin($pdo->query('SELECT account_status FROM users WHERE id = 1')->fetchColumn() === 'active', 'Audit failure rolls back the account change.');
assert_admin(!$pdo->inTransaction(), 'Failed actions must close their transaction.');
echo "Admin suspension, reactivation, role enforcement, protected accounts, audit writes, and rollback checks passed (SQLite fixture).\n";
