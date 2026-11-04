<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
function check_activity(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$item = ['prompt' => '_____ styles a web page.', 'answer' => 'CSS', 'accepted_answers' => ['Cascading Style Sheets'], 'options' => [], 'points' => 10, 'level' => 'easy', 'explanation' => 'CSS defines presentation.'];
$reused = reusable_quiz_copy(['id' => 9, 'title' => 'Reusable quiz', 'questions' => [$item], 'game_type' => 'fill_blank', 'grade_category_id' => 'quiz', 'grade_period_id' => 'prelim', 'grade_max_score' => 50, 'created_at' => 'old', 'updated_at' => 'old']);
check_activity(!isset($reused['id']) && $reused['title'] === 'Reusable quiz' && $reused['questions'] === [$item], 'Reuse preserves activity content but allocates a new destination ID.');
check_activity($reused['grade_category_id'] === '' && $reused['grade_period_id'] === '' && $reused['grade_max_score'] === null, 'Reuse resets classroom-specific grading assignments.');
check_activity(array_keys(game_modes()) === ['standard', 'fill_blank', 'crossword', 'master_ladder'], 'Only the four supported game modes are discoverable.');
foreach (['fill_blank', 'emoji_quiz'] as $mode) {
    $prepared = prepare_activity_questions($mode, [$item]);
    check_activity(!$prepared['errors'], $mode . ' must validate.');
    $question = $prepared['questions'][0];
    check_activity(activity_answer_is_correct($question, ' css '), 'Whitespace and case normalization.');
    check_activity(activity_answer_is_correct($question, 'cascading style sheets'), 'Alternative answers.');
    check_activity(!activity_answer_is_correct($question, '') && !activity_answer_is_correct($question, ['CSS']), 'Blank and malformed answers fail.');
    check_activity(!activity_answer_is_correct(array_replace($question, ['case_sensitive' => true]), 'css'), 'Case-sensitive setting.');
    $rows = attempt_question_review_rows(['game_type' => $mode, 'questions' => [$question]], ['answers' => ['css']]);
    check_activity($rows[0]['earned_points'] === 10 && $rows[0]['correct_answer'] === 'CSS', 'Review awards correct points and answer label.');
    check_activity($rows[0]['guidance'] === $item['explanation'], 'Explanation appears in review.');
}
$pairs = [$item, array_replace($item, ['prompt' => 'HTML', 'answer' => 'Markup'])];
check_activity(!prepare_activity_questions('flip_match', $pairs)['errors'], 'Valid matching pairs.');
check_activity((bool) prepare_activity_questions('flip_match', [$item, $item])['errors'], 'Ambiguous pairs rejected.');
check_activity((bool) prepare_activity_questions('fill_blank', array_fill(0, 101, $item))['errors'], 'Question count bounded.');
check_activity((bool) prepare_activity_questions('fill_blank', [['prompt' => []]])['errors'], 'Malformed prompt rejected.');
$words = [
    ['prompt' => 'A programming language', 'answer' => 'PYTHON', 'preferred_direction' => 'across', 'options' => [], 'level' => 'easy'],
    ['prompt' => 'A data kind', 'answer' => 'TYPE', 'preferred_direction' => 'down', 'options' => [], 'level' => 'easy'],
    ['prompt' => 'A network machine', 'answer' => 'HOST', 'preferred_direction' => 'down', 'options' => [], 'level' => 'easy'],
];
$crossword = prepare_activity_questions('crossword', $words);
check_activity(!$crossword['errors'], 'Connected crossword validates: ' . implode('; ', $crossword['errors']));
check_activity(count($crossword['layout']['placements']) === 3, 'Every word placed.');
check_activity($crossword['layout'] === prepare_activity_questions('crossword', $words)['layout'], 'Preview and save layout deterministic.');
$automatic = array_map(static fn(array $word): array => array_replace($word, ['preferred_direction' => 'auto']), $words);
check_activity(!prepare_activity_questions('crossword', $automatic)['errors'], 'Automatic directions connect the grid.');
$sameDirection = array_map(static fn(array $word): array => array_replace($word, ['preferred_direction' => 'across']), $words);
check_activity((bool) prepare_activity_questions('crossword', $sameDirection)['errors'], 'Explicit incompatible directions are not silently changed.');
$standard = prepare_activity_questions('standard', [['prompt' => '2 + 2?', 'options' => ['1','2','3','4'], 'correct_index' => 3]]);
check_activity(!$standard['errors'] && $standard['questions'][0]['correct_index'] === 3, 'Standard multiple-choice behavior retained.');
echo "Activity validation, grading review, matching, and crossword layout checks passed.\n";
