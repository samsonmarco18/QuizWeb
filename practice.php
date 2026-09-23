<?php

require_once __DIR__ . '/includes/layout.php';

$user = require_role('student');
$profile = student_learning_profile((int) $user['id']);
$practiceQuestions = learning_practice_questions($profile);

if (!$practiceQuestions) {
    render_header('Focus Practice', 'dashboard-page');
    ?>
    <section class="dashboard-hero glass">
        <div>
            <span class="eyebrow">Focus Practice</span>
            <h1>No practice queue yet</h1>
            <p class="lead">Play at least one quiz with multiple-choice questions. Once the system finds weak items, it will build a focused practice round here.</p>
            <div class="feature-pills">
                <span><?php echo esc((string) $profile['attempts']); ?> attempts</span>
                <span><?php echo esc((string) $profile['questions_seen']); ?> questions analyzed</span>
                <span><?php echo esc((string) count($profile['focus_items'])); ?> weak items</span>
            </div>
        </div>
        <div class="hero-side-stack">
            <article class="hero-note-card">
                <span class="eyebrow">Next step</span>
                <h3>Build your signal</h3>
                <p>Open a classroom quiz, finish a real run, then return here for a custom practice set.</p>
            </article>
        </div>
    </section>

    <section class="glass panel">
        <div class="action-row">
            <a class="button button-secondary" href="/QuizWeb/dashboard.php">Back to Dashboard</a>
        </div>
    </section>
    <?php
    render_footer();
    exit;
}

$quizData = [
    'id' => 0,
    'title' => 'Focus Practice',
    'game_type' => 'time_attack',
    'mastery_threshold' => 75,
    'crossword_layout' => null,
    'questions' => array_map(function (array $question, int $index) {
        return [
            'id' => $index + 1,
            'prompt' => $question['prompt'],
            'answer' => $question['options'][$question['correct_index']] ?? '',
            'options' => array_values($question['options']),
            'correct_index' => (int) $question['correct_index'],
            'points' => (int) ($question['points'] ?? 10),
            'level' => $question['level'] ?? 'easy',
            'crossword' => null,
        ];
    }, $practiceQuestions, array_keys($practiceQuestions)),
];
$_SESSION['focus_training_quiz'] = $quizData;

render_header('Focus Practice', 'game-page mode-time_attack practice-page');
?>

<section class="game-shell glass">
    <div class="game-hud">
        <div>
            <span class="eyebrow">Self-learning coach</span>
            <h1>Focus Practice</h1>
            <p class="lead compact">A no-grade round built from your weakest recent multiple-choice items.</p>
            <div class="feature-pills">
                <span><?php echo esc(count($practiceQuestions) . ' practice questions'); ?></span>
                <span>Recorded as training</span>
                <span><?php echo esc((string) $profile['overall_accuracy']); ?>% current accuracy</span>
            </div>
        </div>
        <div class="hud-stats">
            <div class="hud-pill"><span>Question</span><strong data-progress-count>1 / <?php echo esc((string) count($practiceQuestions)); ?></strong></div>
            <div class="hud-pill"><span>Score</span><strong data-score-value>0</strong></div>
            <div class="hud-pill"><span>Streak</span><strong data-streak-value>0x</strong></div>
            <div class="hud-pill"><span>Timer</span><strong data-timer-value>0s</strong></div>
        </div>
    </div>

    <div
        class="game-board"
        data-game-root
        data-classroom-id="0"
        data-quiz='<?php echo esc(json_encode($quizData, JSON_UNESCAPED_SLASHES)); ?>'
        data-submit-url="/QuizWeb/submit_focus.php"
        data-is-preview="0"
        data-practice-mode="1"
        data-return-url="/QuizWeb/dashboard.php"
    >
        <div class="game-progress">
            <div class="game-progress-bar" data-progress-bar></div>
        </div>
        <div class="battle-strip" data-battle-strip hidden>
            <div class="battle-meter">
                <span>Boss</span>
                <div class="battle-meter-track">
                    <div class="battle-meter-fill battle-meter-fill-boss" data-boss-health></div>
                </div>
            </div>
            <div class="battle-meter">
                <span>Shield</span>
                <div class="battle-meter-track">
                    <div class="battle-meter-fill battle-meter-fill-player" data-player-health></div>
                </div>
            </div>
        </div>
        <div class="mode-stage">
            <div class="mode-decoration mode-decoration-one"></div>
            <div class="mode-decoration mode-decoration-two"></div>
            <div class="mode-decoration mode-decoration-three"></div>
            <article class="question-stage glass">
                <span class="question-points" data-question-points></span>
                <h2 data-question-text>Loading question...</h2>
                <p class="question-helper" data-question-helper></p>
                <div class="answers-grid" data-answer-grid></div>
                <div class="game-controls" data-game-controls></div>
            </article>
        </div>
        <div class="game-note" data-game-note></div>
    </div>
</section>

<?php render_footer(['/QuizWeb/assets/js/game.js']); ?>
