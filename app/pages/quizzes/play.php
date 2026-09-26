<?php

require_once dirname(__DIR__, 3) . '/includes/layout.php';

$user = require_login();
$classroomId = (int) ($_GET['classroom_id'] ?? 0);
$quizId = (int) ($_GET['quiz_id'] ?? 0);
$classroom = find_classroom($classroomId);

if (!$classroom || !classroom_belongs_to_user($classroom, $user)) {
    flash_set('danger', 'Classroom access denied.');
    redirect('/QuizWeb/dashboard.php');
}

$quiz = classroom_quiz($classroom, $quizId);

if (!$quiz) {
    flash_set('danger', 'Quiz not found.');
    redirect('/QuizWeb/classroom.php?id=' . $classroom['id']);
}

$modes = game_modes();
$isPreview = $user['role'] === 'teacher';
$quizData = [
    'id' => $quiz['id'],
    'title' => $quiz['title'],
    'game_type' => $quiz['game_type'],
    'mastery_threshold' => mastery_threshold_for_quiz($quiz),
    'crossword_layout' => $quiz['crossword_layout'] ?? null,
    'questions' => array_map(function (array $question) {
        return [
            'id' => (int) ($question['id'] ?? 0),
            'prompt' => $question['prompt'],
            'answer' => $question['answer'] ?? ($question['options'][0] ?? ''),
            'options' => array_values($question['options'] ?? []),
            'correct_index' => (int) ($question['correct_index'] ?? 0),
            'points' => (int) ($question['points'] ?? 10),
            'level' => $question['level'] ?? 'easy',
            'crossword' => $question['crossword'] ?? null,
        ];
    }, $quiz['questions']),
];

render_header($quiz['title'], 'game-page mode-' . $quiz['game_type']);
?>

<section class="game-shell glass">
    <div class="game-hud">
        <div>
            <span class="eyebrow"><?php echo esc($modes[$quiz['game_type']]['label'] ?? 'Quiz Mode'); ?></span>
            <h1><?php echo esc($quiz['title']); ?></h1>
            <p class="lead compact"><?php echo esc($quiz['description'] ?: $modes[$quiz['game_type']]['description']); ?></p>
            <div class="feature-pills">
                <span><?php echo esc(count($quiz['questions']) . (($quiz['game_type'] ?? '') === 'crossword' ? ' words' : ' questions')); ?></span>
                <span><?php echo esc($isPreview ? 'Teacher preview' : 'Live student run'); ?></span>
                <span><?php echo esc($classroom['name']); ?></span>
                <?php if (($quiz['game_type'] ?? '') === 'crossword'): ?>
                    <span>Intersecting word grid</span>
                <?php endif; ?>
                <?php if (($quiz['game_type'] ?? '') === 'master_ladder'): ?>
                    <span><?php echo esc((string) mastery_threshold_for_quiz($quiz)); ?>% to unlock next level</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="hud-stats">
            <div class="hud-pill"><span>Question</span><strong data-progress-count>1 / <?php echo esc((string) count($quiz['questions'])); ?></strong></div>
            <div class="hud-pill"><span>Score</span><strong data-score-value>0</strong></div>
            <div class="hud-pill"><span>Streak</span><strong data-streak-value>0x</strong></div>
            <div class="hud-pill"><span>Timer</span><strong data-timer-value>0s</strong></div>
        </div>
    </div>

    <div
        class="game-board"
        data-game-root
        data-classroom-id="<?php echo esc((string) $classroom['id']); ?>"
        data-quiz='<?php echo esc(json_encode($quizData, JSON_UNESCAPED_SLASHES)); ?>'
        data-submit-url="/QuizWeb/submit_game.php"
        data-is-preview="<?php echo $isPreview ? '1' : '0'; ?>"
        data-return-url="/QuizWeb/classroom.php?id=<?php echo esc((string) $classroom['id']); ?>"
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
