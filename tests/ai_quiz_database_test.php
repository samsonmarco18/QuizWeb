<?php
session_save_path(sys_get_temp_dir());
putenv('QUIZWEB_DB_DRIVER=mysql');
$testName = 'quizweb_ai_test_' . bin2hex(random_bytes(8));
putenv('QUIZWEB_DB_NAME=' . $testName);
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/ai_quiz.php';
function ai_db_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function ai_db_limited(callable $fn): void { try { $fn(); } catch (RuntimeException $error) { ai_db_check($error->getCode() === 429, 'Expected quota status'); return; } throw new RuntimeException('Quota not enforced'); }
$server = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE ' . $testName . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
try {
    $teacher = register_user('AI Teacher', 'ai@quiz.test', 'TestUser!2026', 'teacher');
    $second = register_user('Another Teacher', 'other@quiz.test', 'TestUser!2026', 'teacher');
    $now = strtotime('2026-10-12 10:00:00');
    $pdo = db();
    ai_db_check(ai_quiz_reserve($pdo, (int) $teacher['id'], $now) === 19, 'First quota reservation');
    ai_db_limited(fn() => ai_quiz_reserve($pdo, (int) $teacher['id'], $now + 10));
    ai_db_check(!$pdo->inTransaction(), 'Rejected quota transaction must close');
    $connection = new PDO(database_dsn(), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    ai_db_check(ai_quiz_reserve($connection, (int) $teacher['id'], $now + 30) === 18, 'Quota shared across connections');
    for ($i = 2; $i < 20; $i++) ai_quiz_reserve($pdo, (int) $teacher['id'], $now + $i * 30);
    ai_db_limited(fn() => ai_quiz_reserve($connection, (int) $teacher['id'], $now + 600));
    ai_db_check(ai_quiz_reserve($pdo, (int) $second['id'], $now) === 19, 'Independent teacher quota');
    ai_db_check(ai_quiz_reserve($pdo, (int) $teacher['id'], $now + 86400) === 19, 'Next-day quota reset');
    $classroom = ['id' => 501, 'teacher_id' => $teacher['id'], 'name' => 'AI Class', 'subject' => 'Science', 'description' => '', 'code' => 'AI501', 'student_ids' => [], 'quizzes' => [], 'announcements' => [], 'chat_messages' => [], 'created_at' => now_iso(), 'updated_at' => now_iso()];
    save_classroom($classroom);
    $_SESSION['user_id'] = $teacher['id']; $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = ['classroom_id' => 501, 'game_type' => 'standard'];
    ob_start(); require __DIR__ . '/../app/pages/quizzes/quiz_builder.php'; $html = ob_get_clean();
    ai_db_check(str_contains($html, 'id="ai-quiz-panel"') && str_contains($html, 'data-classroom-id="501"') && str_contains($html, '/assets/js/ai-quiz.js'), 'Rendered teacher builder contains integration');
    ai_db_check(!str_contains($html, 'x-goog-api-key'), 'Builder must not expose credentials');
    echo "AI database checks passed: durable cross-connection quotas, cooldown, daily reset, independent teachers, schema and actual builder rendering.\n";
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $testName);
}
