<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/classroom_ui.php';
function check_activity(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$class = ['id' => 1, 'teacher_id' => 5, 'student_ids' => [6], 'quizzes' => [
    ['id' => 1, 'title' => 'Lab game', 'activity_type' => 'Laboratory', 'game_type' => 'crossword', 'questions' => []],
    ['id' => 2, 'title' => 'Quiz game', 'game_type' => 'standard', 'questions' => []],
]];
$user = ['id' => 6, 'role' => 'student'];
$book = grading_empty_book();
$book['config']['categories'] = [['id' => 'lab', 'name' => 'Laboratory', 'weight' => 100]];
$book['items'] = [
    'manual-1' => ['id' => 'manual-1', 'source' => 'manual', 'name' => 'Lab report', 'category_id' => 'lab', 'max_score' => 50, 'accept_responses' => true],
    'manual-2' => ['id' => 'manual-2', 'source' => 'manual', 'name' => 'Archived', 'category_id' => 'lab', 'max_score' => 50, 'archived' => true],
    'quiz-1' => ['id' => 'quiz-1', 'source' => 'quiz', 'quiz_id' => 1],
];
$book['scores']['manual-1']['6'] = 42;
$manual = classroom_manual_activity_cards($class, $user, $book);
check_activity(count($manual) === 1, 'Hide archived activities and avoid duplicating games.');
check_activity($manual[0]['score'] === null && !$manual[0]['completed'], 'Unpublished scores and grading state must stay private.');
$manual = classroom_manual_activity_cards($class, $user, $book, ['manual-1' => true]);
check_activity($manual[0]['completed'] && $manual[0]['status'] === 'Submitted', 'A response counts as completed without exposing a grade.');
check_activity(str_contains($manual[0]['url'], 'student_portal.php') && str_contains($manual[0]['url'], 'manual-1'), 'Manual activities use the existing protected response page.');
$games = classroom_quiz_cards($class, $user, []);
$all = array_merge($games, $manual);
$filters = classroom_quiz_filters(['quiz_filter' => 'activity:Laboratory']);
check_activity(count(classroom_filter_quizzes($all, $filters)) === 2, 'Activity types combine manual and game activities.');
$filters['filter'] = 'activity:Quiz';
check_activity(count(classroom_filter_quizzes($all, $filters)) === 1, 'Existing games default to Quiz.');
$filters['filter'] = 'crossword';
check_activity(count(classroom_filter_quizzes($all, $filters)) === 1, 'Game mode filters exclude manual work.');
$teacher = classroom_manual_activity_cards($class, ['id' => 5, 'role' => 'teacher'], $book);
check_activity(str_contains($teacher[0]['url'], 'view=entry'), 'Teachers open the activity grade entry page.');
$book['published']['students']['6']['items']['manual-1']['score'] = 40;
check_activity(classroom_manual_activity_cards($class, $user, $book)[0]['score'] === 40, 'Only the released snapshot supplies manual scores.');
echo "Activity types, mixed catalog, response links, archived items, and unpublished-score privacy passed.\n";
