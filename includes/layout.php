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
        $links[] = ['/QuizWeb/student_results.php', 'Track Scores'];
        $links[] = ['/QuizWeb/game_modes.php', 'Play Game Modes'];
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
        'Track Scores' => '<path d="M5 20V10m7 10V4m7 16V7"/><path d="M3 20h18"/>',
        'Play Game Modes' => '<path d="M8 8h8a5 5 0 0 1 5 5v3a3 3 0 0 1-5 2l-2-2h-4l-2 2a3 3 0 0 1-5-2v-3a5 5 0 0 1 5-5Z"/><path d="M8 11v4m-2-2h4m6 0h.01"/>',
        'archive' => '<path d="M4 7h16v14H4V7Zm-1-4h18v4H3V3Zm6 8h6"/>',
        'bookmark' => '<path d="M6 3h12v18l-6-4-6 4V3Z"/>',
        'message' => '<path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-3 2 1.8-5.2A8.5 8.5 0 1 1 21 11.5Z"/><path d="M7 10h8m-8 4h5"/>',
        'clip' => '<path d="m8 13 7-7a3 3 0 0 1 4 4L9 20a5 5 0 0 1-7-7L13 2m-8 14 10-10"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1"/><path d="m3 17 5-5 4 4 4-6 5 7"/>',
        'link' => '<path d="m10 13 4-4m-6 6-2 2a4 4 0 0 1-6-6l4-4a4 4 0 0 1 6 0m4 2 2-2a4 4 0 0 1 6 6l-4 4a4 4 0 0 1-6 0" transform="translate(1 0)"/>',
        'smile' => '<circle cx="12" cy="12" r="9"/><path d="M8 14a4 4 0 0 0 8 0M8 9h.01M16 9h.01"/>',
        'poll' => '<path d="M4 20V10h4v10m2 0V4h4v16m2 0v-7h4v7M2 20h20"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4 20-7ZM22 2 11 13"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
        'arrow-right' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
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
        <link rel="stylesheet" href="/QuizWeb/assets/css/refinements.css">
    </head>
    <body class="ui-refined <?php echo esc($pageClass . ($user && $showHeader && !str_contains($pageClass, 'game-page') ? ' has-classroom-sidebar' : '')); ?>">
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
            <?php if ($user): ?><button class="sidebar-toggle" type="button" aria-expanded="false" aria-controls="classroom-sidebar" aria-label="Open navigation"><?php echo nav_icon('menu'); ?></button><?php endif; ?>
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
        <aside class="classroom-sidebar" id="classroom-sidebar" aria-label="Classrooms">
            <?php $sidebarClassrooms = user_classrooms($user); ?>
            <?php if (($user['role'] ?? '') === 'student'): ?>
                <h2>Classrooms</h2>
                <details class="student-classes-dropdown" open>
                    <summary class="student-sidebar-heading"><?php echo nav_icon('Focus Practice'); ?><strong>My Classes</strong><?php echo nav_icon('chevron'); ?></summary>
                    <nav class="student-classroom-nav" aria-label="Your classrooms">
                        <a class="classroom-sidebar-link<?php echo current_path() === '/QuizWeb/dashboard.php' ? ' is-active' : ''; ?>" href="/QuizWeb/dashboard.php#joined-classrooms"><?php echo nav_icon('Dashboard'); ?><span>All Classes</span></a>
                        <?php foreach ($sidebarClassrooms as $sidebarIndex => $sidebarClassroom): ?>
                            <a class="classroom-sidebar-link student-class-link" href="/QuizWeb/classroom.php?id=<?php echo esc((string) $sidebarClassroom['id']); ?>">
                                <i class="student-class-dot dot-<?php echo esc((string) (($sidebarIndex % 3) + 1)); ?>" aria-hidden="true"></i><span><?php echo esc($sidebarClassroom['name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!$sidebarClassrooms): ?><p class="sidebar-empty">Join a class to see it here.</p><?php endif; ?>
                    </nav>
                </details>
                <nav class="student-sidebar-tools" aria-label="Student tools">
                    <a class="classroom-sidebar-link<?php echo current_path() === '/QuizWeb/archive.php' ? ' is-active' : ''; ?>" href="/QuizWeb/archive.php"><?php echo nav_icon('archive'); ?><span>Archive</span></a>
                    <a class="classroom-sidebar-link<?php echo current_path() === '/QuizWeb/saved.php' ? ' is-active' : ''; ?>" href="/QuizWeb/saved.php"><?php echo nav_icon('bookmark'); ?><span>Saved</span></a>
                    <a class="classroom-sidebar-link<?php echo current_path() === '/QuizWeb/student_results.php' ? ' is-active' : ''; ?>" href="/QuizWeb/student_results.php"><?php echo nav_icon('chart'); ?><span>Results</span></a>
                </nav>
            <?php else: ?>
            <h2>Classrooms</h2>
            <nav aria-label="Your classrooms">
                <?php foreach ($sidebarClassrooms as $sidebarClassroom): ?>
                    <?php $isCurrentClassroom = current_path() === '/QuizWeb/classroom.php' && (int) ($_GET['id'] ?? 0) === (int) $sidebarClassroom['id']; ?>
                    <a class="classroom-sidebar-link<?php echo $isCurrentClassroom ? ' is-active' : ''; ?>" href="/QuizWeb/classroom.php?id=<?php echo esc((string) $sidebarClassroom['id']); ?>" <?php echo $isCurrentClassroom ? 'aria-current="page"' : ''; ?>>
                        <?php echo nav_icon('Join Class'); ?><span><?php echo esc($sidebarClassroom['name']); ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (!$sidebarClassrooms): ?><p class="sidebar-empty"><?php echo $user['role'] === 'teacher' ? 'Your classrooms will appear here.' : 'Join a class to see it here.'; ?></p><?php endif; ?>
            </nav>
            <?php endif; ?>
        </aside>
        <?php endif; ?>
            <?php if ($flash): ?>
                <div class="flash flash-<?php echo esc($flash['type']); ?>" role="status">
                    <span class="flash-message"><?php echo esc($flash['message']); ?></span>
                </div>
            <?php endif; ?>
        <main class="page-shell">
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
    $_SESSION['chat_csrf'] = $_SESSION['chat_csrf'] ?? bin2hex(random_bytes(32));
    ?>
    <div class="messenger-dock" data-messenger-dock data-chat-csrf="<?php echo esc($_SESSION['chat_csrf']); ?>" data-default-chat-id="<?php echo esc((string) $defaultClassroomId); ?>" data-current-user-id="<?php echo esc((string) $user['id']); ?>">
        <button class="messenger-launcher" type="button" aria-expanded="false" aria-controls="messenger-panel" aria-label="Messages" title="Messages">
            <span class="messenger-launcher-icon" aria-hidden="true"><?php echo nav_icon('message'); ?></span>
            <span class="messenger-badge messenger-launcher-badge" data-messenger-badge hidden>0</span>
        </button>

        <section class="messenger-panel" id="messenger-panel" aria-hidden="true" hidden>
            <div class="messenger-shell">
                <button class="messenger-close" type="button" data-messenger-close aria-label="Close messenger"><?php echo nav_icon('close'); ?></button>
                <div class="messenger-shell-body">
                    <aside class="messenger-group-list" aria-label="Group chats">
                <div class="messenger-shell-header">
                    <div>
                        <h2><?php echo nav_icon('send'); ?> Messenger</h2>
                        <p>Classroom conversations in one place.</p>
                    </div>
                </div>
                        <label class="messenger-search"><?php echo nav_icon('search'); ?><input type="search" data-messenger-search placeholder="Search conversations..." aria-label="Search conversations"></label>
                        <div class="messenger-filters" aria-label="Filter conversations">
                            <button type="button" class="is-active" data-messenger-filter="all" aria-pressed="true">All</button>
                            <button type="button" data-messenger-filter="unread" aria-pressed="false">Unread</button>
                        </div>
                        <p class="messenger-search-empty" data-messenger-search-empty hidden>No matching conversations.</p>
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
                                        <span class="messenger-group-avatar" aria-hidden="true"><?php echo esc(strtoupper(substr(trim((string) $classroom['name']), 0, 1)) ?: 'C'); ?></span>
                                        <div>
                                            <span class="eyebrow"><?php echo esc($classroom['subject'] ?: 'Classroom'); ?></span>
                                            <h3><?php echo esc($classroom['name']); ?></h3>
                                        </div>
                                        <a class="messenger-open-link" aria-label="Open classroom" title="Open classroom" href="/QuizWeb/classroom.php?id=<?php echo esc((string) $classroom['id']); ?>"><?php echo nav_icon('arrow-right'); ?></a>
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
                                            <textarea name="chat_body" rows="1" maxlength="1500" placeholder="Type a message..."></textarea>
                                        </label>
                                        <div class="messenger-compose-actions">
                                            <button class="button button-primary" type="submit" aria-label="Send message" title="Send message"><?php echo nav_icon('send'); ?></button>
                                        </div>
                                        <div class="messenger-tools">
                                            <button type="button" data-chat-file="file"><?php echo nav_icon('clip'); ?> File</button>
                                            <button type="button" data-chat-file="image"><?php echo nav_icon('image'); ?> Image</button>
                                            <button type="button" data-chat-toggle="link" aria-expanded="false"><?php echo nav_icon('link'); ?> Link</button>
                                            <button type="button" data-chat-toggle="poll" aria-expanded="false"><?php echo nav_icon('poll'); ?> Poll</button>
                                            <button type="button" data-chat-toggle="emoji" aria-expanded="false" aria-label="Insert emoji"><?php echo nav_icon('smile'); ?></button>
                                        </div>
                                        <input type="file" data-chat-files multiple hidden>
                                        <div class="chat-selected-files" data-chat-selected hidden></div>
                                        <div class="chat-extra" data-chat-extra="link" hidden><label>Link URL<input type="url" name="link" placeholder="https://example.com"></label></div>
                                        <div class="chat-extra" data-chat-extra="poll" hidden><label>Poll question<input name="poll_question" maxlength="240" placeholder="What should we review next?"></label><label>Options (one per line)<textarea name="poll_options" rows="3" placeholder="Option one&#10;Option two"></textarea></label></div>
                                        <div class="chat-emoji-picker" data-chat-extra="emoji" hidden>
                                            <?php foreach (['😀', '😊', '👍', '❤️', '🎉', '👏', '🤔', '✅'] as $emoji): ?><button type="button" data-chat-emoji="<?php echo esc($emoji); ?>" aria-label="Insert <?php echo esc($emoji); ?>"><?php echo $emoji; ?></button><?php endforeach; ?>
                                        </div>
                                        <p class="chat-send-status" data-chat-status role="status"></p>
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

