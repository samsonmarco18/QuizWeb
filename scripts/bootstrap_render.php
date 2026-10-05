<?php
// Deployment-only provisioning. Never expose this command through HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (getenv('RENDER') !== 'true') {
    fwrite(STDERR, "This bootstrap must run inside the Render service. No accounts were created.\n");
    exit(1);
}
foreach (['HOST', 'PORT', 'NAME', 'USER', 'PASS'] as $key) {
    if (!getenv('QUIZWEB_DB_' . $key)) {
        fwrite(STDERR, "Render is missing QUIZWEB_DB_{$key}. No accounts were created.\n");
        exit(1);
    }
}
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/seed_sample_data.php';

try {
    $pdo = db();
    // Session lock serializes overlapping deployments without nesting the
    // transactions used by account and attempt creation.
    $pdo->query('SELECT pg_advisory_lock(724103826)');
    try {
        $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
        $sections = explode('-- DEMO ADMIN: explicit import only', $schema ?: '', 2);
        if (count($sections) !== 2) throw new RuntimeException('Main PostgreSQL setup file is invalid.');
        // Use the same admin insert as a full import of the one main SQL file.
        // ON CONFLICT preserves existing passwords, roles, and account status.
        $pdo->exec($sections[1]);
        $admin = find_user_by_email('admin@chalk.local');
        if (!$admin || $admin['role'] !== 'admin' || ($admin['account_status'] ?? 'active') !== 'active') {
            throw new RuntimeException('The sample admin email belongs to an inactive or non-admin account. Resolve that conflict in Render PostgreSQL; no existing account was changed.');
        }
        $seeded = seed_sample_data();
        echo 'Render PostgreSQL ready: administrator, ' . count($seeded['teachers']) . ' sample teachers, and ' . count($seeded['students']) . " sample students. Existing credentials and results are preserved.\n";
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->query('SELECT pg_advisory_unlock(724103826)');
    }
} catch (Throwable $error) {
    // Do not log connection credentials or full PDO connection errors.
    fwrite(STDERR, "Render database/account bootstrap failed. Check the service's PostgreSQL connection and account conflicts.\n");
    exit(1);
}
