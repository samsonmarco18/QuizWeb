<?php

require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) {
    redirect('/QuizWeb/dashboard.php');
}

render_header('Home', 'landing-page');
?>

<section class="hero-grid">
    <div class="hero-copy">
        <span class="eyebrow">Cool classrooms, fast gameplay, full control</span>
        <h1>Build a quiz arena your students will actually want to play.</h1>
        <p class="lead">
            Teachers can create classrooms, share join codes, and fully edit quiz questions and answers across
            five game modes. Students can join instantly, compete in animated quiz challenges, and keep track of scores.
        </p>
        <div class="cta-row">
            <a class="button button-primary" href="/QuizWeb/register.php">Start as Teacher</a>
            <a class="button button-secondary" href="/QuizWeb/login.php">Student Login</a>
        </div>
        <div class="feature-pills">
            <span>5 editable games</span>
            <span>Teacher + student roles</span>
            <span>Classroom join codes</span>
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
            <article class="metric-pill">
                <strong>1</strong>
                <span>shared class hub</span>
            </article>
        </div>
    </div>
    <div class="hero-panel glass">
        <div class="showcase-window">
            <div class="showcase-row">
                <div>
                    <span class="showcase-badge">Live Classroom Flow</span>
                    <h2>From announcement to leaderboard in one space</h2>
                </div>
                <span class="code-badge">PX-204</span>
            </div>
            <div class="showcase-rail">
                <article class="showcase-module">
                    <strong>Morning Brief</strong>
                    <p>Teachers post reminders, modules, images, and files to the class feed.</p>
                </article>
                <article class="showcase-module">
                    <strong>Quiz Launch</strong>
                    <p>Students jump from the feed into a game mode without leaving the classroom page.</p>
                </article>
                <article class="showcase-module">
                    <strong>Instant Results</strong>
                    <p>Attempts and scores flow back into the same class space for fast review.</p>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="info-grid">
    <article class="glass info-card">
        <h2>Teacher Control</h2>
        <p>Create classrooms, design quizzes, edit every question, choose correct answers, and launch multiple game styles from one dashboard.</p>
    </article>
    <article class="glass info-card">
        <h2>Student Friendly</h2>
        <p>Students join with a code, see their classes instantly, and play responsive quiz games that work on desktop and mobile.</p>
    </article>
    <article class="glass info-card">
        <h2>Gamified Design</h2>
        <p>Moving backgrounds, glowing controls, score tracking, and vibrant cool-tone visuals give the whole site a playful classroom feel.</p>
    </article>
</section>

<section class="story-grid">
    <article class="glass story-card">
        <span class="eyebrow">Teacher workflow</span>
        <h2>Create, announce, launch</h2>
        <p class="lead compact">Set up a room, post a module or reminder, then publish interactive quizzes without juggling multiple tools.</p>
    </article>
    <article class="glass story-card">
        <span class="eyebrow">Student workflow</span>
        <h2>Join, play, improve</h2>
        <p class="lead compact">Students land in a cleaner classroom hub with clearer actions, richer feedback, and less clutter on every screen.</p>
    </article>
</section>

<?php render_footer(); ?>
