<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/grading_ui.php';
function monitor_check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$book = grading_empty_book();
$book['config'] = grading_validate_config(grading_templates()['balanced']['config'], $book);
$students = [['id' => 1, 'name' => 'Student']];
$classroomId = 1; $url = '/QuizWeb/gradebook.php?classroom_id=1';
$category = $book['config']['categories'][0];
$book['items']['manual-1'] = ['id' => 'manual-1', 'name' => '<Activity>', 'category_id' => $category['id'], 'period_id' => 'prelim', 'source' => 'manual', 'max_score' => 100];
$render = function () use (&$book, &$rows, $students, $classroomId, $url) {
    ob_start(); require __DIR__ . '/../includes/academic_monitor_view.php'; return ob_get_clean();
};
$rows = [1 => grading_calculate($book, 1, [])];
$html = $render();
monitor_check(str_contains($html, 'Scores needed') && str_contains($html, 'No activities yet'), 'Missing scores and empty components must be distinct.');
monitor_check(str_contains($html, '&lt;Activity&gt;') && !str_contains($html, '<Activity>'), 'Activity names must be escaped.');
$book['scores']['manual-1']['1'] = 0;
$rows = [1 => grading_calculate($book, 1, [])];
$html = $render();
monitor_check(str_contains($html, '>Complete</span>') && !str_contains($html, 'Scores needed'), 'Recorded zero must count as complete.');
monitor_check(str_contains($html, 'No activities yet'), 'A recorded component must not hide other empty components.');
ob_start(); render_grade_components($rows[1], $book['config'], true); $components = ob_get_clean();
monitor_check(str_contains($components, 'Activities needed'), 'A component with missing term activities must not appear complete.');
monitor_check(str_contains($components, '>0.00%<'), 'A zero component score must display as a recorded percentage.');
$book['items']['manual-1']['archived'] = true;
$rows = [1 => grading_calculate($book, 1, [])];
monitor_check(!str_contains($render(), '&lt;Activity&gt;'), 'Archived activities must not count toward readiness.');
unset($book['config']['periods']);
$rows = [1 => grading_calculate($book, 1, [])];
monitor_check(str_contains($render(), 'Grading periods are not configured'), 'Legacy grading must show the period setup guidance.');
ob_start(); render_compact_grade_panel($rows[1], $book['config']); $compact = ob_get_clean();
monitor_check(str_contains($compact, 'Components') && !str_contains($compact, 'Grade Breakdown'), 'The side panel must show components.');
monitor_check(!str_contains($compact, 'Prelim') && !str_contains($compact, 'Midterm') && !str_contains($compact, 'Finals'), 'Legacy component summaries must not fabricate term rows.');
echo "Component monitoring: empty activities, missing scores, zero scores, archived activities, escaped names, and legacy grading passed.\n";
