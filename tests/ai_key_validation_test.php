<?php
require_once __DIR__ . '/../includes/ai_settings.php';
function key_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
// Synthetic examples, not working provider credentials.
$standard = 'AIza' . str_repeat('a', 35);
$authorization = 'AQ.' . str_repeat('aBc123_-', 80) . '+/=';
foreach ([$standard, $authorization, 'FuturePrefix.ValidOpaqueToken+With/Encoding=', str_repeat('A', 4096)] as $key) {
    key_check(ai_settings_normalize_key($key) === $key, 'Opaque key must be accepted regardless of prefix or legacy length');
}
key_check(strlen($authorization) > 250, 'Authorization regression fixture must exceed the old limit');
key_check(ai_settings_normalize_key(" \r\n" . $authorization . "\r\n ") === $authorization, 'Trim pasted surrounding whitespace');
key_check(ai_settings_normalize_key('') === '', 'Blank field retains existing key');
foreach ([[], str_repeat('A', 4097), "AQtoken\r\nx-injected: value", "AQtoken\0suffix", 'AQ token', "AQtoken\tother", "AQtoken\x7f", "AQtoken\xff"] as $invalid) {
    try { ai_settings_normalize_key($invalid); throw new RuntimeException('Unsafe or oversized key was accepted'); }
    catch (InvalidArgumentException $expected) {}
}
echo "API key validation passed: legacy/new/future prefixes, long authorization keys, punctuation, paste trimming, blank preservation and HTTP-header safety.\n";
