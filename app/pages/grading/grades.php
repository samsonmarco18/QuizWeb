<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/grading_ui.php';
$user = require_role('student');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(403); exit('Students cannot modify grades.'); }
if (isset($_GET['student_id']) && (!is_string($_GET['student_id']) || $_GET['student_id'] !== (string) $user['id'])) { http_response_code(403); exit('You can only view your own grades.'); }
$classroomId = (int) ($_GET['classroom_id'] ?? 0);
$classroom = $classroomId ? find_classroom($classroomId) : null;
if ($classroomId && (!$classroom || !classroom_belongs_to_user($classroom, $user))) { http_response_code(403); exit('Classroom access denied.'); }
$GLOBALS['quizweb_current_classroom_id'] = $classroomId;
render_header('My Grades', 'grades-page');
?>
<header class="page-heading"><div><span class="eyebrow">My Grades</span><h1><?php echo esc($classroom ? $classroom['name'] : 'Choose a class'); ?></h1><p>Your teacher’s published grade release.</p></div><a class="button button-secondary" href="<?php echo $classroom ? '/QuizWeb/classroom.php?id=' . $classroomId : '/QuizWeb/dashboard.php'; ?>">Back to <?php echo $classroom ? 'Classroom' : 'Dashboard'; ?></a></header>
<?php if (!$classroom): $classes = student_classrooms((int) $user['id']); $classPage = page_records($classes); ?>
    <section class="class-directory"><?php foreach ($classPage['items'] as $class): ?><article class="glass panel"><h2><?php echo esc($class['name']); ?></h2><p><?php echo esc($class['subject']); ?></p><a class="button button-primary" href="/QuizWeb/grades.php?classroom_id=<?php echo (int) $class['id']; ?>">View My Grades</a></article><?php endforeach; ?><?php if (!$classes): ?><article class="glass panel empty-state"><h2>No classes yet</h2><p>Join a classroom to see your published grades.</p><a class="button button-primary" href="/QuizWeb/join.php">Join Class</a></article><?php endif; ?></section>
    <?php render_pagination($classPage); ?>
<?php else: $book = grading_load(db(), $classroomId); $grade = grading_published_student($book, $classroom, $user); ?>
    <?php if ($grade): ?><p class="status-badge">Published <?php echo esc(format_date($book['published']['at'])); ?> · Draft changes are released by your teacher.</p><?php render_grade_breakdown($grade, $book['published']['config']); ?>
    <?php else: ?><section class="glass panel empty-state"><h2>Grades have not been released yet</h2><p>Your teacher will publish your grade breakdown after reviewing it. Quiz results remain available in Results.</p><a class="button button-secondary" href="/QuizWeb/student_results.php">View Quiz Results</a></section><?php endif; ?>
<?php endif; ?>
<?php render_footer(); ?>
