<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/grading_ui.php';
require_once dirname(__DIR__, 3) . '/includes/academic_ui.php';
$user = require_role('teacher');
$classroomId = (int) ($_GET['classroom_id'] ?? 0);
$classroom = find_classroom($classroomId);
if (!$classroom || (int) $classroom['teacher_id'] !== (int) $user['id']) { http_response_code(403); exit('Gradebook access denied.'); }
$GLOBALS['quizweb_current_classroom_id'] = $classroomId;
$views = ['overview' => 'Gradebook', 'items' => 'Activities', 'setup' => 'Grading structure', 'analytics' => 'Analytics', 'audit' => 'Grade history'];
$view = is_string($_GET['view'] ?? null) ? $_GET['view'] : 'overview';
if (!isset($views[$view]) && !in_array($view, ['student', 'entry'], true)) $view = 'overview';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_form_csrf();
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $input = $_POST;
    if ($action === 'config') $input['config'] = is_string($_POST['config_payload'] ?? null) && strlen($_POST['config_payload']) <= 20000 ? json_decode($_POST['config_payload'], true) : null;
    try {
        grading_mutate(db(), $classroomId, $user, $action, $input);
        flash_set('success', $action === 'publish' ? 'Grade release published to enrolled students.' : 'Gradebook changes saved.');
        $params = ['classroom_id' => $classroomId, 'view' => $view];
        foreach (['student_id', 'item_id', 'page'] as $key) if (isset($_GET[$key]) && is_scalar($_GET[$key])) $params[$key] = $_GET[$key];
        redirect('/QuizWeb/gradebook.php?' . http_build_query($params));
    } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
    catch (Throwable $exception) { error_log('Gradebook save failed: ' . $exception->getMessage()); $error = 'Could not save grades. Your entered values are still here. Please retry.'; }
}
$book = grading_load(db(), $classroomId);
$students = classroom_students($classroom);
usort($students, fn($a, $b) => [$a['name'], $a['id']] <=> [$b['name'], $b['id']]);
$select = db()->prepare('SELECT * FROM attempts WHERE classroom_id = ? ORDER BY id'); $select->execute([$classroomId]); $attempts = array_map('hydrate_attempt', $select->fetchAll());
$rows = [];
if ($book['config']) foreach ($students as $student) $rows[(int) $student['id']] = grading_calculate($book, (int) $student['id'], $attempts);
$studentId = (int) ($_GET['student_id'] ?? 0);
if ($view === 'student' && !isset($rows[$studentId])) { http_response_code(404); exit('Enrolled student not found.'); }
$itemId = is_string($_GET['item_id'] ?? null) ? $_GET['item_id'] : '';
$item = $book['items'][$itemId] ?? null;
if ($view === 'entry' && (!$item || $item['source'] !== 'manual' || !empty($item['archived']))) { http_response_code(404); exit('Active manual activity not found.'); }
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="classroom-grades.csv"');
    $out = fopen('php://output', 'w'); fputcsv($out, ['Student', 'Prelim', 'Midterm', 'Finals', 'Final grade', 'Status']);
    foreach ($students as $student) {
        $row = $rows[$student['id']] ?? null;
        $name = preg_match('/^[=+@\-\t\r]/', $student['name']) ? "'" . $student['name'] : $student['name'];
        fputcsv($out, [$name, $row['periods']['prelim']['overall'] ?? '', $row['periods']['midterm']['overall'] ?? '', $row['periods']['finals']['overall'] ?? '', $row['overall'] ?? '', $row['status'] ?? 'Not Graded']);
    }
    fclose($out); exit;
}
$url = '/QuizWeb/gradebook.php?classroom_id=' . $classroomId;
render_header('Gradebook', 'gradebook-page academic-page', ['/QuizWeb/assets/css/gradebook.css', '/QuizWeb/assets/css/academic-grades.css']);
?>
<header class="page-heading"><div><span class="eyebrow"><?php echo esc($classroom['name']); ?> · Teacher gradebook</span><h1><?php echo esc($views[$view] ?? ($view === 'entry' ? 'Enter scores' : 'Student breakdown')); ?></h1><p>Draft calculations use saved scores. Students see their own published release.</p></div><a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo $classroomId; ?>">Back to Classroom</a></header>
<nav class="classroom-tabs" aria-label="Gradebook sections"><?php foreach ($views as $key => $label): ?><a href="<?php echo esc($url . '&view=' . $key); ?>" <?php echo $key === $view ? 'class="is-active" aria-current="page"' : ''; ?>><?php echo esc($label); ?></a><?php endforeach; ?></nav>
<?php if ($error): ?><p class="inline-error" role="alert"><?php echo esc($error); ?></p><?php endif; ?>
<?php if (!$book['config'] && $view !== 'setup'): ?>
<section class="glass panel empty-state"><h2>Set up classroom grading</h2><p>Choose your categories, weights, passing grade, and scale before assigning graded activities.</p><a class="button button-primary" href="<?php echo esc($url . '&view=setup'); ?>">Set Up Grading</a></section>
<?php elseif ($view === 'setup'): ?>
<section class="glass panel">
    <form method="post" id="grading-setup" class="stack-form" novalidate>
        <?php render_grade_form_fields($book); ?><input type="hidden" name="action" value="config"><input type="hidden" name="config_payload" id="grading-config-payload">
        <h2>Choose a grading template or build your own</h2>
        <p>Templates are editable starting points. Review the categories, weights, scale, and passing grade, then save your classroom grading structure.</p>
        <label><span>Grading template</span><select id="grading-template"><option value="">Choose a template</option><?php foreach (grading_templates() as $id => $template): ?><option value="<?php echo esc($id); ?>"><?php echo esc($template['name']); ?></option><?php endforeach; ?><option value="custom">Custom — build manually</option></select></label>
        <div id="grading-template-summary" class="learning-summary-grid"></div>
        <button type="button" id="apply-grading-template" class="button button-secondary">Use Selected Template</button>
        <label><span>Reason for changing a structure with recorded or published grades</span><input name="reason" maxlength="500" value="<?php echo esc(is_string($_POST['reason'] ?? null) ? $_POST['reason'] : ''); ?>"></label>
        <fieldset class="academic-period-settings"><legend>Grading periods</legend><label class="grade-confirm"><input type="checkbox" id="enable-academic-periods" <?php echo !empty($book['config']['periods']) || !$book['config'] ? 'checked' : ''; ?>><span>Use Prelim, Midterm, and Finals</span></label><p>Configure period weights totaling 100%. Assign older activities to periods before saving.</p><div id="grading-period-weights"></div><p id="grading-period-total" role="status"></p></fieldset>
        <?php if ($book['items']): ?><details class="academic-migration"><summary>Assign existing activities to grading periods</summary><div class="split-fields"><?php foreach ($book['items'] as $id => $entry): if (!empty($entry['archived'])) continue; ?><label><span><?php echo esc($entry['name']); ?></span><select name="period_assignments[<?php echo esc($id); ?>]"><?php render_period_options($_POST['period_assignments'][$id] ?? $entry['period_id'] ?? ''); ?></select></label><?php endforeach; ?></div></details><?php endif; ?>
        <p>Changing a template updates the draft only after you save. Existing activity scores remain; categories used by active activities must be retained.</p>
        <nav class="builder-steps" aria-label="Grading setup steps"><?php foreach (['Categories', 'Weights', 'Grade Scale', 'Review'] as $index => $label): ?><button type="button" data-grading-step="<?php echo $index; ?>"><?php echo ($index + 1) . '. ' . esc($label); ?></button><?php endforeach; ?></nav>
        <p id="grading-setup-status" role="status" aria-live="polite"></p>
        <section data-grading-panel="0"><h2>Your grading categories</h2><p>Rename and reorder categories. Categories used by active items must be reassigned or archived before removal.</p><div id="grading-categories"></div><button type="button" class="button button-secondary" id="add-grading-category">Add Category</button></section>
        <section data-grading-panel="1" hidden><h2>Category weights</h2><div id="grading-weights"></div><p id="grading-weight-total" role="status"></p><progress id="grading-weight-progress" max="100" value="0" aria-label="Configured category weight"></progress><p>Category calculation: earned points ÷ possible points × 100. Items with larger maximum scores have proportionally more influence.</p></section>
        <section data-grading-panel="2" hidden><h2>Grade scale and rules</h2><label><span>Passing grade (%)</span><input name="passing" type="number" min="0" max="100" step="0.01" value="<?php echo esc((string) ($book['config']['passing'] ?? 75)); ?>"></label>
        <label><span>Blank / missing scores</span><select name="missing_policy"><option value="exclude">Exclude ungraded scores; normalize available category weights</option><option value="zero" <?php echo ($book['config']['missing_policy'] ?? '') === 'zero' ? 'selected' : ''; ?>>Count assigned missing scores as zero</option></select></label>
        <p>Each band starts at its minimum and continues up to the next minimum. The first band must start at 0; the last includes 100.</p><div id="grading-scale"></div><button type="button" class="button button-secondary" id="add-grading-band">Add Scale Band</button></section>
        <section data-grading-panel="3" hidden><h2>Review the formula</h2><div id="grading-setup-review"></div><button type="submit" class="button button-primary">Save Grading Structure</button><p>Saving changes the draft calculation. Publish a release to update student grades.</p></section>
        <div class="action-row builder-toolbar"><button type="button" class="button button-secondary" id="grading-previous">Previous</button><button type="button" class="button button-primary" id="grading-next">Next</button></div>
    </form>
</section>
<?php $configSeed = $book['config'] ?? ['categories' => [], 'scale' => [['min' => 0, 'label' => 'Below passing'], ['min' => 75, 'label' => 'Passed']], 'passing' => 75, 'missing_policy' => 'exclude']; if ($error && isset($input['config']) && is_array($input['config'])) $configSeed = $input['config']; ?>
<script>window.gradingConfigSeed = <?php echo json_encode($configSeed, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>; window.gradingTemplates = <?php echo json_encode(grading_templates(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>; window.gradingUsedCategories = <?php echo json_encode(array_values(array_unique(array_column(array_filter($book['items'], fn($item) => empty($item['archived'])), 'category_id'))), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>; window.gradingPostedDraft = <?php echo $error ? 'true' : 'false'; ?>;</script>
<?php elseif ($view === 'overview'): require dirname(__DIR__, 3) . '/includes/academic_teacher_view.php'; ?>
<?php elseif ($view === 'items'): $edit = $item && $item['source'] === 'manual' ? $item : []; ?>
<section class="glass panel"><details <?php echo $error || $edit ? 'open' : ''; ?>><summary><?php echo $edit ? 'Edit manual activity' : 'Add Manual Activity'; ?></summary><form method="post" class="stack-form" data-grade-dirty><?php render_grade_form_fields($book); ?><input type="hidden" name="action" value="item"><input type="hidden" name="item_id" value="<?php echo esc($edit['id'] ?? ''); ?>"><?php if (!empty($book['config']['periods'])): ?><label><span>Grading period</span><select name="period_id" required><?php render_period_options($_POST['period_id'] ?? $edit['period_id'] ?? ''); ?></select></label><?php endif; ?><label><span>Correction reason (required when changing an assessment with saved scores)</span><input name="reason" maxlength="500" value="<?php echo esc($_POST['reason'] ?? ''); ?>"></label><div class="split-fields"><label><span>Activity name</span><input name="name" maxlength="120" required value="<?php echo esc(is_string($_POST['name'] ?? null) ? $_POST['name'] : ($edit['name'] ?? '')); ?>"></label><label><span>Category</span><select name="category_id" required><?php render_grading_category_options($book, is_string($_POST['category_id'] ?? null) ? $_POST['category_id'] : ($edit['category_id'] ?? '')); ?></select></label><label><span>Maximum score</span><input name="max_score" type="number" min="0.01" max="100000" step="0.01" required value="<?php echo esc(is_string($_POST['max_score'] ?? null) ? $_POST['max_score'] : (string) ($edit['max_score'] ?? 100)); ?>"></label><label><span>Date</span><input name="date" type="date" required value="<?php echo esc(is_string($_POST['date'] ?? null) ? $_POST['date'] : ($edit['date'] ?? date('Y-m-d'))); ?>"></label></div><button class="button button-primary">Save Activity</button></form></details>
<div class="grade-activities-heading"><h2>Classroom activities</h2><span><?php echo count($book['items']); ?> activities</span></div>
<div class="grade-activity-list">
<?php $itemPage = page_records(array_values($book['items']), 'page', 10); foreach ($itemPage['items'] as $entry): $entryCategory = grading_category($book, $entry['category_id']); ?>
    <article class="recent-item grade-activity-card">
        <div class="grade-activity-info"><h3><?php echo esc($entry['name']); ?></h3><div class="grade-activity-meta"><span class="grade-activity-kind"><?php echo $entry['source'] === 'manual' ? 'Manual activity' : 'Quiz'; ?></span><span><?php echo esc(grade_display($entry['max_score'])); ?> points</span><?php if ($entryCategory): ?><span><?php echo esc($entryCategory['name']); ?></span><?php if (!empty($entry['period_id'])): ?><span><?php echo esc(ucfirst($entry['period_id'])); ?></span><?php endif; ?><?php endif; ?><?php if (!empty($entry['archived'])): ?><span>Archived · excluded from grades</span><?php endif; ?></div></div>
        <?php if (empty($entry['archived'])): ?>
        <div class="action-row grade-activity-actions">
            <?php if ($entry['source'] === 'manual'): ?><a class="button button-primary" href="<?php echo esc($url . '&view=entry&item_id=' . $entry['id']); ?>">Enter Scores</a><a class="button button-secondary" href="<?php echo esc($url . '&view=items&item_id=' . $entry['id']); ?>">Edit Activity</a>
            <?php else: ?><a class="button button-secondary" href="/QuizWeb/quiz_builder.php?classroom_id=<?php echo $classroomId; ?>&amp;quiz_id=<?php echo (int) $entry['quiz_id']; ?>">Edit Quiz Grading</a><?php endif; ?>
        </div>
        <details class="grade-activity-archive"><summary>Archive activity</summary>
            <form method="post" class="stack-form" data-grade-dirty>
                <?php render_grade_form_fields($book); ?><input type="hidden" name="action" value="archive"><input type="hidden" name="item_id" value="<?php echo esc($entry['id']); ?>">
                <p>Exclude this activity from draft grades. Saved scores and earlier published grades remain intact.</p>
                <label class="grade-confirm"><input type="checkbox" name="confirm" value="yes" required><span>Confirm archiving <?php echo esc($entry['name']); ?></span></label>
                <button class="button button-secondary">Archive Activity</button>
            </form>
        </details>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
</div>
<?php if (!$book['items']): ?><div class="empty-state"><h3>No activities yet</h3><p>Add a manual activity above or assign a grading category to a quiz in its builder.</p></div><?php endif; render_pagination($itemPage); ?></section>
<?php elseif ($view === 'entry'): $entryPage = page_records($students, 'page', 20); ?>
<section class="glass panel"><h2><?php echo esc($item['name'] . ' — ' . grade_display($item['max_score']) . ' points'); ?></h2><p>Tab moves between scores. Blank means Not Yet Graded; zero is a recorded score. Save this page before moving to another page.</p><form method="post" class="stack-form" data-grade-dirty><?php render_grade_form_fields($book); ?><input type="hidden" name="action" value="scores"><input type="hidden" name="item_id" value="<?php echo esc($itemId); ?>"><label><span>Correction reason (required when changing a recorded score)</span><input name="reason" maxlength="500" value="<?php echo esc($_POST['reason'] ?? ''); ?>"></label><div class="grade-score-list"><?php foreach ($entryPage['items'] as $student): $sid = (int) $student['id']; $savedScore = $book['scores'][$itemId][(string) $sid] ?? null; $posted = $_POST['scores'][$sid] ?? null; ?><label><span><?php echo esc($student['name']); ?></span><input type="number" name="scores[<?php echo $sid; ?>]" min="0" max="<?php echo esc((string) $item['max_score']); ?>" step="0.01" value="<?php echo esc(is_string($posted) ? $posted : ($savedScore === null ? '' : (string) $savedScore)); ?>" placeholder="Not Yet Graded"><small><?php echo isset($book['overrides'][(string) $sid][$itemId]) ? 'Adjustment active; open student to restore it.' : ($savedScore === null ? 'Not Yet Graded' : 'Graded'); ?></small></label><?php endforeach; ?></div><div class="action-row builder-toolbar"><button class="button button-primary">Save Scores</button><a class="button button-secondary" href="<?php echo esc($url . '&view=items'); ?>">Back to Activities</a></div></form><?php render_pagination($entryPage); ?></section>
<?php elseif ($view === 'student'): $selectedStudent = array_values(array_filter($students, fn($student) => (int) $student['id'] === $studentId))[0]; $grade = $rows[$studentId]; ?>
<header class="page-heading"><h2><?php echo esc($selectedStudent['name']); ?></h2><a class="button button-secondary" href="<?php echo esc($url); ?>">Back to Gradebook</a></header>
<?php render_grade_breakdown($grade, $book['config']); ?>
<?php require dirname(__DIR__, 3) . '/includes/academic_student_status_view.php'; ?>
<section class="glass panel"><details <?php echo $error ? 'open' : ''; ?>><summary>Adjust / Restore a Grade</summary><form method="post" class="stack-form" data-grade-dirty><?php render_grade_form_fields($book); ?><input type="hidden" name="student_id" value="<?php echo $studentId; ?>"><label><span>Grade to adjust</span><select name="item_id"><?php foreach ($grade['items'] as $id => $entry): ?><option value="<?php echo esc($id); ?>" <?php echo ($_POST['item_id'] ?? '') === $id ? 'selected' : ''; ?>><?php echo esc($entry['name'] . ' (0–' . $entry['max_score'] . ')'); ?></option><?php endforeach; ?></select></label><label><span>Adjusted value (leave blank when restoring)</span><input name="value" type="number" min="0" max="100000" step="0.01" value="<?php echo esc(is_string($_POST['value'] ?? null) ? $_POST['value'] : ''); ?>"></label><label><span>Correction reason (required)</span><textarea name="reason" maxlength="500" rows="2" required><?php echo esc(is_string($_POST['reason'] ?? null) ? $_POST['reason'] : ''); ?></textarea></label><div class="action-row"><button class="button button-primary" name="action" value="override">Save Adjustment</button><button class="button button-secondary" name="action" value="restore">Restore Automatic Grade</button></div></form><p>Assessment corrections use score units; final grades are calculated automatically. The original calculation and change history are preserved.</p></details></section>
<section class="glass panel"><details><summary>Quiz Attempt History</summary><?php $history = array_values(array_filter($attempts, fn($attempt) => (int) $attempt['student_id'] === $studentId)); $historyPage = page_records($history, 'attempt_page', 10); foreach ($historyPage['items'] as $attempt): ?><div class="recent-item"><strong><?php echo esc($attempt['quiz_title']); ?></strong><span><?php echo esc($attempt['score'] . '/' . $attempt['max_score'] . ' · ' . format_date($attempt['played_at'])); ?></span><a class="button button-secondary" href="/QuizWeb/results.php?id=<?php echo (int) $attempt['id']; ?>">View Attempt</a></div><?php endforeach; if (!$history): ?><p>No attempts yet.</p><?php endif; render_pagination($historyPage); ?></details></section>
<?php elseif ($view === 'analytics'): $numeric = array_values(array_filter(array_column($rows, 'overall'), fn($value) => $value !== null)); ?>
<?php if (!empty($book['config']['periods'])): require dirname(__DIR__, 3) . '/includes/academic_analytics_view.php'; else: ?>
<section class="glass panel"><h2>Draft grade analysis</h2><div class="learning-summary-grid"><article class="stat-card"><strong><?php echo $numeric ? esc(grade_display(array_sum($numeric) / count($numeric))) : '—'; ?></strong><span>Class average (<?php echo count($numeric); ?> graded students)</span></article><article class="stat-card"><strong><?php echo count(array_filter($rows, fn($row) => $row['status'] === 'Complete')); ?>/<?php echo count($students); ?></strong><span>Complete gradebooks</span></article></div>
<h3>Grade distribution</h3><?php foreach ($book['config']['scale'] as $band): ?><p><?php echo esc($band['label']); ?>: <?php echo count(array_filter($rows, fn($row) => $row['scale_label'] === $band['label'])); ?> students</p><?php endforeach; ?>
<h3>Category averages</h3><?php foreach ($book['config']['categories'] as $category): $values = []; foreach ($rows as $row) foreach ($row['categories'] as $calculatedCategory) if ($calculatedCategory['id'] === $category['id'] && $calculatedCategory['percentage'] !== null) $values[] = $calculatedCategory['percentage']; ?><p><?php echo esc($category['name']); ?>: <?php echo $values ? esc(grade_display(array_sum($values) / count($values))) . '%' : 'Not Graded'; ?></p><?php endforeach; ?>
<h3>Activity performance</h3><?php $performance = []; foreach ($book['items'] as $id => $entry) { if (!empty($entry['archived'])) continue; $values = []; foreach ($rows as $row) if (isset($row['items'][$id]) && $row['items'][$id]['percentage'] !== null) $values[] = $row['items'][$id]['percentage']; $performance[] = ['name' => $entry['name'], 'average' => $values ? array_sum($values) / count($values) : null, 'count' => count($values)]; } usort($performance, fn($a, $b) => ($a['average'] ?? INF) <=> ($b['average'] ?? INF)); $performancePage = page_records($performance); foreach ($performancePage['items'] as $entry): ?><p><?php echo esc($entry['name']); ?> · <?php echo esc(grade_display($entry['average'])); ?>% · <?php echo $entry['count']; ?>/<?php echo count($students); ?> graded</p><?php endforeach; render_pagination($performancePage); ?></section>
<?php endif; ?>
<?php elseif ($view === 'audit'): $auditPage = max(1, (int) ($_GET['page'] ?? 1)); $query = db()->prepare('SELECT COUNT(*) FROM grade_changes WHERE classroom_id = ?'); $query->execute([$classroomId]); $total = (int) $query->fetchColumn(); $auditPage = min($auditPage, max(1, (int) ceil($total / 15))); $query = db()->prepare('SELECT * FROM grade_changes WHERE classroom_id = ? ORDER BY id DESC LIMIT 15 OFFSET ' . (($auditPage - 1) * 15)); $query->execute([$classroomId]); ?>
<section class="glass panel"><p>Changes record the actor, student, activity, previous/new value, and timestamp.</p><?php foreach ($query->fetchAll() as $change): ?><details class="grade-audit-row"><summary><?php echo esc(ucfirst($change['action']) . ' · ' . format_date($change['created_at'])); ?></summary><p>Teacher #<?php echo (int) $change['actor_id']; ?> · Student <?php echo $change['student_id'] === null ? '—' : '#' . (int) $change['student_id']; ?> · Activity <?php echo esc($change['item_id'] ?? 'Class grading'); ?></p><pre><?php echo esc('Previous: ' . $change['previous_value'] . "\nNew: " . $change['new_value']); ?></pre></details><?php endforeach; if (!$total): ?><p>No grading changes yet.</p><?php endif; render_pagination(['page' => $auditPage, 'pages' => max(1, (int) ceil($total / 15)), 'key' => 'page']); ?></section>
<?php endif; ?>
<script>window.gradingPostedDraft = <?php echo $error ? 'true' : 'false'; ?>;</script>
<?php render_footer(['/QuizWeb/assets/js/grading.js', '/QuizWeb/assets/js/academic-grades.js']); ?>
