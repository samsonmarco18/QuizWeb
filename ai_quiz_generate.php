<?php
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/ai_quiz.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$remaining = null;
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('Use the quiz builder to generate questions.', 405);
    $user = current_user();
    $classroomId = filter_var($_POST['classroom_id'] ?? null, FILTER_VALIDATE_INT);
    $classroom = $classroomId ? find_classroom($classroomId) : null;
    ai_quiz_authorize($user, $classroom, $_POST['csrf'] ?? null, $_SESSION['quiz_builder_csrf'] ?? '');
    $options = ai_quiz_options($_POST);
    $source = ai_quiz_source($_POST['source_text'] ?? '', isset($_FILES['lesson']) && is_array($_FILES['lesson']) ? $_FILES['lesson'] : null);
    $config = ai_quiz_config();
    if (!$config['enabled']) throw new RuntimeException('AI quiz generation is disabled by the administrator.', 503);
    if ($config['key'] === '') throw new RuntimeException('Gemini is not configured yet. Ask the administrator to add the server API key.', 503);
    if (!function_exists('curl_init')) throw new RuntimeException('Enable PHP cURL on the server to use Gemini.', 503);
    $remaining = ai_quiz_reserve(db(), (int) $user['id'], time());
    session_write_close();
    set_time_limit(75);
    $generated = ai_quiz_generate(ai_quiz_request($options, $source, $config['model']), $config['key']);
    echo json_encode(ai_quiz_validate($generated, $options) + ['remaining' => $remaining], JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    echo json_encode(['error' => $error->getMessage(), 'remaining' => $remaining]);
} catch (Throwable $error) {
    $status = in_array($error->getCode(), [403, 405, 429, 502, 503], true) ? $error->getCode() : 500;
    http_response_code($status);
    echo json_encode(['error' => $status === 500 ? 'Could not generate questions. Please try again later.' : $error->getMessage(), 'remaining' => $remaining]);
}
