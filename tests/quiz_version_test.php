<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
class QuizVersionPDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
function assert_version(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$pdo = new QuizVersionPDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TABLE classrooms (id INTEGER PRIMARY KEY, teacher_id INTEGER, name TEXT, subject TEXT, description TEXT, code TEXT, student_ids TEXT, quizzes TEXT, announcements TEXT, chat_messages TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE attempts (id INTEGER PRIMARY KEY, classroom_id INTEGER, quiz_id INTEGER, answers TEXT)');
$pdo->exec('CREATE TABLE gradebooks (classroom_id INTEGER PRIMARY KEY, data TEXT, updated_at TEXT)');
$old = ['id' => 1, 'title' => 'Original quiz', 'game_type' => 'standard', 'questions' => [
    ['id' => 1, 'prompt' => 'First', 'options' => ['A', 'B', 'C', 'D'], 'correct_index' => 0, 'points' => 10, 'level' => 'easy'],
    ['id' => 2, 'prompt' => 'Second', 'options' => ['A', 'B', 'C', 'D'], 'correct_index' => 1, 'points' => 10, 'level' => 'medium'],
]];
$class = ['id' => 1, 'teacher_id' => 5, 'name' => 'Class', 'subject' => 'Subject', 'description' => '', 'code' => 'ABC123', 'student_ids' => [1], 'quizzes' => [$old], 'announcements' => [], 'chat_messages' => [], 'created_at' => now_iso(), 'updated_at' => now_iso()];
insert_classroom_record($pdo, $class);
$pdo->exec("INSERT INTO attempts VALUES (1, 1, 1, '[0,1]')");
$new = $old; $new['title'] = 'Reordered'; $new['questions'] = array_reverse($old['questions']);
persist_builder_quiz($pdo, 1, 5, $new, false);
$saved = json_decode($pdo->query('SELECT answers FROM attempts WHERE id = 1')->fetchColumn(), true);
assert_version($saved['_quiz_snapshot']['title'] === 'Original quiz', 'Existing attempts must retain their original quiz version.');
$rows = attempt_question_review_rows($new, ['answers' => $saved]);
assert_version($rows[0]['prompt'] === 'First' && $rows[0]['is_correct'] && $rows[1]['is_correct'], 'Reordering cannot change historical feedback or earned points.');
persist_builder_quiz($pdo, 1, 5, $old, false);
$after = json_decode($pdo->query('SELECT answers FROM attempts WHERE id = 1')->fetchColumn(), true);
assert_version($after['_quiz_snapshot'] === $saved['_quiz_snapshot'], 'Later edits must not overwrite historical versions.');
persist_builder_quiz($pdo, 1, 5, $new, true);
$quizzes = json_decode($pdo->query('SELECT quizzes FROM classrooms WHERE id = 1')->fetchColumn(), true);
assert_version(count($quizzes) === 2 && $quizzes[1]['id'] === 2, 'Creation allocates a fresh quiz ID while the classroom is locked.');
try { persist_builder_quiz($pdo, 1, 6, $new, false); throw new RuntimeException('Unrelated teacher saved a quiz.'); }
catch (RuntimeException $expected) { assert_version($expected->getMessage() === 'Classroom access denied.', 'Only the classroom owner can persist a quiz.'); }
$pdo->exec("INSERT INTO attempts VALUES (2, 1, 1, '[0,1]'); CREATE TRIGGER reject_class BEFORE INSERT ON classrooms BEGIN SELECT RAISE(ABORT, 'Classroom storage unavailable'); END");
try { persist_builder_quiz($pdo, 1, 5, $new, false); throw new RuntimeException('Storage failure should abort the save.'); }
catch (PDOException $expected) {}
$failed = json_decode($pdo->query('SELECT answers FROM attempts WHERE id = 2')->fetchColumn(), true);
assert_version(!isset($failed['_quiz_snapshot']), 'Failed quiz saves roll back historical changes too.');
assert_version(!$pdo->inTransaction(), 'Failed saves close their transaction.');
echo "Historical quiz versions, reordered feedback, ownership, unique quiz IDs, and atomic save rollback checks passed (SQLite fixture).\n";
