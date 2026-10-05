<?php
// Exercise the actual seeding function with isolated memory stores. These are
// fixtures, never accounts in a local or deployed database.
$source = file_get_contents(__DIR__ . '/../scripts/seed_sample_data.php');
$start = strpos($source, 'function seed_sample_data(');
$end = strpos($source, "\nif (realpath(", $start);
eval(substr($source, $start, $end - $start));
$users = []; $classes = []; $runs = [];
function check_seed(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function find_user_by_email(string $email): ?array {
    global $users;
    foreach ($users as $user) if ($user['email'] === $email) return $user;
    return null;
}
function register_user($name, $email, $password, $role, $profile): array {
    global $users;
    $user = compact('name', 'email', 'role', 'profile');
    $user['password'] = password_hash($password, PASSWORD_DEFAULT);
    $user['id'] = count($users) + 1;
    return $users[] = $user;
}
function classrooms(): array { global $classes; return $classes; }
function save_classrooms(array $records): void { global $classes; $classes = $records; }
function attempts(): array { global $runs; return $runs; }
function find_classroom_by_code(string $code): ?array {
    foreach (classrooms() as $class) if ($class['code'] === $code) return $class;
    return null;
}
function next_id(array $records): int { return $records ? max(array_column($records, 'id')) + 1 : 1; }
function generate_join_code(): string { return 'UNUSED'; }
function now_iso(): string { return '2026-10-05T00:00:00+08:00'; }
function create_attempt($student, $classroom, $quiz, $answers, $seconds): void {
    global $runs;
    $runs[] = ['student_id' => $student, 'classroom_id' => $classroom,
        'quiz_id' => $quiz['id'], 'answers' => $answers, 'elapsed_seconds' => $seconds];
}
seed_sample_data();
check_seed(count($users) === 8 && count($classes) === 3 && count($runs) === 15, 'Initial demo cohort must be complete.');
check_seed(password_verify('Sample123!', find_user_by_email('ava.mendoza@chalk.demo')['password']), 'Named sample student can authenticate.');
// Existing credentials, actual members, work, and deadlines survive redeploy.
$users[0]['password'] = 'existing-rotated-hash';
$classes[0]['student_ids'][] = 999;
$classes[0]['announcements'][] = ['id' => 9, 'body' => 'Existing work'];
$classes[0]['quizzes'][0]['due_at'] = '2026-01-01T00:00:00Z';
$beforeUsers = $users; $beforeClasses = $classes; $beforeRuns = $runs;
seed_sample_data();
check_seed($users === $beforeUsers, 'Rerun must preserve users and passwords.');
check_seed($classes === $beforeClasses, 'Rerun must preserve membership, work, and deadlines.');
check_seed($runs === $beforeRuns, 'Rerun must not duplicate or overwrite attempts.');
$classes[0]['quizzes'][0]['game_type'] = 'fill_blank';
$classes[0]['quizzes'][0]['questions'] = [['prompt' => 'Name a planet', 'accepted_answers' => ['Earth'], 'points' => 10]];
$runs = array_values(array_filter($runs, fn($run) => $run['classroom_id'] !== $classes[0]['id']));
$convertedQuiz = $classes[0]['quizzes'][0];
seed_sample_data();
check_seed($classes[0]['quizzes'][0] === $convertedQuiz && count($runs) === 10, 'Preserve converted activities without fabricating attempts.');
$classes = $beforeClasses;
// A legacy classroom with a different cohort must not suppress named accounts.
$users = array_slice($users, 0, 3);
$classes[0]['student_ids'] = [101, 102, 103, 104, 105];
$classes[1]['student_ids'] = []; $classes[2]['student_ids'] = [];
$runs = [];
seed_sample_data();
$ava = find_user_by_email('ava.mendoza@chalk.demo');
check_seed($ava !== null && count($users) === 8, 'Legacy cohort must not suppress Ava or other demo users.');
check_seed(in_array(101, $classes[0]['student_ids'], true) && in_array($ava['id'], $classes[0]['student_ids'], true), 'Merge demo enrollments without removing existing students.');
$users[0]['role'] = 'student';
try { seed_sample_data(); throw new LogicException('Conflicting teacher email was accepted.'); }
catch (RuntimeException $expected) { check_seed(str_contains($expected->getMessage(), 'conflicting role'), 'Report account conflicts.'); }
echo "Render seeding: named accounts, enrollments, idempotency, credential/history preservation, and role-conflict checks passed (memory fixtures).\n";
