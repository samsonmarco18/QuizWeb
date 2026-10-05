<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
class CapturedSchemaPDO extends PDO {
    public string $executed = '';
    public function __construct() {}
    public function exec(string $statement): int|false { $this->executed .= $statement; return 0; }
}
$pdo = new CapturedSchemaPDO();
ensure_database_schema($pdo);
foreach (['users', 'classrooms', 'attempts', 'audit_logs', 'gradebooks', 'grade_changes'] as $table) {
    if (!str_contains($pdo->executed, 'CREATE TABLE IF NOT EXISTS ' . $table)) throw new RuntimeException('Missing table: ' . $table);
}
if (!str_contains($pdo->executed, 'ADD COLUMN IF NOT EXISTS profile') || !str_contains($pdo->executed, 'ADD COLUMN IF NOT EXISTS chat_messages')) throw new RuntimeException('Legacy schema upgrades missing.');
if (str_contains($pdo->executed, 'admin@chalk.local') || str_contains($pdo->executed, 'INSERT INTO users')) throw new RuntimeException('Startup must not create the sample account.');
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
if (!preg_match('/\$2y\$10\$[.\/A-Za-z0-9]{53}/', $schema, $hash) || !password_verify('ChalkAdmin!2026', $hash[0])) throw new RuntimeException('Sample administrator password is invalid.');
if (!str_contains($schema, 'ON CONFLICT (email) DO NOTHING')) throw new RuntimeException('Import must preserve existing accounts.');
if (str_contains(substr($schema, strpos($schema, '-- DEMO ADMIN: explicit import only')), 'UPDATE users')) throw new RuntimeException('Demo import must not reset credentials.');
echo "Single-schema completeness, legacy columns, safe startup, sample password verification, and account-preserving import checks passed (no live PostgreSQL connection).\n";
