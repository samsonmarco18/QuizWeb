<?php

require_once dirname(__DIR__, 3) . '/includes/app.php';

$user = require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/QuizWeb/dashboard.php');
}

$classroomId = (int) ($_POST['classroom_id'] ?? 0);
$quizId = (int) ($_POST['quiz_id'] ?? 0);
$elapsedSeconds = max(0, (int) ($_POST['elapsed_seconds'] ?? 0));
$answers = json_decode($_POST['answers'] ?? '[]', true);
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

if (!is_array($answers)) {
    $answers = [];
}

if (activity_uses_text_answers($quiz['game_type'])) {
    if (!is_string($_POST['csrf'] ?? null) || !isset($_SESSION['activity_csrf']) || !hash_equals($_SESSION['activity_csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('Your activity session expired. Return to the classroom and start again.');
    }
    foreach ($quiz['questions'] as $index => $question) {
        $answers[$index] = is_string($answers[$index] ?? null) && strlen($answers[$index]) <= 2000 ? $answers[$index] : '';
    }
    $answers = array_intersect_key($answers, array_flip(array_merge(array_keys($quiz['questions']), ['_moves'])));
    if (isset($answers['_moves'])) $answers['_moves'] = max(0, min(100000, (int) $answers['_moves']));
}

if ($disqualified) {
    $answers = [
        '_disqualified' => true,
        '_reason' => trim((string) ($_POST['violation_reason'] ?? 'quiz_integrity_violation')),
        '_violations' => max(0, (int) ($_POST['violation_count'] ?? 0)),
    ];
}

$attempt = create_attempt((int) $user['id'], (int) $classroom['id'], $quiz, $answers, $elapsedSeconds, $disqualified);
redirect('/QuizWeb/results.php?id=' . $attempt['id']);
