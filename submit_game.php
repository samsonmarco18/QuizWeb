<?php

require_once __DIR__ . '/includes/app.php';

$user = require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/QuizWeb/dashboard.php');
}

$classroomId = (int) ($_POST['classroom_id'] ?? 0);
$quizId = (int) ($_POST['quiz_id'] ?? 0);
$elapsedSeconds = max(0, (int) ($_POST['elapsed_seconds'] ?? 0));
$answers = json_decode($_POST['answers'] ?? '[]', true);
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

$attempt = create_attempt((int) $user['id'], (int) $classroom['id'], $quiz, $answers, $elapsedSeconds);
redirect('/QuizWeb/results.php?id=' . $attempt['id']);
