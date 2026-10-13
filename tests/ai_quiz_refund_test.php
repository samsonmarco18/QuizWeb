<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/ai_quiz.php';

// Exercise transaction/state behavior without a running MySQL instance.
// Production MySQL/PostgreSQL row locks require separate live database QA.
class AiRefundTestPDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
function refund_check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function refund_limited(callable $call): void {
    try { $call(); } catch (RuntimeException $error) {
        refund_check($error->getCode() === 429, 'Expected cooldown or quota rejection'); return;
    }
    throw new RuntimeException('Expected rejection');
}
$pdo = new AiRefundTestPDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY); CREATE TABLE ai_quiz_usage (teacher_id INTEGER, usage_day TEXT, request_count INTEGER, last_requested_at INTEGER, PRIMARY KEY (teacher_id, usage_day))');
$pdo->exec('INSERT INTO users VALUES (1), (2)');
$now = strtotime('2026-10-13 23:59:20');
refund_check(ai_quiz_remaining($pdo, 1, $now) === 10, 'Unused teacher starts with 10 attempts');
refund_check((int) $pdo->query('SELECT COUNT(*) FROM ai_quiz_usage')->fetchColumn() === 0, 'Reading the allowance cannot consume or reserve an attempt');
refund_check(ai_quiz_reserve($pdo, 1, $now) === 9, 'Reserve request');
refund_check(ai_quiz_remaining($pdo, 1, $now) === 9, 'Read persisted remaining attempts after reservation');
refund_check(ai_quiz_refund_unavailable($pdo, 1, $now) === 10, 'Restore confirmed unavailable request');
refund_check(ai_quiz_remaining($pdo, 1, $now) === 10, 'Read restored attempts');
refund_limited(fn() => ai_quiz_reserve($pdo, 1, $now + 10));
refund_check(!$pdo->inTransaction(), 'Cooldown rejection closes transaction');
refund_check(ai_quiz_reserve($pdo, 1, $now + 30) === 9, 'Refunded slot is usable after cooldown');
// Another request may reserve a slot while an earlier generation is running.
refund_check(ai_quiz_reserve($pdo, 1, $now + 31 + 86400) === 9, 'Next day has separate quota');
refund_check(ai_quiz_reserve($pdo, 2, $now) === 9, 'Second teacher quota');
refund_check(ai_quiz_refund_unavailable($pdo, 1, $now + 30) === 10, 'Refund uses original Manila date across midnight');
$next = $pdo->query("SELECT request_count FROM ai_quiz_usage WHERE teacher_id = 1 AND usage_day = '2026-10-14'")->fetchColumn();
refund_check((int) $next === 1, 'Do not refund a new day reservation');
refund_check((int) $pdo->query('SELECT request_count FROM ai_quiz_usage WHERE teacher_id = 2')->fetchColumn() === 1, 'Other teacher is unaffected');
// Two reservations on a day: refund one without deleting the newer timestamp.
$dayTime = strtotime('2026-10-15 10:00:00');
ai_quiz_reserve($pdo, 1, $dayTime); ai_quiz_reserve($pdo, 1, $dayTime + 30);
refund_check(ai_quiz_refund_unavailable($pdo, 1, $dayTime) === 9, 'Only one of overlapping reservations restored');
refund_limited(fn() => ai_quiz_reserve($pdo, 1, $dayTime + 35));
for ($i = 2; $i <= 10; $i++) ai_quiz_reserve($pdo, 1, $dayTime + $i * 30);
refund_limited(fn() => ai_quiz_reserve($pdo, 1, $dayTime + 330));
refund_check(ai_quiz_remaining($pdo, 1, $dayTime) === 0, 'Exhausted teacher reads zero remaining attempts');
refund_check(ai_quiz_remaining($pdo, 1, $dayTime + 86400) === 10, 'Next-day display resets without a usage row');
refund_check(ai_quiz_refund_unavailable($pdo, 1, $dayTime + 300) === 1, 'Restore a slot at the daily limit');
refund_check(ai_quiz_reserve($pdo, 1, $dayTime + 330) === 0, 'Restored slot can be used');
$pdo->exec("CREATE TRIGGER reject_refund BEFORE UPDATE ON ai_quiz_usage WHEN NEW.request_count < OLD.request_count BEGIN SELECT RAISE(ABORT, 'Fixture storage unavailable'); END");
try { ai_quiz_refund_unavailable($pdo, 1, $dayTime + 330); throw new RuntimeException('Expected rollback'); }
catch (PDOException $expected) {}
refund_check(!$pdo->inTransaction() && (int) $pdo->query("SELECT request_count FROM ai_quiz_usage WHERE teacher_id = 1 AND usage_day = '2026-10-15'")->fetchColumn() === 10, 'Failed refund rolls back');
echo "AI refund checks passed (SQLite): restored allowance, retained cooldown, Manila date boundaries, overlapping reservations, separate teachers, daily limit and rollback.\n";
