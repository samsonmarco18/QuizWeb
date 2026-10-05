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
if ($quizId && !$editingQuiz) {
    flash_set('danger', 'Quiz not found.');
    redirect('/QuizWeb/classroom.php?id=' . $classroomId . '&tab=quizzes');
}
$errors = [];
$defaultMasteryThreshold = mastery_threshold_for_quiz($editingQuiz ?? []);
$gradebook = grading_load(db(), $classroomId);
$gradeCategory = is_string($_POST['grade_category_id'] ?? null) ? $_POST['grade_category_id'] : ($editingQuiz['grade_category_id'] ?? '');
$gradePolicy = is_string($_POST['grade_attempt_policy'] ?? null) ? $_POST['grade_attempt_policy'] : ($editingQuiz['grade_attempt_policy'] ?? 'highest');
$gradeMaxInput = is_string($_POST['grade_max_score'] ?? null) ? $_POST['grade_max_score'] : (isset($editingQuiz['grade_max_score']) ? (string) $editingQuiz['grade_max_score'] : '');
$GLOBALS['quizweb_current_classroom_id'] = $classroomId;
$_SESSION['quiz_builder_csrf'] ??= bin2hex(random_bytes(32));
$modes = game_modes();
$masteryLevels = mastery_levels();
$requestedGameType = is_string($_GET['game_type'] ?? null) ? $_GET['game_type'] : '';
$selectedGameType = is_string($_POST['game_type'] ?? null) ? $_POST['game_type'] : ($editingQuiz['game_type'] ?? $requestedGameType);
$titleValue = is_string($_POST['title'] ?? null) ? $_POST['title'] : ($editingQuiz['title'] ?? '');
$descriptionValue = is_string($_POST['description'] ?? null) ? $_POST['description'] : ($editingQuiz['description'] ?? '');
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
    $masteryThreshold = filter_var($_POST['mastery_threshold'] ?? $defaultMasteryThreshold, FILTER_VALIDATE_INT);
    if ($masteryThreshold === false || $masteryThreshold < 50 || $masteryThreshold > 100) {
        $errors[] = 'Mastery target must be between 50% and 100%.';
    }
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
    $gradeMax = null;
    if ($gradeCategory !== '') {
        if (!grading_category($gradebook, $gradeCategory)) $errors[] = 'Choose a configured classroom grading category.';
        if (!in_array($gradePolicy, ['highest', 'latest', 'first', 'average'], true)) $errors[] = 'Choose a valid attempt grading policy.';
        try {
            $gradeMax = grading_number($gradeMaxInput !== '' ? $gradeMaxInput : array_sum(array_column($questions, 'points')), .01, 100000, 'Gradebook maximum score');
        } catch (InvalidArgumentException $exception) { $errors[] = $exception->getMessage(); }
    }

    if (($_POST['action'] ?? '') === 'preview') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code($errors ? 422 : 200);
        echo json_encode(['errors' => $errors, 'title' => $title, 'game_type' => $gameType, 'mastery_threshold' => $masteryThreshold, 'questions' => $questions, 'crossword_layout' => $crosswordLayout]);
        exit;
    }
    if (!$errors) {
        $quiz = [
            'id' => $editingQuiz['id'] ?? next_id($classroom['quizzes'] ?? []),
            'title' => $title,
            'description' => $description,
            'due_at' => $dueAt,
            'game_type' => $gameType,
            'mastery_threshold' => $masteryThreshold,
            'grade_category_id' => $gradeCategory,
            'grade_attempt_policy' => $gradePolicy,
            'grade_max_score' => $gradeMax,
            'questions' => $questions,
            'crossword_layout' => $gameType === 'crossword' ? ($crosswordLayout ?? build_crossword_layout($questions)) : null,
            'updated_at' => now_iso(),
            'created_at' => $editingQuiz['created_at'] ?? now_iso(),
        ];

        try {
            persist_builder_quiz(db(), $classroomId, (int) $user['id'], $quiz, !$editingQuiz);
            flash_set('success', $editingQuiz ? 'Quiz updated successfully.' : 'Quiz created successfully.');
            redirect('/QuizWeb/classroom.php?id=' . $classroom['id'] . '&tab=quizzes');
        } catch (Throwable $exception) {
            error_log('Quiz save failed: ' . $exception->getMessage());
            $errors[] = 'Could not save this quiz. Your draft is still here. Please try again.';
        }
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
            <span>Choose game</span>
            <span>Build and preview</span>
            <span>Review and save</span>
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
                <details><summary>Preview Mechanics</summary><p><?php echo esc($key === 'master_ladder' ? 'Start at Easy. Reach the configured accuracy target to unlock Medium, Hard, and Master.' : ($key === 'flip_match' ? 'Reveal two cards to match a term with its definition. Moves and elapsed time are informational.' : ($key === 'crossword' ? 'Fill an intersecting grid from Across and Down clues. Whole correct words earn points.' : ($key === 'standard' ? 'Choose one of four answers, see feedback, and continue to the next question.' : 'Type your responses, use configured hints, and review before submitting.')))); ?></p></details>
                <?php if ($key === 'crossword'): ?>
                    <div class="card-meta">
                        <span>Words + clues</span>
                        <span>Intersecting grid</span>
                    </div>
                <?php endif; ?>
                <a class="button button-primary" href="/QuizWeb/quiz_builder.php?classroom_id=<?php echo esc((string) $classroom['id']); ?>&game_type=<?php echo esc($key); ?>">Select <?php echo esc($mode['label']); ?></a>
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
    <form method="post" id="quiz-builder-form" class="stack-form" novalidate>
        <noscript><p class="inline-error">Enable JavaScript to edit questions and preview this activity.</p></noscript>
        <nav class="builder-steps" aria-label="Quiz builder steps">
            <button type="button" data-step="0">1. Game</button>
            <button type="button" data-step="1">2. Details</button>
            <button type="button" data-step="2">3. Questions</button>
            <button type="button" data-step="3">4. Settings</button>
            <button type="button" data-step="4">5. Grading</button>
            <button type="button" data-step="5">6. Review</button>
        </nav>
        <p id="builder-step-summary" class="builder-step-summary" aria-live="polite"></p>
        <div id="builder-recovery" hidden><p>A local draft is available on this browser.</p><button type="button" class="button button-secondary" id="restore-builder-draft">Restore local draft</button><button type="button" class="button button-ghost" id="discard-builder-draft">Discard local draft</button></div>
        <p id="builder-status" role="status" aria-live="polite"></p>
        <input type="hidden" name="csrf" value="<?php echo esc($_SESSION['quiz_builder_csrf']); ?>">
        <?php foreach ($errors as $error): ?>
            <div class="inline-error"><?php echo esc($error); ?></div>
        <?php endforeach; ?>
        <div data-builder-step="0">
            <label>
                <span>Game Mode / Change Mode</span>
                <select name="game_type">
                    <?php $selectedMode = $selectedGameType; ?>
                    <?php foreach ($modes as $key => $mode): ?>
                        <option value="<?php echo esc($key); ?>" <?php echo $selectedMode === $key ? 'selected' : ''; ?>>
                            <?php echo esc($mode['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div id="builder-game-cards" class="quiz-grid"></div>
        </div>
        <div data-builder-step="1" hidden>
            <label>
                <span>Quiz Title</span>
                <input type="text" name="title" maxlength="255" required value="<?php echo esc($titleValue); ?>" placeholder="Photosynthesis Showdown">
            </label>
        <label>
            <span>Description</span>
            <textarea name="description" rows="3" placeholder="Give students a quick teaser about the game"><?php echo esc($descriptionValue); ?></textarea>
        </label>
        <label>
            <span>Deadline <small>(optional)</small></span>
            <?php $deadlineValue = is_string($_POST['due_at'] ?? null) ? $_POST['due_at'] : (!empty($editingQuiz['due_at']) ? date('Y-m-d\TH:i', strtotime($editingQuiz['due_at'])) : ''); ?>
            <input type="datetime-local" name="due_at" value="<?php echo esc($deadlineValue); ?>">
        </label>
        </div>
        <div data-builder-step="2" hidden>
        <div class="builder-mode-note" data-builder-mode-note>
            <strong><?php echo esc(($selectedMode ?? '') === 'crossword' ? 'Crossword checklist' : 'Quiz checklist'); ?></strong>
            <span><?php echo esc(($selectedMode ?? '') === 'crossword'
                ? 'Use 3+ unique words with shared letters and clear clues, then preview the grid before saving.'
                : 'Write complete prompts, four options, one correct answer, and points for each question.'); ?></span>
        </div>

        <div class="builder-header">
            <div>
                <span class="eyebrow">Questions</span>
                <h2><?php echo esc(($selectedMode ?? '') === 'crossword' ? 'Word and clue editor' : 'Question editor'); ?></h2>
            </div>
            <div class="action-row"><button class="button button-primary" type="button" id="add-question-button"><?php echo esc(($selectedMode ?? '') === 'crossword' ? 'Add Word' : 'Add Question'); ?></button></div>
        </div>

        <div class="builder-question-layout">
            <nav id="question-navigator" aria-label="Question navigator"></nav>
            <div><div class="action-row">
                <button type="button" class="button button-secondary" id="duplicate-question">Duplicate</button>
                <button type="button" class="button button-secondary" id="move-question-up">Move up</button>
                <button type="button" class="button button-secondary" id="move-question-down">Move down</button>
            </div><div id="question-list" class="question-list"></div></div>
        </div>
        </div>
        <section data-builder-step="3" hidden>
            <h2>Activity settings</h2>
            <p>Points and difficulty are set per question. The selected game mode controls timing and gameplay rules.</p>
            <div id="builder-settings"></div>
            <label id="mastery-target-setting" hidden><span>Mastery target (%)</span><input type="number" name="mastery_threshold" min="50" max="100" value="<?php echo (int) ($_POST['mastery_threshold'] ?? $defaultMasteryThreshold); ?>"><small>Accuracy needed to unlock the next difficulty level.</small></label>
            <p>Deadlines are reminders; they do not close submissions. Students may retry. Configure the deadline in Details.</p>
        </section>
        <section data-builder-step="4" hidden>
            <h2>Academic grading</h2>
            <fieldset class="grading-quiz-settings"><legend>Grading</legend>
                <label><span>Grade category</span><select name="grade_category_id"><option value="">Not graded / Practice activity</option><?php foreach ($gradebook['config']['categories'] ?? [] as $category): ?><option value="<?php echo esc($category['id']); ?>" <?php echo $gradeCategory === $category['id'] ? 'selected' : ''; ?>><?php echo esc($category['name']); ?></option><?php endforeach; ?></select></label>
                <?php if (!$gradebook['config']): ?><p>Set up classroom grading to assign categories. <a href="/QuizWeb/gradebook.php?classroom_id=<?php echo $classroomId; ?>&amp;view=setup">Open grading setup</a></p><?php endif; ?>
                <div data-quiz-graded hidden><label><span>Gradebook maximum score (optional)</span><input name="grade_max_score" type="number" min="0.01" max="100000" step="0.01" value="<?php echo esc($gradeMaxInput); ?>" placeholder="Use total question points"><small>Results are scaled from the saved quiz percentage; game scoring stays unchanged.</small></label>
                <label><span>Attempt grade</span><select name="grade_attempt_policy"><?php foreach (['highest' => 'Highest attempt', 'latest' => 'Latest attempt', 'first' => 'First attempt', 'average' => 'Average of attempt percentages'] as $policy => $label): ?><option value="<?php echo esc($policy); ?>" <?php echo $gradePolicy === $policy ? 'selected' : ''; ?>><?php echo esc($label); ?></option><?php endforeach; ?></select></label></div>
            </fieldset>
        </section>
        <section data-builder-step="5" hidden>
            <h2>Preview &amp; Review</h2>
            <div id="builder-review"></div>
            <button type="button" class="button button-secondary" id="play-test">Play test</button>
        </section>
        <input type="hidden" name="questions_payload" id="questions_payload">
        <div class="action-row builder-toolbar">
            <a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo esc((string) $classroom['id']); ?>">Back to Classroom</a>
            <button type="button" class="button button-secondary" id="builder-previous">Previous</button>
            <button type="button" class="button button-secondary" id="preview-quiz">Preview Activity</button>
            <button type="button" class="button button-secondary" id="toggle-live-preview" aria-expanded="true" aria-controls="builder-live-preview">Question preview</button>
            <button type="button" class="button button-primary" id="builder-next">Next</button>
            <button class="button button-primary" id="builder-save" type="submit" hidden><?php echo esc($editingQuiz ? 'Save Changes' : 'Save Activity'); ?></button>
        </div>
    </form>
    <aside id="builder-live-preview" class="builder-live-preview" aria-label="Live unsaved preview">
        <h2>Live preview</h2><p>Unsaved activity · Responses stay in this browser.</p>
        <div id="live-preview-content"></div>
    </aside>
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
                <option value="auto">Automatic</option>
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
                <input type="number" min="5" max="1000" step="5" data-field="points" value="10">
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
window.quizBuilderModes = <?php echo json_encode($modes, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
window.quizBuilderThreshold = <?php echo $defaultMasteryThreshold; ?>;
window.quizBuilderUnsaved = <?php echo $_SERVER['REQUEST_METHOD'] === 'POST' ? 'true' : 'false'; ?>;
</script>

<dialog id="activity-preview" class="activity-preview" aria-labelledby="preview-title">
    <header class="section-heading"><div><span class="eyebrow">PREVIEW MODE</span><h2 id="preview-title">Activity Preview</h2><p>Unsaved play test. No attempt, leaderboard entry, or grade is recorded.</p></div><form method="dialog"><button class="button button-secondary">Close</button></form></header>
    <div class="preview-devices" aria-label="Preview viewport"><button type="button" class="button button-secondary" data-preview-width="100%" aria-pressed="true">Desktop</button><button type="button" class="button button-secondary" data-preview-width="768px" aria-pressed="false">Tablet</button><button type="button" class="button button-secondary" data-preview-width="390px" aria-pressed="false">Mobile</button></div>
    <p id="preview-status" role="status"></p>
    <div id="preview-content"></div>
</dialog>
<?php render_footer(['/QuizWeb/assets/js/game-experience.js', '/QuizWeb/assets/js/builder-workflow.js', '/QuizWeb/assets/js/builder-preview.js']); ?>
