<?php

require_once __DIR__ . '/includes/layout.php';

$user = require_role('student');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $classroom = find_classroom_by_code($code);

    if (!$classroom) {
        $errors[] = 'That classroom code was not found.';
    } elseif (in_array((int) $user['id'], $classroom['student_ids'] ?? [], true)) {
        $errors[] = 'You have already joined this classroom.';
    } else {
        $classroom['student_ids'][] = (int) $user['id'];
        $classroom['student_ids'] = array_values(array_unique(array_map('intval', $classroom['student_ids'])));
        save_classroom($classroom);
        flash_set('success', 'Classroom joined successfully.');
        redirect('/QuizWeb/classroom.php?id=' . $classroom['id']);
    }
}

render_header('Join Classroom', 'join-page');
?>

<section class="auth-wrap glass">
    <div class="auth-copy">
        <span class="eyebrow">Student access</span>
        <h1>Join a classroom with a code</h1>
        <p class="lead">Ask your teacher for the classroom code, then unlock the quiz games waiting inside.</p>
        <div class="auth-badges">
            <span>Quick entry</span>
            <span>Class newsfeed</span>
            <span>Game-ready rooms</span>
        </div>
    </div>
    <form method="post" class="stack-form">
        <?php foreach ($errors as $error): ?>
            <div class="inline-error"><?php echo esc($error); ?></div>
        <?php endforeach; ?>
        <label>
            <span>Classroom Code</span>
            <input type="text" name="code" required maxlength="6" placeholder="Enter 6-character code">
        </label>
        <button class="button button-primary" type="submit">Join Classroom</button>
    </form>
    <aside class="auth-side">
        <article class="auth-side-card">
            <strong>What this page does</strong>
            <p>Use the teacher’s code to enter the right class space, see announcements, and unlock quizzes built for your room.</p>
        </article>
        <article class="auth-side-card">
            <strong>After joining</strong>
            <p>You’ll land inside the classroom feed where files, quiz games, and score activity all live together.</p>
        </article>
    </aside>
</section>

<?php render_footer(); ?>
