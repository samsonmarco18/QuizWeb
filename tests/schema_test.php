<?php
session_save_path(sys_get_temp_dir());
// Verify the PostgreSQL contract even when a developer opts into local MySQL.
putenv('QUIZWEB_DB_DRIVER=pgsql');
require_once __DIR__ . '/../includes/app.php';
class CapturedSchemaPDO extends PDO {
    public string $executed = '';
    public bool $failSchema = false;
    public function __construct(?string $version = null) {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($version !== null) {
            parent::exec('CREATE TABLE quizweb_schema_state (id INTEGER PRIMARY KEY, version VARCHAR(64) NOT NULL)');
            $this->prepare('INSERT INTO quizweb_schema_state VALUES (1, ?)')->execute([$version]);
        }
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        try { return parent::query($query); }
        catch (PDOException $error) {
            if (str_contains($error->getMessage(), 'no such table: quizweb_schema_state')) {
                $missing = new PDOException('Missing schema state');
                $missing->errorInfo = ['42P01'];
                throw $missing;
            }
            throw $error;
        }
    }
    public function exec(string $statement): int|false {
        $this->executed .= $statement;
        if (str_starts_with($statement, 'CREATE TABLE IF NOT EXISTS quizweb_schema_state')) return parent::exec($statement);
        if ($this->failSchema) throw new RuntimeException('Simulated schema failure');
        return 0;
    }
}
$pdo = new CapturedSchemaPDO();
ensure_database_schema($pdo);
foreach (['users', 'classrooms', 'attempts', 'audit_logs', 'gradebooks', 'grade_changes'] as $table) {
    if (!str_contains($pdo->executed, 'CREATE TABLE IF NOT EXISTS ' . $table)) throw new RuntimeException('Missing table: ' . $table);
}
if (!str_contains($pdo->executed, 'ADD COLUMN IF NOT EXISTS profile') || !str_contains($pdo->executed, 'ADD COLUMN IF NOT EXISTS chat_messages')) throw new RuntimeException('Legacy schema upgrades missing.');
if (str_contains($pdo->executed, 'admin@chalk.local') || str_contains($pdo->executed, 'INSERT INTO users')) throw new RuntimeException('Startup must not create the sample account.');
$version = $pdo->query('SELECT version FROM quizweb_schema_state WHERE id = 1')->fetchColumn();
$executed = $pdo->executed;
ensure_database_schema($pdo);
if ($pdo->executed !== $executed) throw new RuntimeException('Same connection repeated schema writes.');
$nextRequest = new CapturedSchemaPDO($version);
ensure_database_schema($nextRequest);
if ($nextRequest->executed !== '') throw new RuntimeException('An unchanged schema must not execute DDL on the next request.');
$upgrade = new CapturedSchemaPDO('previous-schema-version');
ensure_database_schema($upgrade);
if (!str_contains($upgrade->executed, 'CREATE TABLE IF NOT EXISTS users') || $upgrade->query('SELECT version FROM quizweb_schema_state WHERE id = 1')->fetchColumn() !== $version) throw new RuntimeException('Schema changes must run and update the saved version.');
$failedUpgrade = new CapturedSchemaPDO('old-version');
$failedUpgrade->failSchema = true;
try { ensure_database_schema($failedUpgrade); throw new LogicException('Expected failed schema update'); }
catch (RuntimeException $error) { if ($error->getMessage() !== 'Simulated schema failure') throw $error; }
if ($failedUpgrade->query('SELECT version FROM quizweb_schema_state WHERE id = 1')->fetchColumn() !== 'old-version') throw new RuntimeException('Failed schema update must not mark itself complete.');
$failedUpgrade->failSchema = false;
ensure_database_schema($failedUpgrade);
if ($failedUpgrade->query('SELECT version FROM quizweb_schema_state WHERE id = 1')->fetchColumn() !== $version) throw new RuntimeException('Failed update must remain retryable.');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
if (!preg_match('/\$2y\$10\$[.\/A-Za-z0-9]{53}/', $schema, $hash) || !password_verify('ChalkAdmin!2026', $hash[0])) throw new RuntimeException('Sample administrator password is invalid.');
if (!str_contains($schema, 'ON CONFLICT (email) DO NOTHING')) throw new RuntimeException('Import must preserve existing accounts.');
if (str_contains(substr($schema, strpos($schema, '-- DEMO ADMIN: explicit import only')), 'UPDATE users')) throw new RuntimeException('Demo import must not reset credentials.');
echo "Single-schema completeness, legacy columns, safe startup, sample password verification, and account-preserving import checks passed (no live PostgreSQL connection).\n";
