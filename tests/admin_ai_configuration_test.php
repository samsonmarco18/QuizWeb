<?php
session_save_path(sys_get_temp_dir());
putenv('QUIZWEB_AI_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)));
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/ai_quiz.php';
class AiConfigurationTestPDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $query = str_replace(' FOR UPDATE', '', $query);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}
function configuration_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function configuration_reject(callable $call): void {
    try { $call(); } catch (InvalidArgumentException $expected) { return; }
    throw new RuntimeException('Expected invalid configuration or role rejection');
}
$pdo = new AiConfigurationTestPDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT, account_status TEXT); CREATE TABLE academic_settings (id TEXT PRIMARY KEY, data TEXT); CREATE TABLE audit_logs (id INTEGER PRIMARY KEY, actor_id INTEGER, target_id INTEGER, action TEXT, occurred_at TEXT)');
$pdo->exec("INSERT INTO users VALUES (1, 'admin', 'active'), (2, 'teacher', 'active'), (3, 'admin', 'suspended')");
$input = ['api_key' => 'FixtureOnlyKeyNoExternalAccess', 'enabled' => 'yes', 'model' => 'gemini-3.8-flash'];
ai_settings_save($pdo, 1, $input);
configuration_check(ai_quiz_config($pdo)['backup_model'] === 'gemini-3.5-flash-lite', 'Old settings forms gain a default backup');
ai_settings_save($pdo, 1, ['api_key' => '', 'enabled' => 'yes', 'model' => 'gemini-3.8-flash', 'backup_model' => 'gemini-3.1-flash-lite']);
configuration_check(ai_quiz_config($pdo)['backup_model'] === 'gemini-3.1-flash-lite', 'Save explicit backup');
ai_settings_save($pdo, 1, ['api_key' => '', 'enabled' => 'yes', 'model' => 'gemini-3.6-flash']);
configuration_check(ai_quiz_config($pdo)['backup_model'] === 'gemini-3.1-flash-lite', 'Omitted backup preserves existing selection');
ai_settings_save($pdo, 1, ['api_key' => '', 'enabled' => 'yes', 'model' => 'gemini-3.8-flash', 'backup_model' => '']);
configuration_check(ai_quiz_config($pdo)['backup_model'] === '' && ai_quiz_config($pdo)['key'] === $input['api_key'], 'Blank backup disables failover while preserving encrypted key');
$before = ai_settings_read($pdo);
foreach (['https://invalid.test', [], 'gemini-with space'] as $invalid) {
    configuration_reject(fn() => ai_settings_save($pdo, 1, array_replace($input, ['backup_model' => $invalid])));
    configuration_check(!$pdo->inTransaction() && ai_settings_read($pdo) === $before, 'Rejected backup rolls back without changing settings');
}
foreach ([2, 3] as $actor) configuration_reject(fn() => ai_settings_test_generation($pdo, $actor));
configuration_check(!str_contains(json_encode($pdo->query('SELECT * FROM audit_logs')->fetchAll()), $input['api_key']), 'Audit entries contain no key');
// Older stored rows have no backup field; deployment upgrades their read defaults.
unset($before['backup_model']);
$write = $pdo->prepare('UPDATE academic_settings SET data = ? WHERE id = ?');
$write->execute([json_encode($before), 'ai_quiz_settings']);
configuration_check(ai_quiz_config($pdo)['backup_model'] === 'gemini-3.5-flash-lite', 'Legacy persisted settings use default failover');
echo "Admin AI configuration checks passed (SQLite): backup defaults/save/preserve/disable, validation rollback, role enforcement and private audit entries.\n";
