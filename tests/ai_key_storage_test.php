<?php
// Exercise local encryption-file persistence in a disposable directory.
$directory = sys_get_temp_dir() . '/quizweb_ai_storage_' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
define('DATA_DIR', $directory);
define('DB_PASS', 'FakeDatabaseEncryptionPasswordForTest');
putenv('RENDER'); putenv('QUIZWEB_AI_ENCRYPTION_KEY');
require_once __DIR__ . '/../includes/ai_settings.php';
function storage_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$path = $directory . '/ai_master.local.php';
try {
    $encrypted = ai_settings_encrypt('FakeKeyForLocalStorageTest1234');
    storage_check($encrypted['source'] === 'local' && is_file($path), 'Local encryption file automatically created');
    storage_check(ai_settings_decrypt($encrypted) === 'FakeKeyForLocalStorageTest1234', 'Local credential survives separate master-key reads');
    $master = file_get_contents($path);
    ai_settings_encrypt('SecondFakeKeyForLocalStorage1234');
    storage_check(file_get_contents($path) === $master, 'Encryption master must remain stable');
    $process = proc_open([PHP_BINARY, $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
    storage_check($code === 0 && $output === '' && $errors === '', 'Direct PHP execution must expose no key');
    putenv('RENDER=true');
    $cloud = ai_settings_encrypt('FakeKeyForRenderPersistence1234');
    storage_check($cloud['source'] === 'database' && ai_settings_decrypt($cloud) === 'FakeKeyForRenderPersistence1234', 'Render encryption stable from existing server secret');
    putenv('QUIZWEB_AI_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)));
    $dedicated = ai_settings_encrypt('FakeKeyForDedicatedSecret1234');
    storage_check($dedicated['source'] === 'environment' && ai_settings_decrypt($dedicated) === 'FakeKeyForDedicatedSecret1234', 'Dedicated secret overrides new encryption');
    storage_check(ai_settings_decrypt($cloud) === 'FakeKeyForRenderPersistence1234' && ai_settings_decrypt($encrypted) === 'FakeKeyForLocalStorageTest1234', 'Existing keys keep their original encryption source');
    echo "AI key storage checks passed: local persistence, guarded file, stable Render encryption, dedicated secret and migration compatibility.\n";
} finally {
    if (is_file($path)) unlink($path);
    rmdir($directory);
}
