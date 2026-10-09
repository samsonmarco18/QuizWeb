<?php
require_once __DIR__ . '/../includes/uploads.php';
define('UPLOADS_DIR', sys_get_temp_dir() . '/chalk-absent-' . bin2hex(random_bytes(8)));
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE uploaded_files (scope TEXT, stored_name TEXT, data TEXT, PRIMARY KEY(scope, stored_name))');
$name = str_repeat('a', 48);
$bytes = "\x00\xff\x80PDF and ZIP bytes\r\n";
persistent_upload_store('chat', $name, $bytes, $pdo);
if (is_dir(UPLOADS_DIR) || persistent_upload_read('chat', $name, $pdo) !== $bytes) throw new RuntimeException('Attachment depended on deployment filesystem or changed bytes.');
if (persistent_upload_read('announcements', $name, $pdo) !== null) throw new RuntimeException('Storage scopes were mixed.');
foreach (['../secret', str_repeat('b', 48) . '/secret'] as $invalid) {
    try { persistent_upload_read('chat', $invalid, $pdo); throw new RuntimeException('Unsafe path accepted.'); }
    catch (InvalidArgumentException $expected) {}
}
persistent_upload_delete('chat', $name, $pdo);
if (persistent_upload_read('chat', $name, $pdo) !== null) throw new RuntimeException('Upload cleanup failed.');
$large = random_bytes(10 * 1024 * 1024);
persistent_upload_store('announcements', str_repeat('c', 32) . '.zip', $large, $pdo);
if (persistent_upload_read('announcements', str_repeat('c', 32) . '.zip', $pdo) !== $large) throw new RuntimeException('Maximum size upload failed.');
echo "Database uploads survive absent filesystem, preserve binary contents up to 10 MB, isolate scopes, reject unsafe paths, and support cleanup.\n";
