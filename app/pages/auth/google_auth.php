<?php

require_once dirname(__DIR__, 3) . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/google_oauth.php';

if (is_logged_in()) redirect('/QuizWeb/dashboard.php');
$config = google_oauth_config();
if (!$config['enabled']) { flash_set('warning', 'Google sign-in is not configured yet.'); redirect('/QuizWeb/login.php'); }

if (!isset($_GET['code'])) {
    if (isset($_GET['error'])) { flash_set('warning', 'Google sign-in was cancelled.'); redirect('/QuizWeb/login.php'); }
    redirect(google_oauth_authorization_url());
}

$state = is_string($_GET['state'] ?? null) ? $_GET['state'] : '';
$expected = is_string($_SESSION['google_oauth_state'] ?? null) ? $_SESSION['google_oauth_state'] : '';
unset($_SESSION['google_oauth_state']);
if ($expected === '' || !hash_equals($expected, $state)) { flash_set('danger', 'Google sign-in expired. Please try again.'); redirect('/QuizWeb/login.php'); }

try {
    $tokens = google_oauth_http('https://oauth2.googleapis.com/token', ['client_id' => $config['client_id'], 'client_secret' => $config['client_secret'], 'code' => (string) $_GET['code'], 'grant_type' => 'authorization_code', 'redirect_uri' => $config['redirect_uri']]);
    $accessToken = is_string($tokens['access_token'] ?? null) ? $tokens['access_token'] : '';
    if ($accessToken === '') throw new RuntimeException('Google did not return an access token.');
    $info = google_oauth_http('https://openidconnect.googleapis.com/v1/userinfo', [], $accessToken);
    $subject = is_string($info['sub'] ?? null) ? $info['sub'] : '';
    $email = is_string($info['email'] ?? null) ? strtolower(trim($info['email'])) : '';
    if ($subject === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($info['email_verified'])) throw new RuntimeException('Google did not provide a verified email address.');
    $linked = google_oauth_identity(db(), $subject);
    if ($linked) {
        if (($linked['account_status'] ?? 'active') !== 'active') throw new RuntimeException('This account is suspended. Contact an administrator.');
        login_user($linked); record_audit('google_login_succeeded', (int) $linked['id'], (int) $linked['id']); redirect('/QuizWeb/dashboard.php');
    }
    $existing = find_user_by_email($email);
    if ($existing) {
        if (($existing['account_status'] ?? 'active') !== 'active') throw new RuntimeException('This account is suspended. Contact an administrator.');
        google_oauth_link(db(), (int) $existing['id'], $subject); login_user($existing); record_audit('google_account_linked', (int) $existing['id'], (int) $existing['id']); redirect('/QuizWeb/dashboard.php');
    }
    $_SESSION['google_onboarding'] = ['subject' => $subject, 'email' => $email, 'given_name' => substr(trim((string) ($info['given_name'] ?? '')), 0, 50), 'family_name' => substr(trim((string) ($info['family_name'] ?? '')), 0, 50), 'expires' => time() + 900];
    redirect('/QuizWeb/google_onboarding.php');
} catch (Throwable $exception) {
    error_log('Google OAuth failed: ' . $exception->getMessage());
    flash_set('danger', $exception instanceof RuntimeException ? $exception->getMessage() : 'Google sign-in failed. Please try again.');
    redirect('/QuizWeb/login.php');
}
