<?php

require_once dirname(__DIR__, 3) . '/includes/layout.php';

$user = require_role('teacher');
$classroomId = (int) ($_GET['classroom_id'] ?? 0);
$quizId = (int) ($_GET['quiz_id'] ?? 0);
$classroom = find_classroom($classroomId);

if (!$classroom || (int) $classroom['teacher_id'] !== (int) $user['id']) {
    flash_set('danger', 'Classroom not found.');
    redirect('/QuizWeb/dashboard.php');
}

$editingQuiz = $quizId ? classroom_quiz($classroom, $quizId) : null;
$errors = [];
$_SESSION['quiz_builder_csrf'] ??= bin2hex(random_bytes(32));
$modes = game_modes();
$masteryLevels = mastery_levels();
$requestedGameType = is_string($_GET['game_type'] ?? null) ? $_GET['game_type'] : '';
$selectedGameType = is_string($_POST['game_type'] ?? null) ? $_POST['game_type'] : ($editingQuiz['game_type'] ?? $requestedGameType);
$isChoosingGameType = !$editingQuiz && $_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($modes[$selectedGameType]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['quiz_builder_csrf'], $_POST['csrf'])) {
        $errors[] = 'Your form expired. Reload the editor and try again.';
    }
    $title = is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '';
    $description = is_string($_POST['description'] ?? null) ? trim($_POST['description']) : '';
    $dueAtInput = is_string($_POST['due_at'] ?? null) ? trim($_POST['due_at']) : '';
    $dueAt = '';
    $gameType = $selectedGameType;
    $payload = is_string($_POST['questions_payload'] ?? null) && strlen($_POST['questions_payload']) <= 250000 ? $_POST['questions_payload'] : '[]';
    $decodedQuestions = json_decode($payload, true);
    $questions = [];

    if ($title === '' || strlen($title) > 255) {
        $errors[] = 'Enter a quiz title of up to 255 characters.';
    }

    if (!isset($modes[$gameType])) {
        $errors[] = 'Please choose a valid game mode.';
    }

    if ($dueAtInput !== '') {
        $dueTimestamp = strtotime($dueAtInput);
        if ($dueTimestamp === false) {
            $errors[] = 'Enter a valid quiz deadline.';
        } else {
            $dueAt = date(DATE_ATOM, $dueTimestamp);
        }
    }

    $prepared = prepare_activity_questions($gameType, $decodedQuestions);
    $questions = $prepared['questions'];
    $crosswordLayout = $prepared['layout'];
    $errors = array_merge($errors, $prepared['errors']);

    if (($_POST['action'] ?? '') === 'preview') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code($errors ? 422 : 200);
        echo json_encode(['errors' => $errors, 'title' => $title, 'game_type' => $gameType, 'questions' => $questions, 'crossword_layout' => $crosswordLayout]);
        exit;
    }
    if (!$errors) {
        $quiz = [
            'id' => $editingQuiz['id'] ?? next_id($classroom['quizzes'] ?? []),
            'title' => $title,
            'description' => $description,
            'due_at' => $dueAt,
            'game_type' => $gameType,
            'mastery_threshold' => $editingQuiz['mastery_threshold'] ?? 75,
            'questions' => $questions,
            'crossword_layout' => $gameType === 'crossword' ? ($crosswordLayout ?? build_crossword_layout($questions)) : null,
            'updated_at' => now_iso(),
            'created_at' => $editingQuiz['created_at'] ?? now_iso(),
        ];

        $classroom['quizzes'] = $classroom['quizzes'] ?? [];
        $classroom = update_quiz_in_classroom($classroom, $quiz);
        save_classroom($classroom);

        flash_set('success', $editingQuiz ? 'Quiz updated successfully.' : 'Quiz created successfully.');
        redirect('/QuizWeb/classroom.php?id=' . $classroom['id'] . '&tab=quizzes');
    }
}

render_header($editingQuiz ? 'Edit Quiz' : 'Create Quiz', 'builder-page');
?>

<?php if ($isChoosingGameType): ?>
<section class="dashboard-hero glass">
    <div>
        <span class="eyebrow"><?php echo esc($classroom['name']); ?></span>
        <h1>Choose the quiz type first</h1>
        <p class="lead">Pick the activity format before entering the main creation editor, so the builder can show the right fields.</p>
        <div class="feature-pills">
            <span>Step 1: Select type</span>
            <span>Step 2: Build content</span>
            <span>Step 3: Publish</span>
        </div>
    </div>
</section>

<section class="glass panel">
    <div class="section-heading"><div><span class="eyebrow">Activity templates</span><h2>Choose how students learn</h2><p>Build your content, preview the activity, then save it to this class.</p></div></div>
    <?php $templateKeys = ['standard', 'crossword', 'flip_match', 'fill_blank', 'emoji_quiz', 'master_ladder']; ?>
    <div class="quiz-grid">
        <?php foreach ($templateKeys as $key): $mode = $modes[$key]; ?>
            <article class="quiz-card mode-<?php echo esc($key); ?>">
                <div class="quiz-card-head">
                    <span class="mode-badge"><?php echo esc($mode['icon']); ?></span>
                    <strong><?php echo esc($mode['label']); ?></strong>
                </div>
                <p><?php echo esc($mode['description']); ?></p>
                <?php if ($key === 'crossword'): ?>
                    <div class="card-meta">
                        <span>Words + clues</span>
                        <span>Intersecting grid</span>
                    </div>
                <?php endif; ?>
                <a class="button button-primary" href="/QuizWeb/quiz_builder.php?classroom_id=<?php echo esc((string) $classroom['id']); ?>&game_type=<?php echo esc($key); ?>">Choose <?php echo esc($mode['label']); ?></a>
            </article>
        <?php endforeach; ?>
    </div>
    <details class="additional-modes"><summary>More game styles</summary><div class="quiz-grid">
        <?php foreach (array_diff(array_keys($modes), $templateKeys) as $key): $mode = $modes[$key]; ?>
            <article class="quiz-card"><h3><?php echo esc($mode['label']); ?></h3><p><?php echo esc($mode['description']); ?></p><a class="button button-secondary" href="/QuizWeb/quiz_builder.php?classroom_id=<?php echo (int) $classroom['id']; ?>&amp;game_type=<?php echo esc($key); ?>">Choose <?php echo esc($mode['label']); ?></a></article>
        <?php endforeach; ?>
    </div></details>
    <div class="action-row">
        <a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo esc((string) $classroom['id']); ?>">Back to Classroom</a>
    </div>
</section>

<?php render_footer(); ?>
<?php exit; ?>
<?php endif; ?>

<section class="dashboard-hero glass">
    <div>
        <span class="eyebrow"><?php echo esc($classroom['name']); ?></span>
        <h1><?php echo esc($editingQuiz ? 'Edit Quiz Game' : 'Create a New Quiz Game'); ?></h1>
        <p class="lead">Teachers can fully modify the quiz title, questions, answers, points, and the game mode students will play.</p>
        <div class="feature-pills">
            <span>Custom prompts</span>
            <span>Editable scoring</span>
            <span>Mode-based play</span>
        </div>
    </div>
</section>

<section class="glass panel builder-panel">
    <form method="post" id="quiz-builder-form" class="stack-form">
        <input type="hidden" name="csrf" value="<?php echo esc($_SESSION['quiz_builder_csrf']); ?>">
        <?php foreach ($errors as $error): ?>
            <div class="inline-error"><?php echo esc($error); ?></div>
        <?php endforeach; ?>
        <div class="split-fields">
            <label>
                <span>Quiz Title</span>
                <input type="text" name="title" required value="<?php echo esc($_POST['title'] ?? ($editingQuiz['title'] ?? '')); ?>" placeholder="Photosynthesis Showdown">
            </label>
            <label>
                <span>Game Mode</span>
                <select name="game_type">
                    <?php $selectedMode = $selectedGameType; ?>
                    <?php foreach ($modes as $key => $mode): ?>
                        <option value="<?php echo esc($key); ?>" <?php echo $selectedMode === $key ? 'selected' : ''; ?>>
                            <?php echo esc($mode['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>
            <span>Description</span>
            <textarea name="description" rows="3" placeholder="Give students a quick teaser about the game"><?php echo esc($_POST['description'] ?? ($editingQuiz['description'] ?? '')); ?></textarea>
        </label>
        <label>
            <span>Deadline <small>(optional)</small></span>
            <?php $deadlineValue = $_POST['due_at'] ?? (!empty($editingQuiz['due_at']) ? date('Y-m-d\TH:i', strtotime($editingQuiz['due_at'])) : ''); ?>
            <input type="datetime-local" name="due_at" value="<?php echo esc($deadlineValue); ?>">
        </label>

        <div class="builder-mode-note" data-builder-mode-note>
            <strong><?php echo esc(($selectedMode ?? '') === 'crossword' ? 'Crossword checklist' : 'Quiz checklist'); ?></strong>
            <span><?php echo esc(($selectedMode ?? '') === 'crossword'
                ? 'Use 3+ unique words, varied lengths, clear clues, and answers that can intersect through matching letters.'
                : 'Write complete prompts, four options, one correct answer, and points for each question.'); ?></span>
        </div>

        <div class="builder-header">
            <div>
                <span class="eyebrow">Questions</span>
                <h2><?php echo esc(($selectedMode ?? '') === 'crossword' ? 'Word and clue editor' : 'Question editor'); ?></h2>
            </div>
            <div class="action-row"><button class="button button-secondary" type="button" id="preview-quiz">Preview Activity</button><button class="button button-primary" type="button" id="add-question-button"><?php echo esc(($selectedMode ?? '') === 'crossword' ? 'Add Word' : 'Add Question'); ?></button></div>
        </div>

        <div id="question-list" class="question-list"></div>
        <input type="hidden" name="questions_payload" id="questions_payload">

        <div class="action-row">
            <a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo esc((string) $classroom['id']); ?>">Back to Classroom</a>
            <button class="button button-primary" type="submit"><?php echo esc($editingQuiz ? 'Save Changes' : 'Create Quiz'); ?></button>
        </div>
    </form>
</section>

<template id="question-template">
    <article class="question-card glass">
        <div class="question-card-head">
            <strong data-question-label>Question</strong>
            <button class="button button-ghost remove-question" type="button">Remove</button>
        </div>
        <label>
            <span data-prompt-label>Prompt</span>
            <textarea data-field="prompt" rows="3" placeholder="Type the question here"></textarea>
        </label>
        <label data-answer-field>
            <span data-answer-label>Answer</span>
            <input type="text" data-field="answer" placeholder="Single word, letters only">
        </label>
        <div data-text-only class="stack-form">
            <label><span>Alternative answers (one per line)</span><textarea data-field="accepted_answers" rows="2"></textarea></label>
            <label><span><input type="checkbox" data-field="case_sensitive"> Match capitalization exactly</span></label>
            <label><span>Hint (optional)</span><input type="text" data-field="hint" maxlength="500"></label>
            <label><span>Explanation after submission (optional)</span><textarea data-field="explanation" rows="2" maxlength="2000"></textarea></label>
        </div>
        <label data-crossword-only>
            <span>Direction</span>
            <select data-field="preferred_direction">
                <option value="across">Horizontal</option>
                <option value="down">Vertical</option>
            </select>
        </label>
        <div class="option-grid" data-choice-only>
            <label>
                <span>Option A</span>
                <input type="text" data-field="option-0" placeholder="Option A">
            </label>
            <label>
                <span>Option B</span>
                <input type="text" data-field="option-1" placeholder="Option B">
            </label>
            <label>
                <span>Option C</span>
                <input type="text" data-field="option-2" placeholder="Option C">
            </label>
            <label>
                <span>Option D</span>
                <input type="text" data-field="option-3" placeholder="Option D">
            </label>
        </div>
        <div class="question-meta-grid">
            <label data-choice-only>
                <span>Correct Answer</span>
                <select data-field="correct_index">
                    <option value="0">Option A</option>
                    <option value="1">Option B</option>
                    <option value="2">Option C</option>
                    <option value="3">Option D</option>
                </select>
            </label>
            <label>
                <span>Points</span>
                <input type="number" min="5" step="5" data-field="points" value="10">
            </label>
            <label>
                <span>Level</span>
                <select data-field="level">
                    <?php foreach ($masteryLevels as $key => $label): ?>
                        <option value="<?php echo esc($key); ?>"><?php echo esc($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </article>
</template>

<script>
window.quizBuilderSeed = <?php echo json_encode(
    !empty($_POST['questions_payload']) ? json_decode($_POST['questions_payload'], true) : ($editingQuiz['questions'] ?? []),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
); ?>;
window.quizBuilderMode = <?php echo json_encode($selectedMode ?? 'time_attack'); ?>;
</script>

<dialog id="activity-preview" class="activity-preview" aria-labelledby="preview-title">
    <header class="section-heading"><div><span class="eyebrow">Unsaved preview</span><h2 id="preview-title">Activity Preview</h2><p>Check your activity before saving. Preview responses are not recorded.</p></div><form method="dialog"><button class="button button-secondary">Close</button></form></header>
    <p id="preview-status" role="status"></p>
    <div id="preview-content"></div>
</dialog>
<?php render_footer(['/QuizWeb/assets/js/builder-preview.js']); ?>
