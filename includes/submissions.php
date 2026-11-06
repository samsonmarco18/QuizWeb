<?php
const SUBMISSION_MAX_FILES = 5;

function submission_files(PDO $pdo, int $classroomId, string $itemId, ?int $studentId = null): array
{
    $sql = 'SELECT * FROM activity_submission_files WHERE classroom_id = ? AND item_id = ?';
    $params = [$classroomId, $itemId];
    if ($studentId !== null) { $sql .= ' AND student_id = ?'; $params[] = $studentId; }
    $query = $pdo->prepare($sql . ' ORDER BY uploaded_at, stored_name'); $query->execute($params);
    return $query->fetchAll();
}

function submission_file_url(array $file): string
{
    return '/QuizWeb/submission_file.php?file=' . rawurlencode($file['stored_name']);
}

function render_submission_files(array $files): void
{
    if (!$files) return;
    ?><ul class="academic-submission-files"><?php foreach ($files as $file): ?><li><a href="<?php echo esc(submission_file_url($file)); ?>"><?php echo esc($file['original_name']); ?></a> <small><?php echo esc((string) round($file['file_size'] / 1024, 1)); ?> KB</small></li><?php endforeach; ?></ul><?php
}

// Called only inside the classroom-locked submission transaction.
function submission_update_files(PDO $pdo, int $classroomId, string $itemId, int $studentId, array $files, array $remove): void
{
    $existing = submission_files($pdo, $classroomId, $itemId, $studentId);
    if (count($remove) > SUBMISSION_MAX_FILES || count(array_filter($remove, 'is_string')) !== count($remove)) throw new InvalidArgumentException('Choose valid attachments to remove.');
    foreach (array_unique($remove) as $name) {
        if (!in_array($name, array_column($existing, 'stored_name'), true)) throw new InvalidArgumentException('Attachment access denied.');
        $pdo->prepare('DELETE FROM activity_submission_files WHERE stored_name = ?')->execute([$name]);
        persistent_upload_delete('submissions', $name, $pdo);
    }
    $remaining = count($existing) - count(array_unique($remove));
    if (!$files) return;
    if (!is_array($files['name'] ?? null) || count($files['name']) > SUBMISSION_MAX_FILES) throw new InvalidArgumentException('Attach up to five files.');
    $allowed = announcement_allowed_extensions(); $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($files['name'] as $index => $original) {
        $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) throw new InvalidArgumentException(upload_error_message($error));
        if (++$remaining > SUBMISSION_MAX_FILES) throw new InvalidArgumentException('Keep at most five files per submission. Remove an existing file before attaching another.');
        $tmp = $files['tmp_name'][$index] ?? ''; $size = (int) ($files['size'][$index] ?? 0);
        if (!is_string($original) || !is_string($tmp) || !is_uploaded_file($tmp)) throw new InvalidArgumentException('Invalid uploaded file.');
        if (!in_array(strtolower(pathinfo($original, PATHINFO_EXTENSION)), $allowed, true)) throw new InvalidArgumentException('Unsupported file type. Use a document, image, text file, or ZIP archive.');
        if ($size < 1 || $size > 10 * 1024 * 1024) throw new InvalidArgumentException('Each file must be between 1 byte and 10 MB.');
        $bytes = file_get_contents($tmp);
        if ($bytes === false || strlen($bytes) !== $size) throw new InvalidArgumentException('Could not read the uploaded file.');
        $name = bin2hex(random_bytes(24));
        persistent_upload_store('submissions', $name, $bytes, $pdo);
        $pdo->prepare('INSERT INTO activity_submission_files (stored_name,classroom_id,item_id,student_id,original_name,mime_type,file_size,uploaded_at) VALUES (?,?,?,?,?,?,?,?)')->execute([$name,$classroomId,$itemId,$studentId,mb_substr(sanitize_filename($original),0,255),$finfo->file($tmp) ?: 'application/octet-stream',$size,now_iso()]);
    }
}
