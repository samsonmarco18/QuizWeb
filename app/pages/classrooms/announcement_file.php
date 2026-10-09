<?php

require_once dirname(__DIR__, 3) . '/includes/app.php';

$user = require_login();
$classroomId = (int) ($_GET['classroom_id'] ?? 0);
$announcementId = (int) ($_GET['announcement_id'] ?? 0);
$storedName = trim((string) ($_GET['file'] ?? ''));
$viewInline = ($_GET['view'] ?? '') === '1';

$classroom = find_classroom($classroomId);

if (!$classroom || !classroom_belongs_to_user($classroom, $user)) {
    http_response_code(403);
    exit('Access denied.');
}

$announcement = find_classroom_announcement($classroom, $announcementId);

if (!$announcement || $storedName === '') {
    http_response_code(404);
    exit('Attachment not found.');
}

$attachment = null;

foreach ($announcement['attachments'] ?? [] as $candidate) {
    if (($candidate['stored_name'] ?? '') === $storedName) {
        $attachment = $candidate;
        break;
    }
}

if (!$attachment) {
    http_response_code(404);
    exit('Attachment not found.');
}

$bytes = persistent_upload_read('announcements', $attachment['stored_name']);

if ($bytes === null) {
    http_response_code(404);
    exit('Attachment file is missing.');
}

$filename = sanitize_filename((string) ($attachment['original_name'] ?? 'attachment'));
$mimeType = (string) ($attachment['mime_type'] ?? 'application/octet-stream');
$disposition = $viewInline && is_image_attachment($attachment) ? 'inline' : 'attachment';

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . strlen($bytes));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));

echo $bytes;
exit;
