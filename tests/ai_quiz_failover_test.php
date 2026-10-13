<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/ai_quiz.php';
function failover_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$options = ai_quiz_options(['count' => 3, 'game_type' => 'standard', 'difficulty' => 'mixed', 'language' => 'English']);
$request = ai_quiz_request($options, ai_quiz_source(str_repeat('Plants use sunlight for growth. ', 6), null), 'gemini-3.8-flash');
failover_check($request['generation_config']['thinking_level'] === 'low' && $request['generation_config']['max_output_tokens'] === 2850, 'Bounded output and low reasoning for primary model');
failover_check(ai_quiz_thinking_config('gemini-custom') === [], 'Do not guess custom model thinking support');
$questions = [];
foreach (['easy', 'medium', 'hard'] as $i => $level) $questions[] = ['prompt' => 'Plant question ' . $i, 'topic' => 'Plants', 'options' => ['Sunlight', 'Water', 'Roots', 'Leaves'], 'correct_index' => $i, 'level' => $level, 'explanation' => 'Based on the sample lesson.'];
$text = json_encode(['questions' => $questions, 'warning' => '']);
$success = ['status' => 200, 'body' => json_encode(['status' => 'completed', 'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]]]]])];
$calls = [];
$transport = static function (array $body, int $timeout) use (&$calls, $success): array {
    $calls[] = ['model' => $body['model'], 'config' => $body['generation_config'], 'source' => $body['input'], 'store' => $body['store'], 'timeout' => $timeout];
    return count($calls) === 1 ? ['status' => 503, 'body' => '{}'] : $success;
};
$result = ai_quiz_generate_result($request, 'fixture-key', 'gemini-3.5-flash-lite', $transport);
failover_check($result['used_backup'] && $result['model'] === 'gemini-3.5-flash-lite' && $result['text'] === $text, 'Primary unavailability uses configured backup and reports its identity');
failover_check(count($calls) === 2 && $calls[0]['model'] === 'gemini-3.8-flash' && $calls[1]['config']['thinking_level'] === 'minimal', 'Change model and reasoning for backup');
failover_check($calls[0]['source'] === $calls[1]['source'] && $calls[1]['store'] === false, 'Backup preserves source and stateless generation');
$model = ai_settings_generation_probe(['key' => 'fixture-key', 'model' => 'gemini-3.8-flash', 'backup_model' => ''], static fn(array $body, int $timeout) => $success);
failover_check($model === 'gemini-3.8-flash', 'Admin generation test validates real quiz schema');
try {
    ai_settings_generation_probe(['key' => 'fixture-key', 'model' => 'gemini-3.8-flash'], static fn(array $body, int $timeout) => ['status' => 200, 'body' => '{"status":"completed","steps":[]}']);
    throw new LogicException('Generation probe accepted an empty response');
} catch (RuntimeException $expected) { failover_check($expected->getCode() === 502, 'Probe must reject invalid output'); }
echo "AI failover checks passed: primary/backup payloads, reasoning/output limits, source preservation, model reporting and administrator generation validation.\n";
