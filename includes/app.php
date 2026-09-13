<?php

session_start();

date_default_timezone_set('Asia/Manila');

define('APP_NAME', 'CHALK');
define('DATA_DIR', __DIR__ . '/../data');
define('USERS_FILE', DATA_DIR . '/users.json');
define('CLASSROOMS_FILE', DATA_DIR . '/classrooms.json');
define('ATTEMPTS_FILE', DATA_DIR . '/attempts.json');
define('UPLOADS_DIR', DATA_DIR . '/uploads');
define('ANNOUNCEMENT_UPLOADS_DIR', UPLOADS_DIR . '/announcements');
define('ANNOUNCEMENT_MAX_FILE_SIZE', 10 * 1024 * 1024);
define('DB_HOST', getenv('QUIZWEB_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('QUIZWEB_DB_PORT') ?: '3306');
define('DB_NAME', getenv('QUIZWEB_DB_NAME') ?: 'quizweb');
define('DB_USER', getenv('QUIZWEB_DB_USER') ?: 'root');
define('DB_PASS', getenv('QUIZWEB_DB_PASS') ?: '');

function ensure_storage(): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0777, true);
    }

    if (!is_dir(UPLOADS_DIR)) {
        mkdir(UPLOADS_DIR, 0777, true);
    }

    if (!is_dir(ANNOUNCEMENT_UPLOADS_DIR)) {
        mkdir(ANNOUNCEMENT_UPLOADS_DIR, 0777, true);
    }
}

function read_json(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    $raw = file_get_contents($file);

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

function write_json(string $file, array $data): void
{
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function database_server_dsn(): string
{
    return sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
}

function database_dsn(): string
{
    return sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
}

function db_json_encode(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES);
}

function db_json_decode(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $server = new PDO(database_server_dsn(), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    $pdo = new PDO(database_dsn(), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    ensure_database_schema($pdo);
    migrate_legacy_json_data($pdo);

    return $pdo;
}

function ensure_database_schema(PDO $pdo): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $statements = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL,
            created_at VARCHAR(40) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS classrooms (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            name VARCHAR(190) NOT NULL,
            subject VARCHAR(190) NOT NULL,
            description TEXT NOT NULL,
            code VARCHAR(20) NOT NULL UNIQUE,
            student_ids LONGTEXT NOT NULL,
            quizzes LONGTEXT NOT NULL,
            announcements LONGTEXT NOT NULL,
            chat_messages LONGTEXT NOT NULL,
            created_at VARCHAR(40) NOT NULL,
            updated_at VARCHAR(40) NOT NULL,
            KEY idx_classrooms_teacher_id (teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS attempts (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            classroom_id INT NOT NULL,
            quiz_id INT NOT NULL,
            quiz_title VARCHAR(255) NOT NULL,
            game_type VARCHAR(100) NOT NULL,
            answers LONGTEXT NOT NULL,
            score INT NOT NULL,
            max_score INT NOT NULL,
            elapsed_seconds INT NOT NULL,
            played_at VARCHAR(40) NOT NULL,
            KEY idx_attempts_student_id (student_id),
            KEY idx_attempts_classroom_id (classroom_id),
            KEY idx_attempts_quiz_id (quiz_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    if (!table_has_column($pdo, 'classrooms', 'chat_messages')) {
        $pdo->exec('ALTER TABLE classrooms ADD COLUMN chat_messages LONGTEXT NOT NULL');
        $pdo->exec("UPDATE classrooms SET chat_messages = '[]' WHERE chat_messages IS NULL OR chat_messages = ''");
    }

    $initialized = true;
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->query('DESCRIBE `' . $table . '`');

    foreach ($statement->fetchAll() as $row) {
        if (strcasecmp((string) ($row['Field'] ?? ''), $column) === 0) {
            return true;
        }
    }

    return false;
}

function table_is_empty(PDO $pdo, string $table): bool
{
    $statement = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`');

    return (int) $statement->fetchColumn() === 0;
}

function insert_user_record(PDO $pdo, array $user): void
{
    $statement = $pdo->prepare('
        INSERT INTO users (id, name, email, password, role, created_at)
        VALUES (:id, :name, :email, :password, :role, :created_at)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            email = VALUES(email),
            password = VALUES(password),
            role = VALUES(role),
            created_at = VALUES(created_at)
    ');
    $statement->execute([
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => strtolower($user['email']),
        'password' => $user['password'],
        'role' => $user['role'],
        'created_at' => $user['created_at'],
    ]);
}

function insert_classroom_record(PDO $pdo, array $classroom): void
{
    $statement = $pdo->prepare('
        INSERT INTO classrooms (
            id, teacher_id, name, subject, description, code, student_ids, quizzes, announcements, chat_messages, created_at, updated_at
        ) VALUES (
            :id, :teacher_id, :name, :subject, :description, :code, :student_ids, :quizzes, :announcements, :chat_messages, :created_at, :updated_at
        )
        ON DUPLICATE KEY UPDATE
            teacher_id = VALUES(teacher_id),
            name = VALUES(name),
            subject = VALUES(subject),
            description = VALUES(description),
            code = VALUES(code),
            student_ids = VALUES(student_ids),
            quizzes = VALUES(quizzes),
            announcements = VALUES(announcements),
            chat_messages = VALUES(chat_messages),
            created_at = VALUES(created_at),
            updated_at = VALUES(updated_at)
    ');
    $statement->execute([
        'id' => (int) $classroom['id'],
        'teacher_id' => (int) $classroom['teacher_id'],
        'name' => $classroom['name'],
        'subject' => $classroom['subject'],
        'description' => $classroom['description'] ?? '',
        'code' => $classroom['code'],
        'student_ids' => db_json_encode(array_values($classroom['student_ids'] ?? [])),
        'quizzes' => db_json_encode(array_values($classroom['quizzes'] ?? [])),
        'announcements' => db_json_encode(array_values($classroom['announcements'] ?? [])),
        'chat_messages' => db_json_encode(array_values($classroom['chat_messages'] ?? [])),
        'created_at' => $classroom['created_at'],
        'updated_at' => $classroom['updated_at'],
    ]);
}

function insert_attempt_record(PDO $pdo, array $attempt): void
{
    $statement = $pdo->prepare('
        INSERT INTO attempts (
            id, student_id, classroom_id, quiz_id, quiz_title, game_type, answers, score, max_score, elapsed_seconds, played_at
        ) VALUES (
            :id, :student_id, :classroom_id, :quiz_id, :quiz_title, :game_type, :answers, :score, :max_score, :elapsed_seconds, :played_at
        )
        ON DUPLICATE KEY UPDATE
            student_id = VALUES(student_id),
            classroom_id = VALUES(classroom_id),
            quiz_id = VALUES(quiz_id),
            quiz_title = VALUES(quiz_title),
            game_type = VALUES(game_type),
            answers = VALUES(answers),
            score = VALUES(score),
            max_score = VALUES(max_score),
            elapsed_seconds = VALUES(elapsed_seconds),
            played_at = VALUES(played_at)
    ');
    $statement->execute([
        'id' => (int) $attempt['id'],
        'student_id' => (int) $attempt['student_id'],
        'classroom_id' => (int) $attempt['classroom_id'],
        'quiz_id' => (int) $attempt['quiz_id'],
        'quiz_title' => $attempt['quiz_title'],
        'game_type' => $attempt['game_type'],
        'answers' => db_json_encode($attempt['answers'] ?? []),
        'score' => (int) $attempt['score'],
        'max_score' => (int) $attempt['max_score'],
        'elapsed_seconds' => (int) $attempt['elapsed_seconds'],
        'played_at' => $attempt['played_at'],
    ]);
}

function migrate_legacy_json_data(PDO $pdo): void
{
    static $migrated = false;

    if ($migrated) {
        return;
    }

    if (table_is_empty($pdo, 'users')) {
        foreach (read_json(USERS_FILE) as $user) {
            insert_user_record($pdo, $user);
        }
    }

    if (table_is_empty($pdo, 'classrooms')) {
        foreach (read_json(CLASSROOMS_FILE) as $classroom) {
            insert_classroom_record($pdo, $classroom);
        }
    }

    if (table_is_empty($pdo, 'attempts')) {
        foreach (read_json(ATTEMPTS_FILE) as $attempt) {
            insert_attempt_record($pdo, $attempt);
        }
    }

    $migrated = true;
}

function hydrate_user(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'password' => $row['password'],
        'role' => $row['role'],
        'created_at' => $row['created_at'],
    ];
}

function hydrate_classroom(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'teacher_id' => (int) $row['teacher_id'],
        'name' => $row['name'],
        'subject' => $row['subject'],
        'description' => $row['description'] ?? '',
        'code' => $row['code'],
        'student_ids' => array_map('intval', db_json_decode($row['student_ids'] ?? '[]')),
        'quizzes' => db_json_decode($row['quizzes'] ?? '[]'),
        'announcements' => db_json_decode($row['announcements'] ?? '[]'),
        'chat_messages' => db_json_decode($row['chat_messages'] ?? '[]'),
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ];
}

function hydrate_attempt(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'student_id' => (int) $row['student_id'],
        'classroom_id' => (int) $row['classroom_id'],
        'quiz_id' => (int) $row['quiz_id'],
        'quiz_title' => $row['quiz_title'],
        'game_type' => $row['game_type'],
        'answers' => db_json_decode($row['answers'] ?? '[]'),
        'score' => (int) $row['score'],
        'max_score' => (int) $row['max_score'],
        'elapsed_seconds' => (int) $row['elapsed_seconds'],
        'played_at' => $row['played_at'],
    ];
}

function users(): array
{
    $statement = db()->query('SELECT * FROM users ORDER BY id ASC');

    return array_map('hydrate_user', $statement->fetchAll());
}

function save_users(array $users): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->exec('DELETE FROM users');

        foreach (array_values($users) as $user) {
            insert_user_record($pdo, $user);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function classrooms(): array
{
    $statement = db()->query('SELECT * FROM classrooms ORDER BY id ASC');

    return array_map('hydrate_classroom', $statement->fetchAll());
}

function save_classrooms(array $classrooms): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->exec('DELETE FROM classrooms');

        foreach (array_values($classrooms) as $classroom) {
            insert_classroom_record($pdo, $classroom);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function attempts(): array
{
    $statement = db()->query('SELECT * FROM attempts ORDER BY id ASC');

    return array_map('hydrate_attempt', $statement->fetchAll());
}

function save_attempts(array $attempts): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->exec('DELETE FROM attempts');

        foreach (array_values($attempts) as $attempt) {
            insert_attempt_record($pdo, $attempt);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function next_id(array $records): int
{
    $ids = array_column($records, 'id');

    return $ids ? (max($ids) + 1) : 1;
}

function current_user(): ?array
{
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        return null;
    }

    foreach (users() as $user) {
        if ((int) $user['id'] === (int) $userId) {
            return $user;
        }
    }

    unset($_SESSION['user_id']);

    return null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function current_request_uri(): string
{
    return $_SERVER['REQUEST_URI'] ?? '/QuizWeb/dashboard.php';
}

function safe_local_path(string $path, string $fallback = '/QuizWeb/dashboard.php'): string
{
    $path = trim($path);

    if ($path !== '' && str_starts_with($path, '/QuizWeb/')) {
        return $path;
    }

    return $fallback;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function flash_get(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

function require_login(): array
{
    $user = current_user();

    if (!$user) {
        flash_set('warning', 'Please log in to continue.');
        redirect('/QuizWeb/login.php');
    }

    return $user;
}

function require_role(string $role): array
{
    $user = require_login();

    if ($user['role'] !== $role) {
        flash_set('danger', 'You do not have access to that page.');
        redirect('/QuizWeb/dashboard.php');
    }

    return $user;
}

function esc(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function now_iso(): string
{
    return date('c');
}

function format_date(string $date): string
{
    $time = strtotime($date);

    return $time ? date('M d, Y g:i A', $time) : $date;
}

function format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / (1024 * 1024), 1) . ' MB';
}

function sanitize_filename(string $filename): string
{
    $filename = trim($filename);
    $filename = preg_replace('/[^A-Za-z0-9._ -]/', '-', $filename) ?? 'file';
    $filename = preg_replace('/\s+/', ' ', $filename) ?? 'file';

    return trim($filename, '. ') ?: 'file';
}

function upload_error_message(int $error): string
{
    switch ($error) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'One of the files is too large for upload.';
        case UPLOAD_ERR_PARTIAL:
            return 'One of the files did not finish uploading.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'The server upload temp directory is missing.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'The server could not save one of the uploaded files.';
        case UPLOAD_ERR_EXTENSION:
            return 'A server extension blocked one of the uploaded files.';
        default:
            return 'One of the uploaded files could not be processed.';
    }
}

function announcement_allowed_extensions(): array
{
    return [
        'pdf',
        'doc',
        'docx',
        'ppt',
        'pptx',
        'xls',
        'xlsx',
        'csv',
        'txt',
        'zip',
        'rar',
        '7z',
        'png',
        'jpg',
        'jpeg',
        'gif',
        'webp',
        'mp4',
        'mp3',
        'wav',
    ];
}

function uploaded_files_present(array $files): bool
{
    if (!isset($files['error']) || !is_array($files['error'])) {
        return false;
    }

    foreach ($files['error'] as $error) {
        if ((int) $error !== UPLOAD_ERR_NO_FILE) {
            return true;
        }
    }

    return false;
}

function announcement_attachment_path(array $attachment): string
{
    return ANNOUNCEMENT_UPLOADS_DIR . '/' . ($attachment['stored_name'] ?? '');
}

function is_image_attachment(array $attachment): bool
{
    return strpos((string) ($attachment['mime_type'] ?? ''), 'image/') === 0;
}

function classroom_announcements(array $classroom): array
{
    $announcements = $classroom['announcements'] ?? [];

    usort($announcements, function (array $a, array $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    return $announcements;
}

function classroom_chat_messages(array $classroom): array
{
    $messages = $classroom['chat_messages'] ?? [];

    usort($messages, function (array $a, array $b) {
        return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
    });

    return $messages;
}

function classroom_latest_chat_message(array $classroom): ?array
{
    $messages = classroom_chat_messages($classroom);

    if (!$messages) {
        return null;
    }

    return $messages[array_key_last($messages)] ?? null;
}

function chat_message_excerpt(?array $message, int $length = 64): string
{
    if (!$message) {
        return 'No messages yet';
    }

    $body = trim((string) ($message['body'] ?? ''));
    $body = preg_replace('/\s+/', ' ', $body) ?: '';

    if ($body === '') {
        return 'No messages yet';
    }

    if (function_exists('mb_substr') && function_exists('mb_strlen')) {
        return mb_strlen($body) > $length ? mb_substr($body, 0, $length - 1) . '…' : $body;
    }

    return strlen($body) > $length ? substr($body, 0, $length - 1) . '…' : $body;
}

function find_classroom_announcement(array $classroom, int $announcementId): ?array
{
    foreach ($classroom['announcements'] ?? [] as $announcement) {
        if ((int) ($announcement['id'] ?? 0) === $announcementId) {
            return $announcement;
        }
    }

    return null;
}

function store_announcement_attachments(array $files): array
{
    ensure_storage();

    if (!isset($files['name']) || !is_array($files['name'])) {
        return ['attachments' => [], 'errors' => []];
    }

    $attachments = [];
    $errors = [];
    $savedPaths = [];
    $allowedExtensions = array_fill_keys(announcement_allowed_extensions(), true);
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;

    foreach ($files['name'] as $index => $originalName) {
        $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = upload_error_message($error);
            continue;
        }

        $originalName = trim((string) $originalName);
        $size = (int) ($files['size'][$index] ?? 0);
        $tmpName = (string) ($files['tmp_name'][$index] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($originalName === '' || $tmpName === '') {
            $errors[] = 'One of the uploaded files is missing its filename.';
            continue;
        }

        if ($size <= 0) {
            $errors[] = 'Empty files cannot be attached to announcements.';
            continue;
        }

        if ($size > ANNOUNCEMENT_MAX_FILE_SIZE) {
            $errors[] = sanitize_filename($originalName) . ' is larger than 10 MB.';
            continue;
        }

        if (!isset($allowedExtensions[$extension])) {
            $errors[] = sanitize_filename($originalName) . ' has an unsupported file type.';
            continue;
        }

        $safeName = sanitize_filename($originalName);
        $storedName = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
        $destination = ANNOUNCEMENT_UPLOADS_DIR . '/' . $storedName;

        if (!move_uploaded_file($tmpName, $destination)) {
            $errors[] = 'The server could not store ' . $safeName . '.';
            continue;
        }

        $savedPaths[] = $destination;
        $mimeType = $finfo ? (finfo_file($finfo, $destination) ?: 'application/octet-stream') : 'application/octet-stream';

        $attachments[] = [
            'original_name' => $safeName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'size' => $size,
            'uploaded_at' => now_iso(),
        ];
    }

    if ($finfo) {
        finfo_close($finfo);
    }

    if ($errors) {
        foreach ($savedPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        return [
            'attachments' => [],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    return [
        'attachments' => $attachments,
        'errors' => [],
    ];
}

function find_user_by_email(string $email): ?array
{
    foreach (users() as $user) {
        if (strcasecmp($user['email'], $email) === 0) {
            return $user;
        }
    }

    return null;
}

function find_user_by_id(int $id): ?array
{
    foreach (users() as $user) {
        if ((int) $user['id'] === $id) {
            return $user;
        }
    }

    return null;
}

function register_user(string $name, string $email, string $password, string $role): array
{
    $records = users();
    $user = [
        'id' => next_id($records),
        'name' => $name,
        'email' => strtolower($email),
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'created_at' => now_iso(),
    ];

    $records[] = $user;
    save_users($records);

    return $user;
}

function attempt_login(string $email, string $password): ?array
{
    $user = find_user_by_email($email);

    if (!$user) {
        return null;
    }

    return password_verify($password, $user['password']) ? $user : null;
}

function login_user(array $user): void
{
    $_SESSION['user_id'] = $user['id'];
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}

function game_modes(): array
{
    return [
        'time_attack' => [
            'label' => 'Time Attack',
            'icon' => 'T',
            'description' => 'Race the clock through a fast-paced lightning round.',
        ],
        'rocket_rush' => [
            'label' => 'Rocket Rush',
            'icon' => 'R',
            'description' => 'Blast through questions with arcade energy and moving space visuals.',
        ],
        'memory_flip' => [
            'label' => 'Memory Flip',
            'icon' => 'M',
            'description' => 'Reveal answers on animated cards with memory-game vibes.',
        ],
        'treasure_dive' => [
            'label' => 'Treasure Dive',
            'icon' => 'D',
            'description' => 'Swim through underwater prompts and collect glowing treasure points.',
        ],
        'boss_battle' => [
            'label' => 'Boss Battle',
            'icon' => 'B',
            'description' => 'Answer correctly to wear down the boss and protect your health.',
        ],
        'crossword' => [
            'label' => 'Crossword Puzzle',
            'icon' => 'C',
            'description' => 'Build a clue-based word grid with real intersections and black squares.',
        ],
        'master_ladder' => [
            'label' => 'Mastery Ladder',
            'icon' => 'L',
            'description' => 'Climb from Easy to Master and unlock each level only after proving mastery.',
        ],
    ];
}

function mastery_levels(): array
{
    return [
        'easy' => 'Easy',
        'medium' => 'Medium',
        'hard' => 'Hard',
        'master' => 'Master',
    ];
}

function mastery_threshold_for_quiz(array $quiz): int
{
    return max(50, min(100, (int) ($quiz['mastery_threshold'] ?? 75)));
}

function role_label(string $role): string
{
    return $role === 'teacher' ? 'Teacher' : 'Student';
}

function crossword_normalize_answer(string $answer): string
{
    return strtoupper(preg_replace('/[^A-Za-z]/', '', $answer));
}

function crossword_cell_key(int $row, int $col): string
{
    return $row . ':' . $col;
}

function crossword_can_place_word(array $grid, string $word, int $row, int $col, string $direction, int $size): array
{
    $length = strlen($word);
    $intersections = 0;
    $deltaRow = $direction === 'down' ? 1 : 0;
    $deltaCol = $direction === 'across' ? 1 : 0;
    $beforeRow = $row - $deltaRow;
    $beforeCol = $col - $deltaCol;
    $afterRow = $row + ($deltaRow * $length);
    $afterCol = $col + ($deltaCol * $length);

    if ($row < 0 || $col < 0 || $row + ($deltaRow * ($length - 1)) >= $size || $col + ($deltaCol * ($length - 1)) >= $size) {
        return [false, 0];
    }

    if (isset($grid[crossword_cell_key($beforeRow, $beforeCol)]) || isset($grid[crossword_cell_key($afterRow, $afterCol)])) {
        return [false, 0];
    }

    for ($index = 0; $index < $length; $index += 1) {
        $cellRow = $row + ($deltaRow * $index);
        $cellCol = $col + ($deltaCol * $index);
        $key = crossword_cell_key($cellRow, $cellCol);
        $existing = $grid[$key] ?? null;

        if ($existing !== null && $existing !== $word[$index]) {
            return [false, 0];
        }

        if ($existing === $word[$index]) {
            $intersections += 1;
            continue;
        }

        if ($direction === 'across') {
            if (isset($grid[crossword_cell_key($cellRow - 1, $cellCol)]) || isset($grid[crossword_cell_key($cellRow + 1, $cellCol)])) {
                return [false, 0];
            }
        } else {
            if (isset($grid[crossword_cell_key($cellRow, $cellCol - 1)]) || isset($grid[crossword_cell_key($cellRow, $cellCol + 1)])) {
                return [false, 0];
            }
        }
    }

    return [true, $intersections];
}

function crossword_place_word(array &$grid, string $word, int $row, int $col, string $direction): void
{
    $deltaRow = $direction === 'down' ? 1 : 0;
    $deltaCol = $direction === 'across' ? 1 : 0;

    for ($index = 0; $index < strlen($word); $index += 1) {
        $grid[crossword_cell_key($row + ($deltaRow * $index), $col + ($deltaCol * $index))] = $word[$index];
    }
}

function build_crossword_layout(array $questions): ?array
{
    if (count($questions) < 2) {
        return null;
    }

    $entries = array_values($questions);
    usort($entries, function (array $a, array $b) {
        return strlen($b['answer'] ?? '') <=> strlen($a['answer'] ?? '');
    });

    $longest = strlen($entries[0]['answer'] ?? '');
    $size = max(13, min(25, $longest + (count($entries) * 3)));
    $center = intdiv($size, 2);
    $grid = [];
    $placements = [];
    $firstWord = $entries[0]['answer'];
    $firstDirection = ($entries[0]['preferred_direction'] ?? '') === 'down' ? 'down' : 'across';
    $firstRow = $firstDirection === 'down' ? max(0, $center - intdiv(strlen($firstWord), 2)) : $center;
    $firstCol = $firstDirection === 'across' ? max(0, $center - intdiv(strlen($firstWord), 2)) : $center;

    crossword_place_word($grid, $firstWord, $firstRow, $firstCol, $firstDirection);
    $placements[] = [
        'question_id' => (int) $entries[0]['id'],
        'answer' => $firstWord,
        'row' => $firstRow,
        'col' => $firstCol,
        'direction' => $firstDirection,
        'intersections' => 0,
    ];

    foreach (array_slice($entries, 1) as $entry) {
        $word = $entry['answer'];
        $candidates = [];
        $preferredDirection = ($entry['preferred_direction'] ?? '') === 'down' ? 'down' : 'across';

        foreach ($placements as $placed) {
            $placedWord = $placed['answer'];
            $direction = $placed['direction'] === 'across' ? 'down' : 'across';

            if ($direction !== $preferredDirection) {
                continue;
            }

            for ($wordIndex = 0; $wordIndex < strlen($word); $wordIndex += 1) {
                for ($placedIndex = 0; $placedIndex < strlen($placedWord); $placedIndex += 1) {
                    if ($word[$wordIndex] !== $placedWord[$placedIndex]) {
                        continue;
                    }

                    if ($direction === 'down') {
                        $row = $placed['row'] - $wordIndex;
                        $col = $placed['col'] + $placedIndex;
                    } else {
                        $row = $placed['row'] + $placedIndex;
                        $col = $placed['col'] - $wordIndex;
                    }

                    [$canPlace, $intersections] = crossword_can_place_word($grid, $word, $row, $col, $direction, $size);

                    if ($canPlace && $intersections > 0) {
                        $distance = abs($center - $row) + abs($center - $col);
                        $candidates[] = [
                            'row' => $row,
                            'col' => $col,
                            'direction' => $direction,
                            'score' => ($intersections * 20) - $distance,
                            'intersections' => $intersections,
                        ];
                    }
                }
            }
        }

        if (!$candidates) {
            return null;
        }

        usort($candidates, function (array $a, array $b) {
            return $b['score'] <=> $a['score'];
        });

        $best = $candidates[0];
        crossword_place_word($grid, $word, $best['row'], $best['col'], $best['direction']);
        $placements[] = [
            'question_id' => (int) $entry['id'],
            'answer' => $word,
            'row' => $best['row'],
            'col' => $best['col'],
            'direction' => $best['direction'],
            'intersections' => $best['intersections'],
        ];
    }

    $rows = array_map('intval', array_map(function (string $key) {
        return explode(':', $key)[0];
    }, array_keys($grid)));
    $cols = array_map('intval', array_map(function (string $key) {
        return explode(':', $key)[1];
    }, array_keys($grid)));
    $minRow = max(0, min($rows) - 1);
    $maxRow = min($size - 1, max($rows) + 1);
    $minCol = max(0, min($cols) - 1);
    $maxCol = min($size - 1, max($cols) + 1);
    $cells = [];

    for ($row = $minRow; $row <= $maxRow; $row += 1) {
        $line = [];
        for ($col = $minCol; $col <= $maxCol; $col += 1) {
            $line[] = $grid[crossword_cell_key($row, $col)] ?? null;
        }
        $cells[] = $line;
    }

    $acrossNumber = 1;
    $downNumber = 1;
    foreach ($placements as &$placement) {
        $placement['row'] -= $minRow;
        $placement['col'] -= $minCol;
        if ($placement['direction'] === 'across') {
            $placement['number'] = $acrossNumber;
            $acrossNumber += 1;
        } else {
            $placement['number'] = $downNumber;
            $downNumber += 1;
        }
        unset($placement['answer']);
    }
    unset($placement);

    return [
        'rows' => count($cells),
        'cols' => count($cells[0] ?? []),
        'cells' => $cells,
        'placements' => $placements,
    ];
}

function mastery_demo_questions(): array
{
    return [
        [
            'prompt' => 'What is 8 + 5?',
            'options' => ['11', '12', '13', '14'],
            'correct_index' => 2,
            'points' => 10,
            'level' => 'easy',
        ],
        [
            'prompt' => 'Which planet is known as the Red Planet?',
            'options' => ['Mars', 'Venus', 'Mercury', 'Jupiter'],
            'correct_index' => 0,
            'points' => 10,
            'level' => 'easy',
        ],
        [
            'prompt' => 'Which word is the verb in the sentence: "Birds fly high"?',
            'options' => ['Birds', 'fly', 'high', 'the'],
            'correct_index' => 1,
            'points' => 10,
            'level' => 'easy',
        ],
        [
            'prompt' => 'Water freezes at what temperature in Celsius?',
            'options' => ['100', '32', '0', '-10'],
            'correct_index' => 2,
            'points' => 10,
            'level' => 'easy',
        ],
        [
            'prompt' => 'What is 15% of 200?',
            'options' => ['20', '25', '30', '35'],
            'correct_index' => 2,
            'points' => 15,
            'level' => 'medium',
        ],
        [
            'prompt' => 'Which organ pumps blood through the body?',
            'options' => ['Lungs', 'Brain', 'Heart', 'Liver'],
            'correct_index' => 2,
            'points' => 15,
            'level' => 'medium',
        ],
        [
            'prompt' => 'Who wrote "Romeo and Juliet"?',
            'options' => ['Jane Austen', 'William Shakespeare', 'Charles Dickens', 'Mark Twain'],
            'correct_index' => 1,
            'points' => 15,
            'level' => 'medium',
        ],
        [
            'prompt' => 'Which gas do plants absorb from the atmosphere?',
            'options' => ['Oxygen', 'Nitrogen', 'Carbon Dioxide', 'Hydrogen'],
            'correct_index' => 2,
            'points' => 15,
            'level' => 'medium',
        ],
        [
            'prompt' => 'Solve for x: 3x + 4 = 19',
            'options' => ['3', '4', '5', '6'],
            'correct_index' => 2,
            'points' => 20,
            'level' => 'hard',
        ],
        [
            'prompt' => 'Which part of the cell contains the genetic material?',
            'options' => ['Cell membrane', 'Nucleus', 'Cytoplasm', 'Ribosome'],
            'correct_index' => 1,
            'points' => 20,
            'level' => 'hard',
        ],
        [
            'prompt' => 'What is the main idea of a paragraph?',
            'options' => ['The smallest detail', 'Its central message', 'The last sentence', 'Its title only'],
            'correct_index' => 1,
            'points' => 20,
            'level' => 'hard',
        ],
        [
            'prompt' => 'What is the value of pi rounded to two decimal places?',
            'options' => ['3.14', '3.41', '3.04', '3.44'],
            'correct_index' => 0,
            'points' => 20,
            'level' => 'hard',
        ],
        [
            'prompt' => 'If a triangle has angles of 35 degrees and 65 degrees, what is the third angle?',
            'options' => ['70 degrees', '80 degrees', '90 degrees', '100 degrees'],
            'correct_index' => 1,
            'points' => 25,
            'level' => 'master',
        ],
        [
            'prompt' => 'Which process lets plants convert light energy into chemical energy?',
            'options' => ['Respiration', 'Evaporation', 'Photosynthesis', 'Transpiration'],
            'correct_index' => 2,
            'points' => 25,
            'level' => 'master',
        ],
        [
            'prompt' => 'Which sentence uses proper subject-verb agreement?',
            'options' => ['The team are winning.', 'The team is winning.', 'The team were winning.', 'The team have winning.'],
            'correct_index' => 1,
            'points' => 25,
            'level' => 'master',
        ],
        [
            'prompt' => 'Which data set has the greatest range?',
            'options' => ['4, 5, 6, 7', '12, 13, 14, 15', '3, 8, 9, 18', '20, 21, 22, 23'],
            'correct_index' => 2,
            'points' => 25,
            'level' => 'master',
        ],
    ];
}

function mastery_demo_quiz(array $classroom): array
{
    $questions = [];

    foreach (mastery_demo_questions() as $index => $question) {
        $question['id'] = $index + 1;
        $questions[] = $question;
    }

    return [
        'id' => next_id($classroom['quizzes'] ?? []),
        'title' => 'Mastery Mountain Demo',
        'description' => 'Clear Easy, Medium, Hard, and Master. You need 75% on each level before the next one unlocks.',
        'game_type' => 'master_ladder',
        'mastery_threshold' => 75,
        'questions' => $questions,
        'updated_at' => now_iso(),
        'created_at' => now_iso(),
    ];
}

function ensure_mastery_demo_classroom(array $teacher): array
{
    $records = classrooms();
    $targetClassroom = null;

    foreach ($records as $classroom) {
        if ((int) $classroom['teacher_id'] !== (int) $teacher['id']) {
            continue;
        }

        foreach ($classroom['quizzes'] ?? [] as $quiz) {
            if (($quiz['title'] ?? '') === 'Mastery Mountain Demo' && ($quiz['game_type'] ?? '') === 'master_ladder') {
                return $classroom;
            }
        }

        if ($targetClassroom === null) {
            $targetClassroom = $classroom;
        }
    }

    if ($targetClassroom === null) {
        $targetClassroom = [
            'id' => next_id($records),
            'teacher_id' => (int) $teacher['id'],
            'name' => 'CHALK Mastery Lab',
            'subject' => 'Progression Demo',
            'description' => 'A sample classroom for testing mastery-based level progression.',
            'code' => generate_join_code(),
            'student_ids' => [],
            'quizzes' => [],
            'announcements' => [],
            'chat_messages' => [],
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ];
        $records[] = $targetClassroom;
    }

    $targetClassroom['quizzes'] = $targetClassroom['quizzes'] ?? [];
    $targetClassroom['quizzes'][] = mastery_demo_quiz($targetClassroom);
    $targetClassroom['updated_at'] = now_iso();
    save_classroom($targetClassroom);

    return $targetClassroom;
}

function generate_join_code(): string
{
    $existing = array_column(classrooms(), 'code');

    do {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    } while (in_array($code, $existing, true));

    return $code;
}

function teacher_classrooms(int $teacherId): array
{
    return array_values(array_filter(classrooms(), function (array $classroom) use ($teacherId) {
        return (int) $classroom['teacher_id'] === $teacherId;
    }));
}

function student_classrooms(int $studentId): array
{
    return array_values(array_filter(classrooms(), function (array $classroom) use ($studentId) {
        return in_array($studentId, $classroom['student_ids'] ?? [], true);
    }));
}

function user_classrooms(array $user): array
{
    if (($user['role'] ?? '') === 'teacher') {
        return teacher_classrooms((int) $user['id']);
    }

    if (($user['role'] ?? '') === 'student') {
        return student_classrooms((int) $user['id']);
    }

    return [];
}

function find_classroom(int $classroomId): ?array
{
    foreach (classrooms() as $classroom) {
        if ((int) $classroom['id'] === $classroomId) {
            return $classroom;
        }
    }

    return null;
}

function save_classroom(array $updatedClassroom): void
{
    $records = classrooms();

    foreach ($records as $index => $classroom) {
        if ((int) $classroom['id'] === (int) $updatedClassroom['id']) {
            $records[$index] = $updatedClassroom;
            save_classrooms($records);

            return;
        }
    }

    $records[] = $updatedClassroom;
    save_classrooms($records);
}

function create_classroom_announcement(array $classroom, array $teacher, string $title, string $body, array $attachments): array
{
    $classroom['announcements'] = $classroom['announcements'] ?? [];
    $classroom['announcements'][] = [
        'id' => next_id($classroom['announcements']),
        'teacher_id' => (int) $teacher['id'],
        'teacher_name' => $teacher['name'],
        'title' => $title,
        'body' => $body,
        'attachments' => $attachments,
        'created_at' => now_iso(),
    ];
    $classroom['updated_at'] = now_iso();

    return $classroom;
}

function create_classroom_chat_message(array $classroom, array $user, string $body): array
{
    $classroom['chat_messages'] = $classroom['chat_messages'] ?? [];
    $classroom['chat_messages'][] = [
        'id' => next_id($classroom['chat_messages']),
        'user_id' => (int) $user['id'],
        'user_name' => $user['name'],
        'user_role' => $user['role'],
        'body' => $body,
        'created_at' => now_iso(),
    ];
    $classroom['updated_at'] = now_iso();

    return $classroom;
}

function classroom_belongs_to_user(array $classroom, array $user): bool
{
    if ($user['role'] === 'teacher') {
        return (int) $classroom['teacher_id'] === (int) $user['id'];
    }

    return in_array((int) $user['id'], $classroom['student_ids'] ?? [], true);
}

function find_classroom_by_code(string $code): ?array
{
    foreach (classrooms() as $classroom) {
        if (strcasecmp($classroom['code'], $code) === 0) {
            return $classroom;
        }
    }

    return null;
}

function classroom_teacher_name(array $classroom): string
{
    $teacher = find_user_by_id((int) $classroom['teacher_id']);

    return $teacher['name'] ?? 'Unknown Teacher';
}

function classroom_students(array $classroom): array
{
    $studentIds = $classroom['student_ids'] ?? [];

    return array_values(array_filter(array_map(function (int $id) {
        return find_user_by_id($id);
    }, $studentIds)));
}

function classroom_quiz(array $classroom, int $quizId): ?array
{
    foreach ($classroom['quizzes'] ?? [] as $quiz) {
        if ((int) $quiz['id'] === $quizId) {
            return $quiz;
        }
    }

    return null;
}

function update_quiz_in_classroom(array $classroom, array $quiz): array
{
    $found = false;

    foreach ($classroom['quizzes'] as $index => $existingQuiz) {
        if ((int) $existingQuiz['id'] === (int) $quiz['id']) {
            $classroom['quizzes'][$index] = $quiz;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $classroom['quizzes'][] = $quiz;
    }

    $classroom['updated_at'] = now_iso();

    return $classroom;
}

function latest_attempt_for_quiz(int $studentId, int $classroomId, int $quizId): ?array
{
    $matches = array_values(array_filter(attempts(), function (array $attempt) use ($studentId, $classroomId, $quizId) {
        return (int) $attempt['student_id'] === $studentId
            && (int) $attempt['classroom_id'] === $classroomId
            && (int) $attempt['quiz_id'] === $quizId;
    }));

    usort($matches, function (array $a, array $b) {
        return strcmp($b['played_at'], $a['played_at']);
    });

    return $matches[0] ?? null;
}

function classroom_attempts(int $classroomId): array
{
    $records = array_values(array_filter(attempts(), function (array $attempt) use ($classroomId) {
        return (int) $attempt['classroom_id'] === $classroomId;
    }));

    usort($records, function (array $a, array $b) {
        return strcmp($b['played_at'], $a['played_at']);
    });

    return $records;
}

function student_attempts(int $studentId): array
{
    $records = array_values(array_filter(attempts(), function (array $attempt) use ($studentId) {
        return (int) $attempt['student_id'] === $studentId;
    }));

    usort($records, function (array $a, array $b) {
        return strcmp($b['played_at'], $a['played_at']);
    });

    return $records;
}

function create_attempt(int $studentId, int $classroomId, array $quiz, array $answers, int $elapsedSeconds, bool $forceZero = false): array
{
    $records = attempts();
    $score = 0;
    $maxScore = 0;

    foreach ($quiz['questions'] as $index => $question) {
        if ($forceZero) {
            $maxScore += (int) ($question['points'] ?? 10);
            continue;
        }

        if (($quiz['game_type'] ?? '') === 'crossword') {
            $points = (int) ($question['points'] ?? 10);
            $expected = crossword_normalize_answer((string) ($question['answer'] ?? ($question['options'][0] ?? '')));
            $submitted = crossword_normalize_answer((string) ($answers[$index] ?? ''));
            $maxScore += $points;

            if ($expected !== '' && $submitted === $expected) {
                $score += $points;
            }

            continue;
        }

        $isAttemptedQuestion = ($quiz['game_type'] ?? '') === 'master_ladder'
            ? array_key_exists($index, $answers)
            : true;

        if ($isAttemptedQuestion) {
            $maxScore += (int) ($question['points'] ?? 10);
        }

        $selected = $answers[$index] ?? null;

        if ($selected !== null && (int) $selected === (int) $question['correct_index']) {
            $score += (int) ($question['points'] ?? 10);
        }
    }

    $attempt = [
        'id' => next_id($records),
        'student_id' => $studentId,
        'classroom_id' => $classroomId,
        'quiz_id' => $quiz['id'],
        'quiz_title' => $quiz['title'],
        'game_type' => $quiz['game_type'],
        'answers' => $answers,
        'score' => $score,
        'max_score' => $maxScore,
        'elapsed_seconds' => $elapsedSeconds,
        'played_at' => now_iso(),
    ];

    $records[] = $attempt;
    save_attempts($records);

    return $attempt;
}

function classroom_leaderboard(array $classroom): array
{
    $students = classroom_students($classroom);
    $records = classroom_attempts((int) $classroom['id']);
    $rows = [];

    foreach ($students as $student) {
        $rows[(int) $student['id']] = [
            'student' => $student,
            'best_by_quiz' => [],
            'attempts' => 0,
            'total_score' => 0,
            'max_score' => 0,
            'average_percent' => 0,
            'elapsed_seconds' => 0,
            'rank' => 0,
        ];
    }

    foreach ($records as $attempt) {
        $studentId = (int) ($attempt['student_id'] ?? 0);

        if (!isset($rows[$studentId])) {
            continue;
        }

        $quizId = (int) ($attempt['quiz_id'] ?? 0);
        $percent = percentage((int) ($attempt['score'] ?? 0), (int) ($attempt['max_score'] ?? 0));
        $attempt['percent'] = $percent;
        $rows[$studentId]['attempts'] += 1;

        $currentBest = $rows[$studentId]['best_by_quiz'][$quizId] ?? null;
        if (!$currentBest
            || $percent > (int) ($currentBest['percent'] ?? 0)
            || ($percent === (int) ($currentBest['percent'] ?? 0) && (int) $attempt['elapsed_seconds'] < (int) ($currentBest['elapsed_seconds'] ?? PHP_INT_MAX))
        ) {
            $rows[$studentId]['best_by_quiz'][$quizId] = $attempt;
        }
    }

    foreach ($rows as &$row) {
        foreach ($row['best_by_quiz'] as $attempt) {
            $row['total_score'] += (int) ($attempt['score'] ?? 0);
            $row['max_score'] += (int) ($attempt['max_score'] ?? 0);
            $row['elapsed_seconds'] += max(1, (int) ($attempt['elapsed_seconds'] ?? 0));
        }

        $row['average_percent'] = percentage((int) $row['total_score'], (int) $row['max_score']);
        $row['quizzes_played'] = count($row['best_by_quiz']);
        unset($row['best_by_quiz']);
    }
    unset($row);

    $rows = array_values($rows);
    usort($rows, function (array $a, array $b) {
        return [$b['total_score'], $b['average_percent'], $b['quizzes_played'], $a['elapsed_seconds'], $a['student']['name']]
            <=> [$a['total_score'], $a['average_percent'], $a['quizzes_played'], $b['elapsed_seconds'], $b['student']['name']];
    });

    $rank = 0;
    $previousKey = null;
    foreach ($rows as $index => &$row) {
        $key = $row['total_score'] . ':' . $row['average_percent'] . ':' . $row['quizzes_played'];
        if ($key !== $previousKey) {
            $rank = $index + 1;
            $previousKey = $key;
        }
        $row['rank'] = $rank;
    }
    unset($row);

    return $rows;
}

function percentage(int $value, int $max): int
{
    if ($max <= 0) {
        return 0;
    }

    return (int) round(($value / $max) * 100);
}

function average_percentage(array $values): int
{
    $values = array_values(array_filter($values, function ($value) {
        return is_numeric($value);
    }));

    if (!$values) {
        return 0;
    }

    return (int) round(array_sum($values) / count($values));
}

function attempt_is_disqualified(array $attempt): bool
{
    $answers = $attempt['answers'] ?? [];

    return is_array($answers) && !empty($answers['_disqualified']);
}

function answer_at_index(array $answers, int $index, bool &$exists)
{
    if (array_key_exists($index, $answers)) {
        $exists = true;
        return $answers[$index];
    }

    $stringIndex = (string) $index;
    if (array_key_exists($stringIndex, $answers)) {
        $exists = true;
        return $answers[$stringIndex];
    }

    $exists = false;
    return null;
}

function learning_level_label(string $level): string
{
    $levels = mastery_levels();

    return $levels[$level] ?? ucfirst($level ?: 'easy');
}

function option_answer_label(array $question, $selected): string
{
    if ($selected === null || $selected === '') {
        return 'No answer';
    }

    $selectedIndex = (int) $selected;
    $options = array_values($question['options'] ?? []);

    if (!array_key_exists($selectedIndex, $options)) {
        return 'Invalid answer';
    }

    return chr(65 + $selectedIndex) . '. ' . $options[$selectedIndex];
}

function correct_answer_label(array $question, string $gameType): string
{
    if ($gameType === 'crossword') {
        return crossword_normalize_answer((string) ($question['answer'] ?? ($question['options'][0] ?? '')));
    }

    return option_answer_label($question, $question['correct_index'] ?? null);
}

function learning_guidance(array $question, bool $isCorrect, bool $attempted, string $gameType): string
{
    if ($isCorrect) {
        return 'Keep it fresh by explaining why this answer is correct in one sentence.';
    }

    if (!$attempted) {
        return 'Replay this item and answer it before moving on; skipped questions usually hide the real weak spot.';
    }

    if ($gameType === 'crossword') {
        $answer = correct_answer_label($question, $gameType);
        return 'Review the meaning and spelling of "' . $answer . '", then use the clue to recall it without looking.';
    }

    $level = (string) ($question['level'] ?? 'easy');
    if (in_array($level, ['hard', 'master'], true)) {
        return 'Break the problem into steps, then compare each step with the correct answer.';
    }

    return 'Compare your answer with the correct one and write a short rule you can reuse next time.';
}

function attempt_question_review_rows(array $quiz, array $attempt): array
{
    if (attempt_is_disqualified($attempt)) {
        return [];
    }

    $gameType = (string) ($quiz['game_type'] ?? ($attempt['game_type'] ?? 'time_attack'));
    $answers = is_array($attempt['answers'] ?? null) ? $attempt['answers'] : [];
    $rows = [];

    foreach (array_values($quiz['questions'] ?? []) as $index => $question) {
        $answerExists = false;
        $selected = answer_at_index($answers, $index, $answerExists);
        $points = (int) ($question['points'] ?? 10);
        $level = (string) ($question['level'] ?? 'easy');
        $countsForLearning = $gameType === 'master_ladder' ? $answerExists : true;

        if ($gameType === 'crossword') {
            $expected = crossword_normalize_answer((string) ($question['answer'] ?? ($question['options'][0] ?? '')));
            $submitted = crossword_normalize_answer((string) ($selected ?? ''));
            $attempted = $submitted !== '';
            $isCorrect = $expected !== '' && $submitted === $expected;
            $submittedLabel = $attempted ? $submitted : 'No answer';
        } else {
            $correctIndex = (int) ($question['correct_index'] ?? 0);
            $attempted = $answerExists && $selected !== null && $selected !== '';
            $isCorrect = $attempted && (int) $selected === $correctIndex;
            $submittedLabel = option_answer_label($question, $selected);
        }

        $rows[] = [
            'index' => $index,
            'question_id' => (int) ($question['id'] ?? ($index + 1)),
            'prompt' => (string) ($question['prompt'] ?? ''),
            'level' => $level,
            'level_label' => learning_level_label($level),
            'points' => $points,
            'earned_points' => $isCorrect ? $points : 0,
            'attempted' => $attempted,
            'counts_for_learning' => $countsForLearning,
            'is_correct' => $isCorrect,
            'submitted_answer' => $submittedLabel,
            'correct_answer' => correct_answer_label($question, $gameType),
            'guidance' => learning_guidance($question, $isCorrect, $attempted, $gameType),
            'game_type' => $gameType,
            'options' => array_values($question['options'] ?? []),
            'correct_index' => (int) ($question['correct_index'] ?? 0),
            'answer' => $question['answer'] ?? ($question['options'][0] ?? ''),
        ];
    }

    return $rows;
}

function learning_empty_profile(): array
{
    return [
        'attempts' => 0,
        'analyzed_attempts' => 0,
        'questions_seen' => 0,
        'correct' => 0,
        'overall_accuracy' => 0,
        'trend_message' => 'Play a quiz first so the learning coach can build a profile.',
        'focus_items' => [],
        'weak_levels' => [],
        'strong_levels' => [],
        'plan_steps' => [
            'Join a classroom or open an assigned quiz.',
            'Complete one full game so the system can find your weak areas.',
        ],
        'disqualified_attempts' => 0,
    ];
}

function finalize_learning_stats(array $stats): array
{
    foreach ($stats as &$row) {
        $row['accuracy'] = percentage((int) ($row['correct'] ?? 0), (int) ($row['attempts'] ?? 0));
        $row['missed'] = max(0, (int) ($row['attempts'] ?? 0) - (int) ($row['correct'] ?? 0));
    }
    unset($row);

    return $stats;
}

function student_learning_profile(int $studentId, ?int $classroomId = null): array
{
    $records = student_attempts($studentId);

    if ($classroomId !== null) {
        $records = array_values(array_filter($records, function (array $attempt) use ($classroomId) {
            return (int) ($attempt['classroom_id'] ?? 0) === $classroomId;
        }));
    }

    $profile = learning_empty_profile();
    $profile['attempts'] = count($records);

    $levelStats = [];
    $questionStats = [];
    $recentPercentages = [];

    foreach ($records as $attempt) {
        if (attempt_is_disqualified($attempt)) {
            $profile['disqualified_attempts'] += 1;
            continue;
        }

        $classroom = find_classroom((int) ($attempt['classroom_id'] ?? 0));
        if (!$classroom) {
            continue;
        }

        $quiz = classroom_quiz($classroom, (int) ($attempt['quiz_id'] ?? 0));
        if (!$quiz) {
            continue;
        }

        $profile['analyzed_attempts'] += 1;
        $recentPercentages[] = percentage((int) ($attempt['score'] ?? 0), (int) ($attempt['max_score'] ?? 0));

        foreach (attempt_question_review_rows($quiz, $attempt) as $row) {
            if (!$row['counts_for_learning']) {
                continue;
            }

            $level = $row['level'];
            if (!isset($levelStats[$level])) {
                $levelStats[$level] = [
                    'level' => $level,
                    'label' => $row['level_label'],
                    'attempts' => 0,
                    'correct' => 0,
                    'missed' => 0,
                    'accuracy' => 0,
                ];
            }

            $profile['questions_seen'] += 1;
            $levelStats[$level]['attempts'] += 1;

            if ($row['is_correct']) {
                $profile['correct'] += 1;
                $levelStats[$level]['correct'] += 1;
            }

            $questionKey = implode(':', [
                (int) $classroom['id'],
                (int) ($quiz['id'] ?? 0),
                (int) $row['question_id'],
                $row['index'],
            ]);

            if (!isset($questionStats[$questionKey])) {
                $questionStats[$questionKey] = [
                    'classroom_id' => (int) $classroom['id'],
                    'classroom_name' => $classroom['name'],
                    'quiz_id' => (int) ($quiz['id'] ?? 0),
                    'quiz_title' => $quiz['title'] ?? $attempt['quiz_title'],
                    'game_type' => $quiz['game_type'] ?? $attempt['game_type'],
                    'prompt' => $row['prompt'],
                    'level' => $row['level'],
                    'level_label' => $row['level_label'],
                    'correct_answer' => $row['correct_answer'],
                    'last_submitted_answer' => $row['submitted_answer'],
                    'attempts' => 0,
                    'correct' => 0,
                    'missed' => 0,
                    'accuracy' => 0,
                    'guidance' => $row['guidance'],
                    'question' => [
                        'id' => $row['question_id'],
                        'prompt' => $row['prompt'],
                        'options' => $row['options'],
                        'correct_index' => $row['correct_index'],
                        'points' => $row['points'],
                        'level' => $row['level'],
                        'answer' => $row['answer'],
                    ],
                ];
            }

            $questionStats[$questionKey]['attempts'] += 1;
            $questionStats[$questionKey]['last_submitted_answer'] = $row['submitted_answer'];
            $questionStats[$questionKey]['guidance'] = $row['guidance'];

            if ($row['is_correct']) {
                $questionStats[$questionKey]['correct'] += 1;
            }
        }
    }

    $profile['overall_accuracy'] = percentage((int) $profile['correct'], (int) $profile['questions_seen']);
    $levelStats = finalize_learning_stats($levelStats);
    $questionStats = finalize_learning_stats($questionStats);

    $weakLevels = array_values(array_filter($levelStats, function (array $row) {
        return (int) ($row['attempts'] ?? 0) > 0 && (int) ($row['accuracy'] ?? 0) < 75;
    }));
    usort($weakLevels, function (array $a, array $b) {
        return [$a['accuracy'], $b['attempts']] <=> [$b['accuracy'], $a['attempts']];
    });

    $strongLevels = array_values(array_filter($levelStats, function (array $row) {
        return (int) ($row['attempts'] ?? 0) > 0 && (int) ($row['accuracy'] ?? 0) >= 85;
    }));
    usort($strongLevels, function (array $a, array $b) {
        return [$b['accuracy'], $b['attempts']] <=> [$a['accuracy'], $a['attempts']];
    });

    $focusItems = array_values(array_filter($questionStats, function (array $row) {
        return (int) ($row['attempts'] ?? 0) > 0 && ((int) ($row['accuracy'] ?? 0) < 70 || (int) ($row['correct'] ?? 0) === 0);
    }));
    usort($focusItems, function (array $a, array $b) {
        return [$a['accuracy'], $b['missed'], $b['attempts']] <=> [$b['accuracy'], $a['missed'], $a['attempts']];
    });

    if (!$focusItems && $questionStats) {
        $focusItems = array_values($questionStats);
        usort($focusItems, function (array $a, array $b) {
            return [$a['accuracy'], $b['missed']] <=> [$b['accuracy'], $a['missed']];
        });
    }

    $profile['focus_items'] = array_slice($focusItems, 0, 6);
    $profile['weak_levels'] = array_slice($weakLevels, 0, 4);
    $profile['strong_levels'] = array_slice($strongLevels, 0, 4);

    $latestAverage = average_percentage(array_slice($recentPercentages, 0, 3));
    $previousAverage = average_percentage(array_slice($recentPercentages, 3, 3));

    if (!$profile['analyzed_attempts']) {
        $profile['trend_message'] = 'No scored attempts yet. Finish one quiz to unlock recommendations.';
    } elseif ($previousAverage > 0) {
        $difference = $latestAverage - $previousAverage;
        if ($difference > 0) {
            $profile['trend_message'] = 'Latest average is ' . $latestAverage . '%, up ' . $difference . ' points from the previous set.';
        } elseif ($difference < 0) {
            $profile['trend_message'] = 'Latest average is ' . $latestAverage . '%, down ' . abs($difference) . ' points. Focus practice is recommended.';
        } else {
            $profile['trend_message'] = 'Latest average is steady at ' . $latestAverage . '%. Push one weak area above 75%.';
        }
    } else {
        $profile['trend_message'] = 'Latest average is ' . $latestAverage . '%. Add more attempts to reveal a trend.';
    }

    if ($profile['focus_items']) {
        $first = $profile['focus_items'][0];
        $profile['plan_steps'] = [
            'Start Focus Practice for the weakest multiple-choice items.',
            'Replay "' . $first['quiz_title'] . '" and aim for at least 80%.',
            'Write a one-sentence explanation for: ' . $first['prompt'],
        ];
    } elseif ($profile['analyzed_attempts']) {
        $profile['plan_steps'] = [
            'No urgent weak area is below 70%; raise the target to 90%.',
            'Replay your lowest quiz and try to beat your best time.',
        ];
    }

    return $profile;
}

function learning_practice_questions(array $profile, int $limit = 8): array
{
    $questions = [];
    $seen = [];

    foreach ($profile['focus_items'] ?? [] as $item) {
        $question = $item['question'] ?? [];
        $options = array_values($question['options'] ?? []);
        $correctIndex = (int) ($question['correct_index'] ?? -1);

        if (count($options) < 4 || $correctIndex < 0 || $correctIndex > 3) {
            continue;
        }

        $key = md5((string) ($question['prompt'] ?? '') . '|' . implode('|', $options));
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $questions[] = [
            'id' => count($questions) + 1,
            'prompt' => $question['prompt'],
            'options' => array_slice($options, 0, 4),
            'correct_index' => $correctIndex,
            'points' => 10,
            'level' => $question['level'] ?? 'easy',
        ];

        if (count($questions) >= $limit) {
            break;
        }
    }

    return $questions;
}

function classroom_learning_profile(array $classroom): array
{
    $records = classroom_attempts((int) $classroom['id']);
    $levelStats = [];
    $questionStats = [];
    $analyzedAttempts = 0;
    $questionsSeen = 0;
    $correct = 0;

    foreach ($records as $attempt) {
        if (attempt_is_disqualified($attempt)) {
            continue;
        }

        $quiz = classroom_quiz($classroom, (int) ($attempt['quiz_id'] ?? 0));
        if (!$quiz) {
            continue;
        }

        $analyzedAttempts += 1;
        $student = find_user_by_id((int) ($attempt['student_id'] ?? 0));

        foreach (attempt_question_review_rows($quiz, $attempt) as $row) {
            if (!$row['counts_for_learning']) {
                continue;
            }

            $questionsSeen += 1;
            if ($row['is_correct']) {
                $correct += 1;
            }

            $level = $row['level'];
            if (!isset($levelStats[$level])) {
                $levelStats[$level] = [
                    'level' => $level,
                    'label' => $row['level_label'],
                    'attempts' => 0,
                    'correct' => 0,
                    'missed' => 0,
                    'accuracy' => 0,
                ];
            }
            $levelStats[$level]['attempts'] += 1;
            if ($row['is_correct']) {
                $levelStats[$level]['correct'] += 1;
            }

            $questionKey = implode(':', [
                (int) ($quiz['id'] ?? 0),
                (int) $row['question_id'],
                $row['index'],
            ]);

            if (!isset($questionStats[$questionKey])) {
                $questionStats[$questionKey] = [
                    'quiz_id' => (int) ($quiz['id'] ?? 0),
                    'quiz_title' => $quiz['title'] ?? $attempt['quiz_title'],
                    'game_type' => $quiz['game_type'] ?? $attempt['game_type'],
                    'prompt' => $row['prompt'],
                    'level_label' => $row['level_label'],
                    'correct_answer' => $row['correct_answer'],
                    'attempts' => 0,
                    'correct' => 0,
                    'missed' => 0,
                    'accuracy' => 0,
                    'missed_students' => [],
                ];
            }

            $questionStats[$questionKey]['attempts'] += 1;
            if ($row['is_correct']) {
                $questionStats[$questionKey]['correct'] += 1;
            } else {
                $studentName = $student['name'] ?? 'Student';
                $questionStats[$questionKey]['missed_students'][(int) ($attempt['student_id'] ?? 0)] = $studentName;
            }
        }
    }

    $levelStats = finalize_learning_stats($levelStats);
    $questionStats = finalize_learning_stats($questionStats);

    foreach ($questionStats as &$row) {
        $row['missed_student_count'] = count($row['missed_students']);
        $row['missed_students'] = array_values($row['missed_students']);
    }
    unset($row);

    $weakQuestions = array_values(array_filter($questionStats, function (array $row) {
        return (int) ($row['attempts'] ?? 0) > 0 && (int) ($row['accuracy'] ?? 0) < 75;
    }));
    usort($weakQuestions, function (array $a, array $b) {
        return [$a['accuracy'], $b['missed_student_count'], $b['attempts']] <=> [$b['accuracy'], $a['missed_student_count'], $a['attempts']];
    });

    $weakLevels = array_values(array_filter($levelStats, function (array $row) {
        return (int) ($row['attempts'] ?? 0) > 0 && (int) ($row['accuracy'] ?? 0) < 75;
    }));
    usort($weakLevels, function (array $a, array $b) {
        return [$a['accuracy'], $b['attempts']] <=> [$b['accuracy'], $a['attempts']];
    });

    return [
        'attempts' => count($records),
        'analyzed_attempts' => $analyzedAttempts,
        'questions_seen' => $questionsSeen,
        'overall_accuracy' => percentage($correct, $questionsSeen),
        'weak_questions' => array_slice($weakQuestions, 0, 6),
        'weak_levels' => array_slice($weakLevels, 0, 4),
    ];
}

function attempt_learning_summary(array $quiz, array $attempt): array
{
    $rows = attempt_question_review_rows($quiz, $attempt);
    $counted = array_values(array_filter($rows, function (array $row) {
        return !empty($row['counts_for_learning']);
    }));
    $weakRows = array_values(array_filter($counted, function (array $row) {
        return empty($row['is_correct']);
    }));
    $correctRows = array_values(array_filter($counted, function (array $row) {
        return !empty($row['is_correct']);
    }));

    return [
        'rows' => $rows,
        'weak_rows' => $weakRows,
        'correct_rows' => $correctRows,
        'accuracy' => percentage(count($correctRows), count($counted)),
        'headline' => $weakRows
            ? 'Focus next on ' . $weakRows[0]['level_label'] . ': ' . $weakRows[0]['prompt']
            : 'No weak item in this run. Keep replaying for speed and consistency.',
    ];
}

function teacher_dashboard_stats(int $teacherId): array
{
    $classrooms = teacher_classrooms($teacherId);
    $quizCount = 0;
    $studentCount = 0;
    $attemptCount = 0;

    foreach ($classrooms as $classroom) {
        $quizCount += count($classroom['quizzes'] ?? []);
        $studentCount += count($classroom['student_ids'] ?? []);
        $attemptCount += count(classroom_attempts((int) $classroom['id']));
    }

    return [
        'classrooms' => count($classrooms),
        'quizzes' => $quizCount,
        'students' => $studentCount,
        'attempts' => $attemptCount,
    ];
}

function student_dashboard_stats(int $studentId): array
{
    $classrooms = student_classrooms($studentId);
    $attempts = student_attempts($studentId);
    $bestScore = 0;

    foreach ($attempts as $attempt) {
        $bestScore = max($bestScore, percentage((int) $attempt['score'], (int) $attempt['max_score']));
    }

    return [
        'classrooms' => count($classrooms),
        'attempts' => count($attempts),
        'best_score' => $bestScore,
    ];
}

ensure_storage();
