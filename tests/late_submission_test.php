<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
function late_assert(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$attempt = ['id' => 1, 'game_type' => 'standard', 'score' => 0, 'max_score' => 20, 'played_at' => '2026-11-01T12:00:00+08:00', 'answers' => ['_late_review' => ['status' => 'pending', 'original_score' => 18]]];
foreach (['first', 'latest', 'highest', 'average'] as $policy) {
    late_assert(grading_attempt_result([$attempt], $policy) === null, 'Pending late score must be excluded: ' . $policy);
    $accepted = $attempt; $accepted['score'] = 15; $accepted['answers']['_late_review']['status'] = 'accepted';
    late_assert(grading_attempt_result([$accepted], $policy)['percentage'] === 75.0, 'Accepted deduction must count: ' . $policy);
    $rejected = $attempt; $rejected['answers']['_late_review']['status'] = 'rejected';
    late_assert(grading_attempt_result([$rejected], $policy)['percentage'] == 0.0, 'Rejected submission must count as zero: ' . $policy);
}
echo "Late pending, accepted deduction, and rejected zero grading checks passed.\n";

