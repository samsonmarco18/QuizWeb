<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/notifications.php';
function check_lifecycle(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$class = ['id' => 1, 'name' => 'Grade 10', 'teacher_id' => 5, 'student_ids' => [7, 8], 'announcements' => [['id' => 1, 'title' => 'Update', 'created_at' => now_iso()]]];
$student = ['id' => 7, 'role' => 'student', 'profile' => ['archived_classrooms' => ['1' => true]]];
check_lifecycle(classroom_delete_phrase($class) === 'SUDO DELETE Grade 10', 'Deletion phrase must name the exact class.');
check_lifecycle(navbar_notifications($student, [$class]) === [], 'Archived classes must not send notifications.');
check_lifecycle(count(navbar_notifications(array_replace($student, ['profile' => []]), [$class])) === 1, 'Restored classes must receive notifications again.');
check_lifecycle(!classroom_is_archived(['id' => 8], 1), 'Archive state must be private to each account.');
if (!in_array('--database', $argv, true)) { echo "Archive privacy, notification suppression, and confirmation phrase passed.\n"; exit; }
// Connection-local temporary tables shadow real tables. No real account or class is modified.
$pdo = new PDO(database_dsn(), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$engine = DB_DRIVER === 'mysql' ? ' ENGINE=InnoDB' : '';
$pdo->exec('CREATE TEMPORARY TABLE users (id INT PRIMARY KEY, profile JSON)' . $engine);
$pdo->exec('CREATE TEMPORARY TABLE classrooms (id INT PRIMARY KEY, teacher_id INT, name VARCHAR(190), subject VARCHAR(190), code VARCHAR(30), student_ids JSON, created_at VARCHAR(40), updated_at VARCHAR(40))' . $engine);
$pdo->prepare('INSERT INTO users VALUES (?, ?)')->execute([7, db_json_encode(['student_number' => '001', 'materials' => ['1:1' => ['saved' => true]]])]);
$pdo->prepare('INSERT INTO classrooms VALUES (1, 5, ?, ?, ?, ?, ?, ?)')->execute(['Grade 10', 'Science', 'TEST', '[7,8]', now_iso(), now_iso()]);
$loadProfile = fn() => db_json_decode($pdo->query('SELECT profile FROM users WHERE id = 7')->fetchColumn());
$members = fn() => db_json_decode($pdo->query('SELECT student_ids FROM classrooms WHERE id = 1')->fetchColumn());
$member = ['id' => 7, 'role' => 'student'];
change_student_classroom($pdo, $member, 1, 'archive', '');
check_lifecycle(classroom_is_archived(['profile' => $loadProfile()], 1) && $members() === [7,8], 'Archive must preserve membership.');
check_lifecycle($loadProfile()['student_number'] === '001' && $loadProfile()['materials']['1:1']['saved'], 'Archive must preserve profile and saved materials.');
change_student_classroom($pdo, $member, 1, 'restore', '');
check_lifecycle(!classroom_is_archived(['profile' => $loadProfile()], 1), 'Restore must clear archive state.');
foreach ([['id' => 9, 'role' => 'student'], ['id' => 5, 'role' => 'teacher']] as $outsider) {
    try { change_student_classroom($pdo, $outsider, 1, 'delete', 'SUDO DELETE Grade 10'); throw new RuntimeException('Unauthorized deletion accepted.'); }
    catch (InvalidArgumentException $expected) {}
}
try { change_student_classroom($pdo, $member, 1, 'delete', 'sudo delete Grade 10'); throw new RuntimeException('Wrong phrase accepted.'); }
catch (InvalidArgumentException $expected) {}
check_lifecycle($members() === [7,8], 'Failed confirmation must leave membership unchanged.');
change_student_classroom($pdo, $member, 1, 'delete', 'SUDO DELETE Grade 10');
check_lifecycle($members() === [8] && (int) $pdo->query('SELECT COUNT(*) FROM classrooms')->fetchColumn() === 1, 'Delete removes only this student; class and other students remain.');
echo "Temporary-table integration: archive, restore, profile preservation, permissions, exact confirmation, and enrollment removal passed.\n";
