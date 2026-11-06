<?php
require_once __DIR__ . '/includes/app.php';
$user = require_login();
$name = is_string($_GET['file'] ?? null) ? $_GET['file'] : '';
if (!preg_match('/^[a-f0-9]{48}$/D', $name)) { http_response_code(404); exit('Attachment not found.'); }
$query = db()->prepare('SELECT * FROM activity_submission_files WHERE stored_name = ?'); $query->execute([$name]); $file = $query->fetch();
if (!$file) { http_response_code(404); exit('Attachment not found.'); }
$class = find_classroom((int) $file['classroom_id']);
if (!$class || !classroom_belongs_to_user($class, $user) || !(($user['role'] === 'teacher' && (int) $class['teacher_id'] === (int) $user['id']) || ($user['role'] === 'student' && (int) $file['student_id'] === (int) $user['id']))) { http_response_code(403); exit('Attachment access denied.'); }
$bytes = persistent_upload_read('submissions', $name);
if ($bytes === null) { http_response_code(404); exit('Attachment not found.'); }
$filename = sanitize_filename($file['original_name']);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="attachment"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store'); header('Content-Length: ' . strlen($bytes));
echo $bytes;
