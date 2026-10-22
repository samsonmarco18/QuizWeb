<?php

function material_state(array $user, int $classroomId, string $announcementId): array
{
    return $user['profile']['materials'][$classroomId . ':' . $announcementId] ?? [];
}

function material_is_accessible(array $user, array $classrooms, int $classroomId, string $announcementId): bool
{
    if (($user['role'] ?? '') !== 'student') return false;
    foreach ($classrooms as $classroom) {
        if ((int) $classroom['id'] !== $classroomId || !classroom_belongs_to_user($classroom, $user)) continue;
        foreach (classroom_announcements($classroom) as $announcement) {
            if ((string) $announcement['id'] === $announcementId) return true;
        }
    }
    return false;
}

function handle_material_action(array $user, array $classrooms): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'material_state') return;
    require_form_csrf();
    $classroomId = (int) ($_POST['classroom_id'] ?? 0);
    $announcementId = is_string($_POST['announcement_id'] ?? null) ? $_POST['announcement_id'] : '';
    $operation = $_POST['operation'] ?? '';
    $allowed = ['save' => ['saved', true], 'unsave' => ['saved', false], 'archive' => ['archived', true], 'restore' => ['archived', false]];
    $found = material_is_accessible($user, $classrooms, $classroomId, $announcementId);
    if (!$found || !is_string($operation) || !isset($allowed[$operation])) {
        http_response_code(403);
        exit('Material unavailable.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT profile FROM users WHERE id = :id FOR UPDATE');
        $query->execute(['id' => $user['id']]);
        $profile = db_json_decode($query->fetchColumn());
        [$flag, $value] = $allowed[$operation];
        $profile['materials'][$classroomId . ':' . $announcementId][$flag] = $value;
        $query = $pdo->prepare('UPDATE users SET profile = :profile WHERE id = :id');
        $query->execute(['profile' => db_json_encode($profile), 'id' => $user['id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
    flash_set('success', ['save' => 'Material saved.', 'unsave' => 'Material removed from Saved.', 'archive' => 'Material archived for your account.', 'restore' => 'Material restored.'][$operation]);
    $return = $_POST['material_return'] ?? '';
    redirect(in_array($return, ['saved', 'archive'], true) ? '/QuizWeb/' . $return . '.php' : '/QuizWeb/classroom.php?id=' . $classroomId . '&tab=materials');
}

function render_material_actions(array $user, array $classroom, array $announcement, string $return = ''): void
{
    if (($user['role'] ?? '') !== 'student') return;
    $state = material_state($user, (int) $classroom['id'], (string) $announcement['id']);
    ?>
    <form method="post" class="material-actions">
        <input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>">
        <input type="hidden" name="action" value="material_state">
        <input type="hidden" name="classroom_id" value="<?php echo (int) $classroom['id']; ?>">
        <input type="hidden" name="announcement_id" value="<?php echo esc((string) $announcement['id']); ?>">
        <input type="hidden" name="material_return" value="<?php echo esc($return); ?>">
        <button class="button button-secondary" name="operation" value="<?php echo empty($state['saved']) ? 'save' : 'unsave'; ?>"><?php echo nav_icon('bookmark'); ?><?php echo empty($state['saved']) ? 'Save' : 'Unsave'; ?></button>
        <button class="button button-secondary" name="operation" value="<?php echo empty($state['archived']) ? 'archive' : 'restore'; ?>"><?php echo nav_icon('archive'); ?><?php echo empty($state['archived']) ? 'Archive' : 'Restore'; ?></button>
    </form>
    <?php
}
