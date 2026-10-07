<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/classroom_ui.php';
$input = stream_get_contents(STDIN);
$request = $input === '' ? [] : json_decode($input, true, 512, JSON_THROW_ON_ERROR);
$_GET = $request['query'] ?? [];
$user = ['id' => ($request['role'] ?? '') === 'teacher' ? 5 : 6, 'role' => ($request['role'] ?? '') === 'teacher' ? 'teacher' : 'student'];
$classroomId = 1;
$modes = game_modes();
$question = ['points' => 10];
$classroom = ['id' => 1, 'name' => 'Grade 10 Science', 'subject' => 'Science', 'code' => 'DEMO1', 'description' => 'Learn through classroom quiz activities.', 'teacher_id' => 5, 'student_ids' => [6, 7], 'quizzes' => []];
foreach ([['time_attack', 'Science Foundations'], ['standard', 'DASD'], ['flip_match', 'Image Match Demo'], ['crossword', 'Key Terms Review'], ['fill_blank', 'Written Terms'], ['emoji_quiz', '<img src=x onerror=alert(1)>'], ['rocket_rush', 'Beyond First Page']] as $i => [$type, $title]) {
    $classroom['quizzes'][] = ['id' => $i + 1, 'title' => $title, 'description' => 'A classroom activity for learning and practice.', 'game_type' => $type, 'questions' => array_fill(0, $type === 'flip_match' ? 2 : 4, $question), 'due_at' => $type === 'crossword' ? date('c', time() + 2 * 86400) : null];
}
if (!empty($request['empty'])) $classroom['quizzes'] = [];
$attemptsList = [
    ['id' => 11, 'student_id' => 6, 'classroom_id' => 1, 'quiz_id' => 1, 'score' => 40, 'max_score' => 40],
    ['id' => 12, 'student_id' => 6, 'classroom_id' => 1, 'quiz_id' => 1, 'score' => 10, 'max_score' => 40],
    ['id' => 13, 'student_id' => 6, 'classroom_id' => 1, 'quiz_id' => 3, 'score' => 20, 'max_score' => 20],
    ['id' => 14, 'student_id' => 7, 'classroom_id' => 1, 'quiz_id' => 2, 'score' => 40, 'max_score' => 40],
];
$quizCards = classroom_quiz_cards($classroom, $user, $attemptsList);
$quizFilters = classroom_quiz_filters($_GET);
$filteredQuizCards = classroom_filter_quizzes($quizCards, $quizFilters);
$quizPage = page_records($filteredQuizCards, 'quiz_page', 6);
$host = ['id' => 5, 'name' => 'Dr. Elena Cruz', 'profile' => []];
$roster = [['id' => 6, 'name' => 'Ava Mendoza', 'email' => 'ava@example.test', 'profile' => []]];
$progressDone = $user['role'] === 'teacher' ? 2 : count(array_filter($quizCards, static fn(array $card): bool => $card['completed']));
$progressTotal = $user['role'] === 'teacher' ? 2 : count($quizCards);
$classProgress = percentage($progressDone, $progressTotal);
$upcomingQuizzes = array_values(array_filter($quizCards, static fn(array $card): bool => $card['due'] !== null));
$classroomViews = ['overview' => 'Overview', 'quizzes' => 'Quizzes', 'materials' => 'Materials', 'results' => 'Results', 'grades' => 'Grades'];
$tabIcons = ['overview' => 'archive', 'quizzes' => 'Play Game Modes', 'materials' => 'document', 'results' => 'chart', 'grades' => 'star'];
$activeView = $request['view'] ?? 'quizzes';
$announcements = [];
$announcementErrors = $chatErrors = [];
$chatMessages = [];
$chatMessageCount = 0;
$classLearningProfile = ['overall_accuracy' => 75, 'analyzed_attempts' => 3, 'weak_questions' => [], 'weak_levels' => []];
$studentClassProfile = ['overall_accuracy' => 70, 'questions_seen' => 4, 'focus_items' => [], 'trend_message' => 'Keep practising.'];
$studentClassPracticeQuestions = [];
$source = file_get_contents(__DIR__ . '/../app/pages/classrooms/classroom.php');
$start = strpos($source, '<section class="classroom-banner glass">');
$end = strpos($source, '<?php render_footer(', $start);
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
foreach (['site', 'refinements', 'visual-polish', 'game-experience', 'classroom'] as $style) echo '<link rel="stylesheet" href="/QuizWeb/assets/css/' . $style . '.css">';
echo '</head><body class="ui-refined classroom-page classroom-redesign"><main class="page-shell">';
eval('?>' . substr($source, $start, $end - $start));
echo '</main></body></html>';
