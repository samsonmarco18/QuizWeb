<?php

require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) {
    redirect('/QuizWeb/dashboard.php');
}

render_header('Home', 'landing-page');
?>

<section class="hero-grid landing-hero hero-shell" id="hero">
    <div class="hero-copy hero-copy-wide">
        <span class="eyebrow">CHALK classroom hub</span>
        <h1>Unleash better classroom play.</h1>
        <p class="lead">
            Build rooms, launch quizzes, and keep scores in one clean flow.
        </p>
        <div class="cta-row">
            <a class="button button-primary" href="/QuizWeb/register.php">Get Started</a>
            <a class="button button-secondary" href="/QuizWeb/login.php">Learn More</a>
        </div>
        <div class="hero-metrics">
            <article class="metric-pill">
                <strong>5</strong>
                <span>game modes</span>
            </article>
            <article class="metric-pill">
                <strong>2</strong>
                <span>user roles</span>
            </article>
        </div>
    </div>

    <div class="hero-center">
        <div class="hero-art" aria-hidden="true">
            <div class="hero-art-ring hero-art-ring-one"></div>
            <div class="hero-art-ring hero-art-ring-two"></div>
            <div class="hero-art-ring hero-art-ring-three"></div>
            <div class="hero-art-core"></div>
            <div class="hero-art-sphere hero-art-sphere-one"></div>
            <div class="hero-art-sphere hero-art-sphere-two"></div>
            <div class="hero-art-sphere hero-art-sphere-three"></div>
        </div>
    </div>

    <aside class="hero-panel glass hero-side">
        <article class="hero-side-card hero-side-feature">
            <strong>Fresh layout</strong>
            <p>Clean panels, simple spacing, and a stronger visual focus.</p>
        </article>

        <div class="hero-side-list">
            <span>Easy classroom setup</span>
            <span>Quiz launch flow</span>
            <span>Green gradient theme</span>
            <span>Responsive cards</span>
        </div>

        <article class="hero-side-thumbnail">
            <div class="hero-side-thumb-art"></div>
        </article>
    </aside>
</section>

<?php render_footer(); ?>
