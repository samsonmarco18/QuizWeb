<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/profile.php';

function expect_profile(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$valid = ['student_number' => '001-2026', 'birthdate' => '2004-02-29', 'gender' => 'Prefer not to say', 'program' => 'BS Information Technology', 'year_level' => '3rd Year'];
expect_profile(profile_errors($valid, 'student') === [], 'Valid student details must pass.');
expect_profile(count(profile_errors(profile_input([]), 'student')) === 5, 'All five student fields are required.');
expect_profile(profile_errors(array_replace($valid, ['student_number' => '', 'program' => '', 'year_level' => '']), 'teacher') === [], 'Teacher academic fields are optional.');
foreach (['2025-02-29', '2020-02-31', '1899-01-01', date('Y-m-d', strtotime('+1 day')), 'not-a-date'] as $date) {
    expect_profile(count(profile_errors(array_replace($valid, ['birthdate' => $date]), 'student')) > 0, 'Invalid birthday must fail: ' . $date);
}
expect_profile(count(profile_errors(array_replace($valid, ['year_level' => 'invalid']), 'student')) > 0, 'Unknown year level must fail.');
expect_profile(profile_input(['program' => ['invalid']])['program'] === '', 'Array values must not reach string validation.');
$legacy = ['id' => 1, 'name' => 'Legacy', 'email' => 'legacy@example.test', 'password' => 'hash', 'role' => 'student', 'created_at' => now_iso()];
expect_profile(hydrate_user($legacy)['profile'] === [], 'Existing accounts without details must remain compatible.');
expect_profile(hydrate_user($legacy + ['profile' => json_encode($valid)])['profile'] === $valid, 'Profile details must hydrate without losing leading zeroes.');
echo "Profile validation and compatibility checks passed.\n";

if (in_array('--database', $argv, true)) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $id = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM users')->fetchColumn();
        $fixture = array_replace($legacy, ['id' => $id, 'email' => 'profile-test-' . bin2hex(random_bytes(6)) . '@example.test', 'profile' => $valid]);
        insert_user_record($pdo, $fixture);
        $statement = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);
        expect_profile(hydrate_user($statement->fetch())['profile'] === $valid, 'Saved details must survive a database reload.');
        $valid['year_level'] = '4th Year';
        $statement = $pdo->prepare('UPDATE users SET profile = :profile WHERE id = :id');
        $statement->execute(['profile' => json_encode($valid), 'id' => $id]);
        $statement = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);
        expect_profile(hydrate_user($statement->fetch())['profile'] === $valid, 'Profile edits must persist.');
        echo "Database insert, reload, and edit checks passed (rolled back).\n";
    } finally {
        $pdo->rollBack();
    }
}
