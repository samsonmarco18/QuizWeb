<?php
require_once __DIR__ . '/includes/app.php';
$user = require_login();
$classroom = find_classroom((int) ($_GET['classroom_id'] ?? 0));
if (!$classroom || !classroom_belongs_to_user($classroom, $user)) { http_response_code(403); exit('Access denied.'); }
foreach ($classroom['chat_messages'] ?? [] as $message) {
    if ((int) $message['id'] !== (int) ($_GET['message_id'] ?? 0)) continue;
    foreach ($message['attachments'] ?? [] as $file) {
        if ($file['stored_name'] !== ($_GET['file'] ?? '') || !preg_match('/^[a-f0-9]{48}$/D', $file['stored_name'])) continue;
        $path = UPLOADS_DIR . '/chat/' . $file['stored_name'];
        if (!is_file($path)) break;
        $inline = !empty($file['image']) && ($_GET['view'] ?? '') === '1';
        header('Content-Type: ' . ($inline ? $file['mime'] : 'application/octet-stream'));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($file['name']));
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
http_response_code(404);
echo 'File not found.';
