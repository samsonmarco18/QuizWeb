<?php

function ai_settings_read(PDO $pdo): ?array
{
    $query = $pdo->prepare('SELECT data FROM academic_settings WHERE id = ?');
    $query->execute(['ai_quiz_settings']);
    $raw = $query->fetchColumn();
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    if (!is_array($data)) throw new RuntimeException('AI settings could not be read. Ask an administrator to save them again.', 503);
    return $data;
}

function ai_settings_master_key(?string $source = null, bool $create = false): array
{
    if ($source === null) $source = getenv('QUIZWEB_AI_ENCRYPTION_KEY') ? 'environment' : (getenv('RENDER') === 'true' ? 'database' : 'local');
    if ($source === 'environment') {
        $key = base64_decode((string) getenv('QUIZWEB_AI_ENCRYPTION_KEY'), true);
        if ($key === false || strlen($key) !== 32) throw new RuntimeException('The server AI encryption secret must be a base64-encoded 32-byte key.', 503);
    } elseif ($source === 'database') {
        // Render's database credential is a stable server-side secret across redeploys.
        // A dedicated encryption variable takes priority for newly saved keys.
        if (DB_PASS === '') throw new RuntimeException('Configure a server AI encryption secret before saving an API key.', 503);
        $key = hash_hkdf('sha256', DB_PASS, 32, 'QuizWeb Gemini credentials v1');
    } elseif ($source === 'local') {
        $path = DATA_DIR . '/ai_master.local.php';
        if (!$create && !is_file($path)) throw new RuntimeException('The saved API key cannot be unlocked. Restore the server encryption file or replace the API key.', 503);
        $file = @fopen($path, $create ? 'c+b' : 'rb');
        if (!$file) throw new RuntimeException('The server encryption file is unavailable. Check data directory permissions.', 503);
        try {
            if (!flock($file, $create ? LOCK_EX : LOCK_SH)) throw new RuntimeException('The server encryption file is busy. Try again.', 503);
            $contents = stream_get_contents($file);
            if ($contents === '' && $create) {
                $contents = "<?php\nhttp_response_code(404); exit;\n// AI_MASTER_KEY=" . base64_encode(random_bytes(32)) . "\n";
                if (fwrite($file, $contents) !== strlen($contents) || !fflush($file)) throw new RuntimeException('Could not initialize server encryption.', 503);
                @chmod($path, 0600);
            }
            if (!is_string($contents) || !preg_match('/AI_MASTER_KEY=([A-Za-z0-9+\/=]+)/', $contents, $match)) throw new RuntimeException('Restore the server encryption file or replace the API key.', 503);
            $key = base64_decode($match[1], true);
            if ($key === false || strlen($key) !== 32) throw new RuntimeException('The server encryption file is invalid.', 503);
        } finally { flock($file, LOCK_UN); fclose($file); }
    } else {
        throw new RuntimeException('The saved API key uses an unknown encryption source.', 503);
    }
    return ['key' => $key, 'source' => $source];
}

function ai_settings_encrypt(#[SensitiveParameter] string $secret): array
{
    if (!function_exists('openssl_encrypt')) throw new RuntimeException('Enable PHP OpenSSL to save API keys.', 503);
    $master = ai_settings_master_key(null, true);
    $iv = random_bytes(12); $tag = '';
    $cipher = openssl_encrypt($secret, 'aes-256-gcm', $master['key'], OPENSSL_RAW_DATA, $iv, $tag, 'ai_quiz_settings:v1', 16);
    if ($cipher === false) throw new RuntimeException('Could not encrypt the API key.', 503);
    return ['version' => 1, 'source' => $master['source'], 'value' => base64_encode($iv . $tag . $cipher)];
}

function ai_settings_decrypt(array $encrypted): string
{
    if (!function_exists('openssl_decrypt')) throw new RuntimeException('Enable PHP OpenSSL to read saved API keys.', 503);
    $bytes = base64_decode(is_string($encrypted['value'] ?? null) ? $encrypted['value'] : '', true);
    if (($encrypted['version'] ?? null) !== 1 || $bytes === false || strlen($bytes) <= 28 || !is_string($encrypted['source'] ?? null)) throw new RuntimeException('The saved API key is invalid. Replace it in administration.', 503);
    $master = ai_settings_master_key($encrypted['source']);
    $secret = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm', $master['key'], OPENSSL_RAW_DATA, substr($bytes, 0, 12), substr($bytes, 12, 16), 'ai_quiz_settings:v1');
    if ($secret === false) throw new RuntimeException('The saved API key cannot be unlocked. Replace it in administration.', 503);
    return $secret;
}

function ai_settings_require_admin(PDO $pdo, int $actorId, bool $lock = false): void
{
    $query = $pdo->prepare('SELECT role, account_status FROM users WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
    $query->execute([$actorId]);
    $actor = $query->fetch();
    if (!$actor || $actor['role'] !== 'admin' || $actor['account_status'] !== 'active') throw new InvalidArgumentException('Active administrator access is required.');
}

function ai_settings_csrf($token, string $expected): void
{
    if ($expected === '' || !is_string($token) || !hash_equals($expected, $token)) throw new InvalidArgumentException('Your form expired. Reload and try again.');
}

function ai_settings_normalize_key(#[SensitiveParameter] $input): string
{
    if (!is_string($input)) throw new InvalidArgumentException('Paste the complete API key from Google AI Studio.');
    $key = trim($input);
    // API keys are opaque: Google validates their prefix and format. Reject only
    // oversized values and characters unsafe in the x-goog-api-key HTTP header.
    if (strlen($key) > 4096 || preg_match('/[\x00-\x20\x7f-\xff]/', $key)) {
        throw new InvalidArgumentException('Paste the complete API key without internal spaces or line breaks (maximum 4096 characters).');
    }
    return $key;
}

function ai_settings_save(PDO $pdo, int $actorId, #[SensitiveParameter] array $input, string $action = 'save'): void
{
    if (!in_array($action, ['save', 'remove'], true)) throw new InvalidArgumentException('Choose a valid AI settings action.');
    $pdo->beginTransaction();
    try {
        // All administrators share this lock, including the first configuration save.
        $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id FOR UPDATE")->fetchAll();
        ai_settings_require_admin($pdo, $actorId);
        $saved = ai_settings_read($pdo);
        if ($action === 'remove') {
            $settings = ['enabled' => false, 'model' => $saved['model'] ?? 'gemini-3.8-flash', 'encrypted_key' => null];
        } else {
            $model = $input['model'] ?? '';
            $secret = ai_settings_normalize_key($input['api_key'] ?? '');
            if (!is_string($model) || !preg_match('/\Agemini-[a-zA-Z0-9.-]{1,80}\z/', $model)) throw new InvalidArgumentException('Enter a valid Gemini model name.');
            $enabled = ($input['enabled'] ?? '') === 'yes';
            $encrypted = $saved['encrypted_key'] ?? null;
            if ($secret !== '') $encrypted = ai_settings_encrypt($secret);
            elseif (!$saved) {
                $legacy = ai_quiz_server_config();
                if ($legacy['key'] !== '') $encrypted = ai_settings_encrypt($legacy['key']);
            }
            if ($enabled && !$encrypted) throw new InvalidArgumentException('Add an API key before enabling AI generation.');
            // Allow replacing an unreadable key; only preservation needs decryption.
            if ($enabled && $encrypted && $secret === '') ai_settings_decrypt($encrypted);
            $settings = ['enabled' => $enabled, 'model' => $model, 'encrypted_key' => $encrypted];
        }
        $settings['updated_at'] = now_iso(); $settings['updated_by'] = $actorId;
        database_upsert_record($pdo, 'academic_settings', 'id', ['id' => 'ai_quiz_settings', 'data' => json_encode($settings, JSON_THROW_ON_ERROR)]);
        $audit = $pdo->prepare('INSERT INTO audit_logs (actor_id, target_id, action, occurred_at) VALUES (?, ?, ?, ?)');
        $audit->execute([$actorId, null, $action === 'remove' ? 'ai_api_key_removed' : 'ai_settings_updated', now_iso()]);
        $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

function ai_settings_probe_result(int $status, string $body): void
{
    if ($status === 429) throw new RuntimeException('Gemini quota is currently exhausted. Try the connection test later.', 429);
    if (in_array($status, [400, 401, 403], true)) throw new RuntimeException('Gemini rejected the API key or its permissions. Check the key in Google AI Studio.', 503);
    if ($status === 404) throw new RuntimeException('The configured model is unavailable. Check the model name.', 503);
    $data = json_decode($body, true);
    if ($status < 200 || $status >= 300 || !is_array($data) || !is_string($data['name'] ?? null)) throw new RuntimeException('Gemini did not return valid model information. Try again later.', 502);
}

function ai_settings_test(PDO $pdo, int $actorId): void
{
    ai_settings_require_admin($pdo, $actorId);
    $config = ai_quiz_config($pdo, true);
    if ($config['key'] === '') throw new InvalidArgumentException('Save an API key before testing the connection.');
    if (!preg_match('/\Agemini-[a-zA-Z0-9.-]{1,80}\z/', $config['model'])) throw new InvalidArgumentException('Save a valid model name before testing.');
    $response = ai_quiz_http('models/' . rawurlencode($config['model']), $config['key']);
    ai_settings_probe_result($response['status'], $response['body']);
    $audit = $pdo->prepare('INSERT INTO audit_logs (actor_id, target_id, action, occurred_at) VALUES (?, ?, ?, ?)');
    $audit->execute([$actorId, null, 'ai_connection_tested', now_iso()]);
}
