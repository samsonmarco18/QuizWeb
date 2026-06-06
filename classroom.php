<?php

require_once __DIR__ . '/includes/layout.php';

$user = require_login();
$classroomId = (int) ($_GET['id'] ?? 0);
$classroom = find_classroom($classroomId);
$announcementErrors = [];
$chatErrors = [];
$GLOBALS['quizweb_current_classroom_id'] = $classroomId;

if (!$classroom || !classroom_belongs_to_user($classroom, $user)) {
    flash_set('danger', 'Classroom not found or access denied.');
    redirect('/QuizWeb/dashboard.php');
}

if ($user['role'] === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'post_announcement') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $hasUploads = uploaded_files_present($_FILES['attachments'] ?? []);

    if ($title === '') {
        $announcementErrors[] = 'Announcement title is required.';
    }

    if ($body === '' && !$hasUploads) {
        $announcementErrors[] = 'Add a message or at least one attachment.';
    }

    if (strlen($title) > 120) {
        $announcementErrors[] = 'Announcement title must be 120 characters or fewer.';
    }

    if (strlen($body) > 4000) {
        $announcementErrors[] = 'Announcement message must be 4000 characters or fewer.';
    }

    if (!$announcementErrors) {
        $uploadResult = store_announcement_attachments($_FILES['attachments'] ?? []);
        $announcementErrors = $uploadResult['errors'];

        if (!$announcementErrors) {
            $classroom = create_classroom_announcement($classroom, $user, $title, $body, $uploadResult['attachments']);
            save_classroom($classroom);
            flash_set('success', 'Announcement posted to the classroom newsfeed.');
            redirect('/QuizWeb/classroom.php?id=' . $classroom['id']);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'post_chat_message') {
    if (!classroom_belongs_to_user($classroom, $user)) {
        flash_set('danger', 'Classroom not found or access denied.');
        redirect('/QuizWeb/dashboard.php');
    }

    $chatBody = trim($_POST['chat_body'] ?? '');

    if ($chatBody === '') {
        $chatErrors[] = 'Message cannot be empty.';
    }

    if (strlen($chatBody) > 1500) {
        $chatErrors[] = 'Message must be 1500 characters or fewer.';
    }

    if (!$chatErrors) {
        $classroom = create_classroom_chat_message($classroom, $user, $chatBody);
        save_classroom($classroom);
        flash_set('success', 'Message posted to the classroom chat.');
        $returnUrl = safe_local_path($_POST['return_url'] ?? '', '/QuizWeb/classroom.php?id=' . $classroom['id']);
        redirect($returnUrl);
    }
}

$modes = game_modes();
$attemptsList = classroom_attempts((int) $classroom['id']);
$leaderboard = classroom_leaderboard($classroom);
$currentStudentRank = null;
foreach ($leaderboard as $leaderboardRow) {
    if ((int) ($leaderboardRow['student']['id'] ?? 0) === (int) $user['id']) {
        $currentStudentRank = $leaderboardRow;
        break;
    }
}
$announcements = classroom_announcements($classroom);
$chatMessages = classroom_chat_messages($classroom);
$chatMessageCount = count($chatMessages);

render_header($classroom['name'], 'classroom-page');
?>

<section class="dashboard-hero glass">
    <div>
        <span class="eyebrow"><?php echo esc($classroom['subject']); ?></span>
        <h1><?php echo esc($classroom['name']); ?></h1>
        <p class="lead"><?php echo esc($classroom['description'] ?: 'Gamified classroom space for your quizzes and student activity.'); ?></p>
        <div class="feature-pills">
            <span><?php echo esc(count($classroom['quizzes'] ?? [])); ?> quizzes</span>
            <span><?php echo esc(count($classroom['student_ids'] ?? [])); ?> students</span>
            <span><?php echo esc($user['role'] === 'teacher' ? 'Teaching hub' : 'Learning hub'); ?></span>
        </div>
    </div>
    <div class="hero-side-stack">
        <div class="hero-actions">
            <div class="code-panel">
                <span>Join Code</span>
                <strong><?php echo esc($classroom['code']); ?></strong>
            </div>
            <?php if ($user['role'] === 'teacher'): ?>
                <a class="button button-primary" href="/QuizWeb/quiz_builder.php?classroom_id=<?php echo esc((string) $classroom['id']); ?>">Create Quiz Game</a>
            <?php endif; ?>
        </div>
        <article class="hero-note-card">
            <span class="eyebrow">Page focus</span>
            <h3><?php echo esc($user['role'] === 'teacher' ? 'Guide the whole classroom flow' : 'Stay synced with your class'); ?></h3>
            <p><?php echo esc($user['role'] === 'teacher'
                ? 'Post announcements, attach modules, manage quizzes, and watch activity from one classroom home.'
                : 'Catch announcements, open files, and jump into the right quiz experience from a single page.'); ?></p>
        </article>
    </div>
</section>

<section class="glass panel newsfeed-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow"><?php echo esc($user['role'] === 'teacher' ? 'Class updates' : 'Newsfeed'); ?></span>
            <h2>Announcements</h2>
        </div>
    </div>

    <?php if ($user['role'] === 'teacher'): ?>
        <form method="post" enctype="multipart/form-data" class="stack-form announcement-form">
            <input type="hidden" name="action" value="post_announcement">
            <?php foreach ($announcementErrors as $error): ?>
                <div class="inline-error"><?php echo esc($error); ?></div>
            <?php endforeach; ?>
            <label>
                <span>Announcement Title</span>
                <input type="text" name="title" maxlength="120" required value="<?php echo esc($_POST['title'] ?? ''); ?>" placeholder="Week 3 module and assignment">
            </label>
            <label>
                <span>Message</span>
                <textarea name="body" rows="5" placeholder="Share reminders, deadlines, links, or instructions for this classroom."><?php echo esc($_POST['body'] ?? ''); ?></textarea>
            </label>
            <label>
                <span>Attachments</span>
                <input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.txt,.zip,.rar,.7z,.png,.jpg,.jpeg,.gif,.webp,.mp4,.mp3,.wav">
            </label>
            <p class="form-hint">Supported files: documents, modules, images, archives, audio, and video up to 10 MB each.</p>
            <button class="button button-primary" type="submit">Post Announcement</button>
        </form>
    <?php endif; ?>

    <div class="newsfeed-list">
        <?php if ($announcements): ?>
            <?php foreach ($announcements as $announcement): ?>
                <article class="announcement-card">
                    <div class="announcement-meta">
                        <div>
                            <strong><?php echo esc($announcement['title']); ?></strong>
                            <span><?php echo esc($announcement['teacher_name'] ?? classroom_teacher_name($classroom)); ?></span>
                        </div>
                        <time datetime="<?php echo esc($announcement['created_at']); ?>"><?php echo esc(format_date($announcement['created_at'])); ?></time>
                    </div>

                    <?php if (!empty($announcement['body'])): ?>
                        <p class="announcement-body"><?php echo nl2br(esc($announcement['body'])); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($announcement['attachments'])): ?>
                        <div class="attachment-grid">
                            <?php foreach ($announcement['attachments'] as $attachment): ?>
                                <?php
                                $downloadUrl = '/QuizWeb/announcement_file.php?classroom_id=' . rawurlencode((string) $classroom['id'])
                                    . '&announcement_id=' . rawurlencode((string) $announcement['id'])
                                    . '&file=' . rawurlencode($attachment['stored_name']);
                                $previewUrl = $downloadUrl . '&view=1';
                                ?>
                                <div class="attachment-card">
                                    <?php if (is_image_attachment($attachment)): ?>
                                        <a class="attachment-preview" href="<?php echo esc($downloadUrl); ?>" target="_blank" rel="noopener">
                                            <img src="<?php echo esc($previewUrl); ?>" alt="<?php echo esc($attachment['original_name']); ?>">
                                        </a>
                                    <?php endif; ?>
                                    <a class="attachment-chip" href="<?php echo esc($downloadUrl); ?>" target="_blank" rel="noopener">
                                        <span><?php echo esc($attachment['original_name']); ?></span>
                                        <small><?php echo esc(format_bytes((int) ($attachment['size'] ?? 0))); ?></small>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="muted"><?php echo esc($user['role'] === 'teacher' ? 'No announcements yet. Post the first update for your class.' : 'No announcements have been posted in this classroom yet.'); ?></p>
        <?php endif; ?>
    </div>
</section>

<section class="glass panel chat-panel">
    <div class="chat-header">
        <div>
            <span class="eyebrow">Group chat</span>
            <h2>Classroom messages</h2>
            <p class="chat-header-copy">Keep the conversation moving with quick updates, questions, and replies.</p>
        </div>
        <div class="chat-status">
            <span class="chat-status-dot"></span>
            <div>
                <strong>Live thread</strong>
                <span><?php echo esc($chatMessageCount ? $chatMessageCount . ' messages' : 'Ready for the first message'); ?></span>
            </div>
        </div>
    </div>

    <div class="chat-thread">
        <?php if ($chatMessages): ?>
            <?php foreach ($chatMessages as $message): ?>
                <?php
                $isTeacher = ($message['user_role'] ?? '') === 'teacher';
                $isCurrentUser = (int) ($message['user_id'] ?? 0) === (int) $user['id'];
                $name = $message['user_name'] ?? 'Member';
                $role = role_label((string) ($message['user_role'] ?? 'student'));
                $trimmedName = trim($name);
                $initial = function_exists('mb_substr')
                    ? mb_substr($trimmedName, 0, 1)
                    : substr($trimmedName, 0, 1);
                $initial = strtoupper($initial);
                ?>
                <article class="chat-message <?php echo $isTeacher ? 'chat-message-teacher' : 'chat-message-student'; ?> <?php echo $isCurrentUser ? 'chat-message-self' : 'chat-message-peer'; ?>">
                    <div class="chat-avatar" aria-hidden="true"><?php echo esc($initial ?: 'M'); ?></div>
                    <div class="chat-bubble">
                        <div class="chat-message-meta">
                            <div>
                                <strong><?php echo esc($isCurrentUser ? 'You' : $name); ?></strong>
                                <span class="role-chip <?php echo $isTeacher ? 'role-chip-teacher' : 'role-chip-student'; ?>"><?php echo esc($role); ?></span>
                                <?php if ($isCurrentUser): ?>
                                    <span>Your message</span>
                                <?php endif; ?>
                            </div>
                            <time datetime="<?php echo esc($message['created_at'] ?? now_iso()); ?>"><?php echo esc(format_date($message['created_at'] ?? now_iso())); ?></time>
                        </div>
                        <p><?php echo nl2br(esc($message['body'] ?? '')); ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="chat-empty-state">
                <div class="chat-empty-icon">💬</div>
                <strong>No messages yet</strong>
                <p>Start the thread with a question, reminder, or quick hello.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="chat-composer-shell">
        <form method="post" class="stack-form chat-form">
            <input type="hidden" name="action" value="post_chat_message">
            <?php foreach ($chatErrors as $error): ?>
                <div class="inline-error"><?php echo esc($error); ?></div>
            <?php endforeach; ?>
            <label class="chat-composer">
                <div class="chat-composer-top">
                    <span>Send a message</span>
                    <small>Friendly, clear, and class-ready</small>
                </div>
                <textarea name="chat_body" rows="3" maxlength="1500" placeholder="Write a message to the class..."><?php echo esc($_POST['chat_body'] ?? ''); ?></textarea>
            </label>
            <div class="chat-composer-actions">
                <p class="chat-composer-hint">Press send to share your update with the class.</p>
                <button class="button button-primary" type="submit">Send</button>
            </div>
        </form>
    </div>
</section>

<section class="panel-grid">
    <article class="glass panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow"><?php echo esc($user['role'] === 'teacher' ? 'Quiz management' : 'Available games'); ?></span>
                <h2><?php echo esc($user['role'] === 'teacher' ? 'Classroom quizzes' : 'Play your assigned quiz modes'); ?></h2>
            </div>
        </div>
        <div class="quiz-grid">
            <?php if (!empty($classroom['quizzes'])): ?>
                <?php foreach ($classroom['quizzes'] as $quiz): ?>
                    <?php $latest = latest_attempt_for_quiz((int) $user['id'], (int) $classroom['id'], (int) $quiz['id']); ?>
                    <article class="quiz-card mode-<?php echo esc($quiz['game_type']); ?>">
                        <div class="quiz-card-head">
                            <span class="mode-badge"><?php echo esc($modes[$quiz['game_type']]['label'] ?? 'Game'); ?></span>
                            <strong><?php echo esc($quiz['title']); ?></strong>
                        </div>
                        <p><?php echo esc($quiz['description'] ?: 'Custom quiz game ready to play.'); ?></p>
                        <div class="card-meta">
                            <span><?php echo esc(count($quiz['questions'] ?? []) . ' questions'); ?></span>
                            <span><?php echo esc(array_sum(array_map(function (array $question) {
                                return (int) ($question['points'] ?? 10);
                            }, $quiz['questions'] ?? [])) . ' pts'); ?></span>
                        </div>
                        <?php if ($user['role'] === 'teacher'): ?>
                            <div class="action-row">
                                <a class="button button-secondary" href="/QuizWeb/quiz_builder.php?classroom_id=<?php echo esc((string) $classroom['id']); ?>&quiz_id=<?php echo esc((string) $quiz['id']); ?>">Edit Quiz</a>
                                <a class="button button-primary" href="/QuizWeb/play.php?classroom_id=<?php echo esc((string) $classroom['id']); ?>&quiz_id=<?php echo esc((string) $quiz['id']); ?>">Preview</a>
                            </div>
                        <?php else: ?>
                            <?php if ($latest): ?>
                                <div class="recent-item">
                                    <strong>Latest Score</strong>
                                    <span><?php echo esc($latest['score'] . '/' . $latest['max_score']); ?></span>
                                </div>
                            <?php endif; ?>
                            <a class="button button-primary" href="/QuizWeb/play.php?classroom_id=<?php echo esc((string) $classroom['id']); ?>&quiz_id=<?php echo esc((string) $quiz['id']); ?>">Play Game</a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="muted"><?php echo esc($user['role'] === 'teacher' ? 'No quizzes yet. Create your first game for this classroom.' : 'Your teacher has not published any quiz games yet.'); ?></p>
            <?php endif; ?>
        </div>
    </article>

    <article class="glass panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow"><?php echo esc($user['role'] === 'teacher' ? 'Students' : 'Teacher'); ?></span>
                <h2><?php echo esc($user['role'] === 'teacher' ? 'Class roster' : 'Classroom host'); ?></h2>
            </div>
        </div>
        <?php if ($user['role'] === 'teacher'): ?>
            <div class="recent-list">
                <?php $students = classroom_students($classroom); ?>
                <?php if ($students): ?>
                    <?php foreach ($students as $student): ?>
                        <div class="recent-item">
                            <strong><?php echo esc($student['name']); ?></strong>
                            <span><?php echo esc($student['email']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="muted">No students have joined this classroom yet.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="teacher-card">
                <strong><?php echo esc(classroom_teacher_name($classroom)); ?></strong>
                <span><?php echo esc($classroom['subject']); ?></span>
                <p>Stay ready for new quiz rounds and classroom activities.</p>
            </div>
        <?php endif; ?>
    </article>
</section>

<section class="glass panel leaderboard-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Leaderboards</span>
            <h2>Student rankings</h2>
            <p class="muted">Ranks use each student’s best score per quiz, then break ties by percentage, completed quizzes, and time.</p>
        </div>
        <?php if ($currentStudentRank): ?>
            <div class="rank-summary">
                <span>Your rank</span>
                <strong>#<?php echo esc((string) $currentStudentRank['rank']); ?></strong>
            </div>
        <?php endif; ?>
    </div>
    <div class="leaderboard-list">
        <?php if ($leaderboard): ?>
            <?php foreach ($leaderboard as $row): ?>
                <?php $isCurrentStudent = (int) ($row['student']['id'] ?? 0) === (int) $user['id']; ?>
                <article class="leaderboard-row <?php echo $isCurrentStudent ? 'is-current-student' : ''; ?>">
                    <div class="leaderboard-rank">#<?php echo esc((string) $row['rank']); ?></div>
                    <div class="leaderboard-student">
                        <strong><?php echo esc($isCurrentStudent ? 'You' : ($row['student']['name'] ?? 'Student')); ?></strong>
                        <span><?php echo esc($row['quizzes_played'] . ' quizzes played · ' . $row['attempts'] . ' attempts'); ?></span>
                    </div>
                    <div class="leaderboard-score">
                        <strong><?php echo esc($row['total_score'] . '/' . $row['max_score']); ?></strong>
                        <span><?php echo esc($row['average_percent'] . '% average'); ?></span>
                    </div>
                    <div class="leaderboard-time"><?php echo esc($row['elapsed_seconds'] ? $row['elapsed_seconds'] . 's best-time total' : 'No runs yet'); ?></div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="muted">No students have joined this classroom yet.</p>
        <?php endif; ?>
    </div>
</section>

<section class="glass panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Recent activity</span>
            <h2>Scoreboard</h2>
        </div>
    </div>
    <div class="recent-list">
        <?php if ($attemptsList): ?>
            <?php foreach (array_slice($attemptsList, 0, 8) as $attempt): ?>
                <?php $student = find_user_by_id((int) $attempt['student_id']); ?>
                <div class="recent-item">
                    <strong><?php echo esc(($student['name'] ?? 'Student') . ' - ' . $attempt['quiz_title']); ?></strong>
                    <span><?php echo esc($attempt['score'] . '/' . $attempt['max_score'] . ' in ' . max(1, (int) $attempt['elapsed_seconds']) . 's'); ?></span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="muted">No quiz attempts yet for this classroom.</p>
        <?php endif; ?>
    </div>
</section>

<?php render_footer(); ?>
