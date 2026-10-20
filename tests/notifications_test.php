<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/layout.php';

function notification_check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$student = ['id' => 7, 'role' => 'student'];
$classroom = ['id' => 1, 'teacher_id' => 2, 'student_ids' => [7], 'name' => 'Science',
    'announcements' => [['id' => 1, 'title' => '<Welcome>', 'created_at' => '2026-10-19T08:00:00+08:00']],
    'quizzes' => [['id' => 1, 'title' => 'Earth science', 'created_at' => '2026-10-20T09:00:00+08:00']]];
$other = array_replace($classroom, ['id' => 2, 'student_ids' => [8], 'teacher_id' => 3]);
$items = navbar_notifications($student, [$classroom, $other]);
notification_check(count($items) === 2, 'Updates must exclude other classrooms.');
notification_check($items[0]['title'] === 'Earth science', 'Newest updates must come first.');
notification_check(count(navbar_notifications(['id' => 2, 'role' => 'teacher'], [$classroom, $other])) === 2, 'Teachers must only see their own classrooms.');
notification_check(navbar_notifications(['id' => 99, 'role' => 'admin'], [$classroom]) === [], 'Admins must not inherit classroom access.');
notification_check(str_ends_with($items[0]['href'], 'tab=quizzes') && str_ends_with($items[1]['href'], 'tab=materials'), 'Updates must link to the relevant classroom tab.');
$many = $classroom;
for ($i = 2; $i < 30; $i++) $many['quizzes'][] = ['id' => $i, 'title' => 'Quiz ' . $i, 'created_at' => '2026-10-20T09:00:00+08:00'];
$many['quizzes'][] = ['id' => 99, 'created_at' => 'invalid'];
notification_check(count(navbar_notifications($student, [$many])) === 20, 'Limit the feed and skip invalid dates.');
ob_start();
render_navbar_notifications($student, $items);
$html = ob_get_clean();
notification_check(str_contains($html, '&lt;Welcome&gt;') && !str_contains($html, '<Welcome>'), 'Escape notification content.');
if (in_array('--html', $argv, true)) { echo $html; exit; }
echo "Notification access, ordering, links, limit, and escaping passed.\n";
