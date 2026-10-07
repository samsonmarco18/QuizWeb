<?php
if (PHP_SAPI !== 'cli' || getenv('QUIZWEB_PROFILE_HTTP_TEST') !== '1') { http_response_code(404); exit(1); }
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
if (DB_DRIVER !== 'mysql' || !preg_match('/^quizweb_test_[a-f0-9]{16}$/D', DB_NAME)) throw new RuntimeException('Use a separate test database.');
$server = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE ' . DB_NAME . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
$pdo = new PDO(database_dsn(), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
ensure_database_schema($pdo);
$profile = ['student_number' => '2026-001', 'birthdate' => '2004-01-01', 'gender' => 'Prefer not to say', 'program' => 'Science', 'year_level' => '3rd Year'];
foreach ([5 => 'teacher', 6 => 'student', 7 => 'peer', 8 => 'outsider'] as $id => $name) {
    insert_user_record($pdo, ['id' => $id, 'name' => ucfirst($name), 'email' => 'photo-test-' . $name . '@example.test',
        'password' => password_hash('TestUser!2026', PASSWORD_DEFAULT), 'role' => $name === 'teacher' ? 'teacher' : 'student', 'created_at' => now_iso(), 'profile' => $profile]);
}
insert_classroom_record($pdo, ['id' => 1, 'teacher_id' => 5, 'name' => 'Photo test class', 'subject' => 'Science', 'description' => '', 'code' => 'PHOTOHTTP',
    'student_ids' => [6, 7], 'quizzes' => [], 'announcements' => [], 'chat_messages' => [], 'created_at' => now_iso(), 'updated_at' => now_iso()]);
echo "Created isolated HTTP fixture.\n";
