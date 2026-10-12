<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/ai_quiz.php';
function ai_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function ai_reject(callable $fn, string $message, int $code = 0): void {
    try { $fn(); } catch (InvalidArgumentException | RuntimeException $error) { ai_check(!$code || $error->getCode() === $code, $message . ' status'); return; }
    throw new RuntimeException($message);
}
$teacher = ['id' => 1, 'role' => 'teacher']; $classroom = ['teacher_id' => 1];
ai_quiz_authorize($teacher, $classroom, 'token', 'token');
ai_reject(fn() => ai_quiz_authorize(null, $classroom, 'token', 'token'), 'Guest rejected', 403);
ai_reject(fn() => ai_quiz_authorize(['id' => 1, 'role' => 'student'], $classroom, 'token', 'token'), 'Student rejected', 403);
ai_reject(fn() => ai_quiz_authorize($teacher, ['teacher_id' => 2], 'token', 'token'), 'Foreign classroom rejected', 403);
ai_reject(fn() => ai_quiz_authorize($teacher, $classroom, 'wrong', 'token'), 'CSRF rejected', 403);
ai_reject(fn() => ai_quiz_authorize($teacher, $classroom, '', ''), 'Empty CSRF rejected', 403);
$input = ['count' => '3', 'game_type' => 'standard', 'difficulty' => 'mixed', 'language' => 'English'];
$options = ai_quiz_options($input);
foreach ([['count' => 21], ['count' => []], ['game_type' => 'crossword'], ['game_type' => []], ['difficulty' => 'master'], ['language' => []], ['topic' => []], ['game_type' => 'master_ladder']] as $change) ai_reject(fn() => ai_quiz_options(array_replace($input, $change)), 'Invalid generation settings rejected');
ai_reject(fn() => ai_quiz_source('short', null), 'Short source rejected');
ai_reject(fn() => ai_quiz_source(str_repeat('x', 60001), null), 'Oversized source rejected');
ai_reject(fn() => ai_quiz_source([], null), 'Malformed source rejected');
ai_reject(fn() => ai_quiz_source('', ['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__, 'name' => 'lesson.txt']), 'Nonupload path rejected');
ai_reject(fn() => ai_quiz_file_part('pdf', 'not a PDF'), 'Fake PDF rejected');
ai_reject(fn() => ai_quiz_file_part('txt', str_repeat('x', 100) . "\0"), 'Binary text rejected');
ai_reject(fn() => ai_quiz_file_part('docx', 'file'), 'Unsupported file rejected');
$source = ai_quiz_source(str_repeat('The lesson explains plant growth. ', 10), null);
ai_check(ai_quiz_file_part('pdf', '%PDF-1.7 source')['type'] === 'document', 'PDF request part');
$request = ai_quiz_request($options, $source, 'gemini-3.8-flash');
ai_check($request['store'] === false && $request['response_format']['schema']['properties']['questions']['maxItems'] === 3, 'Bounded schema and no stored interaction');
ai_check(!isset($request['tools']) && str_contains($request['system_instruction'], 'untrusted'), 'Source instructions isolated; no tools');
$questions = [];
foreach (['easy', 'medium', 'hard'] as $index => $level) $questions[] = ['prompt' => 'Question ' . $index, 'topic' => 'Plants', 'options' => ['First', 'Second', 'Third', 'Fourth'], 'correct_index' => $index, 'level' => $level, 'explanation' => 'Lesson-based feedback.'];
$json = json_encode(['questions' => $questions, 'warning' => '']);
$provider = json_encode(['status' => 'completed', 'steps' => [['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $json]]]]]);
ai_check(ai_quiz_provider_text(200, $provider) === $json, 'Documented REST response parsed');
$validated = ai_quiz_validate($json, $options);
ai_check(count($validated['questions']) === 3 && $validated['questions'][0]['points'] === 10 && $validated['questions'][2]['level'] === 'hard', 'Generated questions pass existing builder validation');
foreach ([429, 401, 403, 500] as $status) ai_reject(fn() => ai_quiz_provider_text($status, '{"error":"secret-key"}'), 'Provider error sanitized', $status === 429 ? 429 : (in_array($status, [401, 403]) ? 503 : 502));
ai_reject(fn() => ai_quiz_provider_text(200, '{"status":"incomplete"}'), 'Partial generation rejected', 502);
ai_reject(fn() => ai_quiz_validate('not-json', $options), 'Invalid JSON rejected', 502);
ai_reject(fn() => ai_quiz_validate('{"questions":[]}', $options), 'Wrong count rejected', 502);
foreach ([['correct_index' => 4], ['correct_index' => '0'], ['options' => ['Same', 'same', 'Other', 'Last']], ['level' => 'unknown'], ['explanation' => ''], ['prompt' => str_repeat('a', 1501)]] as $change) {
    $bad = $questions; $bad[0] = array_replace($bad[0], $change);
    ai_reject(fn() => ai_quiz_validate(json_encode(['questions' => $bad]), $options), 'Invalid generated question rejected', 502);
}
$bad = $questions; $bad[1]['prompt'] = $bad[0]['prompt'];
ai_reject(fn() => ai_quiz_validate(json_encode(['questions' => $bad]), $options), 'Duplicate prompts rejected', 502);
$bad = $questions; $bad[2]['level'] = 'medium';
ai_reject(fn() => ai_quiz_validate(json_encode(['questions' => $bad]), $options), 'Mixed coverage enforced', 502);
echo "AI quiz checks passed: authorization/CSRF, settings, source limits, request schema, provider errors, answers, uniqueness, difficulty, and builder validation.\n";
