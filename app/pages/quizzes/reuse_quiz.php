<?php

require_once dirname(__DIR__, 3) . '/includes/layout.php';

$teacher = require_role('teacher');
$sourceClassroomId = (int) ($_GET['classroom_id'] ?? $_POST['classroom_id'] ?? 0);
$quizId = (int) ($_GET['quiz_id'] ?? $_POST['quiz_id'] ?? 0);
$sourceClassroom = find_classroom($sourceClassroomId);
$sourceQuiz = $sourceClassroom ? classroom_quiz($sourceClassroom, $quizId) : null;

if (!$sourceClassroom || (int) $sourceClassroom['teacher_id'] !== (int) $teacher['id'] || !$sourceQuiz) {
    flash_set('danger', 'The activity could not be found.');
    redirect('/QuizWeb/classes.php');
}
if (!isset(game_modes()[$sourceQuiz['game_type'] ?? ''])) {
    flash_set('warning', 'That legacy game type is no longer available to reuse.');
    redirect('/QuizWeb/classroom.php?id=' . $sourceClassroomId . '&tab=quizzes');
}

$destinations = array_values(array_filter(
    teacher_classrooms((int) $teacher['id']),
    static fn(array $classroom): bool => (int) $classroom['id'] !== $sourceClassroomId
));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_form_csrf();
    $targetId = (int) ($_POST['target_classroom_id'] ?? 0);
    $target = null;
    foreach ($destinations as $candidate) if ((int) $candidate['id'] === $targetId) $target = $candidate;
    if (!$target) {
        $error = 'Choose one of your other classroom sections.';
    } else {
        try {
            $copy = reusable_quiz_copy($sourceQuiz);
            persist_builder_quiz(db(), $targetId, (int) $teacher['id'], $copy, true);
            flash_set('success', 'Activity reused in ' . $target['name'] . '. You can edit the copy independently.');
            redirect('/QuizWeb/classroom.php?id=' . $targetId . '&tab=quizzes');
        } catch (Throwable $exception) {
            error_log('Quiz reuse failed: ' . $exception->getMessage());
            $error = 'The activity could not be copied. Please try again.';
        }
    }
}

render_header('Reuse Activity', 'builder-page');
?>
<header class="page-heading"><div><span class="eyebrow"><?php echo esc($sourceClassroom['name']); ?></span><h1>Reuse <?php echo esc($sourceQuiz['title']); ?></h1><p>Copy this activity into another classroom section you teach.</p></div></header>
<section class="glass panel">
    <div class="section-heading"><div><span class="eyebrow">Independent copy</span><h2>Choose the destination section</h2><p>Questions, answers, points, game mode, deadline, and gameplay settings will be copied. Grade-category assignment will be reset so you can match the destination section's gradebook.</p></div></div>
    <?php if ($error): ?><p class="inline-error" role="alert"><?php echo esc($error); ?></p><?php endif; ?>
    <?php if ($destinations): ?>
    <form method="post" class="stack-form">
        <input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>"><input type="hidden" name="classroom_id" value="<?php echo $sourceClassroomId; ?>"><input type="hidden" name="quiz_id" value="<?php echo $quizId; ?>">
        <label><span>Destination classroom / section</span><select name="target_classroom_id" required><option value="">Choose a section</option><?php foreach ($destinations as $destination): ?><option value="<?php echo (int) $destination['id']; ?>" <?php echo (int) ($_POST['target_classroom_id'] ?? 0) === (int) $destination['id'] ? 'selected' : ''; ?>><?php echo esc($destination['name'] . ' — ' . $destination['subject']); ?></option><?php endforeach; ?></select></label>
        <div class="action-row"><a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo $sourceClassroomId; ?>&amp;tab=quizzes">Cancel</a><button class="button button-primary" type="submit">Reuse activity</button></div>
    </form>
    <?php else: ?><div class="empty-state"><h2>No other sections yet</h2><p>Create another classroom section before reusing this activity.</p><a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo $sourceClassroomId; ?>&amp;tab=quizzes">Back to activities</a></div><?php endif; ?>
</section>
<?php render_footer(); ?>
