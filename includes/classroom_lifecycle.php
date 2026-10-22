<?php
function classroom_is_archived(array $user, int $classroomId): bool {
    return !empty($user['profile']['archived_classrooms'][(string) $classroomId]);
}
function classroom_delete_phrase(array $classroom): string {
    return 'SUDO DELETE ' . $classroom['name'];
}
function render_classroom_menu(array $classroom): void {
    ?>
    <details class="sidebar-class-menu"><summary aria-label="Options for <?php echo esc($classroom['name']); ?>"><?php echo nav_icon('more'); ?></summary><div>
        <a href="/QuizWeb/classroom_action.php?classroom_id=<?php echo (int) $classroom['id']; ?>&amp;operation=archive"><?php echo nav_icon('archive'); ?>Archive</a>
        <a class="class-delete-link" href="/QuizWeb/classroom_action.php?classroom_id=<?php echo (int) $classroom['id']; ?>&amp;operation=delete"><?php echo nav_icon('trash'); ?>Delete</a>
    </div></details>
    <?php
}

function change_student_classroom(PDO $pdo, array $user, int $classroomId, string $operation, string $confirmation): void {
    if (($user['role'] ?? '') !== 'student' || !in_array($operation, ['archive', 'restore', 'delete'], true)) throw new InvalidArgumentException('Classroom action unavailable.');
    $pdo->beginTransaction();
    try {
        $select = $pdo->prepare('SELECT * FROM classrooms WHERE id = ? FOR UPDATE');
        $select->execute([$classroomId]);
        $row = $select->fetch();
        if (!$row) throw new InvalidArgumentException('Classroom unavailable.');
        $classroom = hydrate_classroom($row);
        if (!classroom_belongs_to_user($classroom, $user)) throw new InvalidArgumentException('Classroom access denied.');
        if ($operation === 'delete' && $confirmation !== classroom_delete_phrase($classroom)) throw new InvalidArgumentException('The confirmation must match the displayed phrase exactly.');
        $select = $pdo->prepare('SELECT profile FROM users WHERE id = ? FOR UPDATE');
        $select->execute([$user['id']]);
        $profile = db_json_decode($select->fetchColumn());
        if ($operation === 'archive') $profile['archived_classrooms'][(string) $classroomId] = true;
        else unset($profile['archived_classrooms'][(string) $classroomId]);
        if ($operation === 'delete') {
            $ids = array_values(array_filter($classroom['student_ids'], fn($id) => (int) $id !== (int) $user['id']));
            $pdo->prepare('UPDATE classrooms SET student_ids = ?, updated_at = ? WHERE id = ?')->execute([db_json_encode($ids), now_iso(), $classroomId]);
        }
        $pdo->prepare('UPDATE users SET profile = ? WHERE id = ?')->execute([db_json_encode($profile), $user['id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
