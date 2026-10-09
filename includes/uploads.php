<?php
// File bytes live separately from classroom JSON; URLs still enforce membership.
function upload_storage_key(string $scope, string $name): void {
    if (!in_array($scope, ['chat', 'announcements'], true)
        || !preg_match('/^(?:[a-f0-9]{48}|[a-f0-9]{32}\.[a-z0-9]+)$/D', $name)) {
        throw new InvalidArgumentException('Invalid upload storage key.');
    }
}
function persistent_upload_store(string $scope, string $name, string $bytes, ?PDO $pdo = null): void {
    upload_storage_key($scope, $name);
    if ($bytes === '' || strlen($bytes) > 10 * 1024 * 1024) throw new InvalidArgumentException('Files must be between 1 byte and 10 MB.');
    ($pdo ?? db())->prepare('INSERT INTO uploaded_files (scope, stored_name, data) VALUES (?, ?, ?)')
        ->execute([$scope, $name, base64_encode($bytes)]);
}
function persistent_upload_read(string $scope, string $name, ?PDO $pdo = null): ?string {
    upload_storage_key($scope, $name);
    $pdo ??= db();
    $query = $pdo->prepare('SELECT data FROM uploaded_files WHERE scope = ? AND stored_name = ?');
    $query->execute([$scope, $name]);
    $encoded = $query->fetchColumn();
    if ($encoded !== false) {
        $bytes = base64_decode($encoded, true);
        return $bytes === false ? null : $bytes;
    }
    $path = UPLOADS_DIR . '/' . $scope . '/' . $name;
    if (!is_file($path)) return null;
    $size = filesize($path);
    if (!$size || $size > 10 * 1024 * 1024) return null;
    $bytes = file_get_contents($path);
    return $bytes === false ? null : $bytes;
}
function persistent_upload_delete(string $scope, string $name, ?PDO $pdo = null): void {
    upload_storage_key($scope, $name);
    ($pdo ?? db())->prepare('DELETE FROM uploaded_files WHERE scope = ? AND stored_name = ?')->execute([$scope, $name]);
}
