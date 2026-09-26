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
$modes = game_modes();
$masteryLevels = mastery_levels();
$requestedGameType = $_GET['game_type'] ?? '';
$selectedGameType = $_POST['game_type'] ?? ($editingQuiz['game_type'] ?? $requestedGameType);
$isChoosingGameType = !$editingQuiz && $_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($modes[$selectedGameType]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dueAtInput = trim($_POST['due_at'] ?? '');
    $dueAt = '';
    $gameType = $_POST['game_type'] ?? '';
    $payload = $_POST['questions_payload'] ?? '[]';
    $decodedQuestions = json_decode($payload, true);
    $questions = [];

    if ($title === '') {
        $errors[] = 'Quiz title is required.';
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

    if (!is_array($decodedQuestions) || count($decodedQuestions) < 1) {
        $errors[] = 'Add at least one question to the quiz.';
    } else {
        $levelCounts = array_fill_keys(array_keys($masteryLevels), 0);
        $answerLengths = [];
        $levelsUsed = [];
        $answersUsed = [];

        foreach ($decodedQuestions as $index => $question) {
            $prompt = trim($question['prompt'] ?? '');
            $options = array_map('trim', $question['options'] ?? []);
            $correctIndex = (int) ($question['correct_index'] ?? 0);
            $points = max(5, (int) ($question['points'] ?? 10));
            $level = strtolower(trim((string) ($question['level'] ?? 'easy')));

            if (!isset($masteryLevels[$level])) {
                $level = 'easy';
            }

            if ($gameType === 'crossword') {
                $answer = crossword_normalize_answer((string) ($question['answer'] ?? ''));

                if ($prompt === '' || $answer === '') {
                    $errors[] = 'Crossword word ' . ($index + 1) . ' needs both an answer and a clear clue.';
                    continue;
                }

                if (strlen($answer) < 3 || strlen($answer) > 15) {
                    $errors[] = 'Crossword word ' . ($index + 1) . ' must be 3 to 15 letters long.';
                    continue;
                }

                if (isset($answersUsed[$answer])) {
                    $errors[] = 'Crossword word ' . ($index + 1) . ' duplicates another answer.';
                    continue;
                }

                $answersUsed[$answer] = true;
                $answerLengths[strlen($answer)] = true;
                $levelsUsed[$level] = true;
                $questions[] = [
                    'id' => $index + 1,
                    'prompt' => $prompt,
                    'answer' => $answer,
                    'preferred_direction' => in_array(($question['preferred_direction'] ?? ''), ['across', 'down'], true)
                        ? $question['preferred_direction']
                        : 'across',
                    'options' => [$answer],
                    'correct_index' => 0,
                    'points' => $points,
                    'level' => $level,
                ];
                continue;
            }

            if ($prompt === '' || count($options) < 4 || in_array('', $options, true)) {
                $errors[] = 'Question ' . ($index + 1) . ' is incomplete.';
                continue;
            }

            if ($correctIndex < 0 || $correctIndex > 3) {
                $errors[] = 'Question ' . ($index + 1) . ' must have one correct answer selected.';
                continue;
            }

            $questions[] = [
                'id' => $index + 1,
                'prompt' => $prompt,
                'options' => array_values(array_slice($options, 0, 4)),
                'correct_index' => $correctIndex,
                'points' => $points,
                'level' => $level,
            ];

            $levelCounts[$level] += 1;
        }

        if ($gameType === 'master_ladder') {
            foreach ($masteryLevels as $key => $label) {
                if (($levelCounts[$key] ?? 0) < 1) {
                    $errors[] = 'Mastery Ladder quizzes need at least one ' . $label . ' question.';
                }
            }
        }

        if ($gameType === 'crossword') {
            if (count($questions) < 3) {
                $errors[] = 'Crossword puzzles need at least three words.';
            }

            if (count($answerLengths) < 2) {
                $errors[] = 'Crossword puzzles need words with varied lengths.';
            }

            if (count($levelsUsed) < 2) {
                $errors[] = 'Crossword puzzles need a mix of difficulty levels.';
            }

            $crosswordLayout = $errors ? null : build_crossword_layout($questions);

            if (!$crosswordLayout) {
                $errors[] = 'Crossword words must intersect through matching letters using the selected Horizontal/Vertical directions. Revise the words or directions so every word connects.';
            } else {
                foreach ($questions as &$question) {
                    foreach ($crosswordLayout['placements'] as $placement) {
                        if ((int) $placement['question_id'] === (int) $question['id']) {
                            $question['crossword'] = [
                                'row' => $placement['row'],
                                'col' => $placement['col'],
                                'direction' => $placement['direction'],
                                'number' => $placement['number'],
                            ];
                            break;
                        }
                    }
                }
                unset($question);
            }
        }
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
        redirect('/QuizWeb/classroom.php?id=' . $classroom['id']);
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
    <div class="quiz-grid">
        <?php foreach ($modes as $key => $mode): ?>
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
    <div class="hero-side-stack">
        <article class="hero-note-card">
            <span class="eyebrow">Builder focus</span>
            <h3><?php echo esc($editingQuiz ? 'Refine the challenge' : 'Design your next class game'); ?></h3>
            <p>This page is where you shape the learning experience: write the prompts, tune the scoring, and choose the vibe students will play through.</p>
        </article>
    </div>
</section>

<section class="glass panel builder-panel">
    <form method="post" id="quiz-builder-form" class="stack-form">
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
                    <?php $selectedMode = $_POST['game_type'] ?? ($editingQuiz['game_type'] ?? $selectedGameType); ?>
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
                ? 'Use 3+ unique words, varied lengths and difficulty, clear clues, and answers that can intersect through matching letters.'
                : 'Write complete prompts, four options, one correct answer, and points for each question.'); ?></span>
        </div>

        <div class="builder-header">
            <div>
                <span class="eyebrow">Questions</span>
                <h2><?php echo esc(($selectedMode ?? '') === 'crossword' ? 'Word and clue editor' : 'Question editor'); ?></h2>
            </div>
            <button class="button button-secondary" type="button" id="add-question-button"><?php echo esc(($selectedMode ?? '') === 'crossword' ? 'Add Word' : 'Add Question'); ?></button>
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
        <label data-crossword-only>
            <span>Crossword Answer</span>
            <input type="text" data-field="answer" placeholder="Single word, letters only">
        </label>
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
    JSON_UNESCAPED_SLASHES
); ?>;
window.quizBuilderMode = <?php echo json_encode($selectedMode ?? 'time_attack'); ?>;
</script>

<?php render_footer(); ?>
