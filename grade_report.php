<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/grading_ui.php';
$user = require_role('student'); $subjects = [];
$year = is_string($_GET['year'] ?? null) ? $_GET['year'] : '';
$semester = is_string($_GET['semester'] ?? null) ? $_GET['semester'] : '';
foreach (student_classrooms((int)$user['id']) as $class) {
    $meta = academic_meta($class);
    if (($year && $meta['academic_year'] !== $year) || ($semester && $meta['semester'] !== $semester)) continue;
    $book = grading_load(db(), (int)$class['id']); $grade = grading_published_student($book,$class,$user);
    $subjects[] = ['classroom'=>$class,'meta'=>$meta,'grade'=>$grade,'finalized'=>$grade && !empty($book['published']['finalized']),'published_at'=>$grade ? $book['published']['at'] : null];
}
$average = academic_semester_average($subjects,academic_settings());
if (!$year || !$semester || $year === 'Unassigned' || $semester === 'Unassigned') $average = ['value'=>null,'reason'=>'Choose an assigned academic year and semester to calculate an average.'];
header('Cache-Control: no-store');
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="student-grade-report.csv"');
    $out=fopen('php://output','w'); $safe=fn($value)=>preg_match('/^[=+@\-\t\r]/',(string)$value)?"'".$value:$value;
    fputcsv($out,['Student','Subject code','Subject','Academic year','Semester','Final grade','Release status','Released at']);
    foreach ($subjects as $s) fputcsv($out,array_map($safe,[$user['name'],$s['meta']['subject_code'],$s['classroom']['subject'],$s['meta']['academic_year'],$s['meta']['semester'],$s['grade']['overall'] ?? '',!$s['grade']?'Pending':($s['finalized']?'Finalized':'Published'),$s['published_at'] ?? '']));
    fclose($out); exit;
}
render_header('Grade report','student-page student-portal-page',['/QuizWeb/assets/css/student-portal.css']);
?>
<header class="page-heading"><div><h1>Grade report</h1><p><?php echo esc($user['name'].' · '.$year.' · '.$semester); ?></p></div><div class="action-row report-actions"><a class="button button-secondary" href="/QuizWeb/grades.php?year=<?php echo rawurlencode($year); ?>&semester=<?php echo rawurlencode($semester); ?>">Back</a><a class="button button-primary" href="?year=<?php echo rawurlencode($year); ?>&semester=<?php echo rawurlencode($semester); ?>&export=csv">Download CSV</a><button class="button button-secondary" type="button" onclick="window.print()">Print / Save PDF</button></div></header>
<section class="glass panel"><p class="muted">Generated <?php echo esc(format_date(now_iso())); ?> from your published results. Published grades may change until finalized.</p><div class="portal-table-scroll"><table class="portal-table"><thead><tr><th>Subject code</th><th>Subject</th><th>Final grade</th><th>Release</th></tr></thead><tbody><?php foreach ($subjects as $s): ?><tr><td><?php echo esc($s['meta']['subject_code']); ?></td><th><?php echo esc($s['classroom']['subject']); ?></th><td><?php echo esc(grade_display($s['grade']['overall'] ?? null)); ?></td><td><?php echo !$s['grade']?'Pending':($s['finalized']?'Finalized':'Published'); ?></td></tr><?php endforeach; ?></tbody></table></div><p><strong><?php echo academic_settings()['average_method']==='mean'?'Semester average':'Semester GPA'; ?>: <?php echo esc(grade_display($average['value'])); ?></strong></p><p class="muted"><?php echo esc($average['reason']); ?></p></section>
<style>@media print { .site-header,.classroom-sidebar,.report-actions,.ambient,.screen-mesh,.floating-chat,.chat-widget { display:none!important } body, .page-shell { margin:0!important;padding:0!important;background:white!important;color:black!important } .portal-table :is(th,td) { display:table-cell!important } .portal-table tr { display:table-row!important } .portal-table thead { display:table-header-group!important } .panel { box-shadow:none!important;background:white!important } }</style>
<?php render_footer(); ?>
