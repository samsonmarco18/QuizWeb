<?php
// Run on the existing server BEFORE replacing its filesystem.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/profile.php';
$pdo = db();
$copied = 0;
$missing = 0;
foreach (classrooms() as $classroom) {
    foreach (['announcements' => 'announcements', 'chat_messages' => 'chat'] as $key => $scope) {
        foreach ($classroom[$key] ?? [] as $message) {
            foreach ($message['attachments'] ?? [] as $file) {
                $name = $file['stored_name'];
                $query = $pdo->prepare('SELECT 1 FROM uploaded_files WHERE scope = ? AND stored_name = ?');
                $query->execute([$scope, $name]);
                if ($query->fetchColumn()) continue;
                $bytes = persistent_upload_read($scope, $name);
                if ($bytes === null) { $missing++; continue; }
                persistent_upload_store($scope, $name, $bytes);
                $copied++;
            }
        }
    }
}
foreach (users() as $user) {
    $avatar = $user['profile']['avatar'] ?? null;
    if (!is_array($avatar) || array_key_exists('data', $avatar)) continue;
    $bytes = profile_photo_bytes($user);
    if ($bytes === null) { $missing++; continue; }
    profile_save_changes($pdo, (int) $user['id'], ['avatar' => $avatar + ['data' => base64_encode($bytes)]]);
    $copied++;
}
echo "Copied {$copied} uploads to the database; {$missing} files were already missing.\n";
exit($missing ? 1 : 0);
