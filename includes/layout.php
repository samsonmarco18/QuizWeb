<?php

require_once __DIR__ . '/app.php';

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

    $links[] = ['/QuizWeb/logout.php', 'Logout'];

    return $links;
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
    <body class="<?php echo esc($pageClass); ?>">
        <div class="ambient ambient-one"></div>
        <div class="ambient ambient-two"></div>
        <div class="ambient ambient-three"></div>
        <div class="screen-mesh"></div>
        <?php if ($showHeader): ?>
        <header class="site-header glass">
            <a class="brand" href="<?php echo $user ? '/QuizWeb/dashboard.php' : '/QuizWeb/login.php'; ?>">
                <span class="brand-badge brand-badge-minimal">CH</span>
                <span class="brand-copy">
                    <strong><?php echo esc(APP_NAME); ?></strong>
                    <small>Minimal classroom quiz space</small>
                </span>
            </a>
            <div class="header-actions">
                <nav class="top-nav">
                    <?php foreach (nav_links($user) as [$href, $label]): ?>
                        <a class="<?php echo esc(nav_link_class($href)); ?>" href="<?php echo esc($href); ?>"><?php echo esc($label); ?></a>
                    <?php endforeach; ?>
                </nav>
                <?php if ($user): ?>
                    <div class="user-chip">
                        <span class="user-chip-role"><?php echo esc(ucfirst($user['role'])); ?></span>
                        <strong><?php echo esc($user['name']); ?></strong>
                    </div>
                    <button class="messenger-nav-button" type="button" data-messenger-nav-toggle aria-label="Open messenger" aria-expanded="false">
                        <span class="messenger-nav-icon messenger-bubble-icon" aria-hidden="true">
                            <span class="messenger-bubble-dot"></span>
                            <span class="messenger-bubble-dot"></span>
                            <span class="messenger-bubble-dot"></span>
                        </span>
                        <span class="messenger-badge messenger-nav-badge" data-messenger-badge hidden>0</span>
                    </button>
                <?php endif; ?>
                <button class="theme-toggle" id="theme-toggle" type="button" aria-pressed="false">
                    <span class="theme-toggle-dot"></span>
                    <span class="theme-toggle-label">Dark Mode</span>
                </button>
            </div>
        </header>
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

