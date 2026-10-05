<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
$quiz = ['game_type' => 'crossword', 'questions' => [['id' => 1, 'prompt' => 'A pet', 'answer' => 'CAT', 'options' => ['CAT'], 'accepted_answers' => ['CAT'], 'correct_index' => 0, 'points' => 10]], 'crossword_layout' => ['cells' => [['C', 'A', 'T']], 'placements' => [['answer' => 'CAT', 'question_id' => 1, 'row' => 0, 'col' => 0, 'direction' => 'across']]]];
$public = activity_public_crossword($quiz, false);
if (isset($public['questions'][0]['answer']) || isset($public['questions'][0]['options']) || isset($public['questions'][0]['correct_index'])) throw new RuntimeException('Student keys exposed.');
if ($public['questions'][0]['word_length'] !== 3 || $public['crossword_layout']['cells'] !== [[1, 1, 1]]) throw new RuntimeException('Public puzzle shape invalid.');
if (str_contains(json_encode($public), 'CAT') || $quiz['questions'][0]['answer'] !== 'CAT') throw new RuntimeException('Answer leaked or original grading quiz mutated.');
if (activity_public_crossword($quiz, true) !== $quiz) throw new RuntimeException('Teacher preview lost answer key.');
$review = attempt_question_review_rows($quiz, ['game_type' => 'crossword', 'answers' => [0 => 'cat']]);
if (!$review[0]['is_correct'] || $review[0]['earned_points'] !== 10) throw new RuntimeException('Server review grading changed.');
echo "Crossword public key isolation, shape, teacher preview, unchanged original, and server grading passed.\n";
