<?php

require_once __DIR__ . '/app.php';

function chat_text(array $input, string $key): string
{
    return is_string($input[$key] ?? null) ? trim($input[$key]) : '';
}

function chat_payload(array $input): array
{
    $body = chat_text($input, 'chat_body');
    if (preg_match_all('/./us', $body) > 1500) {
        throw new InvalidArgumentException('Keep messages to 1,500 characters.');
    }
    $link = chat_text($input, 'link');
    if ($link !== '' && (strlen($link) > 2000 || !filter_var($link, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($link, PHP_URL_SCHEME) ?: ''), ['http', 'https'], true))) {
        throw new InvalidArgumentException('Enter a valid http or https link.');
    }
    $poll = null;
    $question = chat_text($input, 'poll_question');
    $options = array_values(array_filter(array_map('trim', explode("\n", chat_text($input, 'poll_options'))), static fn($value) => $value !== ''));
    if ($question !== '' || $options) {
        if ($question === '' || strlen($question) > 240 || count($options) < 2 || count($options) > 6 || count(array_unique($options)) !== count($options)) {
            throw new InvalidArgumentException('A poll needs a question and 2–6 different options.');
        }
        foreach ($options as $option) {
            if (strlen($option) > 120) throw new InvalidArgumentException('Keep each poll option under 120 characters.');
        }
        $poll = ['question' => $question, 'options' => $options, 'votes' => []];
    }
    return ['body' => $body, 'link' => $link, 'poll' => $poll];
}

function chat_vote(array $message, int $userId, int $option): array
{
    if (!isset($message['poll']['options'][$option])) throw new InvalidArgumentException('Choose a valid poll option.');
    $message['poll']['votes'][(string) $userId] = $option;
    return $message;
}

function chat_public_message(array $message, int $classroomId, int $userId): array
{
    unset($message['request_id']);
    foreach ($message['attachments'] ?? [] as $index => $attachment) {
        $message['attachments'][$index] = [
            'name' => $attachment['name'], 'size' => $attachment['size'], 'image' => $attachment['image'],
            'url' => '/QuizWeb/chat_file.php?classroom_id=' . $classroomId . '&message_id=' . $message['id'] . '&file=' . rawurlencode($attachment['stored_name']),
        ];
    }
    if (!empty($message['poll'])) {
        $votes = $message['poll']['votes'] ?? [];
        $message['poll']['counts'] = array_map(static fn($i) => count(array_filter($votes, static fn($v) => (int) $v === $i)), array_keys($message['poll']['options']));
        $message['poll']['selected'] = $votes[(string) $userId] ?? null;
        $message['poll']['total'] = count($votes);
        unset($message['poll']['votes']);
    }
    return $message;
}

function chat_store_files(array $files): array
{
    if (empty($files['name'])) return [];
    if (!is_array($files['name']) || count($files['name']) > 4) throw new InvalidArgumentException('Attach up to four files at a time.');
    $directory = UPLOADS_DIR . '/chat';
    if (!is_dir($directory) && !mkdir($directory, 0777, true)) throw new RuntimeException('Upload storage is unavailable.');
    if (file_put_contents($directory . '/.htaccess', "Require all denied\n") === false) throw new RuntimeException('Upload storage is unavailable.');
    $saved = [];
    try {
        foreach ($files['name'] as $i => $name) {
            $error = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            if ($error !== UPLOAD_ERR_OK) throw new InvalidArgumentException(upload_error_message($error));
            $size = (int) $files['size'][$i];
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($size < 1 || $size > 10 * 1024 * 1024) throw new InvalidArgumentException('Each file must be between 1 byte and 10 MB.');
            if (!in_array($extension, ['pdf','txt','csv','doc','docx','ppt','pptx','xls','xlsx','zip','png','jpg','jpeg','gif','webp'], true)) throw new InvalidArgumentException('Unsupported file type. Choose a document, ZIP, or image.');
            $tmp = $files['tmp_name'][$i];
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
            $isImage = in_array($extension, ['png','jpg','jpeg','gif','webp'], true);
            if ($isImage && (!in_array($mime, ['image/png','image/jpeg','image/gif','image/webp'], true) || !@getimagesize($tmp))) throw new InvalidArgumentException('This image is not valid.');
            $stored = bin2hex(random_bytes(24));
            if (!move_uploaded_file($tmp, $directory . '/' . $stored)) throw new RuntimeException('The file could not be saved.');
            $saved[] = ['name' => sanitize_filename($name), 'stored_name' => $stored, 'size' => $size, 'mime' => $mime, 'image' => $isImage];
        }
    } catch (Throwable $error) {
        foreach ($saved as $file) @unlink($directory . '/' . $file['stored_name']);
        throw $error;
    }
    return $saved;
}
