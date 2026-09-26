<?php
require_once __DIR__ . '/includes/chat.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function chat_reply(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
$user = current_user();
if (!$user) chat_reply(['error' => 'Please sign in again to use messaging.'], 401);
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) chat_reply(['error' => 'Method not allowed.'], 405);
if ($method === 'POST' && (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['chat_csrf'] ?? '', $_POST['csrf']) || empty($_SESSION['chat_csrf']))) chat_reply(['error' => 'Refresh the page and try again.'], 403);
session_write_close();
$saved = [];
$pdo = null;
try {
    if ($method === 'POST') {
        $id = (int) ($_POST['classroom_id'] ?? 0);
        $pdo = db();
        $pdo->beginTransaction();
        $lock = $pdo->prepare('SELECT * FROM classrooms WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $id]);
        $row = $lock->fetch();
        $classroom = $row ? hydrate_classroom($row) : null;
        if (!$classroom || !classroom_belongs_to_user($classroom, $user)) {
            $pdo->rollBack();
            chat_reply(['error' => 'Classroom access denied.'], 403);
        }
        if (($_POST['action'] ?? '') === 'vote') {
            $found = false;
            foreach ($classroom['chat_messages'] as &$message) {
                if ((int) $message['id'] === (int) ($_POST['message_id'] ?? 0)) {
                    $option = filter_var($_POST['option'] ?? null, FILTER_VALIDATE_INT);
                    if ($option === false) throw new InvalidArgumentException('Choose a valid option.');
                    $message = chat_vote($message, (int) $user['id'], $option);
                    $found = true;
                    break;
                }
            }
            unset($message);
            if (!$found) throw new InvalidArgumentException('Poll not found.');
        } else {
            $requestId = chat_text($_POST, 'request_id');
            if (!preg_match('/^[a-zA-Z0-9-]{16,80}$/D', $requestId)) throw new InvalidArgumentException('Refresh the page before sending.');
            $duplicate = false;
            foreach ($classroom['chat_messages'] as $existing) {
                if (($existing['request_id'] ?? '') === $requestId && (int) $existing['user_id'] === (int) $user['id']) $duplicate = true;
            }
            if (!$duplicate) {
            $payload = chat_payload($_POST);
            $saved = chat_store_files($_FILES['chat_files'] ?? []);
            if ($payload['body'] === '' && $payload['link'] === '' && !$payload['poll'] && !$saved) throw new InvalidArgumentException('Write a message or add a file, link, or poll.');
            $classroom = create_classroom_chat_message($classroom, $user, $payload['body']);
            $index = array_key_last($classroom['chat_messages']);
            $classroom['chat_messages'][$index] = array_merge($classroom['chat_messages'][$index], $payload, ['attachments' => $saved, 'request_id' => $requestId]);
            }
        }
        $update = $pdo->prepare('UPDATE classrooms SET chat_messages = :messages WHERE id = :id');
        $update->execute(['messages' => db_json_encode($classroom['chat_messages']), 'id' => $id]);
        $pdo->commit();
        $saved = [];
    }
    $threads = [];
    foreach (user_classrooms($user) as $classroom) {
        $threads[] = ['id' => (int) $classroom['id'], 'messages' => array_map(static fn($m) => chat_public_message($m, (int) $classroom['id'], (int) $user['id']), classroom_chat_messages($classroom))];
    }
    chat_reply(['threads' => $threads]);
} catch (Throwable $error) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    foreach ($saved as $file) @unlink(UPLOADS_DIR . '/chat/' . $file['stored_name']);
    chat_reply(['error' => $error instanceof InvalidArgumentException ? $error->getMessage() : 'Messaging is unavailable. Please try again.'], $error instanceof InvalidArgumentException ? 422 : 500);
}
