<?php
require_once dirname(__DIR__, 3) . '/includes/app.php';
require_once dirname(__DIR__, 3) . '/includes/profile.php';
$viewer = current_user();
if (!$viewer) { http_response_code(401); exit; }
$target = find_user_by_id((int) ($_GET['id'] ?? 0));
if (!$target) { http_response_code(404); exit; }
if (!can_view_profile_photo($viewer, $target, user_classrooms($viewer))) { http_response_code(403); exit; }
$photo = profile_photo_metadata($target);
if (!$photo) { http_response_code(404); exit; }
$bytes = profile_photo_bytes($target);
if ($bytes === null) { http_response_code(404); exit; }
session_write_close();
header('Content-Type: ' . $photo['mime']);
header('Content-Disposition: inline');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
