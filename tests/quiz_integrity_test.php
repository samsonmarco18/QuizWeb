<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/quiz_integrity.php';
function check_integrity(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$run = ['student_id' => 1, 'classroom_id' => 2, 'quiz_id' => 3];
try { quiz_integrity_event($run, 'violation', str_repeat('a', 32), 'focus_loss'); throw new LogicException('Accepted violation before start.'); }
catch (InvalidArgumentException $expected) {}
$run = quiz_integrity_event($run, 'start', str_repeat('a', 32));
for ($i = 1; $i <= 3; $i++) {
    $event = str_repeat((string) $i, 32);
    $run = quiz_integrity_event($run, 'violation', $event, $i === 3 ? 'screenshot_shortcut' : 'focus_loss');
    $retry = quiz_integrity_event($run, 'violation', $event, 'focus_loss');
    check_integrity($run === $retry, 'Repeated event must not count twice.');
    check_integrity($run['integrity']['warnings'] === $i && !$run['integrity']['disqualified'], 'Three warnings are allowed.');
}
$run = quiz_integrity_event($run, 'start', str_repeat('b', 32));
check_integrity($run['integrity']['warnings'] === 3, 'Starting again cannot reset warnings.');
$run = quiz_integrity_event($run, 'violation', str_repeat('4', 32), 'fullscreen_exit');
check_integrity($run['integrity']['warnings'] === 4 && $run['integrity']['disqualified'], 'Fourth violation must force zero.');
check_integrity(quiz_integrity_metadata($run)['_violations'] === 4, 'Attempt metadata uses server warning count.');
$closed = quiz_integrity_event(quiz_integrity_event([], 'start', str_repeat('c', 32)), 'abandon', str_repeat('d', 32), 'page_exit');
check_integrity($closed['integrity']['disqualified'], 'Leaving an active quiz saves zero.');
$done = $run + ['attempt_id' => 12];
check_integrity(quiz_integrity_event($done, 'violation', str_repeat('e', 32), 'focus_loss') === $done, 'Completed attempts are immutable.');
foreach ([['start', 'bad', ''], ['other', str_repeat('f', 32), ''], ['violation', str_repeat('g', 32), 'fake']] as [$action, $id, $reason]) {
    try { quiz_integrity_event(quiz_integrity_event([], 'start', str_repeat('h', 32)), $action, $id, $reason); throw new LogicException('Invalid event accepted.'); }
    catch (InvalidArgumentException $expected) {}
}
$quiz = ['game_type' => 'standard', 'questions' => array_map(static fn($id) => ['id' => $id, 'prompt' => 'Q' . $id,
    'options' => ['A', 'B', 'C', 'D'], 'correct_index' => 1, 'points' => $id * 5], range(1, 8))];
for ($i = 0; $i < 20; $i++) {
    $shuffled = activity_shuffle_for_run($quiz);
    check_integrity(array_column($shuffled['questions'], 'id') !== range(1, 8), 'Question order must be jumbled.');
    foreach ($shuffled['questions'] as $q) {
        check_integrity($q['options'][$q['correct_index']] === 'B' && $q['points'] === $q['id'] * 5, 'Shuffling preserves correct answer and points.');
    }
}
check_integrity(array_column($quiz['questions'], 'id') === range(1, 8), 'Stored classroom quiz order is not mutated.');
$quiz['game_type'] = 'master_ladder';
foreach ($quiz['questions'] as $i => &$q) $q['level'] = ['easy', 'medium', 'hard', 'master'][intdiv($i, 2)];
unset($q);
$ladder = activity_shuffle_for_run($quiz);
check_integrity(array_column($ladder['questions'], 'level') === ['easy', 'easy', 'medium', 'medium', 'hard', 'hard', 'master', 'master'], 'Shuffling keeps mastery level progression and grading order.');
echo "Integrity start, warning limit, screenshot events, retries, abandonment, immutability, question/option shuffling, correct answers, points, and mastery ordering passed.\n";

$clean = activity_submission_answers(['game_type' => 'standard', 'questions' => [['options' => ['A','B','C','D']]]], [0 => 1, '_disqualified' => true, '_violations' => 0, '_quiz_snapshot' => []]);
check_integrity($clean === [0 => 1], 'Client cannot forge reserved security or snapshot metadata.');
$bad = activity_submission_answers(['game_type' => 'standard', 'questions' => [['options' => ['A','B','C','D']]]], [0 => [1]]);
check_integrity($bad[0] === null, 'Invalid multiple-choice types cannot earn points.');
