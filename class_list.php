<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
$classroomId = (int) ($_GET['classroom_id'] ?? 0);
$classroom = find_classroom($classroomId);
if (!$classroom || !classroom_belongs_to_user($classroom, $user)) {
    http_response_code(403);
    exit('Classroom access denied.');
}
$students = classroom_students($classroom);
usort($students, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
$GLOBALS['quizweb_current_classroom_id'] = $classroomId;
render_header('Class list', 'class-list-page', ['/QuizWeb/assets/css/classroom.css']);
?>
<header class="page-heading">
    <div><span class="eyebrow"><?php echo esc($classroom['name']); ?></span><h1>Class list</h1><p><?php echo count($students); ?> enrolled student<?php echo count($students) === 1 ? '' : 's'; ?></p></div>
    <a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo $classroomId; ?>&amp;tab=overview">Back to classroom</a>
</header>
<section class="glass panel classroom-roster-panel" aria-label="Enrolled students">
    <div class="classroom-roster-list">
        <?php foreach ($students as $student): ?>
        <div><?php render_profile_avatar($student, 'classroom-member-avatar'); ?><div><strong><?php echo esc($student['name']); ?></strong><?php if ($user['role'] === 'teacher'): ?><small><?php echo esc($student['email']); ?></small><?php endif; ?></div></div>
        <?php endforeach; ?>
        <?php if (!$students): ?><p>No students have joined this classroom yet.</p><?php endif; ?>
    </div>
</section>
<?php render_footer(); ?>
