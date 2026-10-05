<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
$email = getenv('QUIZWEB_ADMIN_EMAIL') ?: '';
$password = getenv('QUIZWEB_ADMIN_PASSWORD') ?: '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Set QUIZWEB_ADMIN_EMAIL and QUIZWEB_ADMIN_PASSWORD (at least 12 characters) before running this command.\n"); exit(1);
}
try {
    $pdo = db();
    $existing = find_user_by_email($email);
    if ($existing) {
        if ($existing['role'] === 'admin' && ($existing['account_status'] ?? 'active') === 'active' && password_verify($password, $existing['password'])) {
            echo "Administrator already exists and these credentials are valid. Sign in through /QuizWeb/login.php.\n"; exit;
        }
        fwrite(STDERR, "An account with this email already exists; no account was changed.\n"); exit(1);
    }
    $pdo->beginTransaction();
    $pdo->exec('LOCK TABLE users IN EXCLUSIVE MODE');
    $id = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();
    insert_user_record($pdo, ['id' => $id, 'name' => 'Administrator', 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'admin', 'created_at' => now_iso(), 'profile' => []]);
    record_audit('admin_created', $id, $id);
    $pdo->commit();
    echo "Administrator created. Sign in through the existing login page.\n";
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log($error->getMessage()); fwrite(STDERR, "Administrator creation failed; no account was changed. Check PostgreSQL connectivity and the pdo_pgsql PHP extension.\n"); exit(1);
}
