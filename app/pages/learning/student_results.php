<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
$user = require_role('student');
$studentAttempts = student_attempts((int) $user['id']);
$resultsPage = page_records($studentAttempts);
render_header('Results', 'student-page results-history-page');
?>
<header class="page-heading"><div><span class="eyebrow">Track scores</span><h1>Results</h1><p>Review quiz and focus-training history.</p></div></header>
<section class="glass panel compact-panel">
    <?php if ($studentAttempts): ?>
        <?php render_score_game_legend($resultsPage['items']); ?>
        <div class="results-table" role="table" aria-label="Quiz results">
            <div class="results-table-head" role="row"><span>Activity</span><span>Score</span><span>Date</span><span></span></div>
            <?php foreach ($resultsPage['items'] as $attempt): ?>
                <?php $isTraining = ($attempt['game_type'] ?? '') === 'focus_training'; $scoreGame = score_game_type((string) ($attempt['game_type'] ?? '')); ?>
                <article class="results-table-row game-score-type" data-game-type="<?php echo esc($scoreGame['type']); ?>" role="row">
                    <div><strong><?php echo esc($attempt['quiz_title']); ?></strong><small class="game-type-badge"><?php echo esc($scoreGame['label']); ?></small></div>
                    <strong class="game-type-score"><?php echo esc((string) percentage((int) $attempt['score'], (int) $attempt['max_score'])); ?>%</strong>
                    <time datetime="<?php echo esc($attempt['played_at']); ?>"><?php echo esc(format_date($attempt['played_at'])); ?></time>
                    <?php if (!$isTraining): ?><a class="button button-secondary" href="/QuizWeb/results.php?id=<?php echo esc((string) $attempt['id']); ?>">View</a><?php else: ?><span class="status-badge status-completed">Training</span><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state"><?php echo nav_icon('chart'); ?><h2>Complete your first quiz</h2><p>Your scores and training history will appear here.</p><a class="button button-primary" href="/QuizWeb/game_modes.php">Browse Quizzes</a></div>
    <?php endif; ?>
</section>
<?php render_pagination($resultsPage); ?>
<?php render_footer(); ?>
