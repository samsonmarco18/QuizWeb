<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_role('student');
$classroomId = (int) ($_GET['classroom_id'] ?? 0);
$operation = is_string($_GET['operation'] ?? null) ? $_GET['operation'] : '';
$classroom = find_classroom($classroomId);
if (!$classroom || !classroom_belongs_to_user($classroom, $user) || !in_array($operation, ['archive', 'restore', 'delete'], true)) {
    http_response_code(403); exit('Classroom action unavailable.');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_form_csrf();
    try {
        $confirmation = is_string($_POST['confirmation'] ?? null) ? $_POST['confirmation'] : '';
        change_student_classroom(db(), $user, $classroomId, $operation, $confirmation);
        flash_set('success', ['archive' => 'Class archived. Its notifications are paused.', 'restore' => 'Class restored. Notifications are enabled again.', 'delete' => 'Class removed from your account. You are no longer enrolled.'][$operation]);
        redirect('/QuizWeb/' . ($operation === 'archive' ? 'archive.php' : 'classes.php'));
    } catch (Throwable $exception) {
        $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Could not update this class. Please try again.';
    }
}
$deleting = $operation === 'delete';
render_header(ucfirst($operation) . ' classroom', 'classroom-confirm-page');
?>
<section class="glass panel class-action-confirm">
    <span class="eyebrow"><?php echo esc($classroom['name']); ?></span>
    <h1><?php echo $deleting ? 'Delete this class from your account?' : ucfirst($operation) . ' this class?'; ?></h1>
    <?php if ($deleting): ?>
        <div class="class-action-warning" role="note"><strong>Warning: you will lose access to this classroom.</strong><p>You will be removed from the class list and stop receiving its notifications. You will no longer be able to open its activities, materials, or classroom results. Your teacher's class and submitted records will remain. To return, you will need to join the class again.</p></div>
        <p>To confirm, type <strong class="class-delete-phrase"><?php echo esc(classroom_delete_phrase($classroom)); ?></strong> exactly below.</p>
    <?php elseif ($operation === 'archive'): ?>
        <div class="class-action-warning" role="note"><strong>You will no longer receive notifications from this class while it is archived.</strong><p>The class will move out of My Classes into Archive. Your enrollment and work will be kept. Restore it from Archive to receive notifications again.</p></div>
    <?php else: ?><p>The class will return to My Classes and you will receive its notifications again.</p><?php endif; ?>
    <?php if ($error): ?><p class="inline-error" role="alert"><?php echo esc($error); ?></p><?php endif; ?>
    <form method="post" class="stack-form">
        <input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>">
        <?php if ($deleting): ?><label><span>Confirmation phrase</span><input name="confirmation" autocomplete="off" spellcheck="false" required data-delete-confirmation data-expected="<?php echo esc(classroom_delete_phrase($classroom)); ?>"></label><?php endif; ?>
        <div class="class-action-buttons"><a class="button button-secondary" href="/QuizWeb/classes.php">Cancel</a><button class="button <?php echo $deleting ? 'button-danger' : 'button-primary'; ?>" <?php echo $deleting ? 'data-delete-submit disabled' : ''; ?>><?php echo $deleting ? 'Delete from my account' : ucfirst($operation) . ' class'; ?></button></div>
    </form>
</section>
<?php render_footer(['/QuizWeb/assets/js/classroom-confirm.js']); ?>
