<?php

require_once dirname(__DIR__, 3) . '/includes/app.php';
require_once dirname(__DIR__, 3) . '/includes/quiz_integrity.php';

$user = require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/QuizWeb/dashboard.php');
}
if (!is_string($_POST['csrf'] ?? null) || !isset($_SESSION['activity_csrf']) || !hash_equals($_SESSION['activity_csrf'], $_POST['csrf'])) {
    http_response_code(403);
    exit('Your activity session expired. Return to the classroom and start again.');
}

$classroomId = (int) ($_POST['classroom_id'] ?? 0);
$quizId = (int) ($_POST['quiz_id'] ?? 0);
$elapsedSeconds = max(0, (int) ($_POST['elapsed_seconds'] ?? 0));
$answers = json_decode(is_string($_POST['answers'] ?? null) ? $_POST['answers'] : '[]', true);
$disqualified = ($_POST['disqualified'] ?? '') === '1';
$classroom = find_classroom($classroomId);

if (!$classroom || !classroom_belongs_to_user($classroom, $user)) {
    flash_set('danger', 'Classroom access denied.');
    redirect('/QuizWeb/dashboard.php');
}

$quiz = classroom_quiz($classroom, $quizId);

if (!$quiz) {
    flash_set('danger', 'Quiz not found.');
    redirect('/QuizWeb/classroom.php?id=' . $classroom['id']);
}

$runToken = is_string($_POST['run_token'] ?? null) ? $_POST['run_token'] : '';
$run = $_SESSION['activity_runs'][$runToken] ?? null;
if (!$run || (int) $run['classroom_id'] !== $classroomId || (int) $run['quiz_id'] !== $quizId || (int) $run['student_id'] !== (int) $user['id']) {
    http_response_code(403);
    exit('This activity session expired. Return to the classroom and start again.');
}
if (isset($run['attempt_id'])) redirect('/QuizWeb/results.php?id=' . (int) $run['attempt_id']);
$quiz = $run['quiz_snapshot'] ?? $quiz;
if (empty($run['integrity']['started_at'])) {
    http_response_code(403); exit('Start the quiz in fullscreen before submitting.');
}
$disqualified = $disqualified || !empty($run['integrity']['disqualified']);

if (!is_array($answers)) {
    $answers = [];
}

$answers = activity_submission_answers($quiz, $answers);

$answers = array_replace($answers, quiz_integrity_metadata($run));
if ($disqualified) {
    $answers = ['_disqualified' => true] + quiz_integrity_metadata($run);
}

$attempt = create_attempt((int) $user['id'], (int) $classroom['id'], $quiz, $answers, $elapsedSeconds, $disqualified);
$_SESSION['activity_runs'][$runToken]['attempt_id'] = (int) $attempt['id'];
redirect('/QuizWeb/results.php?id=' . $attempt['id']);
