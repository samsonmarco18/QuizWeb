<?php

require_once __DIR__ . '/app.php';

function nav_links(?array $user): array
{
    if (!$user) {
        return [
            ['/QuizWeb/index.php', 'Home'],
            ['/QuizWeb/login.php', 'Login'],
            ['/QuizWeb/register.php', 'Register'],
        ];
    }

    $links = [
        ['/QuizWeb/index.php', 'Home'],
        ['/QuizWeb/dashboard.php', 'Dashboard'],
    ];

    if ($user['role'] === 'student') {
        $links[] = ['/QuizWeb/join.php', 'Join Class'];
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
        <header class="site-header glass">
            <a class="brand" href="/QuizWeb/index.php">
                <span class="brand-badge">BB</span>
                <span class="brand-copy">
                    <strong><?php echo esc(APP_NAME); ?></strong>
                    <small>Cozy quiz classrooms with playful momentum</small>
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
                <?php endif; ?>
                <button class="theme-toggle" id="theme-toggle" type="button" aria-pressed="false">
                    <span class="theme-toggle-dot"></span>
                    <span class="theme-toggle-label">Dark Mode</span>
                </button>
            </div>
        </header>
        <main class="page-shell">
            <?php if ($flash): ?>
                <div class="flash flash-<?php echo esc($flash['type']); ?>">
                    <?php echo esc($flash['message']); ?>
                </div>
            <?php endif; ?>
    <?php
}

function render_footer(array $scripts = []): void
{
    ?>
        </main>
        <script src="/QuizWeb/assets/js/site.js"></script>
        <?php foreach ($scripts as $script): ?>
            <script src="<?php echo esc($script); ?>"></script>
        <?php endforeach; ?>
    </body>
    </html>
    <?php
}
