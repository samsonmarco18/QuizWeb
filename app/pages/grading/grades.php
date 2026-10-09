<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/grading_ui.php';
$user = require_role('student');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(403); exit('Students cannot modify grades.'); }
if (isset($_GET['student_id']) && (!is_string($_GET['student_id']) || $_GET['student_id'] !== (string) $user['id'])) { http_response_code(403); exit('You can only view your own grades.'); }
$classroomId = (int) ($_GET['classroom_id'] ?? 0);
$selectedClass = $classroomId ? find_classroom($classroomId) : null;
if ($classroomId && (!$selectedClass || !classroom_belongs_to_user($selectedClass, $user))) { http_response_code(403); exit('Classroom access denied.'); }
$GLOBALS['quizweb_current_classroom_id'] = $classroomId;
$classes = student_classrooms((int) $user['id']); $settings = academic_settings(); $subjects = []; $years = []; $semesters = [];
foreach ($classes as $class) {
    $meta = academic_meta($class); $years[] = $meta['academic_year']; $semesters[] = $meta['semester'];
    $book = grading_load(db(), (int) $class['id']);
    // Only the signed-in student's officially published snapshot reaches the page.
    $grade = grading_published_student($book, $class, $user);
    $subjects[] = ['classroom' => $class, 'meta' => $meta, 'grade' => $grade, 'config' => $grade ? $book['published']['config'] : null,
        'finalized' => $grade && !empty($book['published']['finalized']), 'published_at' => $grade ? $book['published']['at'] : null];
}
$years = array_values(array_unique($years)); rsort($years);
$semesters = array_values(array_unique(array_merge($settings['semesters'], $semesters)));
$selectedMeta = $selectedClass ? academic_meta($selectedClass) : null;
$year = is_string($_GET['year'] ?? null) ? $_GET['year'] : ($selectedMeta['academic_year'] ?? $years[0] ?? '');
$semester = is_string($_GET['semester'] ?? null) ? $_GET['semester'] : ($selectedMeta['semester'] ?? '');
if ($semester === '') foreach ($subjects as $subject) if ($subject['meta']['academic_year'] === $year) { $semester = $subject['meta']['semester']; break; }
$termSubjects = array_values(array_filter($subjects, fn($s) => $s['meta']['academic_year'] === $year && $s['meta']['semester'] === $semester));
usort($termSubjects, fn($a, $b) => [$a['meta']['subject_code'], $a['classroom']['subject']] <=> [$b['meta']['subject_code'], $b['classroom']['subject']]);
$subjectPage = page_records($termSubjects, 'page', 10);
$average = academic_semester_average($termSubjects, $settings);
if ($year === 'Unassigned' || $semester === 'Unassigned') $average = ['value' => null, 'reason' => 'Your teacher needs to assign academic terms to these subjects.'];
$averageLabel = $settings['average_method'] === 'mean' ? 'Semester Average' : 'Semester GPA';
$finalizedCount = count(array_filter($termSubjects, fn($s) => $s['finalized']));
render_header('My Academic Grades', 'grades-page gradebook-page academic-page', ['/QuizWeb/assets/css/gradebook.css', '/QuizWeb/assets/css/academic-grades.css']);
?>
<header class="page-heading"><div><span class="eyebrow">My Grades</span><h1>My Academic Grades</h1><p>Your officially released grades across enrolled subjects.</p></div><a class="button button-secondary" href="<?php echo $selectedClass ? '/QuizWeb/classroom.php?id=' . $classroomId : '/QuizWeb/dashboard.php'; ?>">Back to <?php echo $selectedClass ? 'Classroom' : 'Dashboard'; ?></a></header>
<?php if (!$classes): ?><section class="glass panel empty-state"><h2>No enrolled subjects yet</h2><p>Join a classroom to see your released academic grades.</p><a class="button button-primary" href="/QuizWeb/join.php">Join Class</a></section>
<?php else: ?>
<form method="get" class="academic-filter-bar" data-auto-filter><label><span>Academic year</span><select name="year"><?php foreach ($years as $option): ?><option value="<?php echo esc($option); ?>" <?php echo $year === $option ? 'selected' : ''; ?>><?php echo esc($option); ?></option><?php endforeach; ?></select></label><label><span>Semester</span><select name="semester"><?php foreach ($semesters as $option): ?><option value="<?php echo esc($option); ?>" <?php echo $semester === $option ? 'selected' : ''; ?>><?php echo esc($option); ?></option><?php endforeach; ?></select></label><button class="button button-secondary">View Semester</button></form>
<div class="academic-student-summary"><article class="glass panel"><div><h2><?php echo esc($averageLabel); ?></h2><strong><?php echo $average['value'] === null ? 'Pending' : esc(grade_display($average['value'])); ?></strong><p class="muted"><?php echo esc($average['reason']); ?></p></div><?php echo nav_icon('My Grades'); ?></article><article class="glass panel"><div><h2>Total subjects</h2><strong><?php echo count($termSubjects); ?></strong><p class="muted"><?php echo $finalizedCount; ?> finalized · <?php echo count($termSubjects) - $finalizedCount; ?> awaiting final release</p></div><?php echo nav_icon('Focus Practice'); ?></article></div>
<section class="glass panel academic-gradebook"><div class="academic-panel-heading"><h2>Subjects and grades</h2><span class="muted"><?php echo esc($semester . ' · ' . $year); ?></span></div>
<div class="academic-table-scroll"><table class="academic-table"><thead><tr><th>#</th><th>Subject code</th><th>Subject name</th><th>Prelim</th><th>Midterm</th><th>Finals</th><th>Final grade</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach ($subjectPage['items'] as $index => $subject): $class = $subject['classroom']; $grade = $subject['grade']; ?><tr><td><?php echo ($subjectPage['page'] - 1) * 10 + $index + 1; ?></td><td><?php echo esc($subject['meta']['subject_code'] ?: '—'); ?></td><th><?php echo esc($class['subject']); ?><small><?php echo esc($class['name']); ?></small></th><?php foreach (['prelim', 'midterm', 'finals'] as $period): ?><td><?php echo esc(grade_display($grade['periods'][$period]['overall'] ?? null)); ?></td><?php endforeach; ?><td><strong class="academic-grade-value"><?php echo esc(grade_display($grade['overall'] ?? null)); ?></strong></td><td><span class="academic-status <?php echo $subject['finalized'] ? 'is-complete' : 'is-pending'; ?>"><?php echo !$grade ? 'Not Released' : ($subject['finalized'] ? 'Finalized' : 'Published · ' . $grade['status']); ?></span></td><td><?php if ($grade): ?><button class="button button-secondary" type="button" data-open-grade="subject-<?php echo (int) $class['id']; ?>">View Breakdown</button><?php else: ?><span class="muted">Awaiting release</span><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$termSubjects): ?><tr><td colspan="9" class="academic-empty">No enrolled subjects for this academic year and semester.</td></tr><?php endif; ?></tbody></table></div>
<?php render_pagination($subjectPage); ?>
<footer class="academic-semester-footer"><div><strong><?php echo esc($averageLabel); ?></strong><p><?php echo $settings['average_method'] === 'mean' ? 'Simple mean of finalized, officially released final subject grades.' : 'Institutional grade equivalents weighted by subject credit units.'; ?></p></div><strong><?php echo $average['value'] === null ? 'Pending' : esc(grade_display($average['value'])); ?></strong></footer>
</section>
<?php foreach ($subjectPage['items'] as $subject): if (!$subject['grade']) continue; $class = $subject['classroom']; ?>
<dialog class="academic-drawer" id="subject-<?php echo (int) $class['id']; ?>" aria-labelledby="subject-title-<?php echo (int) $class['id']; ?>"><header><h2 id="subject-title-<?php echo (int) $class['id']; ?>"><?php echo esc($class['subject']); ?></h2><button type="button" class="academic-close" data-close-grade aria-label="Close subject breakdown">×</button></header><div class="academic-drawer-body"><p class="muted"><?php echo esc($subject['meta']['subject_code'] . ' · ' . $subject['meta']['semester']); ?> · Released <?php echo esc(format_date($subject['published_at'])); ?></p><?php render_grade_breakdown($subject['grade'], $subject['config']); ?></div></dialog>
<?php endforeach; endif; ?>
<?php render_footer(['/QuizWeb/assets/js/academic-grades.js']); ?>
