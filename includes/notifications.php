<?php
// Build updates only from classrooms the signed-in user can access.
function navbar_notifications(array $user, array $classrooms): array
{
    $items = [];
    foreach ($classrooms as $classroom) {
        if (!classroom_belongs_to_user($classroom, $user) || classroom_is_archived($user, (int) $classroom['id'])) continue;
        $classId = (int) $classroom['id'];
        foreach (['announcements' => 'Announcement', 'quizzes' => 'New activity'] as $collection => $label) {
            foreach ($classroom[$collection] ?? [] as $record) {
                $at = $record['created_at'] ?? '';
                if (!$at || strtotime($at) === false) continue;
                $items[] = [
                    'id' => $classId . ':' . $collection . ':' . (int) $record['id'] . ':' . $at,
                    'title' => $record['title'] ?? $label,
                    'context' => ($collection === 'quizzes' ? ($record['activity_type'] ?? 'Quiz') . ' activity' : $label) . ' · ' . $classroom['name'],
                    'at' => $at,
                    'icon' => $collection === 'announcements' ? 'message' : 'document',
                    'href' => '/QuizWeb/classroom.php?id=' . $classId . '&tab=' . ($collection === 'announcements' ? 'materials' : 'quizzes'),
                ];
            }
        }
    }
    usort($items, static fn(array $a, array $b): int => (strtotime($b['at']) <=> strtotime($a['at'])) ?: strcmp($a['id'], $b['id']));
    return array_slice($items, 0, 20);
}

function render_navbar_notifications(array $user, array $items): void
{
    ?>
    <div class="navbar-notifications" data-notifications data-user-id="<?php echo (int) $user['id']; ?>">
        <button class="notification-toggle" type="button" aria-label="Notifications" title="Notifications" aria-expanded="false" aria-controls="notification-panel">
            <?php echo nav_icon('bell'); ?><span class="notification-badge" data-notification-count hidden></span>
        </button>
        <section class="notification-panel" id="notification-panel" aria-labelledby="notification-title" hidden>
            <div class="notification-heading"><h2 id="notification-title">Notifications</h2><button type="button" data-notifications-read <?php echo !$items ? 'disabled' : ''; ?>>Mark all as read</button></div>
            <p class="notification-summary" data-notification-summary role="status" aria-live="polite">Recent classroom updates</p>
            <?php if ($items): ?>
                <ul class="notification-list">
                    <?php foreach ($items as $item): ?>
                        <li><a class="notification-item" href="<?php echo esc($item['href']); ?>" data-notification-id="<?php echo esc($item['id']); ?>">
                            <span class="notification-symbol"><?php echo nav_icon($item['icon']); ?></span>
                            <span class="notification-copy"><strong><?php echo esc($item['title']); ?></strong><span><?php echo esc($item['context']); ?></span><time datetime="<?php echo esc($item['at']); ?>"><?php echo esc(format_date($item['at'])); ?></time></span>
                            <span class="notification-unread-dot" aria-label="Unread" hidden></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="notification-empty"><?php echo nav_icon('bell'); ?><strong>You're all caught up</strong><p>Classroom announcements and new quizzes will appear here.</p></div>
            <?php endif; ?>
        </section>
    </div>
    <?php
}
