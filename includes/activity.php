<?php

function activity_uses_text_answers(string $mode): bool
{
    return in_array($mode, ['fill_blank', 'emoji_quiz', 'flip_match'], true);
}


// Each run owns its shuffled snapshot, so indexes stay stable for server grading.
function activity_shuffle_items(array $items): array
{
    $original = array_values($items);
    $items = $original;
    for ($i = count($items) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
    }
    if (count($items) > 1 && $items === $original) $items[] = array_shift($items);
    return $items;
}

function activity_shuffle_for_run(array $quiz): array
{
    if (($quiz['game_type'] ?? '') === 'master_ladder') {
        $questions = [];
        foreach (['easy', 'medium', 'hard', 'master'] as $level) {
            $group = array_values(array_filter($quiz['questions'], static fn($q) => ($q['level'] ?? 'easy') === $level));
            $questions = array_merge($questions, activity_shuffle_items($group));
        }
        $quiz['questions'] = $questions;
    } else {
        // Crossword positions refer to stable question IDs, not list indexes.
        $quiz['questions'] = activity_shuffle_items($quiz['questions']);
    }
    foreach ($quiz['questions'] as &$question) {
        if (activity_uses_text_answers($quiz['game_type']) || $quiz['game_type'] === 'crossword') continue;
        $options = $question['options'] ?? [];
        if (!$options) continue;
        $order = activity_shuffle_items(array_keys($options));
        $question['options'] = array_map(static fn($i) => $options[$i], $order);
        $question['correct_index'] = array_search((int) ($question['correct_index'] ?? 0), $order, true);
    }
    unset($question);
    return $quiz;
}

// Accept only actual question answers; security metadata is supplied by the
// server and cannot be overwritten by an answers payload.
function activity_submission_answers(array $quiz, array $answers): array
{
    $clean = [];
    $text = activity_uses_text_answers($quiz['game_type']) || $quiz['game_type'] === 'crossword';
    foreach ($quiz['questions'] as $index => $question) {
        if (!array_key_exists($index, $answers)) continue;
        $value = $answers[$index];
        if ($text) $clean[$index] = is_string($value) && strlen($value) <= 2000 ? $value : '';
        else $clean[$index] = is_int($value) && $value >= 0 && $value < count($question['options'] ?? []) ? $value : null;
    }
    if ($quiz['game_type'] === 'flip_match' && isset($answers['_moves']) && is_int($answers['_moves'])) {
        $clean['_moves'] = max(0, min(100000, $answers['_moves']));
    }
    return $clean;
}

// Store small raster images in the existing PostgreSQL quiz JSON, not ephemeral
// Render upload directories. Reject SVG, remote URLs, and oversized images.
function activity_match_image($value): string
{
    if ($value === null || $value === '') return '';
    if (!is_string($value) || strlen($value) > 131200 || !preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#D', $value, $matches)) {
        throw new InvalidArgumentException('Matching images must be uploaded PNG, JPEG, or WebP images under 96 KB.');
    }
    $bytes = base64_decode($matches[2], true);
    $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;
    if (!$info || strlen($bytes) > 98304 || ($info['mime'] ?? '') !== 'image/' . $matches[1] || $info[0] > 1600 || $info[1] > 1600) {
        throw new InvalidArgumentException('Matching image is invalid, too large, or exceeds 1600 pixels.');
    }
    return 'data:' . $info['mime'] . ';base64,' . base64_encode($bytes);
}

// Crossword clients need the shape and word lengths, never the student answer key.
function activity_public_crossword(array $quiz, bool $preview): array
{
    if ($preview || ($quiz['game_type'] ?? '') !== 'crossword') return $quiz;
    foreach ($quiz['questions'] as &$question) {
        $question['word_length'] = strlen(crossword_normalize_answer((string) ($question['answer'] ?? ($question['options'][0] ?? ''))));
        $question = array_intersect_key($question, array_flip(['id', 'prompt', 'points', 'level', 'crossword', 'word_length']));
    }
    unset($question);
    if (is_array($quiz['crossword_layout']['cells'] ?? null)) {
        $quiz['crossword_layout']['cells'] = array_map(static fn($row) => array_map(static fn($cell) => $cell ? 1 : null, $row), $quiz['crossword_layout']['cells']);
    }
    if (is_array($quiz['crossword_layout']['placements'] ?? null)) {
        $quiz['crossword_layout']['placements'] = array_map(static fn($placement) => array_intersect_key($placement, array_flip(['question_id', 'row', 'col', 'direction', 'number', 'intersections'])), $quiz['crossword_layout']['placements']);
    }
    return $quiz;
}

function attempt_quiz_version(array $quiz, array $attempt): array
{
    $snapshot = $attempt['answers']['_quiz_snapshot'] ?? null;
    return is_array($snapshot) && is_array($snapshot['questions'] ?? null) ? $snapshot : $quiz;
}

// Save only this classroom and preserve the interpretation of prior attempts.
function persist_builder_quiz(PDO $pdo, int $classroomId, int $teacherId, array $quiz, bool $isNew): void
{
    $pdo->beginTransaction();
    try {
        $select = $pdo->prepare('SELECT * FROM classrooms WHERE id = ? FOR UPDATE');
        $select->execute([$classroomId]);
        $record = $select->fetch();
        if (!$record || (int) $record['teacher_id'] !== $teacherId) throw new RuntimeException('Classroom access denied.');
        $classroom = hydrate_classroom($record);
        if ($isNew) {
            $quiz['id'] = next_id($classroom['quizzes']);
        } else {
            $oldQuiz = classroom_quiz($classroom, (int) $quiz['id']);
            if (!$oldQuiz) throw new RuntimeException('Quiz not found.');
            $attempts = $pdo->prepare('SELECT id, answers FROM attempts WHERE classroom_id = ? AND quiz_id = ?');
            $attempts->execute([$classroomId, (int) $quiz['id']]);
            $update = $pdo->prepare('UPDATE attempts SET answers = ? WHERE id = ?');
            foreach ($attempts->fetchAll() as $attempt) {
                $answers = db_json_decode($attempt['answers']);
                if (!isset($answers['_quiz_snapshot'])) {
                    $answers['_quiz_snapshot'] = $oldQuiz;
                    $update->execute([db_json_encode($answers), (int) $attempt['id']]);
                }
            }
        }
        $classroom = update_quiz_in_classroom($classroom, $quiz);
        insert_classroom_record($pdo, $classroom);
        grading_sync_quiz($pdo, $classroomId, $teacherId, $quiz);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function activity_answer_is_correct(array $question, $answer): bool
{
    if (!is_string($answer) || trim($answer) === '') return false;
    $normalize = static function (string $value) use ($question): string {
        $value = trim($value);
        return !empty($question['case_sensitive']) ? $value : (function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value));
    };
    $accepted = array_merge([(string) ($question['answer'] ?? '')], $question['accepted_answers'] ?? []);
    return in_array($normalize($answer), array_map($normalize, $accepted), true);
}

// One validation and layout path for both unsaved previews and saved activities.
function prepare_activity_questions(string $gameType, $decodedQuestions): array
{
    $masteryLevels = mastery_levels();
    $errors = [];
    $questions = [];
    $crosswordLayout = null;
    if (!is_array($decodedQuestions) || count($decodedQuestions) < 1 || count($decodedQuestions) > 40) {
        $errors[] = 'Add between 1 and 40 questions to the quiz.';
    } else {
        $levelCounts = array_fill_keys(array_keys($masteryLevels), 0);
        $answerLengths = [];
        $levelsUsed = [];
        $answersUsed = [];

        foreach ($decodedQuestions as $index => $question) {
            if (!is_array($question) || !is_string($question['prompt'] ?? null) || !is_array($question['options'] ?? []) || count(array_filter($question['options'] ?? [], 'is_string')) !== count($question['options'] ?? [])) {
                $errors[] = 'Invalid question data.';
                continue;
            }
            $prompt = trim($question['prompt'] ?? '');
            $options = array_map('trim', $question['options'] ?? []);
            $correctIndex = (int) ($question['correct_index'] ?? 0);
            $points = max(5, min(1000, is_numeric($question['points'] ?? null) ? (int) $question['points'] : 10));
            $level = is_string($question['level'] ?? null) ? strtolower(trim($question['level'])) : 'easy';

            if (!isset($masteryLevels[$level])) {
                $level = 'easy';
            }

            if (activity_uses_text_answers($gameType)) {
                $answer = is_string($question['answer'] ?? null) ? trim($question['answer']) : '';
                $alternatives = $question['accepted_answers'] ?? [];
                if (strlen(is_string($question['hint'] ?? null) ? $question['hint'] : '') > 2000 || strlen(is_string($question['explanation'] ?? null) ? $question['explanation'] : '') > 8000) {
                    $errors[] = 'Keep hints under 500 characters and explanations under 2000 characters.';
                    continue;
                }
                if ($prompt === '' || $answer === '' || strlen($prompt) > 2000 || strlen($answer) > 250 || !is_array($alternatives) || count($alternatives) > 20 || count(array_filter($alternatives, 'is_string')) !== count($alternatives)) {
                    $errors[] = 'Item ' . ($index + 1) . ' needs a prompt and answer (maximum 250 characters), with up to 20 alternative answers.';
                    continue;
                }
                $images = [];
                if ($gameType === 'flip_match') {
                    try {
                        $images = ['prompt_image' => activity_match_image($question['prompt_image'] ?? ''),
                            'answer_image' => activity_match_image($question['answer_image'] ?? '')];
                    } catch (InvalidArgumentException $error) {
                        $errors[] = 'Pair ' . ($index + 1) . ': ' . $error->getMessage();
                        continue;
                    }
                }
                $questions[] = $images + ['id' => $index + 1, 'prompt' => $prompt, 'answer' => $answer,
                    'accepted_answers' => array_values(array_filter(array_map('trim', $alternatives))),
                    'case_sensitive' => !empty($question['case_sensitive']),
                    'hint' => is_string($question['hint'] ?? null) ? trim($question['hint']) : '',
                    'explanation' => is_string($question['explanation'] ?? null) ? trim($question['explanation']) : '',
                    'options' => [], 'correct_index' => 0, 'points' => min(1000, $points), 'level' => $level];
                continue;
            }

            if ($gameType === 'crossword') {
                $answer = crossword_normalize_answer(is_string($question['answer'] ?? null) ? $question['answer'] : '');

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
                    'preferred_direction' => in_array(($question['preferred_direction'] ?? ''), ['auto', 'across', 'down'], true)
                        ? $question['preferred_direction']
                        : 'auto',
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

    if ($gameType === 'flip_match' && (count($questions) < 2 || count($questions) > 12)) {
        $errors[] = 'Flip Match needs 2 to 12 pairs.';
    }
    if ($gameType === 'flip_match') {
        foreach (['prompt', 'answer'] as $field) {
            $values = array_map(static fn($q) => !empty($q[$field . '_image']) ? hash('sha256', $q[$field . '_image']) : strtolower($q[$field]), $questions);
            if (count(array_unique($values)) !== count($values)) $errors[] = 'Use distinct terms and definitions so each card has one matching pair.';
        }
    }

    if ($gameType === 'flip_match' && array_sum(array_map(static fn($q) => strlen($q['prompt_image'] ?? '') + strlen($q['answer_image'] ?? ''), $questions)) > 1048576) {
        $errors[] = 'Keep all matching images together under 1 MB. Use smaller images or fewer image pairs.';
    }

    return ['questions' => $questions, 'layout' => $crosswordLayout, 'errors' => $errors];
}
