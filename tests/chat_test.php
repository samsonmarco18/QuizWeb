<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/chat.php';
function check_chat(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function reject_chat(array $payload): void {
    try { chat_payload($payload); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Invalid input was accepted.');
}
check_chat(chat_payload(['chat_body' => ' Hello 😀 '])['body'] === 'Hello 😀', 'Unicode messages must survive.');
check_chat(chat_payload(['chat_body' => str_repeat('😀', 1500)])['body'] !== '', 'Count Unicode characters, not bytes.');
reject_chat(['chat_body' => str_repeat('x', 1501)]);
foreach (['javascript:alert(1)', 'data:text/html,test', 'ftp://example.com', 'not-a-url'] as $link) reject_chat(['link' => $link]);
check_chat(chat_payload(['link' => 'https://example.com/a?b=1'])['link'] !== '', 'HTTPS links should pass.');
reject_chat(['poll_question' => 'Review?', 'poll_options' => "One"]);
reject_chat(['poll_question' => 'Review?', 'poll_options' => "One\nOne"]);
reject_chat(['poll_question' => '', 'poll_options' => "One\nTwo"]);
$payload = chat_payload(['poll_question' => 'Review?', 'poll_options' => "Math\nScience"]);
$message = ['id' => 1, 'user_id' => 5, 'body' => '', 'created_at' => now_iso(), 'poll' => $payload['poll'], 'request_id' => 'private-key'];
$message = chat_vote($message, 10, 0);
$message = chat_vote($message, 10, 1);
$message = chat_vote($message, 11, 1);
$public = chat_public_message($message, 7, 10);
check_chat($public['poll']['counts'] === [0, 2], 'Changing a vote must replace the previous vote.');
check_chat($public['poll']['total'] === 2 && $public['poll']['selected'] === 1, 'Counts and current selection must match.');
check_chat(!isset($public['poll']['votes']) && !isset($public['request_id']), 'Do not expose other members voting identities or retry keys.');
try { chat_vote($message, 10, 99); throw new RuntimeException('Invalid vote accepted.'); } catch (InvalidArgumentException $e) {}
$classroom = ['teacher_id' => 5, 'student_ids' => [10, 11]];
check_chat(classroom_belongs_to_user($classroom, ['id' => 10, 'role' => 'student']), 'Member should have access.');
check_chat(!classroom_belongs_to_user($classroom, ['id' => 12, 'role' => 'student']), 'Nonmember must not have access.');
check_chat(!classroom_belongs_to_user($classroom, ['id' => 6, 'role' => 'teacher']), 'Other teachers must not have access.');
echo "Chat validation, permissions, voting, and privacy checks passed.\n";
check_chat(chat_message_excerpt(['body' => '', 'poll' => ['question' => 'Review?']]) === 'Review?', 'Polls need a conversation preview.');
check_chat(chat_message_excerpt(['body' => '', 'attachments' => [['name' => 'Lesson.pdf']]]) === 'Lesson.pdf', 'Files need a conversation preview.');
if (in_array('--database', $argv, true)) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $id = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM classrooms')->fetchColumn();
        $fixture = ['id' => $id, 'teacher_id' => 5, 'name' => 'Chat test', 'subject' => 'Test', 'description' => '', 'code' => 'TEST' . bin2hex(random_bytes(5)), 'student_ids' => [10], 'quizzes' => [], 'announcements' => [], 'chat_messages' => [$message], 'created_at' => now_iso(), 'updated_at' => now_iso()];
        insert_classroom_record($pdo, $fixture);
        $select = $pdo->prepare('SELECT * FROM classrooms WHERE id = :id FOR UPDATE');
        $select->execute(['id' => $id]);
        $loaded = hydrate_classroom($select->fetch());
        check_chat(chat_public_message($loaded['chat_messages'][0], $id, 10)['poll']['counts'] === [0, 2], 'Poll votes must persist.');
        $loaded = create_classroom_chat_message($loaded, ['id' => 10, 'name' => 'Test', 'role' => 'student'], 'Hello 😀');
        $update = $pdo->prepare('UPDATE classrooms SET chat_messages = :messages WHERE id = :id');
        $update->execute(['messages' => db_json_encode($loaded['chat_messages']), 'id' => $id]);
        $select->execute(['id' => $id]);
        $loaded = hydrate_classroom($select->fetch());
        check_chat(count($loaded['chat_messages']) === 2 && $loaded['chat_messages'][1]['body'] === 'Hello 😀', 'A new message must persist alongside the poll.');
        echo "Database message and poll persistence checks passed (rolled back).\n";
    } finally { $pdo->rollBack(); }
}
