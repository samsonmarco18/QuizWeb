<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/google_oauth.php';
function google_check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
putenv('GOOGLE_OAUTH_CLIENT_ID=test-client.apps.googleusercontent.com');
putenv('GOOGLE_OAUTH_CLIENT_SECRET=test-secret');
putenv('GOOGLE_OAUTH_REDIRECT_URI=http://localhost/QuizWeb/google_auth.php');
$config = google_oauth_config();
google_check($config['enabled'] && $config['redirect_uri'] === 'http://localhost/QuizWeb/google_auth.php', 'Complete configuration enables Google sign-in.');
$url = google_oauth_authorization_url(); $query = []; parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
google_check(str_starts_with($url, 'https://accounts.google.com/o/oauth2/v2/auth?'), 'Authorization uses Google HTTPS endpoint.');
google_check(($query['scope'] ?? '') === 'openid email profile' && ($query['response_type'] ?? '') === 'code', 'OIDC scopes and authorization code flow are required.');
google_check(isset($_SESSION['google_oauth_state']) && hash_equals($_SESSION['google_oauth_state'], $query['state'] ?? ''), 'Authorization request stores an unpredictable state value.');
putenv('GOOGLE_OAUTH_CLIENT_SECRET=');
google_check(!google_oauth_config()['enabled'], 'Incomplete credentials disable the sign-in button.');
echo "Google OAuth configuration, authorization URL, scopes, redirect, and state checks passed.\n";
