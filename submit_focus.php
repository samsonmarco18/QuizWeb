<?php

require_once __DIR__ . '/includes/app.php';

$user = require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/QuizWeb/practice.php');
}

$quiz = $_SESSION['focus_training_quiz'] ?? null;
$answers = json_decode($_POST['answers'] ?? '[]', true);
$elapsedSeconds = max(0, (int) ($_POST['elapsed_seconds'] ?? 0));

if (!is_array($quiz) || !is_array($quiz['questions'] ?? null) || !is_array($answers)) {
    flash_set('danger', 'The focus training session expired. Please start it again.');
    redirect('/QuizWeb/practice.php');
}

$quiz['id'] = 0;
$quiz['title'] = 'Focus Training';
$quiz['game_type'] = 'focus_training';
create_attempt((int) $user['id'], 0, $quiz, $answers, $elapsedSeconds);
unset($_SESSION['focus_training_quiz']);
$returnUrl = safe_local_path((string) ($_SESSION['focus_training_return'] ?? '/QuizWeb/dashboard.php'), '/QuizWeb/dashboard.php');
unset($_SESSION['focus_training_return']);

flash_set('success', 'Focus Training recorded. It will not affect your classroom leaderboard.');
redirect($returnUrl);
