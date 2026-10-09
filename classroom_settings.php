<?php
require_once __DIR__ . '/includes/layout.php'; require_once __DIR__ . '/includes/academic_ui.php';
$user = require_role('teacher'); $id = (int) ($_GET['classroom_id'] ?? 0); $classroom = find_classroom($id);
if (!$classroom || (int) $classroom['teacher_id'] !== (int) $user['id']) { http_response_code(403); exit('Classroom access denied.'); }
$GLOBALS['quizweb_current_classroom_id'] = $id; $meta = academic_meta($classroom); $settings = academic_settings(); $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_form_csrf();
    try {
        $name = grading_text($_POST['name'] ?? null, 'Classroom name', 190); $subject = grading_text($_POST['subject'] ?? null, 'Subject name', 190);
        $validated = academic_validate_meta($_POST, $settings); $pdo = db(); $pdo->beginTransaction();
        $select = $pdo->prepare('SELECT * FROM classrooms WHERE id = ? FOR UPDATE'); $select->execute([$id]); $fresh = hydrate_classroom($select->fetch()); grading_require_teacher($fresh, $user);
        $fresh['name'] = $name; $fresh['subject'] = $subject; $fresh['updated_at'] = now_iso();
        insert_classroom_record($pdo, $fresh); academic_save_meta($pdo, $id, $validated); record_audit('classroom_academics_updated', (int) $user['id']); $pdo->commit();
        flash_set('success', 'Classroom details saved.'); redirect('/QuizWeb/classroom_settings.php?classroom_id=' . $id);
    } catch (Throwable $ex) { if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); $error = $ex instanceof InvalidArgumentException ? $ex->getMessage() : 'Classroom details could not be saved.'; }
}
render_header('Classroom Settings', 'gradebook-page', ['/QuizWeb/assets/css/gradebook.css']); ?>
<header class="page-heading"><div><span class="eyebrow"><?php echo esc($classroom['name']); ?></span><h1>Classroom settings</h1></div><a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo $id; ?>">Back to Classroom</a></header>
<?php if ($error): ?><p class="inline-error" role="alert"><?php echo esc($error); ?></p><?php endif; ?>
<section class="glass panel"><form method="post" class="stack-form"><input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>"><div class="split-fields"><label><span>Classroom name</span><input name="name" required maxlength="190" value="<?php echo esc($_POST['name'] ?? $classroom['name']); ?>"></label><label><span>Subject name</span><input name="subject" required maxlength="190" value="<?php echo esc($_POST['subject'] ?? $classroom['subject']); ?>"></label></div><label><span>Assigned teacher</span><input value="<?php echo esc($user['name']); ?>" readonly></label><?php render_academic_fields($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $meta, $settings); ?><p>Academic terms are configured by an administrator. Changing the term moves this subject to that semester in My Grades.</p><button class="button button-primary">Save Classroom Details</button><a class="button button-secondary" href="/QuizWeb/gradebook.php?classroom_id=<?php echo $id; ?>&amp;view=setup">Configure Grading</a></form></section>
<?php render_footer(); ?>
