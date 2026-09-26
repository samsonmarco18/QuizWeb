<?php

function activity_uses_text_answers(string $mode): bool
{
    return in_array($mode, ['fill_blank', 'emoji_quiz', 'flip_match'], true);
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
                $questions[] = ['id' => $index + 1, 'prompt' => $prompt, 'answer' => $answer,
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
            $values = array_map('strtolower', array_column($questions, $field));
            if (count(array_unique($values)) !== count($values)) $errors[] = 'Use distinct terms and definitions so each card has one matching pair.';
        }
    }

    return ['questions' => $questions, 'layout' => $crosswordLayout, 'errors' => $errors];
}
