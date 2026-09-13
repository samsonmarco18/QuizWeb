<?php

require_once __DIR__ . '/includes/layout.php';

$user = require_login();
$errors = [];

if ($user['role'] === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_classroom') {
    $name = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $subject === '') {
        $errors[] = 'Classroom name and subject are required.';
    }

    if (!$errors) {
        $records = classrooms();
        $classroom = [
            'id' => next_id($records),
            'teacher_id' => $user['id'],
            'name' => $name,
            'subject' => $subject,
            'description' => $description,
            'code' => generate_join_code(),
            'student_ids' => [],
            'quizzes' => [],
            'announcements' => [],
            'chat_messages' => [],
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ];
        $records[] = $classroom;
        save_classrooms($records);
        flash_set('success', 'Classroom created. Share the join code with your students.');
        redirect('/QuizWeb/classroom.php?id=' . $classroom['id']);
    }
}

if ($user['role'] === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_mastery_demo') {
    $classroom = ensure_mastery_demo_classroom($user);
    flash_set('success', 'Sample Mastery Ladder quiz created. Open the classroom and test the level progression.');
    redirect('/QuizWeb/classroom.php?id=' . $classroom['id']);
}

render_header('Dashboard', 'dashboard-page');

if ($user['role'] === 'teacher') {
    $stats = teacher_dashboard_stats((int) $user['id']);
    $myClassrooms = teacher_classrooms((int) $user['id']);
    $learningProfile = null;
    $practiceQuestions = [];
} else {
    $stats = student_dashboard_stats((int) $user['id']);
    $myClassrooms = student_classrooms((int) $user['id']);
    $learningProfile = student_learning_profile((int) $user['id']);
    $practiceQuestions = learning_practice_questions($learningProfile);
}
?>

<section class="dashboard-hero glass">
    <div>
        <span class="eyebrow"><?php echo esc(ucfirst($user['role'])); ?> Command Center</span>
        <h1><?php echo esc('Hello, ' . $user['name']); ?></h1>
        <p class="lead">
            <?php if ($user['role'] === 'teacher'): ?>
                Create classrooms, build quiz games, and guide your students through an interactive learning arena.
            <?php else: ?>
                Join classrooms, play quiz games, and keep improving your scores across every challenge.
            <?php endif; ?>
        </p>
        <div class="feature-pills">
            <?php if ($user['role'] === 'teacher'): ?>
                <span>Build classrooms</span>
                <span>Post updates</span>
                <span>Launch quiz games</span>
            <?php else: ?>
                <span>Track scores</span>
                <span>Join class spaces</span>
                <span>Play game modes</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="hero-side-stack">
        <div class="stat-grid">
            <?php foreach ($stats as $label => $value): ?>
                <article class="stat-card">
                    <strong><?php echo esc((string) $value); ?></strong>
                    <span><?php echo esc(ucwords(str_replace('_', ' ', $label))); ?></span>
                </article>
            <?php endforeach; ?>
        </div>
        <article class="hero-note-card">
            <span class="eyebrow">Page focus</span>
            <h3><?php echo esc($user['role'] === 'teacher' ? 'Shape each class hub' : 'Stay in the learning loop'); ?></h3>
            <p><?php echo esc($user['role'] === 'teacher'
                ? 'This page is your launchpad for classrooms, quizzes, and class activity.'
                : 'This page keeps your joined classrooms, recent attempts, and next actions in one calm space.'); ?></p>
        </article>
    </div>
</section>

<?php if ($user['role'] === 'student'): ?>
    <section class="glass panel learning-coach-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Self-learning coach</span>
                <h2>Where to focus next</h2>
                <p class="muted"><?php echo esc($learningProfile['trend_message']); ?></p>
            </div>
            <?php if ($practiceQuestions): ?>
                <a class="button button-primary" href="/QuizWeb/practice.php">Start Focus Practice</a>
            <?php endif; ?>
        </div>

        <div class="learning-summary-grid">
            <article class="stat-card">
                <strong><?php echo esc((string) $learningProfile['overall_accuracy']); ?>%</strong>
                <span>Overall accuracy</span>
            </article>
            <article class="stat-card">
                <strong><?php echo esc((string) $learningProfile['questions_seen']); ?></strong>
                <span>Questions analyzed</span>
            </article>
            <article class="stat-card">
                <strong><?php echo esc((string) count($learningProfile['focus_items'])); ?></strong>
                <span>Focus items</span>
            </article>
        </div>

        <div class="learning-grid">
            <article class="learning-card">
                <h3>Weak areas</h3>
                <?php if ($learningProfile['focus_items']): ?>
                    <div class="learning-list">
                        <?php foreach (array_slice($learningProfile['focus_items'], 0, 3) as $item): ?>
                            <div class="learning-item">
                                <div>
                                    <strong><?php echo esc($item['level_label'] . ' - ' . $item['quiz_title']); ?></strong>
                                    <span><?php echo esc($item['prompt']); ?></span>
                                </div>
                                <div class="insight-meter" aria-label="<?php echo esc((string) $item['accuracy']); ?> percent accuracy">
                                    <span style="width: <?php echo esc((string) $item['accuracy']); ?>%"></span>
                                </div>
                                <small><?php echo esc($item['accuracy'] . '% accuracy after ' . $item['attempts'] . ' attempt' . ((int) $item['attempts'] === 1 ? '' : 's')); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">No weak area is visible yet. Finish more quiz games to build a sharper profile.</p>
                <?php endif; ?>
            </article>

            <article class="learning-card">
                <h3>Study plan</h3>
                <ol class="learning-steps">
                    <?php foreach ($learningProfile['plan_steps'] as $step): ?>
                        <li><?php echo esc($step); ?></li>
                    <?php endforeach; ?>
                </ol>
                <?php if ($learningProfile['weak_levels']): ?>
                    <div class="skill-pill-row">
                        <?php foreach ($learningProfile['weak_levels'] as $level): ?>
                            <span class="skill-pill"><?php echo esc($level['label'] . ': ' . $level['accuracy'] . '%'); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </div>
    </section>
<?php endif; ?>

<?php if ($user['role'] === 'teacher'): ?>
    <section class="panel-grid">
        <article class="glass panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">New classroom</span>
                    <h2>Create a class hub</h2>
                </div>
            </div>
            <form method="post" class="stack-form">
                <input type="hidden" name="action" value="create_classroom">
                <?php foreach ($errors as $error): ?>
                    <div class="inline-error"><?php echo esc($error); ?></div>
                <?php endforeach; ?>
                <label>
                    <span>Classroom Name</span>
                    <input type="text" name="name" required placeholder="Grade 10 Science">
                </label>
                <label>
                    <span>Subject</span>
                    <input type="text" name="subject" required placeholder="Earth and Life Science">
                </label>
                <label>
                    <span>Description</span>
                    <textarea name="description" rows="4" placeholder="Add a short classroom description"></textarea>
                </label>
                <button class="button button-primary" type="submit">Create Classroom</button>
            </form>
        </article>
        <article class="glass panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Game modes</span>
                    <h2>Seven editable formats</h2>
                </div>
            </div>
            <div class="mode-list">
                <?php foreach (game_modes() as $mode): ?>
                    <div class="mode-chip">
                        <span class="mode-icon"><?php echo esc($mode['icon']); ?></span>
                        <div>
                            <strong><?php echo esc($mode['label']); ?></strong>
                            <p><?php echo esc($mode['description']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="post" class="stack-form demo-form">
                <input type="hidden" name="action" value="create_mastery_demo">
                <button class="button button-secondary" type="submit">Create Sample Mastery Quiz</button>
            </form>
        </article>
    </section>
<?php else: ?>
    <section class="panel-grid">
        <article class="glass panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Join your teacher</span>
                    <h2>Enter a classroom code</h2>
                </div>
                <a class="button button-secondary" href="/QuizWeb/join.php">Join Class</a>
            </div>
            <p class="lead compact">Use the code your teacher shared to unlock new games and classroom quizzes.</p>
        </article>
        <article class="glass panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Recent progress</span>
                    <h2>Your latest runs</h2>
                </div>
            </div>
            <div class="recent-list">
                <?php $recentAttempts = array_slice(student_attempts((int) $user['id']), 0, 4); ?>
                <?php if ($recentAttempts): ?>
                    <?php foreach ($recentAttempts as $attempt): ?>
                        <div class="recent-item">
                            <strong><?php echo esc($attempt['quiz_title']); ?></strong>
                            <span><?php echo esc(percentage((int) $attempt['score'], (int) $attempt['max_score']) . '% score'); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="muted">No quiz attempts yet. Join a classroom and start playing.</p>
                <?php endif; ?>
            </div>
        </article>
    </section>
<?php endif; ?>

<section class="glass panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow"><?php echo esc($user['role'] === 'teacher' ? 'Your classrooms' : 'Joined classrooms'); ?></span>
            <h2><?php echo esc($user['role'] === 'teacher' ? 'Manage your spaces' : 'Keep learning'); ?></h2>
        </div>
    </div>

    <?php if ($myClassrooms): ?>
        <div class="classroom-grid">
            <?php foreach ($myClassrooms as $classroom): ?>
                <article class="classroom-card glass">
                    <div class="classroom-top">
                        <div>
                            <h3><?php echo esc($classroom['name']); ?></h3>
                            <span><?php echo esc($classroom['subject']); ?></span>
                        </div>
                        <span class="code-badge"><?php echo esc($classroom['code']); ?></span>
                    </div>
                    <p><?php echo esc($classroom['description'] ?: 'Interactive classroom ready for quizzes and game sessions.'); ?></p>
                    <div class="card-meta">
                        <span><?php echo esc(count($classroom['quizzes'] ?? []) . ' quizzes'); ?></span>
                        <span><?php echo esc(count($classroom['student_ids'] ?? []) . ' students'); ?></span>
                    </div>
                    <a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo esc((string) $classroom['id']); ?>">Open Classroom</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="muted"><?php echo esc($user['role'] === 'teacher' ? 'No classrooms yet. Create your first one above.' : 'You have not joined any classrooms yet.'); ?></p>
    <?php endif; ?>
</section>

<?php render_footer(); ?>
