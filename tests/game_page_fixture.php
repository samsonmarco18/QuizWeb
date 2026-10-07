<?php
// Render the real play/practice template with memory records and no database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/layout.php';
$quiz = array_replace(['id' => 1, 'title' => 'Activity', 'description' => '', 'game_type' => 'standard', 'questions' => []], json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR));
$quizData = $quiz;
$modes = game_modes();
$classroomId = 1;
$classroom = ['id' => 1, 'name' => 'Science'];
$isPreview = true;
$runToken = 'fixture';
$_SESSION['activity_csrf'] = 'fixture';
$practiceQuestions = $quiz['questions'];
$practiceReturn = '/QuizWeb/dashboard.php';
$profile = ['overall_accuracy' => 75];
$page = ($argv[1] ?? '') === 'practice' ? 'learning/practice' : 'quizzes/play';
$source = file_get_contents(__DIR__ . '/../app/pages/' . $page . '.php');
$start = strpos($source, '<section class="game-shell"');
$end = strpos($source, '<?php render_footer(', $start);
echo '<!doctype html><html><body class="ui-refined game-page mode-' . esc($quiz['game_type']) . '"><main class="page-shell">';
eval('?>' . substr($source, $start, $end - $start));
echo '</main></body></html>';
