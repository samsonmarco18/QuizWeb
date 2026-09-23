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
$attemptAnswers = $attempt['answers'] ?? [];
$isDisqualified = !empty($attemptAnswers['_disqualified']);
$learningSummary = (!$isDisqualified && $quiz) ? attempt_learning_summary($quiz, $attempt) : null;
$reviewRows = $learningSummary
    ? array_values(array_filter($learningSummary['rows'], function (array $row) {
        return !empty($row['counts_for_learning']);
    }))
    : [];
$studentPracticeQuestions = $user['role'] === 'student'
    ? learning_practice_questions(student_learning_profile((int) $user['id']))
    : [];

render_header('Results', 'results-page');
?>

<section class="results-shell glass">
    <span class="eyebrow"><?php echo $isDisqualified ? 'Quiz Rule Violation' : 'Game Complete'; ?></span>
    <h1><?php echo esc($attempt['quiz_title']); ?></h1>
    <p class="lead">
        <?php if ($isDisqualified): ?>
            This attempt was marked as 0 after the fullscreen/focus warning limit was exceeded.
        <?php else: ?>
            Great run, <?php echo esc($student['name'] ?? 'Player'); ?>. Here is the score snapshot from your latest game.
        <?php endif; ?>
    </p>
    <div class="feature-pills centered">
        <span><?php echo esc($classroom['name'] ?? 'Classroom'); ?></span>
        <span><?php echo esc($quiz['game_type'] ?? $attempt['game_type'] ?? 'Quiz mode'); ?></span>
        <span><?php echo $isDisqualified ? 'Marked 0' : 'Performance recap'; ?></span>
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

    <?php if ($learningSummary && $reviewRows): ?>
        <div class="result-learning-panel">
            <div class="section-heading result-section-heading">
                <div>
                    <span class="eyebrow">Learning review</span>
                    <h2>What this run revealed</h2>
                    <p class="muted"><?php echo esc($learningSummary['headline']); ?></p>
                </div>
            </div>

            <div class="learning-summary-grid">
                <article class="stat-card">
                    <strong><?php echo esc((string) count($learningSummary['correct_rows'])); ?></strong>
                    <span>Correct</span>
                </article>
                <article class="stat-card">
                    <strong><?php echo esc((string) count($learningSummary['weak_rows'])); ?></strong>
                    <span>Needs review</span>
                </article>
                <article class="stat-card">
                    <strong><?php echo esc((string) $learningSummary['accuracy']); ?>%</strong>
                    <span>Question accuracy</span>
                </article>
            </div>

            <div class="review-list">
                <?php foreach ($reviewRows as $row): ?>
                    <article class="review-card <?php echo $row['is_correct'] ? 'is-correct' : 'needs-work'; ?>">
                        <div class="review-card-top">
                            <span><?php echo esc($row['level_label']); ?></span>
                            <strong><?php echo esc($row['is_correct'] ? 'Mastered' : 'Needs practice'); ?></strong>
                        </div>
                        <h3><?php echo esc($row['prompt']); ?></h3>
                        <div class="review-answer-grid">
                            <p><span>Your answer</span><?php echo esc($row['submitted_answer']); ?></p>
                            <p><span>Correct answer</span><?php echo esc($row['correct_answer']); ?></p>
                        </div>
                        <p class="review-guidance"><?php echo esc($row['guidance']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif ($isDisqualified): ?>
        <div class="result-learning-panel">
            <p class="muted">Learning review is unavailable for disqualified attempts because no answer pattern was saved.</p>
        </div>
    <?php endif; ?>

    <div class="action-row centered">
        <a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo esc((string) $classroom['id']); ?>">Back to Classroom</a>
        <?php if ($quiz): ?>
            <a class="button button-primary" href="/QuizWeb/play.php?classroom_id=<?php echo esc((string) $classroom['id']); ?>&quiz_id=<?php echo esc((string) $attempt['quiz_id']); ?>">Play Again</a>
        <?php endif; ?>
        <?php if ($studentPracticeQuestions): ?>
            <a class="button button-secondary" href="/QuizWeb/practice.php?return=<?php echo rawurlencode('/QuizWeb/results.php?id=' . $attempt['id']); ?>">Focus Practice</a>
        <?php endif; ?>
    </div>
</section>

<?php render_footer(); ?>
