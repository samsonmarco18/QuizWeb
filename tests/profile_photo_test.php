<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/chat.php';
function photo_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function photo_reject(callable $action): void {
    try { $action(); } catch (InvalidArgumentException $error) { return; }
    throw new RuntimeException('Unsafe image or access was accepted.');
}
$avatar = ['file' => str_repeat('a', 48), 'mime' => 'image/png'];
$teacher = ['id' => 5, 'name' => 'Teacher', 'role' => 'teacher', 'profile' => ['avatar' => $avatar], 'password' => 'private', 'email' => 'private@example.test'];
$student = ['id' => 6, 'name' => 'Student', 'role' => 'student', 'profile' => []];
$peer = array_replace($student, ['id' => 7, 'name' => 'Peer']);
$outsider = array_replace($student, ['id' => 8]);
$otherTeacher = array_replace($teacher, ['id' => 9]);
$admin = array_replace($student, ['id' => 10, 'role' => 'admin']);
$class = ['id' => 1, 'teacher_id' => 5, 'student_ids' => [6, 7, 7]];
$members = chat_public_members($class, $student, [$teacher, $student, $peer, $outsider, $otherTeacher, $admin]);
photo_check(array_column($members, 'id') === [5, 7, 6], 'Roster must include only teacher and enrolled students, with teacher first.');
foreach ($members as $member) photo_check(array_keys($member) === ['id', 'name', 'role', 'avatar_url'], 'Roster exposed private account details.');
photo_reject(fn() => chat_public_members($class, $outsider, [$teacher, $student]));
photo_check(can_view_profile_photo($student, $teacher, [$class]), 'Classmate photo access failed.');
photo_check(can_view_profile_photo($teacher, $student, [$class]), 'Teacher photo access failed.');
photo_check(can_view_profile_photo($student, $student, []), 'Own photo access failed.');
photo_check(can_view_profile_photo($admin, $teacher, []), 'Administrative photo access failed.');
photo_check(!can_view_profile_photo($outsider, $student, [$class]), 'Outsider can access member photos.');
photo_check(!can_view_profile_photo($otherTeacher, $student, [$class]), 'Unrelated teacher can access member photos.');
photo_check(profile_photo_url($teacher) === '/QuizWeb/profile_photo.php?id=5&v=' . $avatar['file'], 'Photo URL mismatch.');
photo_check(profile_photo_metadata(['profile' => ['avatar' => ['file' => '../../secret', 'mime' => 'image/png']]]) === null, 'Photo path traversal accepted.');
photo_check(profile_photo_metadata(['profile' => ['avatar' => ['file' => $avatar['file'], 'mime' => 'image/svg+xml']]]) === null, 'SVG metadata accepted.');
$temporary = tempnam(sys_get_temp_dir(), 'chalk-photo-');
try {
    file_put_contents($temporary, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
    photo_check(profile_validate_photo($temporary)['mime'] === 'image/png', 'Valid PNG rejected.');
    photo_reject(fn() => profile_store_photo(['error' => UPLOAD_ERR_OK, 'tmp_name' => $temporary]));
    file_put_contents($temporary, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    photo_reject(fn() => profile_validate_photo($temporary));
    file_put_contents($temporary, str_repeat('x', 2 * 1024 * 1024 + 1));
    photo_reject(fn() => profile_validate_photo($temporary));
} finally { unlink($temporary); }
class PhotoTestPDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options); }
}
$pdo = new PhotoTestPDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, profile TEXT NOT NULL)');
$original = ['program' => 'Science', 'avatar' => $avatar, 'existing_setting' => 'preserved', 'preferences' => ['one', 'two']];
$pdo->prepare('INSERT INTO users VALUES (?, ?)')->execute([6, json_encode($original)]);
profile_save_changes($pdo, 6, ['program' => 'Math']);
$saved = db_json_decode($pdo->query('SELECT profile FROM users WHERE id = 6')->fetchColumn());
photo_check($saved['avatar'] === $avatar && $saved['existing_setting'] === 'preserved' && $saved['program'] === 'Math' && $saved['preferences'] === ['one', 'two'], 'Details edit lost photo or other profile fields.');
$pdo->exec("CREATE TRIGGER reject_profile BEFORE UPDATE ON users BEGIN SELECT RAISE(ABORT, 'unavailable'); END");
try { profile_save_changes($pdo, 6, [], true); throw new RuntimeException('Failed save should throw.'); } catch (PDOException $expected) {}
photo_check(!$pdo->inTransaction() && db_json_decode($pdo->query('SELECT profile FROM users WHERE id = 6')->fetchColumn()) === $saved, 'Failed edit changed profile or left a transaction open.');
$pdo->exec('DROP TRIGGER reject_profile');
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=');
$persistentAvatar = $avatar + ['data' => base64_encode($png)];
profile_save_changes($pdo, 6, ['avatar' => $persistentAvatar]);
$reloaded = ['id' => 6, 'profile' => db_json_decode($pdo->query('SELECT profile FROM users WHERE id = 6')->fetchColumn())];
photo_check(profile_photo_bytes($reloaded) === $png, 'Database photo was not readable without an uploaded file.');
profile_save_changes($pdo, 6, ['program' => 'Math']);
$reloaded['profile'] = db_json_decode($pdo->query('SELECT profile FROM users WHERE id = 6')->fetchColumn());
photo_check(profile_photo_bytes($reloaded) === $png, 'Details save lost persistent image bytes.');
$reloaded['profile']['avatar']['data'] = 'invalid base64!';
photo_check(profile_photo_bytes($reloaded) === null, 'Corrupt database image accepted.');
$reloaded['profile']['avatar']['data'] = base64_encode('<script>unsafe</script>');
photo_check(profile_photo_bytes($reloaded) === null, 'Non-image database content accepted.');
profile_save_changes($pdo, 6, [], true);
$removed = db_json_decode($pdo->query('SELECT profile FROM users WHERE id = 6')->fetchColumn());
photo_check(!isset($removed['avatar']) && $removed['program'] === 'Math', 'Photo removal changed profile details.');
echo "Profile photo validation, private roster, member-only photo access, metadata safety, profile preservation, removal, and atomic rollback passed.\n";
