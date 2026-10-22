<?php
require_once __DIR__ . '/../includes/participation.php';
$class = ['id' => 1];
$quiz = ['id' => 2, 'due_at' => '2026-10-20T00:00:00+08:00'];
$students = [['id' => 7], ['id' => 8]];
$attempts = [
    ['id' => 1, 'student_id' => 7, 'quiz_id' => 2, 'score' => 60, 'max_score' => 60, 'played_at' => '2026-10-19T10:00:00+08:00'],
    ['id' => 2, 'student_id' => 7, 'quiz_id' => 2, 'score' => 50, 'max_score' => 60, 'played_at' => '2026-10-22T10:00:00+08:00'],
    ['id' => 3, 'student_id' => 7, 'quiz_id' => 3, 'score' => 100, 'max_score' => 100, 'played_at' => '2026-10-22T11:00:00+08:00'],
];
$rows = participation_rows($class, $quiz, $students, $attempts, [], strtotime('2026-10-23'));
if ($rows[0]['score'] !== 60 || $rows[0]['max_score'] !== 60 || $rows[0]['count'] !== 2) throw new RuntimeException('A lower retake must not replace the best earned points.');
if ($rows[0]['submitted_at'] !== $attempts[1]['played_at'] || !$rows[0]['late'] || $rows[0]['attempts'][0]['id'] !== 2) throw new RuntimeException('Latest submission and chronological history must remain available.');
if ($rows[1]['score'] !== null || $rows[1]['count'] !== 0) throw new RuntimeException('Students without submissions must not inherit another score.');
$attempts[0]['score'] = 0;
$attempts[1]['score'] = 0;
$rows = participation_rows($class, $quiz, $students, $attempts, []);
if ($rows[0]['score'] !== 0 || !$rows[0]['completed']) throw new RuntimeException('A recorded zero is still a completed submission.');
echo "Best points, lower retakes, submission dates, history, and missing/zero scores passed.\n";
