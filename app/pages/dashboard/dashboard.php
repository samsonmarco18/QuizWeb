<?php

require_once dirname(__DIR__, 3) . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/scripts/seed_sample_data.php';

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

render_header('Dashboard', 'dashboard-page ' . ($user['role'] === 'student' ? 'student-dashboard' : 'teacher-dashboard'));

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
    $recentAttempts = array_slice(student_attempts((int) $user['id']), 0, 6);
    $dashboardLeaderboard = student_dashboard_leaderboard((int) $user['id']);
    $upcomingDeadlines = [];
    foreach ($myClassrooms as $classroom) {
        foreach ($classroom['quizzes'] ?? [] as $quiz) {
            if (empty($quiz['due_at'])) continue;
            $upcomingDeadlines[] = ['classroom' => $classroom, 'quiz' => $quiz];
        }
    }
    usort($upcomingDeadlines, fn(array $a, array $b) => strcmp($a['quiz']['due_at'], $b['quiz']['due_at']));
}
?>

<section class="dashboard-hero dashboard-overview">
    <div>
        <span class="eyebrow"><?php echo esc($user['role'] === 'student' ? 'Student Dashboard' : 'Teacher Command Center'); ?></span>
        <h1><?php echo esc('Hello, ' . $user['name']); ?><?php if ($user['role'] === 'student'): ?> <span aria-hidden="true">👋</span><?php endif; ?></h1>
        <p class="lead">
            <?php if ($user['role'] === 'teacher'): ?>
                Create classrooms, build quiz games, and guide your students through an interactive learning arena.
            <?php else: ?>
                Join classrooms, play quiz games, and keep improving your scores across every challenge.
            <?php endif; ?>
        </p>
    </div>
    <div class="hero-side-stack">
        <div class="stat-grid">
            <?php foreach ($stats as $label => $value): ?>
                <article class="stat-card">
                    <span class="dashboard-stat-icon" aria-hidden="true"><?php echo nav_icon($label === 'classrooms' ? 'Focus Practice' : ($label === 'average_score' ? 'chart' : ($label === 'students' ? 'Join Class' : 'chart'))); ?></span>
                    <strong><?php echo esc((string) $value . ($label === 'average_score' ? '%' : '')); ?></strong>
                    <span><?php echo esc($user['role'] === 'student' ? ($label === 'classrooms' ? 'Enrolled Classes' : ($label === 'attempts' ? 'Quizzes Taken' : 'Average Score')) : ucwords(str_replace('_', ' ', $label))); ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($user['role'] === 'student'): ?>
    <div class="dashboard-primary-grid">
    <section class="glass panel dashboard-deadlines-panel">
        <div class="section-heading">
            <div><span class="eyebrow">Schedule</span><h2>Upcoming deadlines</h2></div>
            <a class="button button-secondary" href="#joined-classrooms">View Classes</a>
        </div>
        <div class="dashboard-deadline-list">
            <?php foreach (array_slice($upcomingDeadlines, 0, 4) as $deadline): ?>
                <?php $dueTime = strtotime($deadline['quiz']['due_at']); ?>
                <?php
                $deadlineAttempt = latest_attempt_for_quiz((int) $user['id'], (int) $deadline['classroom']['id'], (int) $deadline['quiz']['id']);
                $deadlineStatus = $deadlineAttempt ? 'completed' : ($dueTime < time() ? 'overdue' : ($dueTime <= strtotime('+2 days') ? 'due-soon' : 'upcoming'));
                $deadlineLabel = ['completed' => 'Completed', 'overdue' => 'Overdue', 'due-soon' => 'Due Soon', 'upcoming' => 'Upcoming'][$deadlineStatus];
                ?>
                <article class="dashboard-deadline-row">
                    <time datetime="<?php echo esc($deadline['quiz']['due_at']); ?>"><strong><?php echo esc(strtoupper(date('M', $dueTime))); ?></strong><span><?php echo esc(date('d', $dueTime)); ?></span></time>
                    <div><strong><?php echo esc($deadline['quiz']['title']); ?></strong><span><?php echo esc($deadline['classroom']['subject'] . ' · ' . classroom_teacher_name($deadline['classroom'])); ?></span></div>
                    <small><?php echo esc(date('D, g:i A', $dueTime)); ?></small>
                    <span class="deadline-status status-<?php echo esc($deadlineStatus); ?>"><?php echo esc($deadlineLabel); ?></span>
                </article>
            <?php endforeach; ?>
            <?php if (!$upcomingDeadlines): ?><p class="muted">No quiz or assignment deadlines have been posted yet.</p><?php endif; ?>
        </div>
    </section>
    <section class="glass panel dashboard-continue-panel">
        <div class="section-heading"><div><span class="eyebrow">Continue learning</span><h2>Your active classes</h2></div><a class="button button-secondary" href="/QuizWeb/game_modes.php">View All</a></div>
        <div class="dashboard-continue-list">
            <?php foreach (array_slice($myClassrooms, 0, 3) as $classroom): ?>
                <?php $continueQuiz = $classroom['quizzes'][0] ?? null; ?>
                <?php $continueAttempt = $continueQuiz ? latest_attempt_for_quiz((int) $user['id'], (int) $classroom['id'], (int) $continueQuiz['id']) : null; ?>
                <?php $continuePercent = $continueAttempt ? percentage((int) $continueAttempt['score'], (int) $continueAttempt['max_score']) : 0; ?>
                <article class="dashboard-continue-row">
                    <span class="dashboard-class-icon"><?php echo nav_icon('Focus Practice'); ?></span>
                    <div><strong><?php echo esc($classroom['subject']); ?></strong><small><?php echo esc($continueQuiz['title'] ?? 'No quiz available'); ?></small><div class="continue-progress"><span style="width: <?php echo esc((string) $continuePercent); ?>%"></span></div></div>
                    <?php if ($continueQuiz): ?><a class="button button-secondary" href="/QuizWeb/play.php?classroom_id=<?php echo esc((string) $classroom['id']); ?>&quiz_id=<?php echo esc((string) $continueQuiz['id']); ?>"><?php echo $continueAttempt ? 'Replay' : 'Start'; ?></a><?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if (!$myClassrooms): ?><div class="empty-state compact-empty"><p>You haven't joined a class yet.</p><a class="button button-primary" href="/QuizWeb/join.php">Join Class</a></div><?php endif; ?>
        </div>
    </section>
    </div>
    <section class="dashboard-start">
        <div>
            <h2>Start Learning Today</h2>
            <p>Join a class, take quizzes, and track your progress.</p>
            <div class="dashboard-shortcuts">
                <a class="button button-primary" href="/QuizWeb/join.php"><?php echo nav_icon('Join Class'); ?>Join Class</a>
                <a class="button button-secondary" href="#recent-progress"><?php echo nav_icon('Dashboard'); ?>Track Scores</a>
                <a class="button button-secondary" href="#joined-classrooms"><?php echo nav_icon('Focus Practice'); ?>Play Game Modes</a>
            </div>
        </div>
        <span class="dashboard-book" aria-hidden="true"><?php echo nav_icon('Focus Practice'); ?></span>
    </section>
    <div class="dashboard-coach-grid">
    <section class="glass panel learning-coach-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Self-learning coach</span>
                <h2>Where to focus next</h2>
                <p class="muted"><?php echo esc($learningProfile['trend_message']); ?></p>
            </div>
            <?php if ($practiceQuestions): ?>
                <a class="button button-primary" href="/QuizWeb/practice.php?return=<?php echo rawurlencode('/QuizWeb/dashboard.php'); ?>">Start Focus Practice</a>
            <?php endif; ?>
        </div>

        <div class="dashboard-learning-overview">
            <div class="dashboard-accuracy-ring<?php echo (int) $learningProfile['questions_seen'] === 0 ? ' is-empty' : ''; ?>"
                 style="--accuracy: <?php echo esc((string) $learningProfile['overall_accuracy']); ?>"
                 role="img"
                 aria-label="Overall accuracy: <?php echo esc((string) $learningProfile['overall_accuracy']); ?> percent">
                <span><strong><?php echo esc((string) $learningProfile['overall_accuracy']); ?>%</strong><small>Accuracy</small></span>
            </div>
            <div class="learning-summary-grid">
                <article class="stat-card">
                    <strong><?php echo esc((string) $learningProfile['questions_seen']); ?></strong>
                    <span>Questions analyzed</span>
                </article>
                <article class="stat-card">
                    <strong><?php echo esc((string) count($learningProfile['focus_items'])); ?></strong>
                    <span>Focus items</span>
                </article>
            </div>
        </div>

        <?php if ($learningProfile['focus_items']): ?>
            <details class="dashboard-weak-areas">
                <summary>Review weak areas</summary>
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
            </details>
        <?php endif; ?>
    </section>

            <section class="glass panel dashboard-study-plan">
                <h2><?php echo nav_icon('Focus Practice'); ?>Study Plan</h2>
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
            </section>
    </div>

    <div class="dashboard-insights-grid">
        <section class="glass panel dashboard-performance-panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Performance insights</span>
                    <h2>Strong and weak areas</h2>
                    <p class="muted">Use your recorded answers to decide where to study next.</p>
                </div>
                <?php if ($practiceQuestions): ?><a class="button button-primary" href="/QuizWeb/practice.php?return=<?php echo rawurlencode('/QuizWeb/dashboard.php'); ?>">Start Focus Training</a><?php endif; ?>
            </div>
            <div class="dashboard-skill-columns">
                <div class="dashboard-skill-group is-strong">
                    <h3><?php echo nav_icon('trophy'); ?> You are doing well</h3>
                    <?php if ($learningProfile['strong_levels']): ?>
                        <?php foreach ($learningProfile['strong_levels'] as $level): ?>
                            <div class="dashboard-skill-row"><span><?php echo esc($level['label']); ?></span><strong><?php echo esc((string) $level['accuracy']); ?>%</strong></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="muted">Complete more quizzes to reveal your strongest levels.</p>
                    <?php endif; ?>
                </div>
                <div class="dashboard-skill-group is-weak">
                    <h3><?php echo nav_icon('Focus Practice'); ?> Study these next</h3>
                    <?php if ($learningProfile['focus_items']): ?>
                        <?php foreach (array_slice($learningProfile['focus_items'], 0, 3) as $item): ?>
                            <article class="dashboard-weak-item">
                                <div><strong><?php echo esc($item['quiz_title']); ?></strong><span><?php echo esc($item['level_label'] . ' - ' . $item['accuracy'] . '% accuracy'); ?></span></div>
                                <a class="button button-secondary" href="/QuizWeb/play.php?classroom_id=<?php echo esc((string) $item['classroom_id']); ?>&quiz_id=<?php echo esc((string) $item['quiz_id']); ?>">Retake Quiz</a>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="muted">No weak quiz area has been identified yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="glass panel dashboard-leaderboard-panel">
            <div class="section-heading"><div><span class="eyebrow">Leaderboard</span><h2>Your class rankings</h2></div></div>
            <div class="dashboard-leaderboard-list">
                <?php foreach (array_slice($dashboardLeaderboard, 0, 5) as $row): ?>
                    <?php $isCurrentStudent = (int) $row['student']['id'] === (int) $user['id']; ?>
                    <article class="dashboard-leaderboard-row<?php echo $isCurrentStudent ? ' is-current' : ''; ?>">
                        <span class="dashboard-rank">#<?php echo esc((string) $row['rank']); ?></span>
                        <div><strong><?php echo esc($isCurrentStudent ? 'You' : $row['student']['name']); ?></strong><small><?php echo esc((string) $row['quizzes_played']); ?> quizzes</small></div>
                        <strong><?php echo esc((string) $row['average_percent']); ?>%</strong>
                    </article>
                <?php endforeach; ?>
                <?php if (!$dashboardLeaderboard): ?><p class="muted">Class rankings will appear once students complete quizzes.</p><?php endif; ?>
            </div>
        </section>
    </div>
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
    <div class="dashboard-bottom-grid">
        <section class="glass panel" id="recent-progress">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Recent progress</span>
                    <h2>Your latest runs</h2>
                </div>
            </div>
            <figure class="dashboard-score-chart<?php echo $recentAttempts ? '' : ' is-empty'; ?>" aria-labelledby="recent-score-chart-title">
                <figcaption id="recent-score-chart-title">Score trend</figcaption>
                <div class="dashboard-chart-plot">
                    <span class="dashboard-chart-line line-100" aria-hidden="true"><small>100%</small></span>
                    <span class="dashboard-chart-line line-50" aria-hidden="true"><small>50%</small></span>
                    <span class="dashboard-chart-line line-0" aria-hidden="true"><small>0%</small></span>
                    <div class="dashboard-chart-bars">
                        <?php if ($recentAttempts): ?>
                            <?php foreach (array_reverse($recentAttempts) as $index => $attempt): ?>
                                <?php $scorePercent = percentage((int) $attempt['score'], (int) $attempt['max_score']); ?>
                                <div class="dashboard-chart-column">
                                    <span class="dashboard-chart-value" style="--score: <?php echo esc((string) $scorePercent); ?>" title="<?php echo esc($attempt['quiz_title'] . ': ' . $scorePercent . '%'); ?>"><i><?php echo esc((string) $scorePercent); ?>%</i></span>
                                    <small>Run <?php echo esc((string) ($index + 1)); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ([28, 44, 35, 62, 48, 72] as $index => $placeholderHeight): ?>
                                <div class="dashboard-chart-column" aria-hidden="true">
                                    <span class="dashboard-chart-value" style="--score: <?php echo esc((string) $placeholderHeight); ?>"></span>
                                    <small>Run <?php echo esc((string) ($index + 1)); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!$recentAttempts): ?>
                        <div class="dashboard-chart-empty">
                            <span aria-hidden="true"><?php echo nav_icon('chart'); ?></span>
                            <strong>Your score trend will appear here</strong>
                            <small>Complete a quiz to plot your first result.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </figure>
            <div class="recent-list">
                <?php if ($recentAttempts): ?>
                    <?php foreach (array_slice($recentAttempts, 0, 4) as $attempt): ?>
                        <div class="recent-item">
                            <strong><?php echo esc($attempt['quiz_title']); ?></strong>
                            <span><?php echo esc(percentage((int) $attempt['score'], (int) $attempt['max_score']) . '% score'); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="muted">No quiz attempts yet. Join a classroom and start playing.</p>
                <?php endif; ?>
            </div>
        </section>
<?php endif; ?>

<section class="glass panel" id="joined-classrooms">
    <div class="section-heading">
        <div>
            <span class="eyebrow"><?php echo esc($user['role'] === 'teacher' ? 'Your classrooms' : 'Joined classrooms'); ?></span>
            <h2><?php echo esc($user['role'] === 'teacher' ? 'Manage your spaces' : 'Your Classes'); ?></h2>
        </div>
        <?php if ($user['role'] === 'student'): ?><a class="button button-secondary" href="/QuizWeb/join.php">Join Class</a><?php endif; ?>
    </div>

    <?php if ($myClassrooms): ?>
        <div class="classroom-grid">
            <?php foreach ($myClassrooms as $classroom): ?>
                <article class="classroom-card glass">
                    <?php if ($user['role'] === 'student'): ?>
                        <div class="dashboard-class-summary">
                            <span class="dashboard-class-icon" aria-hidden="true"><?php echo nav_icon('Focus Practice'); ?></span>
                            <div><h3><?php echo esc($classroom['subject']); ?></h3><span><?php echo esc(classroom_teacher_name($classroom)); ?></span></div>
                            <time datetime="<?php echo esc($classroom['updated_at']); ?>"><?php echo esc(date('M j, g:i A', strtotime($classroom['updated_at']))); ?></time>
                        </div>
                    <?php else: ?>
                        <div class="classroom-top"><div><h3><?php echo esc($classroom['name']); ?></h3><span><?php echo esc($classroom['subject']); ?></span></div><span class="code-badge"><?php echo esc($classroom['code']); ?></span></div>
                        <p><?php echo esc($classroom['description'] ?: 'Interactive classroom ready for quizzes and game sessions.'); ?></p>
                        <div class="card-meta"><span><?php echo esc(count($classroom['quizzes'] ?? []) . ' quizzes'); ?></span><span><?php echo esc(count($classroom['student_ids'] ?? []) . ' students'); ?></span></div>
                    <?php endif; ?>
                    <a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo esc((string) $classroom['id']); ?>">Open Classroom</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="muted"><?php echo esc($user['role'] === 'teacher' ? 'No classrooms yet. Create your first one above.' : 'You have not joined any classrooms yet.'); ?></p>
    <?php endif; ?>
</section>
<?php if ($user['role'] === 'student'): ?></div><?php endif; ?>

<?php render_footer(); ?>
