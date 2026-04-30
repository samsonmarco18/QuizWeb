<?php

require_once __DIR__ . '/includes/layout.php';

$user = require_login();
$attemptId = (int) ($_GET['id'] ?? 0);
$attempt = null;

foreach (attempts() as $record) {
    if ((int) $record['id'] === $attemptId) {
        $attempt = $record;
        break;
    }
}

if (!$attempt) {
    flash_set('danger', 'Result not found.');
    redirect('/QuizWeb/dashboard.php');
}

$classroom = find_classroom((int) $attempt['classroom_id']);

if (!$classroom || !classroom_belongs_to_user($classroom, $user)) {
    flash_set('danger', 'Access denied.');
    redirect('/QuizWeb/dashboard.php');
}

$quiz = classroom_quiz($classroom, (int) $attempt['quiz_id']);
$scorePercent = percentage((int) $attempt['score'], (int) $attempt['max_score']);
$student = find_user_by_id((int) $attempt['student_id']);

render_header('Results', 'results-page');
?>

<section class="results-shell glass">
    <span class="eyebrow">Game Complete</span>
    <h1><?php echo esc($attempt['quiz_title']); ?></h1>
    <p class="lead">Great run, <?php echo esc($student['name'] ?? 'Player'); ?>. Here is the score snapshot from your latest game.</p>
    <div class="feature-pills centered">
        <span><?php echo esc($classroom['name'] ?? 'Classroom'); ?></span>
        <span><?php echo esc($quiz['game_type'] ?? 'Quiz mode'); ?></span>
        <span>Performance recap</span>
    </div>

    <div class="result-score-ring" style="background:
        radial-gradient(circle at center, rgba(7, 25, 47, 0.92) 0 42%, transparent 42%),
        conic-gradient(var(--accent) 0 <?php echo esc((string) $scorePercent); ?>%, rgba(255, 255, 255, 0.08) <?php echo esc((string) $scorePercent); ?>% 100%);">
        <div>
            <strong><?php echo esc((string) $scorePercent); ?>%</strong>
            <span><?php echo esc($attempt['score'] . ' / ' . $attempt['max_score'] . ' points'); ?></span>
        </div>
    </div>

    <div class="stat-grid">
        <article class="stat-card">
            <strong><?php echo esc((string) count($quiz['questions'] ?? [])); ?></strong>
            <span>Questions</span>
        </article>
        <article class="stat-card">
            <strong><?php echo esc((string) max(1, (int) $attempt['elapsed_seconds'])); ?>s</strong>
            <span>Time Used</span>
        </article>
        <article class="stat-card">
            <strong><?php echo esc(format_date($attempt['played_at'])); ?></strong>
            <span>Played At</span>
        </article>
    </div>

    <div class="action-row centered">
        <a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo esc((string) $classroom['id']); ?>">Back to Classroom</a>
        <a class="button button-primary" href="/QuizWeb/play.php?classroom_id=<?php echo esc((string) $classroom['id']); ?>&quiz_id=<?php echo esc((string) $attempt['quiz_id']); ?>">Play Again</a>
    </div>
</section>

<?php render_footer(); ?>
