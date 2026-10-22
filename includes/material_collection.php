<?php
require_once __DIR__ . '/materials.php';
$materialClassrooms = student_classrooms((int) $user['id'], true);
handle_material_action($user, $materialClassrooms);
$resources = [];
foreach ($materialClassrooms as $classroom) {
    foreach (classroom_announcements($classroom) as $announcement) {
        $state = material_state($user, (int) $classroom['id'], (string) $announcement['id']);
        if (empty($state[$collection === 'archive' ? 'archived' : 'saved'])) continue;
        $resources[] = ['classroom' => $classroom, 'announcement' => $announcement];
    }
}
$title = $collection === 'archive' ? 'Archived materials' : 'Saved materials';
render_header($title, 'student-page ' . $collection . '-page');
?>
<header class="page-heading"><div><span class="eyebrow">Learning resources</span><h1><?php echo esc($title); ?></h1><p><?php echo $collection === 'archive' ? 'Materials you archived. Restore them to your classroom feed anytime.' : 'Materials you saved across your classrooms.'; ?></p></div></header>
<?php if ($collection === 'archive'): ?>
<section class="glass panel"><h2>Archived classes</h2><div class="resource-list">
    <?php $archivedClasses = array_filter($materialClassrooms, fn($class) => classroom_is_archived($user, (int) $class['id'])); ?>
    <?php foreach ($archivedClasses as $archivedClass): ?><article class="resource-row"><?php echo nav_icon('archive'); ?><div><strong><?php echo esc($archivedClass['name']); ?></strong><span>Notifications paused</span></div><a class="button button-secondary" href="/QuizWeb/classroom_action.php?classroom_id=<?php echo (int) $archivedClass['id']; ?>&amp;operation=restore">Restore</a></article><?php endforeach; ?>
    <?php if (!$archivedClasses): ?><p class="muted">No archived classes.</p><?php endif; ?>
</div></section>
<h2>Archived materials</h2>
<?php endif; ?>
<section class="glass panel compact-panel">
<?php if ($resources): ?><div class="newsfeed-list">
    <?php foreach ($resources as ['classroom' => $classroom, 'announcement' => $announcement]): ?>
    <article class="announcement-card">
        <div class="announcement-meta"><div><strong><?php echo esc($announcement['title']); ?></strong><span><?php echo esc($classroom['name']); ?></span></div><a href="/QuizWeb/classroom.php?id=<?php echo (int) $classroom['id']; ?>&amp;tab=materials">View classroom</a></div>
        <?php render_material_actions($user, $classroom, $announcement, $collection); ?>
        <?php if (!empty($announcement['body'])): ?><p class="announcement-body"><?php echo nl2br(esc($announcement['body'])); ?></p><?php endif; ?>
        <?php foreach ($announcement['attachments'] ?? [] as $attachment): ?>
            <?php $url = '/QuizWeb/announcement_file.php?classroom_id=' . (int) $classroom['id'] . '&announcement_id=' . rawurlencode((string) $announcement['id']) . '&file=' . rawurlencode($attachment['stored_name']); ?>
            <a class="attachment-chip" href="<?php echo esc($url); ?>" target="_blank" rel="noopener"><span><?php echo esc($attachment['original_name']); ?></span><small><?php echo esc(format_bytes((int) ($attachment['size'] ?? 0))); ?></small></a>
        <?php endforeach; ?>
    </article>
    <?php endforeach; ?>
</div><?php else: ?><div class="empty-state"><?php echo nav_icon($collection === 'archive' ? 'archive' : 'bookmark'); ?><h2>No <?php echo $collection === 'archive' ? 'archived' : 'saved'; ?> materials yet</h2><p>Use <?php echo $collection === 'archive' ? 'Archive' : 'Save'; ?> on a post in your classroom's Materials tab.</p><a class="button button-secondary" href="/QuizWeb/classes.php">View Classes</a></div><?php endif; ?>
</section>
<?php render_footer(); ?>
