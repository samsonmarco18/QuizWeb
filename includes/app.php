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

function create_attempt(int $studentId, int $classroomId, array $quiz, array $answers, int $elapsedSeconds): array
{
    $records = attempts();
    $score = 0;
    $maxScore = 0;

    foreach ($quiz['questions'] as $index => $question) {
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

function percentage(int $value, int $max): int
{
    if ($max <= 0) {
        return 0;
    }

    return (int) round(($value / $max) * 100);
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
