<?php
require_once __DIR__ . '/includes/app.php'; require_once __DIR__ . '/includes/participation.php';
header('Content-Type: application/json'); header('Cache-Control: no-store');
$user = current_user();
if (!$user || $user['role'] !== 'student' || $_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(403); exit('{"error":"Access denied"}'); }
if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['activity_csrf'] ?? '', $_POST['csrf']) || empty($_SESSION['activity_csrf'])) { http_response_code(403); exit('{"error":"Session expired"}'); }
$token = is_string($_POST['run_token'] ?? null) ? $_POST['run_token'] : ''; $run = $_SESSION['activity_runs'][$token] ?? null;
if (!$run || isset($run['attempt_id']) || empty($run['integrity']['started_at']) || !empty($run['integrity']['disqualified'])) { http_response_code(409); exit('{"error":"Quiz run is no longer active"}'); }
$classroom = find_classroom((int) $run['classroom_id']);
if (!$classroom || !classroom_belongs_to_user($classroom, $user) || !classroom_quiz($classroom, (int) $run['quiz_id'])) { http_response_code(403); exit('{"error":"Classroom access denied"}'); }
participation_ping(db(), $user, $run, $token); session_write_close(); echo '{"ok":true}';
