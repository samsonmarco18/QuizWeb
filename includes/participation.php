<?php
function participation_rows(array $classroom, array $quiz, array $students, array $attempts, array $runs, ?int $now = null): array {
    $now ??= time(); $rows = [];
    foreach ($students as $student) {
        $history = array_values(array_filter($attempts, fn($a) => (int) $a['student_id'] === (int) $student['id'] && (int) $a['quiz_id'] === (int) $quiz['id']));
        usort($history, fn($a, $b) => strcmp($b['played_at'], $a['played_at']) ?: ($b['id'] <=> $a['id']));
        $active = (bool) array_filter($runs, fn($r) => (int) $r['student_id'] === (int) $student['id'] && (int) $r['quiz_id'] === (int) $quiz['id'] && empty($r['completed_at']) && strtotime($r['last_seen']) >= $now - 120);
        $due = !empty($quiz['due_at']) ? strtotime($quiz['due_at']) : false;
        $status = $active ? 'Taking Quiz' : ($history ? 'Completed' : ($due !== false && $due < $now ? 'Overdue / Missing' : 'Not Taken'));
        $latest = $history[0] ?? null;
        $rows[] = ['student' => $student, 'status' => $status, 'completed' => (bool) $history, 'active' => $active, 'attempts' => $history,
            'count' => count($history), 'score' => $latest['score'] ?? null, 'max_score' => $latest['max_score'] ?? null,
            'submitted_at' => $latest['played_at'] ?? null, 'late' => $latest && $due !== false && strtotime($latest['played_at']) > $due];
    }
    return $rows;
}
function participation_ping(PDO $pdo, array $user, array $run, string $token): void {
    if (($user['role'] ?? '') !== 'student' || (int) $run['student_id'] !== (int) $user['id']) throw new InvalidArgumentException('Quiz session access denied.');
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = 'INSERT INTO quiz_runs (run_token, classroom_id, quiz_id, student_id, started_at, last_seen) VALUES (?, ?, ?, ?, ?, ?)';
    $sql .= $driver === 'mysql' ? ' ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)' : ' ON CONFLICT (run_token) DO UPDATE SET last_seen = EXCLUDED.last_seen';
    $now = now_iso(); $pdo->prepare($sql)->execute([$token, $run['classroom_id'], $run['quiz_id'], $user['id'], $now, $now]);
}
