<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/layout.php';
function assert_security(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$class = ['id' => 12, 'teacher_id' => 5, 'student_ids' => [1, 2]];
$attempt = ['id' => 8, 'classroom_id' => 12, 'student_id' => 1];
$student = ['id' => 1, 'role' => 'student'];
$peer = ['id' => 2, 'role' => 'student'];
$owner = ['id' => 5, 'role' => 'teacher'];
$stranger = ['id' => 6, 'role' => 'teacher'];
$admin = ['id' => 1, 'role' => 'admin'];
assert_security(can_view_attempt($class, $attempt, $student), 'Student can read own result.');
assert_security(!can_view_attempt($class, $attempt, $peer), 'Class membership must not expose another student result.');
assert_security(can_view_attempt($class, $attempt, $owner), 'Class owner can review results.');
assert_security(!can_view_attempt($class, $attempt, $stranger), 'Unrelated teacher cannot review results.');
assert_security(!classroom_belongs_to_user($class, $admin), 'Admin is not a student even if IDs coincide.');
assert_security(!can_view_attempt($class, array_replace($attempt, ['classroom_id' => 13]), $owner), 'Mismatched classroom must be denied.');
assert_security(nav_links($admin)[0][0] === '/QuizWeb/admin.php', 'Admin navigation is administrative.');
$legacy = ['id' => 1, 'name' => 'Legacy', 'email' => 'legacy@example.test', 'password' => 'hash', 'role' => 'student', 'created_at' => now_iso()];
assert_security(hydrate_user($legacy)['account_status'] === 'active', 'Legacy accounts remain active.');
assert_security(hydrate_user(array_replace($legacy, ['account_status' => 'suspended']))['account_status'] === 'suspended', 'Suspension status is retained.');
$_GET = ['page' => 900];
$page = page_records(range(1, 25));
assert_security($page['page'] === 3 && $page['items'] === [25], 'Out-of-range pagination must clamp and retain the final item.');
assert_security(page_records([])['pages'] === 1, 'Empty lists still have a valid pagination state.');
echo "Role, private result access, admin navigation, legacy status, and pagination checks passed.\n";
