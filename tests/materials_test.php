<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/materials.php';
function expect_material(bool $value, string $message): void {
    if (!$value) throw new RuntimeException($message);
}
$student = ['id' => 7, 'role' => 'student', 'profile' => ['materials' => ['10:post-1' => ['saved' => true, 'archived' => true]]]];
$classroom = ['id' => 10, 'teacher_id' => 3, 'student_ids' => [7], 'announcements' => [['id' => 'post-1', 'created_at' => '2026-10-22T12:00:00+08:00']]];
expect_material(material_is_accessible($student, [$classroom], 10, 'post-1'), 'Members can manage existing posts.');
expect_material(!material_is_accessible(array_replace($student, ['id' => 8]), [$classroom], 10, 'post-1'), 'Nonmembers cannot manage posts.');
expect_material(!material_is_accessible($student, [$classroom], 11, 'post-1'), 'Classroom identifiers cannot cross boundaries.');
expect_material(!material_is_accessible($student, [$classroom], 10, 'missing'), 'Missing posts cannot be saved.');
expect_material(material_state($student, 10, 'post-1') === ['saved' => true, 'archived' => true], 'Saving and archiving coexist.');
expect_material(material_state($student, 11, 'post-1') === [], 'Post state is scoped to its classroom.');
expect_material(material_state(['id' => 8], 10, 'post-1') === [], 'Post state is private to each account.');
ob_start();
render_material_actions($student, $classroom, $classroom['announcements'][0], 'archive');
$html = ob_get_clean();
expect_material(str_contains($html, 'value="unsave"') && str_contains($html, 'value="restore"'), 'Saved and archived posts offer reversal actions.');
expect_material(str_contains($html, esc(form_csrf())), 'Actions include the session CSRF token.');
echo "Material access, account isolation, state, and action checks passed.\n";
