<?php

function mailer_settings_read(PDO $pdo): array
{
    $query = $pdo->prepare('SELECT data FROM academic_settings WHERE id = ?'); $query->execute(['mailer_settings']);
    $raw = $query->fetchColumn();
    if ($raw === false) return ['enabled' => false, 'provider' => 'smtp'];
    $settings = json_decode($raw, true);
    if (!is_array($settings)) throw new RuntimeException('Mailer settings could not be read.');
    return $settings;
}

function mailer_settings_save(PDO $pdo, int $actorId, #[SensitiveParameter] array $input, bool $remove = false): void
{
    ai_settings_require_admin($pdo, $actorId);
    $saved = mailer_settings_read($pdo);
    if ($remove) {
        $settings = ['enabled' => false, 'provider' => 'smtp', 'encrypted_secrets' => null];
    } else {
        $provider = is_string($input['provider'] ?? null) ? $input['provider'] : '';
        if (!in_array($provider, ['smtp', 'http_api', 'google_oauth'], true)) throw new InvalidArgumentException('Choose a supported mail provider.');
        $fromEmail = strtolower(trim((string) ($input['from_email'] ?? ''))); $fromName = trim((string) ($input['from_name'] ?? ''));
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || strlen($fromName) > 150) throw new InvalidArgumentException('Enter a valid sender email and name.');
        $settings = [
            'enabled' => ($input['enabled'] ?? '') === 'yes', 'provider' => $provider,
            'from_email' => $fromEmail, 'from_name' => $fromName,
            'smtp_host' => trim((string) ($input['smtp_host'] ?? '')), 'smtp_port' => (int) ($input['smtp_port'] ?? 587),
            'smtp_security' => in_array($input['smtp_security'] ?? '', ['tls', 'ssl', 'none'], true) ? $input['smtp_security'] : 'tls',
            'smtp_username' => trim((string) ($input['smtp_username'] ?? '')),
            'http_endpoint' => trim((string) ($input['http_endpoint'] ?? '')),
            'google_client_id' => trim((string) ($input['google_client_id'] ?? '')), 'google_user' => strtolower(trim((string) ($input['google_user'] ?? ''))),
        ];
        if ($settings['smtp_port'] < 1 || $settings['smtp_port'] > 65535) throw new InvalidArgumentException('SMTP port must be between 1 and 65535.');
        if ($provider === 'smtp' && $settings['smtp_host'] === '') throw new InvalidArgumentException('Enter the SMTP server hostname.');
        if ($provider === 'http_api' && (!filter_var($settings['http_endpoint'], FILTER_VALIDATE_URL) || !str_starts_with($settings['http_endpoint'], 'https://'))) throw new InvalidArgumentException('Enter an HTTPS mail API endpoint.');
        if ($provider === 'google_oauth' && (!filter_var($settings['google_user'], FILTER_VALIDATE_EMAIL) || $settings['google_client_id'] === '')) throw new InvalidArgumentException('Enter the Google mailbox and OAuth client ID.');
        $enteredSecrets = [
            'smtp_password' => trim((string) ($input['smtp_password'] ?? '')), 'http_token' => trim((string) ($input['http_token'] ?? '')),
            'google_client_secret' => trim((string) ($input['google_client_secret'] ?? '')), 'google_refresh_token' => trim((string) ($input['google_refresh_token'] ?? '')),
        ];
        $hasNewSecret = implode('', $enteredSecrets) !== '';
        $settings['encrypted_secrets'] = $saved['encrypted_secrets'] ?? null;
        if ($hasNewSecret) $settings['encrypted_secrets'] = ai_settings_encrypt(json_encode($enteredSecrets, JSON_THROW_ON_ERROR));
        if ($settings['enabled'] && !$settings['encrypted_secrets']) throw new InvalidArgumentException('Add the password, API token, or OAuth credentials before enabling mail.');
    }
    $settings['updated_at'] = now_iso(); $settings['updated_by'] = $actorId;
    $pdo->beginTransaction();
    try {
        database_upsert_record($pdo, 'academic_settings', 'id', ['id' => 'mailer_settings', 'data' => json_encode($settings, JSON_THROW_ON_ERROR)]);
        $audit = $pdo->prepare('INSERT INTO audit_logs (actor_id, target_id, action, occurred_at) VALUES (?, ?, ?, ?)'); $audit->execute([$actorId, null, $remove ? 'mailer_credentials_removed' : 'mailer_settings_updated', now_iso()]);
        $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
}
