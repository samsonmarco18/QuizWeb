<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
function grade_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function grade_reject(callable $action, string $message): void { try { $action(); } catch (InvalidArgumentException $error) { return; } throw new RuntimeException($message); }
function grade_near($actual, float $expected, string $message): void { grade_assert($actual !== null && abs($actual - $expected) < .00001, $message . ': ' . var_export($actual, true)); }
$book = grading_empty_book();
$config = ['categories' => [['id' => 'quiz', 'name' => 'Quizzes', 'weight' => 30], ['id' => 'work', 'name' => 'Assignments', 'weight' => 20], ['id' => 'exam', 'name' => 'Exams', 'weight' => 50]], 'scale' => [['min' => 0, 'label' => 'Needs improvement'], ['min' => 75, 'label' => 'Passed'], ['min' => 90, 'label' => 'Excellent']], 'passing' => 75, 'missing_policy' => 'exclude'];
$book['config'] = grading_validate_config($config, $book);
$class = ['id' => 1, 'teacher_id' => 5, 'student_ids' => [1, 2]]; $teacher = ['id' => 5, 'role' => 'teacher'];
foreach (['quiz' => 20, 'work' => 100, 'exam' => 100] as $category => $max) grading_apply_action($book, $class, $teacher, 'item', ['name' => $category, 'category_id' => $category, 'max_score' => $max, 'date' => '2026-10-03'], []);
foreach (['manual-1' => 18, 'manual-2' => 85, 'manual-3' => 88] as $id => $score) grading_apply_action($book, $class, $teacher, 'scores', ['item_id' => $id, 'scores' => [1 => (string) $score, 2 => '']], []);
$grade = grading_calculate($book, 1, []); grade_near($grade['overall'], 88, 'Weighted overall'); grade_assert($grade['scale_label'] === 'Passed' && $grade['status'] === 'Complete', 'Passing scale/status');
grade_assert(grading_calculate($book, 2, [])['overall'] === null, 'Blank scores are not zero');
grading_apply_action($book, $class, $teacher, 'scores', ['item_id' => 'manual-1', 'scores' => [2 => '0']], []);
grade_near(grading_calculate($book, 2, [])['overall'], 0, 'Explicit zero counts');
grading_apply_action($book, $class, $teacher, 'scores', ['item_id' => 'manual-1', 'scores' => [2 => '18']], []);
grade_near(grading_calculate($book, 2, [])['overall'], 90, 'Exclude missing categories and normalize');
$zero = $book; $zero['config']['missing_policy'] = 'zero'; grade_near(grading_calculate($zero, 2, [])['overall'], 27, 'Explicit missing-as-zero policy');
$aggregate = $book; $aggregate['items']['manual-4'] = ['id' => 'manual-4', 'source' => 'manual', 'name' => 'Other quiz', 'category_id' => 'quiz', 'max_score' => 50, 'archived' => false]; $aggregate['scores']['manual-4'][1] = 42;
grade_near(grading_calculate($aggregate, 1, [])['categories'][0]['percentage'], 60 / 70 * 100, 'Category uses total earned/possible, not average percentages');
$attempts = [
 ['id' => 1, 'student_id' => 1, 'quiz_id' => 4, 'score' => 18, 'max_score' => 20, 'played_at' => '2026-10-01', 'answers' => []],
 ['id' => 2, 'student_id' => 1, 'quiz_id' => 4, 'score' => 40, 'max_score' => 100, 'played_at' => '2026-10-02', 'answers' => []],
 ['id' => 3, 'student_id' => 1, 'quiz_id' => 4, 'score' => 100, 'max_score' => 100, 'played_at' => '2026-10-03', 'preview_mode' => true, 'answers' => []],
 ['id' => 4, 'student_id' => 1, 'quiz_id' => 4, 'score' => 100, 'max_score' => 100, 'played_at' => '2026-10-04', 'answers' => ['_quiz_snapshot' => ['grade_category_id' => '']]],
 ['id' => 5, 'student_id' => 1, 'quiz_id' => 4, 'score' => 100, 'max_score' => 100, 'played_at' => '2026-10-05', 'game_type' => 'focus_training', 'answers' => []]
];
foreach (['highest' => 90, 'latest' => 40, 'first' => 90, 'average' => 65] as $policy => $expected) { $selected = grading_attempt_result($attempts, $policy); grade_near($selected['percentage'], $expected, $policy); grade_assert($selected['count'] === 2, 'Preview and practice excluded'); }
$quizBook = $book; $quizBook['items']['manual-1'] = ['id' => 'manual-1', 'source' => 'quiz', 'quiz_id' => 4, 'name' => 'Quiz', 'category_id' => 'quiz', 'max_score' => 50, 'attempt_policy' => 'highest', 'archived' => false];
grade_near(grading_calculate($quizBook, 1, $attempts)['items']['manual-1']['score'], 45, 'Quiz score scales to assigned grade maximum without changing attempt points');
grade_assert(grading_calculate($quizBook, 2, $attempts)['items']['manual-1']['score'] === null, 'Another student attempt cannot be used');
grading_apply_action($book, $class, $teacher, 'override', ['student_id' => 1, 'item_id' => 'manual-1', 'value' => '20', 'reason' => 'Correction'], []);
grade_near(grading_calculate($book, 1, [])['overall'], 91, 'Item override recalculates overall');
grade_assert($book['overrides'][1]['manual-1']['calculated_at_change'] === 18.0 && $book['overrides'][1]['manual-1']['actor_id'] === 5, 'Override keeps original and actor');
grading_apply_action($book, $class, $teacher, 'override', ['student_id' => 1, 'item_id' => 'overall', 'value' => '95'], []);
grade_near(grading_calculate($book, 1, [])['overall'], 95, 'Overall override');
grading_apply_action($book, $class, $teacher, 'publish', ['confirm' => 'yes'], []);
$student = ['id' => 1, 'role' => 'student']; $published = grading_published_student($book, $class, $student); grade_near($published['overall'], 95, 'Own published grade');
grading_apply_action($book, $class, $teacher, 'restore', ['student_id' => 1, 'item_id' => 'overall'], []);
grading_apply_action($book, $class, $teacher, 'restore', ['student_id' => 1, 'item_id' => 'manual-1'], []);
grade_near(grading_calculate($book, 1, [])['overall'], 88, 'Restore automatic grade'); grade_near(grading_published_student($book, $class, $student)['overall'], 95, 'Release unchanged by draft edits');
grade_assert(grading_published_student($book, $class, ['id' => 2, 'role' => 'student'])['student_id'] === 2, 'Student accessor returns only self');
grade_reject(fn() => grading_published_student($book, $class, ['id' => 3, 'role' => 'student']), 'Nonmember grades exposed');
foreach ([['id' => 1, 'role' => 'student'], ['id' => 6, 'role' => 'teacher'], ['id' => 5, 'role' => 'admin']] as $actor) grade_reject(function () use (&$book, $class, $actor) { grading_apply_action($book, $class, $actor, 'unpublish', ['confirm' => 'yes'], []); }, 'Unauthorized mutation accepted');
grade_reject(function () use (&$book, $class, $teacher) { grading_apply_action($book, $class, $teacher, 'scores', ['item_id' => 'manual-1', 'scores' => [3 => '10']], []); }, 'Nonmember score accepted');
grade_reject(function () use (&$book, $class, $teacher) { grading_apply_action($book, $class, $teacher, 'scores', ['item_id' => 'manual-1', 'scores' => [1 => '21']], []); }, 'Score above maximum accepted');
$bad = $config; $bad['categories'][0]['weight'] = 29.99; grade_reject(fn() => grading_validate_config($bad, $book), 'Invalid total accepted');
$bad = $config; $bad['scale'][1]['min'] = 0; grade_reject(fn() => grading_validate_config($bad, $book), 'Duplicate band accepted');
$bad = $config; array_shift($bad['categories']); $bad['categories'][0]['weight'] = 50; grade_reject(fn() => grading_validate_config($bad, $book), 'In-use category removed');
$renamed = $config; $renamed['categories'][0]['name'] = 'Knowledge checks'; $renamed['categories'] = array_reverse($renamed['categories']); $changed = grading_validate_config($renamed, $book); grade_assert($changed['categories'][2]['id'] === 'quiz', 'Stable category IDs on rename/reorder');
grading_apply_action($book, $class, $teacher, 'archive', ['item_id' => 'manual-1', 'confirm' => 'yes'], []);
grade_assert(isset($book['scores']['manual-1'][1]) && isset($book['published']['students'][1]['items']['manual-1']), 'Archive preserves scores and published history');
grading_apply_action($book, $class, $teacher, 'unpublish', ['confirm' => 'yes'], []); grade_assert(grading_published_student($book, $class, $student) === null, 'Hidden releases unavailable');

// Real SQL persistence, optimistic concurrency, and all-or-nothing auditing.
class GradeTestPDO extends PDO { public function prepare(string $query, array $options = []): PDOStatement|false { return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options); } }
$pdo = new GradeTestPDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE classrooms (id INTEGER PRIMARY KEY, teacher_id INTEGER, name TEXT, subject TEXT, description TEXT, code TEXT, student_ids TEXT, quizzes TEXT, announcements TEXT, chat_messages TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE attempts (id INTEGER PRIMARY KEY, student_id INTEGER, classroom_id INTEGER, quiz_id INTEGER, quiz_title TEXT, game_type TEXT, answers TEXT, score INTEGER, max_score INTEGER, elapsed_seconds INTEGER, played_at TEXT)');
$pdo->exec('CREATE TABLE gradebooks (classroom_id INTEGER PRIMARY KEY, data TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE grade_changes (id INTEGER PRIMARY KEY, classroom_id INTEGER, student_id INTEGER, item_id TEXT, actor_id INTEGER, action TEXT, previous_value TEXT, new_value TEXT, created_at TEXT)');
insert_classroom_record($pdo, $class + ['name' => 'Class', 'subject' => 'Math', 'description' => '', 'code' => 'CLASS', 'quizzes' => [], 'announcements' => [], 'chat_messages' => [], 'created_at' => now_iso(), 'updated_at' => now_iso()]);
grading_mutate($pdo, 1, $teacher, 'config', ['revision' => '0', 'config' => $config]);
grade_assert(grading_load($pdo, 1)['revision'] === 1 && (int) $pdo->query('SELECT COUNT(*) FROM grade_changes')->fetchColumn() === 1, 'Config and audit committed');
grade_reject(fn() => grading_mutate($pdo, 1, $teacher, 'config', ['revision' => '0', 'config' => $config]), 'Stale save accepted');
grade_reject(fn() => grading_mutate($pdo, 1, ['id' => 6, 'role' => 'teacher'], 'config', ['revision' => '1', 'config' => $config]), 'Other teacher SQL mutation accepted');
$pdo->exec("CREATE TRIGGER reject_grade_audit BEFORE INSERT ON grade_changes BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
try { grading_mutate($pdo, 1, $teacher, 'item', ['revision' => '1', 'name' => 'Test', 'category_id' => 'quiz', 'max_score' => '20', 'date' => '2026-10-03']); throw new RuntimeException('Failed audit did not abort'); } catch (PDOException $expected) {}
grade_assert(grading_load($pdo, 1)['items'] === [] && grading_load($pdo, 1)['revision'] === 1, 'Audit failure rolls back grade write');
$pdo->exec('DROP TRIGGER reject_grade_audit');
$quiz = ['id' => 4, 'title' => 'Real quiz', 'created_at' => now_iso(), 'grade_category_id' => 'quiz', 'grade_max_score' => 50, 'grade_attempt_policy' => 'latest'];
$pdo->beginTransaction(); grading_sync_quiz($pdo, 1, 5, $quiz); $pdo->commit();
$pdo->exec("INSERT INTO attempts VALUES (1, 1, 1, 4, 'Original title', 'standard', '[]', 18, 20, 10, '2026-10-03')");
grading_mutate($pdo, 1, $teacher, 'publish', ['revision' => '2', 'confirm' => 'yes']);
$persisted = grading_load($pdo, 1); grade_near($persisted['published']['students'][1]['items']['quiz-4']['score'], 45, 'Stored legitimate attempt in published grade');
$quiz['title'] = 'Edited quiz'; $quiz['grade_max_score'] = 100;
$pdo->beginTransaction(); grading_sync_quiz($pdo, 1, 5, $quiz); $pdo->commit();
grade_near(grading_calculate(grading_load($pdo, 1), 1, array_map('hydrate_attempt', $pdo->query('SELECT * FROM attempts')->fetchAll()))['items']['quiz-4']['score'], 90, 'Edited max preserves percentage');
grade_near(grading_load($pdo, 1)['published']['students'][1]['items']['quiz-4']['score'], 45, 'Published score survives quiz edits');
$quiz['grade_category_id'] = ''; $pdo->beginTransaction(); grading_sync_quiz($pdo, 1, 5, $quiz); $pdo->commit();
grade_assert(grading_load($pdo, 1)['items']['quiz-4']['archived'] && (int) $pdo->query('SELECT COUNT(*) FROM attempts')->fetchColumn() === 1, 'Practice conversion archives grade item and preserves attempt');
echo "Grading calculation, policies, access, snapshots, persistence, concurrency, and rollback tests passed.\n";
