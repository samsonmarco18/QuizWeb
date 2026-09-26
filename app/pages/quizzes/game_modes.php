<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
$user = require_role('student');
$available = [];
foreach (student_classrooms((int) $user['id']) as $classroom) foreach ($classroom['quizzes'] ?? [] as $quiz) $available[] = ['classroom' => $classroom, 'quiz' => $quiz];
$modes = game_modes();
render_header('Game Modes', 'student-page modes-page');
?>
<header class="page-heading"><div><span class="eyebrow">Play game modes</span><h1>Available quizzes</h1><p>Choose an activity from one of your classes.</p></div></header>
<section class="glass panel compact-panel">
    <?php if ($available): ?><div class="quiz-grid compact-quiz-grid">
        <?php foreach ($available as $item): ?><article class="quiz-card"><span class="mode-badge"><?php echo esc($modes[$item['quiz']['game_type']]['label'] ?? 'Quiz'); ?></span><h2><?php echo esc($item['quiz']['title']); ?></h2><p><?php echo esc($item['classroom']['subject'] . ' · ' . classroom_teacher_name($item['classroom'])); ?></p><a class="button button-primary" href="/QuizWeb/play.php?classroom_id=<?php echo esc((string) $item['classroom']['id']); ?>&quiz_id=<?php echo esc((string) $item['quiz']['id']); ?>">Play</a></article><?php endforeach; ?>
    </div><?php else: ?><div class="empty-state"><?php echo nav_icon('Play Game Modes'); ?><h2>No quizzes available yet</h2><p>Join a class or wait for your teacher to publish an activity.</p><a class="button button-primary" href="/QuizWeb/join.php">Join Class</a></div><?php endif; ?>
</section>
<?php render_footer(); ?>
