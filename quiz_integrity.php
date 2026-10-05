<?php
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/quiz_integrity.php';
$user = require_role('student');
header('Content-Type: application/json');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(json_encode(['error' => 'POST required.'])); }
if (!is_string($_POST['csrf'] ?? null) || !isset($_SESSION['activity_csrf']) || !hash_equals($_SESSION['activity_csrf'], $_POST['csrf'])) {
    http_response_code(403); exit(json_encode(['error' => 'Your quiz session expired. Return to the classroom.']));
}
$token = is_string($_POST['run_token'] ?? null) ? $_POST['run_token'] : '';
$run = $_SESSION['activity_runs'][$token] ?? null;
if (!$run || (int) $run['student_id'] !== (int) $user['id']
    || (int) $run['classroom_id'] !== (int) ($_POST['classroom_id'] ?? 0)
    || (int) $run['quiz_id'] !== (int) ($_POST['quiz_id'] ?? 0)) {
    http_response_code(403); exit(json_encode(['error' => 'Invalid quiz session.']));
}
$classroom = find_classroom((int) $run['classroom_id']);
if (!$classroom || !classroom_belongs_to_user($classroom, $user)) {
    http_response_code(403); exit(json_encode(['error' => 'Classroom access denied.']));
}
try {
    $run = quiz_integrity_event($run,
        is_string($_POST['action'] ?? null) ? $_POST['action'] : '',
        is_string($_POST['event_id'] ?? null) ? $_POST['event_id'] : '',
        is_string($_POST['reason'] ?? null) ? $_POST['reason'] : '');
    // Commit the disqualification state before saving. A retried event can
    // finish a failed write, but cannot convert the run into a normal score.
    $_SESSION['activity_runs'][$token] = $run;
    if (!empty($run['integrity']['disqualified']) && !isset($run['attempt_id'])) {
        $answers = ['_disqualified' => true] + quiz_integrity_metadata($run);
        $elapsed = max(0, time() - (int) $run['integrity']['started_at']);
        $attempt = create_attempt((int) $user['id'], (int) $run['classroom_id'], $run['quiz_snapshot'], $answers, $elapsed, true);
        $run['attempt_id'] = (int) $attempt['id'];
        $_SESSION['activity_runs'][$token] = $run;
    }
    echo json_encode([
        'warnings' => (int) ($run['integrity']['warnings'] ?? 0),
        'max_warnings' => QUIZ_INTEGRITY_MAX_WARNINGS,
        'disqualified' => !empty($run['integrity']['disqualified']),
        'results_url' => isset($run['attempt_id']) ? '/QuizWeb/results.php?id=' . (int) $run['attempt_id'] : null,
    ]);
} catch (InvalidArgumentException $error) {
    http_response_code(422); echo json_encode(['error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('Quiz integrity persistence failed: ' . $error->getMessage());
    http_response_code(503); echo json_encode(['error' => 'Could not save quiz security status. Please retry.']);
}
