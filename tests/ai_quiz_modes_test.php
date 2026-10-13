<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/ai_quiz.php';
function mode_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function mode_reject(callable $call, string $message): void {
    try { $call(); } catch (InvalidArgumentException | RuntimeException $expected) { return; }
    throw new LogicException($message);
}
$source = ai_quiz_source(str_repeat('Python is a programming language. A type describes a kind of data. A host is a computer that serves network requests. ', 3), null);
$sets = [];
foreach (array_keys(game_modes()) as $mode) {
    $count = $mode === 'master_ladder' ? 4 : 3;
    $options = ai_quiz_options(['count' => $count, 'game_type' => $mode, 'difficulty' => 'mixed', 'language' => 'English']);
    $request = ai_quiz_request($options, $source, 'gemini-3.8-flash');
    $properties = $request['response_format']['schema']['properties']['questions']['items']['properties'];
    $textMode = $mode === 'crossword' || activity_uses_text_answers($mode);
    mode_check(isset($properties[$textMode ? 'answer' : 'options']) && !isset($properties[$textMode ? 'options' : 'answer']), $mode . ' requests its own answer format');
    $questions = [];
    foreach (array_slice(['easy', 'medium', 'hard', 'master'], 0, $count) as $i => $level) {
        $question = ['prompt' => 'Computing question ' . $i, 'topic' => 'Computing', 'explanation' => 'Based on the supplied lesson.', 'level' => $level];
        if ($textMode) {
            $question['answer'] = ['PYTHON', 'TYPE', 'HOST'][$i];
            $question['prompt'] = match ($mode) {
                'crossword' => ['A programming language', 'A kind of data', 'A network computer'][$i],
                'fill_blank' => ['_____ is a programming language.', 'A _____ describes a kind of data.', 'A _____ serves network requests.'][$i],
                'emoji_quiz' => ['🐍💻', '🔢🏷️', '🖥️🌐'][$i],
                default => ['Programming language', 'Kind of data', 'Network computer'][$i],
            };
            if ($mode !== 'crossword') $question += ['accepted_answers' => [], 'hint' => ''];
        } else $question += ['options' => ['Python', 'Type', 'Host', 'Other'], 'correct_index' => $i];
        $questions[] = $question;
    }
    $sets[$mode] = ['options' => $options, 'questions' => $questions];
    $validated = ai_quiz_validate(json_encode(['questions' => $questions, 'warning' => '']), $options);
    mode_check(count($validated['questions']) === $count && $validated['questions'][0]['points'] === 10, $mode . ' generated items validate');
    mode_check(!prepare_activity_questions($mode, $validated['questions'])['errors'], $mode . ' uses the existing preview/save validation');
    if ($mode === 'crossword') {
        mode_check(isset($validated['questions'][0]['crossword']) && $validated['questions'][0]['preferred_direction'] === 'auto', 'Every crossword word receives a connected layout and automatic direction');
    } elseif ($textMode) mode_check($validated['questions'][0]['answer'] === 'PYTHON' && $validated['questions'][0]['options'] === [], $mode . ' typed answers remain intact');
}
mode_check(ai_quiz_options(['count' => 2, 'game_type' => 'flip_match', 'difficulty' => 'easy', 'language' => 'Filipino'])['count'] === 2, 'Two pairs supported for single difficulty');
$twoPairs = array_slice($sets['flip_match']['questions'], 0, 2);
foreach ($twoPairs as &$pair) $pair['level'] = 'easy';
unset($pair);
$twoPairOptions = ai_quiz_options(['count' => 2, 'game_type' => 'flip_match', 'difficulty' => 'easy', 'language' => 'Filipino']);
mode_check(count(ai_quiz_validate(json_encode(['questions' => $twoPairs]), $twoPairOptions)['questions']) === 2, 'Two matching pairs pass full generation validation');
foreach ([['count' => 13, 'difficulty' => 'easy'], ['count' => 2, 'difficulty' => 'mixed']] as $invalid) mode_reject(fn() => ai_quiz_options($invalid + ['game_type' => 'flip_match', 'language' => 'English']), 'Matching count rules must be enforced');
foreach (['crossword', 'flip_match', 'fill_blank', 'emoji_quiz'] as $mode) {
    $set = $sets[$mode]; $bad = $set['questions'];
    $bad[0]['answer'] = str_repeat('a', 251);
    mode_reject(fn() => ai_quiz_validate(json_encode(['questions' => $bad]), $set['options']), $mode . ' oversized answer rejected');
    if ($mode !== 'crossword') {
        $bad = $set['questions']; $bad[0]['accepted_answers'] = ['okay', []];
        mode_reject(fn() => ai_quiz_validate(json_encode(['questions' => $bad]), $set['options']), 'Malformed alternatives rejected');
    }
}
foreach (['crossword', 'flip_match'] as $mode) {
    $set = $sets[$mode]; $bad = $set['questions']; $bad[1]['answer'] = strtolower($bad[0]['answer']);
    mode_reject(fn() => ai_quiz_validate(json_encode(['questions' => $bad]), $set['options']), 'Duplicate answers rejected');
}
$set = $sets['crossword']; $bad = $set['questions'];
foreach (['ABC', 'DEF', 'GHI'] as $i => $answer) $bad[$i]['answer'] = $answer;
mode_reject(fn() => ai_quiz_validate(json_encode(['questions' => $bad]), $set['options']), 'Disconnected grid rejected');
foreach (['fill_blank' => 'A sentence without a blank.', 'emoji_quiz' => 'A clue without any emoji.'] as $mode => $prompt) {
    $set = $sets[$mode]; $bad = $set['questions']; $bad[0]['prompt'] = $prompt;
    mode_reject(fn() => ai_quiz_validate(json_encode(['questions' => $bad]), $set['options']), $mode . ' required prompt format enforced');
}
$set = $sets['fill_blank']; $bad = $set['questions']; $bad[0]['prompt'] = '_____ has two _____.';
mode_reject(fn() => ai_quiz_validate(json_encode(['questions' => $bad]), $set['options']), 'A fill-in-the-blank item has exactly one blank');
$set = $sets['flip_match']; $bad = $set['questions']; $bad[0]['prompt_image'] = 'https://untrusted.test/image'; $bad[0]['case_sensitive'] = true;
$clean = ai_quiz_validate(json_encode(['questions' => $bad]), $set['options']);
mode_check($clean['questions'][0]['prompt_image'] === '' && !$clean['questions'][0]['case_sensitive'], 'Model-supplied images and grading settings are ignored');
echo "AI activity checks passed for all 11 modes: schemas, generated answer formats, builder validation, connected crossword grids, matching limits and malformed output rejection.\n";
