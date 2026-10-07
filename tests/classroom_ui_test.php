<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/classroom_ui.php';
function check_classroom(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$now = strtotime('2026-10-07 10:00:00');
$question = ['points' => 10];
$class = ['id' => 1, 'quizzes' => [
    ['id' => 1, 'title' => 'Science Foundations', 'description' => 'Fast science', 'game_type' => 'time_attack', 'questions' => [$question, $question]],
    ['id' => 2, 'title' => 'Written Terms', 'description' => 'Ecology concepts', 'game_type' => 'fill_blank', 'questions' => [$question], 'due_at' => '2026-10-07 16:00:00'],
    ['id' => 3, 'title' => 'Crossword', 'description' => '', 'game_type' => 'crossword', 'questions' => [], 'due_at' => '2026-10-06 16:00:00'],
    ['id' => 4, 'title' => 'Alpha', 'description' => '', 'game_type' => 'standard', 'questions' => [$question], 'due_at' => 'not a date'],
]];
$attempts = [
    ['id' => 1, 'classroom_id' => 1, 'student_id' => 6, 'quiz_id' => 1, 'score' => 9, 'max_score' => 10],
    ['id' => 2, 'classroom_id' => 1, 'student_id' => 6, 'quiz_id' => 1, 'score' => 179, 'max_score' => 200],
    ['id' => 3, 'classroom_id' => 1, 'student_id' => 7, 'quiz_id' => 2, 'score' => 10, 'max_score' => 10],
    ['id' => 4, 'classroom_id' => 2, 'student_id' => 6, 'quiz_id' => 2, 'score' => 10, 'max_score' => 10],
    ['id' => 5, 'classroom_id' => 1, 'student_id' => 6, 'quiz_id' => 4, 'score' => 0, 'max_score' => 10],
];
$cards = classroom_quiz_cards($class, ['id' => 6], $attempts, $now);
check_classroom($cards[0]['best']['id'] === 1, 'Best uses true accuracy, not latest or rounded accuracy.');
check_classroom($cards[0]['points'] === 20 && $cards[0]['percent'] === 90, 'Score and configured points are accurate.');
check_classroom(!$cards[1]['completed'] && $cards[1]['best'] === null, 'Another student or classroom cannot supply the displayed score.');
check_classroom($cards[3]['completed'] && $cards[3]['percent'] === 0, 'A completed zero-point attempt is still completed.');
check_classroom($cards[1]['due_status'] === 'soon' && $cards[1]['days_left'] === 0, 'Same-day deadlines display Due today.');
check_classroom($cards[2]['due_status'] === 'past' && $cards[3]['due'] === null, 'Past deadlines remain playable; malformed dates are omitted.');
$filters = classroom_quiz_filters(['quiz_filter' => 'completed', 'q' => '', 'quiz_sort' => 'assigned']);
check_classroom(array_column(array_column(classroom_filter_quizzes($cards, $filters), 'quiz'), 'id') === [1, 4], 'Completion filter covers the entire catalog.');
$filters['filter'] = 'todo'; check_classroom(count(classroom_filter_quizzes($cards, $filters)) === 2, 'To Do excludes completed attempts.');
$filters['filter'] = 'fill_blank'; $filters['search'] = 'ECOLOGY'; check_classroom(count(classroom_filter_quizzes($cards, $filters)) === 1, 'Game mode and case-insensitive description search combine.');
$filters['filter'] = 'all'; $filters['search'] = 'time attack'; check_classroom(count(classroom_filter_quizzes($cards, $filters)) === 1, 'Search matches readable game labels.');
$filters['search'] = ''; $filters['sort'] = 'due'; check_classroom(classroom_filter_quizzes($cards, $filters)[0]['quiz']['id'] === 3, 'Due-date sorting handles missing dates.');
$filters['sort'] = 'title'; check_classroom(classroom_filter_quizzes($cards, $filters)[0]['quiz']['title'] === 'Alpha', 'Title sorting is alphabetical.');
check_classroom(classroom_quiz_filters(['q' => [], 'quiz_filter' => [], 'quiz_sort' => []]) === ['filter' => 'all', 'sort' => 'assigned', 'search' => ''], 'Malformed query arrays fail safely.');
check_classroom(classroom_quiz_filters(['quiz_filter' => 'unknown'])['filter'] === 'all', 'Unknown game filters fall back to All.');
check_classroom(classroom_quiz_cards(['id' => 1], ['id' => 6], [], $now) === [], 'Empty classrooms render without fabricated progress.');
echo "Classroom catalog: private best attempts, zero-score completion, configured points, safe deadlines, catalog-wide filters/search/sorting, malformed queries, and empty states passed.\n";
