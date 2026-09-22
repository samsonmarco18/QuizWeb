<?php

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/profile.php';

function nav_links(?array $user): array
{
    if (!$user) {
        return [
            ['/QuizWeb/login.php', 'Login'],
            ['/QuizWeb/register.php', 'Register'],
        ];
    }

    $links = [
        ['/QuizWeb/dashboard.php', 'Dashboard'],
    ];

    if ($user['role'] === 'student') {
        $links[] = ['/QuizWeb/join.php', 'Join Class'];
        $links[] = ['/QuizWeb/practice.php', 'Focus Practice'];
    }

    return $links;
}

function nav_icon(string $name): string
{
    $paths = [
        'Dashboard' => '<path d="m3 10 9-7 9 7v10H14v-6h-4v6H5V10"/>',
        'Join Class' => '<circle cx="9" cy="7" r="3"/><path d="M2 21v-3a7 7 0 0 1 14 0v3M16 4a3 3 0 0 1 0 6m3 4a6 6 0 0 1 3 5v2"/>',
        'Focus Practice' => '<path d="M12 5v16M12 5C8 2 5 2 2 4v16c3-2 6-2 10 1 4-3 7-3 10-1V4c-3-2-6-2-10 1Z"/>',
        'user' => '<circle cx="12" cy="7" r="4"/><path d="M4 22v-3a8 8 0 0 1 16 0v3Z"/>',
        'settings' => '<circle cx="12" cy="7" r="4"/><path d="M5 22v-3a7 7 0 0 1 14 0v3"/>',
        'moon' => '<path d="M21 13A9 9 0 0 1 11 3a9 9 0 1 0 10 10Z"/>',
        'logout' => '<path d="M10 3H4v18h6m5-14 5 5-5 5M8 12h12"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'chart' => '<path d="M5 20V10m7 10V4m7 16V7" stroke-width="3"/>',
        'trophy' => '<path d="M8 3h8v6a4 4 0 0 1-8 0V3Zm0 2H4v2a4 4 0 0 0 4 4m8-6h4v2a4 4 0 0 1-4 4m-4 2v5m-4 3h8m-6-3h4"/>',
    ];
    return '<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['user']) . '</svg>';
}

function current_path(): string
{
    return $_SERVER['PHP_SELF'] ?? '';
}

function nav_link_class(string $href): string
{
    $classes = ['nav-link'];
    $currentPath = current_path();
    $targetPath = parse_url($href, PHP_URL_PATH) ?: $href;

    if ($currentPath === $targetPath) {
        $classes[] = 'is-active';
    }

    return implode(' ', $classes);
}

function render_header(string $title, string $pageClass = ''): void
{
    $user = current_user();
    $flash = flash_get();
    $showHeader = !str_contains($pageClass, 'auth-page');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc($title . ' | ' . APP_NAME); ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="/QuizWeb/assets/css/site.css">
    </head>
    <body class="<?php echo esc($pageClass . ($user && $showHeader && !str_contains($pageClass, 'game-page') ? ' has-classroom-sidebar' : '')); ?>">
        <script>try { document.body.classList.toggle('theme-dark', localStorage.getItem('quizweb-theme') === 'dark'); } catch (error) {}</script>
        <div class="ambient ambient-one"></div>
        <div class="ambient ambient-two"></div>
        <div class="ambient ambient-three"></div>
        <div class="screen-mesh"></div>
        <?php if (!$showHeader): ?>
        <button class="auth-theme-toggle" id="theme-toggle" type="button" aria-pressed="false" aria-label="Dark mode" title="Toggle light / dark mode">
            <?php echo nav_icon('moon'); ?><span class="theme-toggle-label">Dark Mode</span>
        </button>
        <?php endif; ?>
        <?php if ($showHeader): ?>
        <header class="site-header glass">
            <a class="brand" href="<?php echo $user ? '/QuizWeb/dashboard.php' : '/QuizWeb/login.php'; ?>">
                <span class="brand-badge brand-badge-minimal">CH</span>
                <span class="brand-copy">
                    <strong><?php echo esc(APP_NAME); ?></strong>
                </span>
            </a>
            <div class="header-actions">
                <nav class="top-nav" aria-label="Main navigation">
                    <?php foreach (nav_links($user) as [$href, $label]): ?>
                        <a class="<?php echo esc(nav_link_class($href)); ?>" href="<?php echo esc($href); ?>" <?php echo current_path() === $href ? 'aria-current="page"' : ''; ?>><?php echo nav_icon($label); ?><span><?php echo esc($label); ?></span></a>
                    <?php endforeach; ?>
                </nav>
                <?php if ($user): ?>
                    <div class="account-menu">
                        <button class="account-toggle" type="button" aria-expanded="false" aria-controls="account-dropdown">
                            <span class="account-avatar"><?php echo nav_icon('user'); ?></span>
                            <strong><?php echo esc($user['name']); ?></strong>
                            <?php echo nav_icon('chevron'); ?>
                        </button>
                        <div class="account-dropdown" id="account-dropdown" hidden>
                            <div class="account-summary"><span class="account-avatar"><?php echo nav_icon('user'); ?></span><div><strong><?php echo esc($user['name']); ?></strong><small><?php echo esc(ucfirst($user['role'])); ?></small></div></div>
                            <button class="account-item" type="button" data-profile-open><?php echo nav_icon('settings'); ?><span>Profile Settings</span></button>
                            <button class="account-item theme-toggle" id="theme-toggle" type="button" aria-pressed="false"><?php echo nav_icon('moon'); ?><span class="theme-toggle-label">Dark Mode</span><span class="theme-switch" aria-hidden="true"></span></button>
                            <a class="account-item" href="/QuizWeb/logout.php"><?php echo nav_icon('logout'); ?><span>Logout</span></a>
                        </div>
                    </div>
                <?php else: ?>
                <button class="theme-toggle" id="theme-toggle" type="button" aria-pressed="false">
                    <span class="theme-toggle-dot"></span>
                    <span class="theme-toggle-label">Dark Mode</span>
                </button>
                <?php endif; ?>
            </div>
        </header>
        <?php if ($user): ?>
        <dialog class="profile-dialog" id="profile-dialog" aria-labelledby="profile-title">
            <h2 id="profile-title">Profile Settings</h2>
            <dl><dt>Name</dt><dd><?php echo esc($user['name']); ?></dd><dt>Email</dt><dd><?php echo esc($user['email'] ?? ''); ?></dd><dt>Role</dt><dd><?php echo esc(ucfirst($user['role'])); ?></dd></dl>
            <dl class="profile-details"><?php foreach (profile_labels() as $field => $label): ?><dt><?php echo esc($label); ?></dt><dd><?php echo esc(($user['profile'][$field] ?? '') ?: 'Not provided'); ?></dd><?php endforeach; ?></dl>
            <a class="button button-secondary" href="/QuizWeb/profile.php">Edit Profile</a>
            <form method="dialog"><button class="button button-primary">Close</button></form>
        </dialog>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($user && $showHeader && !str_contains($pageClass, 'game-page')): ?>
        <aside class="classroom-sidebar" aria-label="Classrooms">
            <h2>Classrooms</h2>
            <nav aria-label="Your classrooms">
                <?php $sidebarClassrooms = user_classrooms($user); ?>
                <?php foreach ($sidebarClassrooms as $sidebarClassroom): ?>
                    <?php $isCurrentClassroom = current_path() === '/QuizWeb/classroom.php' && (int) ($_GET['id'] ?? 0) === (int) $sidebarClassroom['id']; ?>
                    <a class="classroom-sidebar-link<?php echo $isCurrentClassroom ? ' is-active' : ''; ?>" href="/QuizWeb/classroom.php?id=<?php echo esc((string) $sidebarClassroom['id']); ?>" <?php echo $isCurrentClassroom ? 'aria-current="page"' : ''; ?>>
                        <?php echo nav_icon('Join Class'); ?><span><?php echo esc($sidebarClassroom['name']); ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (!$sidebarClassrooms): ?><p class="sidebar-empty"><?php echo $user['role'] === 'teacher' ? 'Your classrooms will appear here.' : 'Join a class to see it here.'; ?></p><?php endif; ?>
            </nav>
        </aside>
        <?php endif; ?>
        <main class="page-shell">
            <?php if ($flash): ?>
                <div class="flash flash-<?php echo esc($flash['type']); ?>">
                    <?php echo esc($flash['message']); ?>
                </div>
            <?php endif; ?>
<?php
}

function render_messenger_dock(): void
{
    $user = current_user();

    if (!$user) {
        return;
    }

    $classrooms = user_classrooms($user);
    usort($classrooms, function (array $a, array $b) {
        return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
    });
    $defaultClassroomId = (int) ($GLOBALS['quizweb_current_classroom_id'] ?? ($classrooms[0]['id'] ?? 0));
    $currentRequest = safe_local_path(current_request_uri(), '/QuizWeb/dashboard.php');
    ?>
    <div class="messenger-dock" data-messenger-dock data-default-chat-id="<?php echo esc((string) $defaultClassroomId); ?>" data-current-user-id="<?php echo esc((string) $user['id']); ?>">
        <button class="messenger-launcher" type="button" aria-expanded="false" aria-controls="messenger-panel" aria-label="Open messenger">
            <span class="messenger-launcher-icon messenger-bubble-icon" aria-hidden="true">
                <span class="messenger-bubble-dot"></span>
                <span class="messenger-bubble-dot"></span>
                <span class="messenger-bubble-dot"></span>
            </span>
            <span class="messenger-badge messenger-launcher-badge" data-messenger-badge hidden>0</span>
        </button>

        <section class="messenger-panel" id="messenger-panel" aria-hidden="true" hidden>
            <div class="messenger-shell">
                <div class="messenger-shell-header">
                    <div>
                        <span class="eyebrow">Messenger</span>
                        <h2>Group chats</h2>
                        <p>Tap a classroom to jump straight into the conversation.</p>
                    </div>
                    <button class="messenger-close" type="button" data-messenger-close aria-label="Close messenger">&times;</button>
                </div>

                <div class="messenger-shell-body">
                    <aside class="messenger-group-list" aria-label="Group chats">
                        <?php if ($classrooms): ?>
                            <?php foreach ($classrooms as $index => $classroom): ?>
                                <?php
                                $latestMessage = classroom_latest_chat_message($classroom);
                                $isActive = $defaultClassroomId
                                    ? (int) $classroom['id'] === $defaultClassroomId
                                    : $index === 0;
                                ?>
                                <button
                                    class="messenger-group-item <?php echo $isActive ? 'is-active' : ''; ?>"
                                    type="button"
                                    data-messenger-target="messenger-thread-<?php echo esc((string) $classroom['id']); ?>"
                                    data-chat-id="<?php echo esc((string) $classroom['id']); ?>"
                                    data-latest-at="<?php echo esc((string) ($latestMessage['created_at'] ?? '')); ?>"
                                    data-latest-user-id="<?php echo esc((string) ($latestMessage['user_id'] ?? 0)); ?>"
                                >
                                    <span class="messenger-group-avatar"><?php echo esc(strtoupper(substr(trim((string) $classroom['name']), 0, 1)) ?: 'C'); ?></span>
                                    <span class="messenger-group-copy">
                                        <strong><?php echo esc($classroom['name']); ?></strong>
                                        <small><?php echo esc($classroom['subject'] ?: 'Classroom'); ?></small>
                                        <span><?php echo esc(chat_message_excerpt($latestMessage)); ?></span>
                                    </span>
                                    <time><?php echo esc($latestMessage ? format_date($latestMessage['created_at'] ?? now_iso()) : ''); ?></time>
                                </button>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="messenger-empty">
                                <strong>No group chats yet</strong>
                                <p>Join or create a classroom to start messaging.</p>
                            </div>
                        <?php endif; ?>
                    </aside>

                    <div class="messenger-thread-stage">
                        <?php if ($classrooms): ?>
                            <?php foreach ($classrooms as $index => $classroom): ?>
                                <?php
                                $latestMessage = classroom_latest_chat_message($classroom);
                                $messages = array_slice(classroom_chat_messages($classroom), -20);
                                $isActive = $defaultClassroomId
                                    ? (int) $classroom['id'] === $defaultClassroomId
                                    : $index === 0;
                                ?>
                                <article class="messenger-thread <?php echo $isActive ? 'is-active' : ''; ?>" data-messenger-thread data-chat-id="<?php echo esc((string) $classroom['id']); ?>" data-latest-at="<?php echo esc((string) ($latestMessage['created_at'] ?? '')); ?>" data-latest-user-id="<?php echo esc((string) ($latestMessage['user_id'] ?? 0)); ?>" id="messenger-thread-<?php echo esc((string) $classroom['id']); ?>">
                                    <div class="messenger-thread-top">
                                        <div>
                                            <span class="eyebrow"><?php echo esc($classroom['subject'] ?: 'Classroom'); ?></span>
                                            <h3><?php echo esc($classroom['name']); ?></h3>
                                        </div>
                                        <a class="messenger-open-link" href="/QuizWeb/classroom.php?id=<?php echo esc((string) $classroom['id']); ?>">Open classroom</a>
                                    </div>

                                    <div class="messenger-thread-messages">
                                        <?php if ($messages): ?>
                                            <?php foreach ($messages as $message): ?>
                                                <?php
                                                $isSelf = (int) ($message['user_id'] ?? 0) === (int) $user['id'];
                                                $isTeacher = ($message['user_role'] ?? '') === 'teacher';
                                                $name = $message['user_name'] ?? 'Member';
                                                $role = role_label((string) ($message['user_role'] ?? 'student'));
                                                $trimmedName = trim($name);
                                                $initial = function_exists('mb_substr')
                                                    ? mb_substr($trimmedName, 0, 1)
                                                    : substr($trimmedName, 0, 1);
                                                $initial = strtoupper($initial);
                                                ?>
                                                <div class="messenger-message <?php echo $isSelf ? 'is-self' : ($isTeacher ? 'is-teacher' : 'is-student'); ?>">
                                                    <span class="messenger-message-avatar" aria-hidden="true"><?php echo esc($initial ?: 'M'); ?></span>
                                                    <div class="messenger-message-bubble">
                                                        <div class="messenger-message-meta">
                                                            <div>
                                                                <strong><?php echo esc($isSelf ? 'You' : $name); ?></strong>
                                                                <span class="role-chip <?php echo $isTeacher ? 'role-chip-teacher' : 'role-chip-student'; ?>"><?php echo esc($role); ?></span>
                                                            </div>
                                                            <time datetime="<?php echo esc($message['created_at'] ?? now_iso()); ?>"><?php echo esc(format_date($message['created_at'] ?? now_iso())); ?></time>
                                                        </div>
                                                        <p><?php echo nl2br(esc($message['body'] ?? '')); ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="messenger-thread-empty">
                                                <strong>Say hello</strong>
                                                <p>This classroom is ready for the first message.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <form class="messenger-compose" method="post" action="/QuizWeb/classroom.php?id=<?php echo esc((string) $classroom['id']); ?>">
                                        <input type="hidden" name="action" value="post_chat_message">
                                        <input type="hidden" name="return_url" value="<?php echo esc($currentRequest); ?>">
                                        <label>
                                            <span>Message</span>
                                            <textarea name="chat_body" rows="2" maxlength="1500" placeholder="Type a message to the class..."></textarea>
                                        </label>
                                        <div class="messenger-compose-actions">
                                            <p>Posts to this classroom chat.</p>
                                            <button class="button button-primary" type="submit">Send</button>
                                        </div>
                                    </form>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="messenger-thread is-active messenger-thread-empty-state">
                                <strong>No classrooms yet</strong>
                                <p>Create or join a classroom to unlock the messenger.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <?php
}

function render_footer(array $scripts = []): void
{
    ?>
        </main>
        <?php render_messenger_dock(); ?>
        <script src="/QuizWeb/assets/js/site.js"></script>
        <?php foreach ($scripts as $script): ?>
            <script src="<?php echo esc($script); ?>"></script>
        <?php endforeach; ?>
    </body>
    </html>
    <?php
}

