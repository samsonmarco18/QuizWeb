<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/layout.php';
$url = '/QuizWeb/assets/js/ai-quiz.js';
$expected = $url . '?v=' . substr(hash_file('sha256', __DIR__ . '/../assets/js/ai-quiz.js'), 0, 12);
if (versioned_asset_url($url) !== $expected || versioned_asset_url($url) !== $expected) throw new RuntimeException('Assets must receive a stable version matching their contents.');
foreach (['https://example.com/script.js', '/QuizWeb/assets/missing.js', '/QuizWeb/assets/../includes/app.php', '/QuizWeb/ai_quiz_generate.php'] as $other) {
    if (versioned_asset_url($other) !== $other) throw new RuntimeException('Only existing files inside assets may be versioned.');
}
echo "Asset version checks passed: content-based versions, stable cache keys, external/missing paths and traversal isolation.\n";
