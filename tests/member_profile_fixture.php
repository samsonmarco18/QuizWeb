<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/layout.php';
$_SERVER['REQUEST_URI'] = '/QuizWeb/dashboard.php';
$fixtureUser = ['id' => 6, 'name' => 'Student', 'email' => 'student@example.test', 'role' => 'student', 'profile' => ['avatar' => ['file' => str_repeat('a', 48), 'mime' => 'image/png']]];
$fixtureUsers = [$fixtureUser, ['id' => 5, 'name' => 'Teacher', 'role' => 'teacher', 'profile' => []]];
$fixtureClasses = [
    ['id' => 1, 'name' => 'Science', 'subject' => 'Science', 'teacher_id' => 5, 'student_ids' => [6], 'updated_at' => now_iso(), 'chat_messages' => []],
    ['id' => 2, 'name' => 'Math', 'subject' => 'Math', 'teacher_id' => 5, 'student_ids' => [6], 'updated_at' => now_iso(), 'chat_messages' => []],
];
// Render the production templates with isolated memory records.
if (in_array('--image-attachments', $argv, true)) {
    $fixtureClasses[0]['chat_messages'] = [['id' => 9, 'user_id' => 6, 'user_name' => 'Student', 'user_role' => 'student', 'body' => '', 'created_at' => now_iso(), 'attachments' => [
        ['name' => 'Screenshot-private.png', 'stored_name' => str_repeat('b', 48), 'size' => 1024, 'image' => true, 'mime' => 'image/png'],
        ['name' => 'Lesson.pdf', 'stored_name' => str_repeat('c', 48), 'size' => 2048, 'image' => false, 'mime' => 'application/pdf'],
    ]]];
}
$source = file_get_contents(__DIR__ . '/../includes/layout.php');
$start = strpos($source, 'function render_messenger_dock(): void');
$end = strpos($source, 'function render_footer(', $start);
$fixture = substr($source, $start, $end - $start);
$fixture = str_replace('function render_messenger_dock(): void', 'function render_member_fixture(): void', $fixture);
$fixture = str_replace('$user = current_user();', '$user = $GLOBALS["fixtureUser"];', $fixture);
$fixture = str_replace('$classrooms = user_classrooms($user);', '$classrooms = $GLOBALS["fixtureClasses"];', $fixture);
$fixture = str_replace('$allChatUsers = users();', '$allChatUsers = $GLOBALS["fixtureUsers"];', $fixture);
eval($fixture);
ob_start();
render_member_fixture();
$user = $fixtureUser; $profile = profile_input([]); $errors = []; $_SESSION['profile_csrf'] = 'fixture';
$source = file_get_contents(__DIR__ . '/../app/pages/account/profile.php');
$start = strpos($source, '<section class="glass panel profile-settings">');
$end = strpos($source, '<?php render_footer(); ?>', $start);
eval('?>' . substr($source, $start, $end - $start));
$html = ob_get_clean();
$threads = [];
foreach ($fixtureClasses as $class) $threads[] = ['id' => $class['id'], 'members' => chat_public_members($class, $fixtureUser, $fixtureUsers), 'messages' => array_map(static fn($message) => chat_public_message($message, $class['id'], $fixtureUser['id']), $class['chat_messages'])];
echo json_encode(['html' => $html, 'threads' => $threads]);
