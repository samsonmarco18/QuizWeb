<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/ai_quiz.php';

function retry_check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function retry_run(array $responses, float $elapsedPerCall = 0, bool $useBackup = false): array {
    $now = 0.0;
    $calls = []; $delays = []; $error = null; $result = null;
    $send = static function (int $timeout) use (&$calls, &$now, $responses, $elapsedPerCall): array {
        $index = count($calls); $calls[] = $timeout;
        retry_check(isset($responses[$index]), 'Unexpected extra provider call');
        $now += min($elapsedPerCall, $timeout / 1000);
        if ($responses[$index] instanceof Throwable) throw $responses[$index];
        return $responses[$index];
    };
    $pause = static function (int $ms) use (&$now, &$delays): void { $delays[] = $ms; $now += $ms / 1000; };
    $clock = static function () use (&$now): float { return $now; };
    try { $result = ai_quiz_generate_with_transport($send, $pause, $clock, $useBackup); }
    catch (RuntimeException $caught) { $error = $caught; }
    return compact('result', 'error', 'calls', 'delays', 'now');
}

$success = ['status' => 200, 'body' => json_encode(['status' => 'completed', 'steps' => [
    ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'draft fixture']]],
]])];
$unavailable = ['status' => 503, 'body' => '{"error":{"status":"UNAVAILABLE","message":"private material"}}'];
$run = retry_run([$unavailable, $success]);
retry_check($run['result'] === 'draft fixture' && !$run['error'] && count($run['calls']) === 2, 'Recover from temporary unavailability');
retry_check($run['delays'][0] >= 1000 && $run['delays'][0] <= 1250, 'First backoff with bounded jitter');
$run = retry_run([$unavailable, $unavailable, $success]);
retry_check(count($run['calls']) === 3 && $run['result'] === 'draft fixture', 'Second retry can recover');
retry_check($run['delays'][1] >= 2000 && $run['delays'][1] <= 2250, 'Increasing backoff');
$run = retry_run([$unavailable, $unavailable, $unavailable]);
retry_check(count($run['calls']) === 3 && $run['error'] instanceof AiQuizProviderException && $run['error']->httpStatus === 503, 'Cap attempts and preserve confirmed rejection for refund');
retry_check(!str_contains($run['error']->getMessage(), 'private material'), 'Retry failure stays sanitized');
foreach ([400, 401, 402, 403, 404, 429, 500, 502, 504] as $status) {
    $run = retry_run([['status' => $status, 'body' => '{}']]);
    retry_check(count($run['calls']) === 1 && !$run['delays'] && $run['error'] instanceof AiQuizProviderException && $run['error']->httpStatus === $status, 'Do not retry other provider errors: ' . $status);
}
$timeout = new RuntimeException('Network timeout', 502);
foreach ([[$timeout], [$unavailable, $timeout], [['status' => 200, 'body' => '{"status":"incomplete"}']]] as $responses) {
    $run = retry_run($responses);
    retry_check(count($run['calls']) === count($responses) && $run['error'] && !($run['error'] instanceof AiQuizProviderException), 'Ambiguous network/completion failure cannot be retried or refunded as HTTP 503');
}
$run = retry_run([array_merge($unavailable, ['retry_after_ms' => 5000]), $success]);
retry_check($run['delays'] === [5000] && $run['result'] === 'draft fixture', 'Honor Retry-After');
$run = retry_run([array_merge($unavailable, ['retry_after_ms' => 120000])]);
retry_check(count($run['calls']) === 1 && !$run['delays'] && $run['error']->httpStatus === 503, 'Do not exceed budget to honor Retry-After');
$run = retry_run([$unavailable, $unavailable], 60);
retry_check(count($run['calls']) === 2 && $run['calls'][0] === 60000 && $run['calls'][1] < 5000 && $run['now'] <= 65, 'All attempts share the deadline');
retry_check($run['error'] instanceof AiQuizProviderException && $run['error']->httpStatus === 503, 'Deadline after confirmed rejection remains refundable');
$run = retry_run([new AiQuizTransportException(28, true), $success], 30, true);
retry_check($run['result'] === 'draft fixture' && $run['calls'] === [30000, 25000] && !$run['delays'], 'Timeout fails over immediately within separate primary/backup budgets');
$run = retry_run([new AiQuizTransportException(28, true), $unavailable, $unavailable], 0, true);
retry_check($run['error'] instanceof AiQuizProviderException && !$run['error']->allowanceRefundable, 'A sent timeout prevents refund even when backup returns HTTP 503');
$run = retry_run([new AiQuizTransportException(28, false), $unavailable, $unavailable], 0, true);
retry_check($run['error'] instanceof AiQuizProviderException && $run['error']->allowanceRefundable, 'Unsent timeout followed by confirmed rejections is refundable');
$run = retry_run([new AiQuizTransportException(28, true), new AiQuizTransportException(28, true)], 30, true);
retry_check(count($run['calls']) === 2 && $run['now'] === 55.0, 'Do not repeat an ambiguous backup timeout');
$run = retry_run([new AiQuizTransportException(28, true), new AiQuizTransportException(6, false)], 0, true);
retry_check($run['error'] instanceof AiQuizTransportException && $run['error']->requestSent, 'An unsent backup failure cannot erase the primary timeout uncertainty');
foreach ([6, 7, 60, 77] as $code) {
    $run = retry_run([new AiQuizTransportException($code, false)], 0, true);
    retry_check(count($run['calls']) === 1 && $run['error']->curlCode === $code && !$run['error']->requestSent, 'Do not fail over on infrastructure errors');
}
echo "AI retry checks passed: recovery, backoff/jitter, bounded attempts/deadline, Retry-After, timeout failover, refund uncertainty and no retry for configuration/quota/infrastructure errors.\n";
