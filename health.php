<?php
require_once __DIR__ . '/includes/app.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
try {
    db()->query('SELECT 1')->fetchColumn();
    echo json_encode([
        'status' => 'ok',
        'storage' => 'postgresql',
        'environment' => getenv('RENDER') === 'true' ? 'render' : 'other',
        'revision' => getenv('RENDER_GIT_COMMIT') ?: null,
    ]);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['status' => 'unavailable']);
}
