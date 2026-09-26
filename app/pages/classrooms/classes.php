<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
$user = require_login();
$myClassrooms = user_classrooms($user);
render_header('My Classes', 'classes-page');
?>
<header class="page-heading">
    <div><span class="eyebrow">Your learning spaces</span><h1>My Classes</h1><p>Choose a classroom to see its quizzes, materials, and results.</p></div>
    <?php if ($user['role'] === 'student'): ?><a class="button button-primary" href="/QuizWeb/join.php">Join Class</a><?php endif; ?>
</header>
<section class="class-directory" aria-label="Your classrooms">
    <?php foreach ($myClassrooms as $classroom): ?>
        <article class="glass panel directory-card">
            <span class="directory-icon" aria-hidden="true"><?php echo nav_icon('Focus Practice'); ?></span>
            <div><span class="eyebrow"><?php echo esc($classroom['subject']); ?></span><h2><?php echo esc($classroom['name']); ?></h2><p><?php echo esc(classroom_teacher_name($classroom)); ?></p></div>
            <div class="card-meta"><span><?php echo count($classroom['quizzes'] ?? []); ?> quizzes</span><span><?php echo count($classroom['student_ids'] ?? []); ?> students</span></div>
            <a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo (int) $classroom['id']; ?>">Open Classroom</a>
        </article>
    <?php endforeach; ?>
    <?php if (!$myClassrooms): ?><div class="glass panel empty-state"><h2>No classes yet</h2><p><?php echo $user['role'] === 'student' ? 'Join a class using the code from your teacher.' : 'Create your first classroom from the dashboard.'; ?></p><a class="button button-secondary" href="/QuizWeb/<?php echo $user['role'] === 'student' ? 'join' : 'dashboard'; ?>.php"><?php echo $user['role'] === 'student' ? 'Join Class' : 'Open Dashboard'; ?></a></div><?php endif; ?>
</section>
<?php render_footer(); ?>
