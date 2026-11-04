<?php

require_once __DIR__ . '/ai_settings.php';

function google_oauth_config(): array
{
    $clientId = trim((string) getenv('GOOGLE_OAUTH_CLIENT_ID')); $clientSecret = trim((string) getenv('GOOGLE_OAUTH_CLIENT_SECRET')); $redirectUri = trim((string) getenv('GOOGLE_OAUTH_REDIRECT_URI')); $source = 'environment'; $enabledSetting = true;
    try {
        $saved = google_oauth_settings_read(db());
        if ($saved) {
            $clientId = (string) ($saved['client_id'] ?? ''); $redirectUri = (string) ($saved['redirect_uri'] ?? ''); $enabledSetting = !empty($saved['enabled']); $source = 'administration';
            $clientSecret = is_array($saved['encrypted_client_secret'] ?? null) ? ai_settings_decrypt($saved['encrypted_client_secret']) : '';
        }
    } catch (Throwable $error) { error_log('Google OAuth settings unavailable: ' . $error->getMessage()); }
    $redirectHost = strtolower((string) parse_url($redirectUri, PHP_URL_HOST));
    $redirectScheme = strtolower((string) parse_url($redirectUri, PHP_URL_SCHEME));
    $safeRedirect = $redirectScheme === 'https' || ($redirectScheme === 'http' && in_array($redirectHost, ['localhost', '127.0.0.1'], true));
    return ['client_id' => $clientId, 'client_secret' => $clientSecret, 'redirect_uri' => $redirectUri, 'source' => $source, 'enabled' => $enabledSetting && $clientId !== '' && $clientSecret !== '' && filter_var($redirectUri, FILTER_VALIDATE_URL) && $safeRedirect];
}

function google_oauth_settings_read(PDO $pdo): ?array
{
    $query = $pdo->prepare('SELECT data FROM academic_settings WHERE id = ?'); $query->execute(['google_oauth_settings']); $raw = $query->fetchColumn();
    if ($raw === false) return null;
    $settings = json_decode($raw, true);
    if (!is_array($settings)) throw new RuntimeException('Google sign-in settings could not be read.');
    return $settings;
}

function google_oauth_settings_save(PDO $pdo, int $actorId, #[SensitiveParameter] array $input, bool $remove = false): void
{
    ai_settings_require_admin($pdo, $actorId); $saved = google_oauth_settings_read($pdo);
    if ($remove) {
        $settings = ['enabled' => false, 'client_id' => '', 'redirect_uri' => '', 'encrypted_client_secret' => null];
    } else {
        $clientId = trim((string) ($input['client_id'] ?? '')); $redirectUri = trim((string) ($input['redirect_uri'] ?? '')); $secret = trim((string) ($input['client_secret'] ?? ''));
        if ($clientId === '' || strlen($clientId) > 500 || preg_match('/[\x00-\x20\x7f]/', $clientId)) throw new InvalidArgumentException('Enter the complete Google OAuth client ID.');
        $host = strtolower((string) parse_url($redirectUri, PHP_URL_HOST)); $scheme = strtolower((string) parse_url($redirectUri, PHP_URL_SCHEME));
        if (!filter_var($redirectUri, FILTER_VALIDATE_URL) || !($scheme === 'https' || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true)))) throw new InvalidArgumentException('Use an HTTPS redirect URI, or HTTP only for localhost development.');
        $encrypted = $saved['encrypted_client_secret'] ?? null;
        if ($secret !== '') $encrypted = ai_settings_encrypt($secret);
        if (!$encrypted) throw new InvalidArgumentException('Enter the Google OAuth client secret.');
        $settings = ['enabled' => ($input['enabled'] ?? '') === 'yes', 'client_id' => $clientId, 'redirect_uri' => $redirectUri, 'encrypted_client_secret' => $encrypted];
    }
    $settings['updated_at'] = now_iso(); $settings['updated_by'] = $actorId;
    $pdo->beginTransaction();
    try {
        database_upsert_record($pdo, 'academic_settings', 'id', ['id' => 'google_oauth_settings', 'data' => json_encode($settings, JSON_THROW_ON_ERROR)]);
        $audit = $pdo->prepare('INSERT INTO audit_logs (actor_id, target_id, action, occurred_at) VALUES (?, ?, ?, ?)'); $audit->execute([$actorId, null, $remove ? 'google_oauth_removed' : 'google_oauth_updated', now_iso()]);
        $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}

function google_oauth_http(string $url, array $post = [], string $bearer = ''): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('Google sign-in requires the PHP cURL extension.');
    $curl = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($bearer !== '') $headers[] = 'Authorization: Bearer ' . $bearer;
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    if ($post) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
    $body = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $error = curl_error($curl); curl_close($curl);
    if (!is_string($body) || $error !== '' || $status < 200 || $status >= 300) throw new RuntimeException('Google sign-in could not be completed. Please try again.');
    $data = json_decode($body, true);
    if (!is_array($data)) throw new RuntimeException('Google returned an invalid sign-in response.');
    return $data;
}

function google_oauth_authorization_url(): string
{
    $config = google_oauth_config();
    if (!$config['enabled']) throw new RuntimeException('Google sign-in is not configured.');
    $state = bin2hex(random_bytes(32)); $_SESSION['google_oauth_state'] = $state;
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(['client_id' => $config['client_id'], 'redirect_uri' => $config['redirect_uri'], 'response_type' => 'code', 'scope' => 'openid email profile', 'state' => $state, 'prompt' => 'select_account']);
}

function google_oauth_identity(PDO $pdo, string $subject): ?array
{
    $query = $pdo->prepare("SELECT u.* FROM oauth_identities oi JOIN users u ON u.id = oi.user_id WHERE oi.provider = 'google' AND oi.provider_subject = ?");
    $query->execute([$subject]); $row = $query->fetch();
    return $row ? hydrate_user($row) : null;
}

function google_oauth_link(PDO $pdo, int $userId, string $subject): void
{
    $sql = DB_DRIVER === 'mysql'
        ? "INSERT INTO oauth_identities (provider, provider_subject, user_id, created_at) VALUES ('google', ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)"
        : "INSERT INTO oauth_identities (provider, provider_subject, user_id, created_at) VALUES ('google', ?, ?, ?) ON CONFLICT (provider, provider_subject) DO UPDATE SET user_id = EXCLUDED.user_id";
    $pdo->prepare($sql)->execute([$subject, $userId, now_iso()]);
}
