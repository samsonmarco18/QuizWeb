<?php

require_once dirname(__DIR__, 3) . '/includes/layout.php';

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

if (!$classroom || !can_view_attempt($classroom, $attempt, $user)) {
    flash_set('danger', 'Access denied.');
    redirect('/QuizWeb/dashboard.php');
}

$quiz = classroom_quiz($classroom, (int) $attempt['quiz_id']);
$quiz = attempt_quiz_version($quiz ?? [], $attempt) ?: null;
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
            This attempt was marked as 0 for a quiz security violation or leaving an active quiz.
        <?php else: ?>
            Great run, <?php echo esc($student['name'] ?? 'Player'); ?>. Here is the score snapshot from your latest game.
        <?php endif; ?>
    </p>
    <?php if (!empty($attemptAnswers['_violations'])): ?>
        <p class="quiz-warning-count">Warnings recorded: <?php echo (int) $attemptAnswers['_violations']; ?>
            <?php $securityReasons = ['fullscreen_exit' => 'Leaving fullscreen', 'focus_loss' => 'Moving focus away', 'tab_hidden' => 'Switching tabs or minimizing', 'screenshot_shortcut' => 'Detected screenshot shortcut', 'page_exit' => 'Leaving or reloading the quiz']; ?>
            <?php if (isset($securityReasons[$attemptAnswers['_reason'] ?? ''])): ?> ? <?php echo esc($securityReasons[$attemptAnswers['_reason']]); ?><?php endif; ?>
        </p>
    <?php endif; ?>
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
        <div class="result-learning-panel" id="answer-review">
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
        <?php if ($reviewRows): ?><a class="button button-secondary" href="#answer-review">Review Answers</a><?php endif; ?>
        <a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo esc((string) $classroom['id']); ?>">Back to Classroom</a>
        <?php if ($quiz): ?>
            <a class="button button-primary" href="/QuizWeb/play.php?classroom_id=<?php echo esc((string) $classroom['id']); ?>&quiz_id=<?php echo esc((string) $attempt['quiz_id']); ?>">Play Again</a>
        <?php endif; ?>
        <?php if ($studentPracticeQuestions): ?>
            <a class="button button-secondary" href="/QuizWeb/practice.php?return=<?php echo rawurlencode('/QuizWeb/results.php?id=' . $attempt['id']); ?>">Focus Practice</a>
        <?php endif; ?>
    </div>
    <?php if ($quiz && !empty($quiz['grade_category_id'])): ?><p class="muted">This game score is one attempt. Your classroom grade uses the teacher's configured <?php echo esc($quiz['grade_attempt_policy'] ?? 'highest'); ?> attempt policy, category weights, and any adjustments. Released grades appear under My Grades.</p><?php endif; ?>
    <?php if (!$isDisqualified && $quiz): ?>
        <?php
        $mode = $quiz['game_type'] ?? '';
        $bestStreak = 0; $currentStreak = 0;
        foreach ($reviewRows as $row) { $currentStreak = $row['is_correct'] ? $currentStreak + 1 : 0; $bestStreak = max($bestStreak, $currentStreak); }
        ?>
        <div class="game-intro-info">
        <?php if ($mode === 'flip_match'): ?><span><?php echo (int) ($attemptAnswers['_moves'] ?? 0); ?> moves · <?php echo count($learningSummary['correct_rows'] ?? []); ?> pairs matched</span>
        <?php elseif ($mode === 'crossword'): ?><span><?php echo count($learningSummary['correct_rows'] ?? []); ?> / <?php echo count($quiz['questions']); ?> words solved</span>
        <?php elseif ($mode === 'master_ladder'): ?>
            <?php $reached = 'Easy'; foreach (mastery_levels() as $level => $label) foreach ($reviewRows as $row) if ($row['level'] === $level) $reached = $label; ?>
            <span>Highest level reached: <?php echo esc($reached); ?></span>
        <?php elseif ($mode === 'boss_battle'): ?>
            <?php $damage = max(4, (int) ceil(100 / max(1, count($quiz['questions'])))); $bossHits = 0; $shieldHits = 0; foreach ($reviewRows as $row) if (array_key_exists($row['index'], $attemptAnswers)) { if ($row['is_correct']) $bossHits++; else $shieldHits++; } ?>
            <span><?php echo $bossHits * $damage >= 100 ? 'Boss defeated' : ($shieldHits * $damage >= 100 ? 'Shield exhausted' : 'Run finished'); ?> · Game health is separate from academic points</span>
        <?php else: ?><span>Best streak: <?php echo $bestStreak; ?> correct answers</span><?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
