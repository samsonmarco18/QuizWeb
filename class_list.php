<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_login();
$classroomId = (int) ($_GET['classroom_id'] ?? 0);
$classroom = find_classroom($classroomId);
if (!$classroom || !classroom_belongs_to_user($classroom, $user)) {
    http_response_code(403);
    exit('Classroom access denied.');
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role('teacher');
    require_form_csrf();
    try {
        classroom_student_action(db(), $classroom, $user, (int) ($_POST['student_id'] ?? 0), (string) ($_POST['student_action'] ?? ''), (string) ($_POST['reason'] ?? ''));
        flash_set('success', 'Student membership updated and the student was notified.');
        redirect('/QuizWeb/class_list.php?classroom_id=' . $classroomId);
    } catch (Throwable $error) { $errors[] = $error->getMessage(); }
    $classroom = find_classroom($classroomId) ?? $classroom;
}
$students = classroom_students($classroom);
usort($students, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
$GLOBALS['quizweb_current_classroom_id'] = $classroomId;
render_header('Class list', 'class-list-page', ['/QuizWeb/assets/css/classroom.css', '/QuizWeb/assets/css/class-list.css']);
?>
<script defer src="<?php echo esc(versioned_asset_url('/QuizWeb/assets/js/class-list.js')); ?>"></script>
<?php
?>
<header class="page-heading">
    <div><span class="eyebrow"><?php echo esc($classroom['name']); ?></span><h1>Class list</h1><p><?php echo count($students); ?> enrolled student<?php echo count($students) === 1 ? '' : 's'; ?><?php if ($user['role'] === 'teacher'): ?> · Manage enrollment and send a reason with each update<?php endif; ?></p></div>
    <a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo $classroomId; ?>&amp;tab=overview">Back to classroom</a>
</header>
<section class="glass panel classroom-roster-panel" aria-label="Enrolled students">
    <?php foreach ($errors as $error): ?><p class="class-roster-error" role="alert"><?php echo esc($error); ?></p><?php endforeach; ?>
    <div class="classroom-roster-list">
        <?php foreach ($students as $student): ?>
        <?php $memberStatus = classroom_student_status($classroom, (int) $student['id']); ?>
        <article class="class-roster-card"><div class="class-roster-identity"><?php render_profile_avatar($student, 'classroom-member-avatar'); ?><div class="class-roster-copy"><strong><?php echo esc($student['name']); ?><?php echo (int) $student['id'] === (int) $user['id'] ? ' (YOU)' : ''; ?></strong><?php if ($user['role'] === 'teacher'): ?><small><?php echo esc($student['email']); ?></small><?php endif; ?></div></div>
        <?php if ($user['role'] === 'teacher'): ?><div class="class-roster-manage"><span class="class-roster-status <?php echo $memberStatus['status'] === 'suspended' ? 'is-suspended' : ''; ?>"><?php echo ucfirst($memberStatus['status']); ?></span><details class="class-roster-actions"><summary><?php echo $memberStatus['status'] === 'suspended' ? 'Manage' : 'Manage student'; ?></summary><form method="post" class="class-roster-action-form"><input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>"><input type="hidden" name="student_id" value="<?php echo (int) $student['id']; ?>"><label><span>Reason sent to student</span><textarea name="reason" minlength="3" maxlength="500" rows="2" required placeholder="Explain this membership update"></textarea></label><?php if ($memberStatus['status'] === 'suspended'): ?><button class="button button-primary" name="student_action" value="reinstate">Reinstate</button><?php else: ?><button class="button button-secondary" name="student_action" value="suspend">Suspend</button><?php endif; ?><button class="button button-danger" name="student_action" value="remove" onclick="return confirm('Remove this student from the classroom? Their work and grades will be retained.');">Remove from class</button></form></details></div><?php endif; ?></article>
        <?php endforeach; ?>
        <?php if (!$students): ?><p>No students have joined this classroom yet.</p><?php endif; ?>
    </div>
</section>
<?php render_footer(); ?>
