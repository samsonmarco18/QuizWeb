<?php

require_once __DIR__ . '/mailer_settings.php';
require_once __DIR__ . '/google_oauth.php';

function quizweb_public_url(): string
{
    $configured = rtrim(trim((string) getenv('QUIZWEB_PUBLIC_URL')), '/');
    if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)) return $configured;
    try {
        $google = google_oauth_settings_read(db());
        $redirect = (string) ($google['redirect_uri'] ?? '');
        if ($redirect !== '') return rtrim(substr($redirect, 0, (int) strrpos($redirect, '/')), '/');
    } catch (Throwable $error) { /* Mail can remain unavailable until configured. */ }
    throw new RuntimeException('Configure QUIZWEB_PUBLIC_URL or Google sign-in redirect settings before sending password resets.');
}

function mailer_secrets(array $settings): array
{
    if (!is_array($settings['encrypted_secrets'] ?? null)) return [];
    $decoded = json_decode(ai_settings_decrypt($settings['encrypted_secrets']), true);
    return is_array($decoded) ? $decoded : [];
}

function mailer_request(string $url, array $fields, string $bearer = ''): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required for mail delivery.');
    $curl = curl_init($url); $headers = ['Accept: application/json'];
    if ($bearer !== '') $headers[] = 'Authorization: Bearer ' . $bearer;
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    $body = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $error = curl_error($curl); curl_close($curl);
    if (!is_string($body) || $error !== '') throw new RuntimeException('Could not connect to the Google OAuth service.');
    $data = json_decode($body, true);
    if ($status < 200 || $status >= 300) {
        $providerError = is_array($data) && is_string($data['error'] ?? null) ? $data['error'] : '';
        if ($providerError === 'invalid_grant') throw new RuntimeException('Google rejected the saved refresh token. Generate a new token using this same Client ID and Client Secret, then save it again.');
        if (in_array($providerError, ['invalid_client', 'unauthorized_client'], true)) throw new RuntimeException('Google rejected the OAuth Client ID or Client Secret. Verify that both belong to the same Web application used to generate the refresh token.');
        throw new RuntimeException('Google rejected the OAuth token request. Verify the saved Client ID, Client Secret, and refresh token.');
    }
    return is_array($data) ? $data : [];
}

function mailer_send_message(string $to, string $name, string $subject, string $text): void
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid recipient email address.');
    $settings = mailer_settings_read(db());
    if (empty($settings['enabled'])) throw new RuntimeException('Automatic mail delivery is disabled.');
    $secrets = mailer_secrets($settings);
    $safeName = trim((string) preg_replace('/[\r\n]+/', ' ', $name));
    $safeSubject = trim((string) preg_replace('/[\r\n]+/', ' ', $subject));
    if (($settings['provider'] ?? '') === 'google_oauth') {
        $token = mailer_request('https://oauth2.googleapis.com/token', ['client_id' => $settings['google_client_id'] ?? '', 'client_secret' => $secrets['google_client_secret'] ?? '', 'refresh_token' => $secrets['google_refresh_token'] ?? '', 'grant_type' => 'refresh_token']);
        if (!is_string($token['access_token'] ?? null)) throw new RuntimeException('Google did not issue a mail access token.');
        $from = $settings['from_email'] ?? $settings['google_user'] ?? '';
        $fromName = trim((string) preg_replace('/[\r\n]+/', ' ', (string) ($settings['from_name'] ?? 'CHALK')));
        $raw = "From: {$fromName} <{$from}>\r\nTo: {$safeName} <{$to}>\r\nSubject: {$safeSubject}\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$text}";
        $payload = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
        $curl = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['raw' => $payload]), CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token['access_token'], 'Content-Type: application/json'], CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        $body = curl_exec($curl); $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE); curl_close($curl);
        if (!is_string($body) || $status < 200 || $status >= 300) throw new RuntimeException('Gmail could not send the email. Check the saved OAuth credentials and Gmail API permission.');
        return;
    }
    throw new RuntimeException('Email delivery currently requires the Google OAuth mail provider.');
}

function mailer_send_temporary_password(string $to, string $name, #[SensitiveParameter] string $temporaryPassword): void
{
    $safeName = trim((string) preg_replace('/[\r\n]+/', ' ', $name));
    $text = "Hello {$safeName},\n\nYour one-time temporary password is:\n\n{$temporaryPassword}\n\nIt expires in 30 minutes and can only be used once. Sign in with your email and this password; CHALK will immediately require you to create a permanent password.\n\nIf you did not request this, you can ignore this email and continue using your current password.";
    mailer_send_message($to, $name, 'Your temporary CHALK password', $text);
}

function password_reset_request(string $email): void
{
    $user = find_user_by_email(strtolower(trim($email)));
    if (!$user || ($user['account_status'] ?? 'active') !== 'active') return;
    $pdo = db(); $recent = $pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE user_id = ? AND created_at > ?'); $recent->execute([(int) $user['id'], time() - 300]);
    if ((int) $recent->fetchColumn() > 0) return;
    $temporaryPassword = strtoupper(substr(bin2hex(random_bytes(8)), 0, 4)) . '-' . substr(bin2hex(random_bytes(8)), 0, 4) . '-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 4));
    $token = bin2hex(random_bytes(32)); $hash = hash('sha256', $token); $now = time();
    $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, temporary_password_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)')->execute([(int) $user['id'], $hash, password_hash($temporaryPassword, PASSWORD_DEFAULT), $now + 1800, $now]);
    try {
        mailer_send_temporary_password($user['email'], $user['name'], $temporaryPassword);
    } catch (Throwable $error) {
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE token_hash = ?')->execute([$hash]);
        error_log('Password reset email failed: ' . $error->getMessage());
    }
}

function attempt_temporary_password(array $user, #[SensitiveParameter] string $password): bool
{
    $query = db()->prepare('SELECT id, temporary_password_hash FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL AND expires_at >= ? AND temporary_password_hash IS NOT NULL ORDER BY id DESC');
    $query->execute([(int) $user['id'], time()]);
    foreach ($query->fetchAll() as $record) {
        if (password_verify($password, (string) $record['temporary_password_hash'])) {
            db()->prepare('UPDATE password_reset_tokens SET used_at = ? WHERE id = ?')->execute([time(), (int) $record['id']]);
            return true;
        }
    }
    return false;
}

function password_reset_user(string $token): ?array
{
    if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) return null;
    $query = db()->prepare('SELECT u.* FROM password_reset_tokens pr JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at >= ?');
    $query->execute([hash('sha256', $token), time()]); $row = $query->fetch(); return $row ? hydrate_user($row) : null;
}

function password_reset_complete(string $token, string $password): void
{
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) throw new InvalidArgumentException('Use at least 8 characters with a letter and a number.');
    $pdo = db(); $pdo->beginTransaction();
    try {
        $select = $pdo->prepare('SELECT id, user_id FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at >= ? FOR UPDATE'); $select->execute([hash('sha256', $token), time()]); $record = $select->fetch();
        if (!$record) throw new InvalidArgumentException('This reset link is invalid or expired.');
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), (int) $record['user_id']]);
        $pdo->prepare('UPDATE password_reset_tokens SET used_at = ? WHERE user_id = ? AND used_at IS NULL')->execute([time(), (int) $record['user_id']]);
        $audit = $pdo->prepare('INSERT INTO audit_logs (actor_id, target_id, action, occurred_at) VALUES (?, ?, ?, ?)'); $audit->execute([(int) $record['user_id'], (int) $record['user_id'], 'password_reset_completed', now_iso()]);
        $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}
