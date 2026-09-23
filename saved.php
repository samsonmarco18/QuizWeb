<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_role('student');
$resources = [];
foreach (student_classrooms((int) $user['id']) as $classroom) {
    foreach (classroom_announcements($classroom) as $announcement) {
        foreach ($announcement['attachments'] ?? [] as $attachment) {
            $resources[] = ['classroom' => $classroom, 'announcement' => $announcement, 'attachment' => $attachment];
        }
    }
}
render_header('Saved Materials', 'student-page saved-page');
?>
<header class="page-heading"><div><span class="eyebrow">Learning resources</span><h1>Saved materials</h1><p>Files shared across your classrooms.</p></div></header>
<section class="glass panel compact-panel">
    <?php if ($resources): ?><div class="resource-list">
        <?php foreach ($resources as $resource): ?>
            <?php $url = '/QuizWeb/announcement_file.php?classroom_id=' . rawurlencode((string) $resource['classroom']['id']) . '&announcement_id=' . rawurlencode((string) $resource['announcement']['id']) . '&file=' . rawurlencode($resource['attachment']['stored_name']); ?>
            <article class="resource-row"><?php echo nav_icon('bookmark'); ?><div><strong><?php echo esc($resource['attachment']['original_name']); ?></strong><span><?php echo esc($resource['classroom']['subject'] . ' · ' . $resource['announcement']['title']); ?></span></div><a class="button button-secondary" href="<?php echo esc($url); ?>" target="_blank" rel="noopener">Open</a></article>
        <?php endforeach; ?>
    </div><?php else: ?><div class="empty-state"><?php echo nav_icon('bookmark'); ?><h2>No saved materials yet</h2><p>Files shared by your teachers will appear here.</p></div><?php endif; ?>
</section>
<?php render_footer(); ?>
