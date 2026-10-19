<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
$user = require_login();
require_once dirname(__DIR__, 3) . '/includes/academic_ui.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') require_form_csrf();
require dirname(__DIR__, 3) . '/includes/class_creation_action.php';
$myClassrooms = user_classrooms($user);
if ($user['role'] === 'student') {
    $allClassrooms = $myClassrooms; $years=[]; $semesters=[];
    foreach ($myClassrooms as $class) { $meta=academic_meta($class); $years[]=$meta['academic_year']; $semesters[]=$meta['semester']; }
    $years=array_unique($years); $semesters=array_unique($semesters);
    $year=is_string($_GET['year'] ?? null)?$_GET['year']:''; $semester=is_string($_GET['semester'] ?? null)?$_GET['semester']:'';
    $myClassrooms=array_values(array_filter($myClassrooms,function($class)use($year,$semester){$meta=academic_meta($class); return (!$year || $meta['academic_year']===$year) && (!$semester || $meta['semester']===$semester);}));
}
$classPage = page_records($myClassrooms);
render_header('My Classes', 'classes-page' . ($user['role'] === 'student' ? ' student-portal-page' : ''), $user['role'] === 'student' ? ['/QuizWeb/assets/css/student-portal.css'] : ['/QuizWeb/assets/css/class-creation.css']);
?>
<header class="page-heading">
    <div><span class="eyebrow">Your learning spaces</span><h1>My Classes</h1><p>Choose a classroom to see its quizzes, materials, and results.</p></div>
    <?php if ($user['role'] === 'student'): ?><a class="button button-primary" href="/QuizWeb/join.php">Join Class</a><?php endif; ?>
    <?php if ($user['role'] === 'teacher'): ?><a class="button button-primary" href="/QuizWeb/classes.php?create=1#new-class">Create Classroom</a><?php endif; ?>
</header>
<?php if ($user['role'] === 'teacher' && (isset($_GET['create']) || $errors)): ?>
<section class="glass panel class-creation-panel" id="new-class"><div class="section-heading"><div><span class="eyebrow">New classroom</span><h2>Create a classroom</h2></div><a class="button button-secondary" href="/QuizWeb/classes.php" aria-label="Close class creation">&times;</a></div>
<?php require dirname(__DIR__, 3) . '/includes/class_creation_form.php'; ?></section>
<?php endif; ?>
<?php if ($user['role']==='student'): ?><form method="get" class="portal-filters"><label><span>Academic year</span><select name="year"><option value="">All years</option><?php foreach($years as $value): ?><option <?php echo $year===$value?'selected':''; ?>><?php echo esc($value); ?></option><?php endforeach; ?></select></label><label><span>Semester</span><select name="semester"><option value="">All semesters</option><?php foreach($semesters as $value): ?><option <?php echo $semester===$value?'selected':''; ?>><?php echo esc($value); ?></option><?php endforeach; ?></select></label><button class="button button-secondary">Apply</button><a class="button button-secondary" href="/QuizWeb/student_portal.php">Student portal</a></form><?php endif; ?>
<section class="class-directory" aria-label="Your classrooms">
    <?php foreach ($classPage['items'] as $classroom): ?>
        <article class="glass panel directory-card">
            <span class="directory-icon" aria-hidden="true"><?php echo nav_icon('Focus Practice'); ?></span>
            <div><span class="eyebrow"><?php echo esc($classroom['subject']); ?></span><h2><?php echo esc($classroom['name']); ?></h2><p><?php echo esc(classroom_teacher_name($classroom)); ?></p></div>
            <div class="card-meta"><span><?php echo count($classroom['quizzes'] ?? []); ?> quizzes</span><span><?php echo count($classroom['student_ids'] ?? []); ?> students</span></div>
            <?php if ($user['role']==='student'): $meta=academic_meta($classroom); ?><p class="portal-subject-meta"><?php echo esc(implode(' · ',array_filter([$meta['subject_code'],$meta['section'],$meta['semester'],$meta['academic_year']]))); ?></p><?php endif; ?>
            <a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo (int) $classroom['id']; ?>">Open Classroom</a>
        </article>
    <?php endforeach; ?>
    <?php if (!$myClassrooms): ?><div class="glass panel empty-state"><h2>No classes yet</h2><p><?php echo $user['role'] === 'student' ? 'Join a class using the code from your teacher.' : 'Create your first classroom to get started.'; ?></p><a class="button button-secondary" href="/QuizWeb/<?php echo $user['role'] === 'student' ? 'join' : 'classes'; ?>.php<?php echo $user['role'] === 'teacher' ? '?create=1#new-class' : ''; ?>"><?php echo $user['role'] === 'student' ? 'Join Class' : 'Create Classroom'; ?></a></div><?php endif; ?>
</section>
<?php render_pagination($classPage); ?>
<?php render_footer(['/QuizWeb/assets/js/class-creation.js']); ?>
