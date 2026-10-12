<?php
// Disposable MySQL database; no provider calls and no real credentials.
session_save_path(sys_get_temp_dir());
putenv('QUIZWEB_DB_DRIVER=mysql');
$testName = 'quizweb_admin_ai_test_' . bin2hex(random_bytes(8));
putenv('QUIZWEB_DB_NAME=' . $testName);
putenv('QUIZWEB_AI_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)));
putenv('GEMINI_API_KEY=LegacyFakeKeyForTestingOnly123456789');
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/ai_quiz.php';
function settings_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function settings_reject(callable $fn, string $message): void {
    try { $fn(); } catch (InvalidArgumentException | RuntimeException $error) { return; }
    throw new RuntimeException($message);
}
$server = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE ' . $testName . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
try {
    $pdo = db();
    $admin = ['id' => 81, 'name' => 'Settings Admin', 'email' => 'settings@admin.test', 'password' => password_hash('Test123!', PASSWORD_DEFAULT), 'role' => 'admin', 'created_at' => now_iso(), 'profile' => [], 'account_status' => 'active'];
    insert_user_record($pdo, $admin);
    $teacher = array_replace($admin, ['id' => 82, 'email' => 'teacher@admin.test', 'role' => 'teacher']);
    insert_user_record($pdo, $teacher);
    $secret = 'FakeGeminiApiKeyForSettingsTest123456789';
    settings_check(ai_quiz_config($pdo)['source'] === 'Server environment', 'Legacy defaults before configuration');
    ai_settings_csrf('abc', 'abc');
    settings_reject(fn() => ai_settings_csrf('wrong', 'abc'), 'CSRF protection');
    settings_reject(fn() => ai_settings_csrf('', ''), 'Empty CSRF protection');
    $input = ['api_key' => $secret, 'model' => 'gemini-3.8-flash', 'enabled' => 'yes'];
    ai_settings_save($pdo, 81, $input);
    $raw = $pdo->query("SELECT data FROM academic_settings WHERE id='ai_quiz_settings'")->fetchColumn();
    settings_check(!str_contains($raw, $secret) && str_contains($raw, 'encrypted_key'), 'No plaintext key in database');
    settings_check(ai_quiz_config($pdo)['key'] === $secret && ai_quiz_config($pdo)['source'] === 'Administration', 'Saved admin key overrides legacy environment');
    ai_settings_save($pdo, 81, array_replace($input, ['api_key' => '', 'model' => 'gemini-3.6-flash']));
    settings_check(ai_quiz_config($pdo)['key'] === $secret && ai_quiz_config($pdo)['model'] === 'gemini-3.6-flash', 'Blank input preserves key while updating model');
    ai_settings_save($pdo, 81, ['api_key' => '', 'model' => 'gemini-3.8-flash']);
    settings_check(!ai_quiz_config($pdo)['enabled'] && ai_quiz_config($pdo)['key'] === '' && ai_quiz_config($pdo, true)['key'] === $secret, 'Disable honored for generator; saved test remains possible');
    ai_settings_save($pdo, 81, array_replace($input, ['api_key' => 'ReplacementFakeApiKeyForTesting12345']));
    settings_check(ai_quiz_config($pdo)['key'] === 'ReplacementFakeApiKeyForTesting12345', 'Key rotation');
    foreach ([['model' => 'https://evil.test'], ['api_key' => "bad\r\nheader"], ['api_key' => []]] as $change) {
        settings_reject(fn() => ai_settings_save($pdo, 81, array_replace($input, $change)), 'Malformed settings rejected');
        settings_check(!$pdo->inTransaction(), 'Rejected settings transaction closed');
    }
    settings_reject(fn() => ai_settings_save($pdo, 82, $input), 'Teacher cannot configure AI');
    settings_reject(fn() => ai_settings_test($pdo, 82), 'Teacher cannot test credentials');
    $pdo->exec("UPDATE users SET account_status='suspended' WHERE id=81");
    settings_reject(fn() => ai_settings_save($pdo, 81, $input), 'Suspended admin cannot configure AI');
    $pdo->exec("UPDATE users SET account_status='active' WHERE id=81");
    $encrypted = ai_settings_encrypt($secret);
    settings_check(ai_settings_decrypt($encrypted) === $secret, 'AES-GCM roundtrip');
    $bytes = base64_decode($encrypted['value']); $bytes[13] = chr(ord($bytes[13]) ^ 1); $encrypted['value'] = base64_encode($bytes);
    settings_reject(fn() => ai_settings_decrypt($encrypted), 'Tampered authentication tag rejected');
    ai_settings_probe_result(200, '{"name":"models/gemini-3.8-flash"}');
    foreach ([400, 403, 404, 429, 500] as $status) settings_reject(fn() => ai_settings_probe_result($status, '{"error":"' . $secret . '"}'), 'Connection errors sanitized');
    $_SESSION['user_id'] = 81; $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = ['tab' => 'ai']; $_POST = [];
    ob_start(); require __DIR__ . '/../app/pages/admin/admin.php'; $html = ob_get_clean();
    settings_check(str_contains($html, 'AI quiz generator') && str_contains($html, 'Test saved connection'), 'Actual admin settings page renders');
    settings_check(!str_contains($html, $secret) && !str_contains($html, 'ReplacementFakeApiKey') && !str_contains($html, 'encrypted_key'), 'Page never exposes saved keys or ciphertext');
    settings_check(str_contains($html, 'type="password" name="api_key" value=""'), 'Key field blank and masked');
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = ['csrf' => 'wrong', 'action' => 'ai_save_settings', 'api_key' => $secret];
    ob_start(); require __DIR__ . '/../app/pages/admin/admin.php'; $html = ob_get_clean();
    settings_check(str_contains($html, 'Your form expired') && !str_contains($html, $secret), 'Failed CSRF form must not reflect entered key');
    ai_settings_save($pdo, 81, [], 'remove');
    settings_check(!ai_quiz_config($pdo)['enabled'] && ai_quiz_config($pdo, true)['key'] === '', 'Removal disables generation despite legacy environment key');
    settings_reject(fn() => ai_settings_save($pdo, 81, ['api_key' => '', 'enabled' => 'yes', 'model' => 'gemini-3.8-flash']), 'Cannot enable missing key');
    $audit = json_encode($pdo->query('SELECT * FROM audit_logs')->fetchAll());
    settings_check(!str_contains($audit, $secret) && !str_contains($audit, 'ReplacementFakeApiKey') && str_contains($audit, 'ai_settings_updated') && str_contains($audit, 'ai_api_key_removed'), 'Audit actions contain no credentials');
    $before = ai_settings_read($pdo);
    $pdo->exec("CREATE TRIGGER reject_ai_audit BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test audit rejection'");
    settings_reject(fn() => ai_settings_save($pdo, 81, $input), 'Audit failure rolls back settings');
    settings_check(ai_settings_read($pdo) === $before && !$pdo->inTransaction(), 'Atomic audit rollback');
    echo "Admin AI checks passed: encrypted persistence, key masking/rotation/removal, defaults precedence, role/CSRF guards, enable toggle, safe connection errors, page rendering, audits and rollback.\n";
} finally {
    $server->exec('DROP DATABASE IF EXISTS ' . $testName);
}
