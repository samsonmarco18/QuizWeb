<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
function query_check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE attempts (id INTEGER PRIMARY KEY, student_id INTEGER, classroom_id INTEGER, quiz_id INTEGER, quiz_title TEXT, game_type TEXT, answers TEXT, score INTEGER, max_score INTEGER, elapsed_seconds INTEGER, played_at TEXT)');
$pdo->exec('CREATE INDEX attempts_student ON attempts(student_id)');
$pdo->exec('CREATE INDEX attempts_classroom ON attempts(classroom_id)');
$insert = $pdo->prepare('INSERT INTO attempts VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
foreach ([[1, 10, 20, 30, '2026-10-01'], [2, 10, 20, 30, '2026-10-03'], [3, 11, 20, 30, '2026-10-04'], [4, 10, 21, 30, '2026-10-05'], [5, 10, 20, 31, '2026-10-06'], [6, 10, 20, 30, '2026-10-03']] as [$id, $student, $classroom, $quiz, $date]) {
    $insert->execute([$id, $student, $classroom, $quiz, 'Sample', 'standard', '[1,2]', 10, 20, 30, $date]);
}
query_check(array_column(student_attempts(10, $pdo), 'id') === [5, 4, 2, 6, 1], 'Student history must exclude other students and preserve date/tie ordering');
query_check(array_column(classroom_attempts(20, $pdo), 'id') === [5, 3, 2, 6, 1], 'Classroom history must exclude other classrooms');
query_check(latest_attempt_for_quiz(10, 20, 30, $pdo)['id'] === 2, 'Latest attempt must match student, classroom and quiz, preserving stable ties');
query_check(latest_attempt_for_quiz(999, 20, 30, $pdo) === null && student_attempts(999, $pdo) === [], 'Empty history behavior');
query_check(student_attempts(10, $pdo)[0]['answers'] === [1, 2], 'History must hydrate answer JSON');
$pdo->exec('CREATE TABLE classrooms (id INTEGER PRIMARY KEY, teacher_id INTEGER, name TEXT, subject TEXT, code TEXT, student_ids TEXT, quizzes TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec("INSERT INTO classrooms VALUES (2, 10, 'Two', 'Science', 'TWO', '[11]', '[]', '', ''), (1, 10, 'One', 'Science', 'ONE', '[11]', '[]', '', ''), (3, 12, 'Other', 'Science', 'OTHER', '[]', '[]', '', '')");
query_check(array_column(teacher_classrooms(10, $pdo), 'id') === [1, 2], 'Teacher classrooms must filter by owner and preserve ordering');
query_check(teacher_classrooms(999, $pdo) === [], 'Unknown teacher has no classrooms');
// A large unrelated history must not change the rows fetched for this student.
$pdo->beginTransaction();
for ($id = 7; $id < 20007; $id++) $insert->execute([$id, 999, 999, 999, 'Other', 'standard', '[]', 0, 10, 1, '2026-10-07']);
$pdo->commit();
query_check(count(student_attempts(10, $pdo)) === 5, 'Do not return unrelated attempts from a large history');
$plan = $pdo->query('EXPLAIN QUERY PLAN SELECT * FROM attempts WHERE student_id = 10 ORDER BY played_at DESC, id ASC')->fetchAll();
query_check(str_contains(json_encode($plan), 'attempts_student'), 'Student history should use the existing student index');
echo "Query checks passed (SQLite): filtered histories, latest attempt, stable ordering, ownership, hydration and indexed lookup with 20,000 unrelated attempts.\n";
