<?php

require_once dirname(__DIR__, 3) . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/classroom_ui.php';

$user = require_login();
$classroomId = (int) ($_GET['id'] ?? 0);
$classroom = find_classroom($classroomId);
$announcementErrors = [];
$chatErrors = [];
$GLOBALS['quizweb_current_classroom_id'] = $classroomId;
$classroomViews = ['overview' => 'Overview', 'quizzes' => 'Quizzes', 'materials' => 'Materials', 'results' => 'Results', 'grades' => 'Grades'];
$requestedView = $_GET['tab'] ?? 'quizzes';
$activeView = is_string($requestedView) && isset($classroomViews[$requestedView]) ? $requestedView : 'quizzes';

if (!$classroom || !classroom_belongs_to_user($classroom, $user)) {
    flash_set('danger', 'Classroom not found or access denied.');
    redirect('/QuizWeb/dashboard.php');
}
if ($activeView === 'grades') redirect('/QuizWeb/' . ($user['role'] === 'teacher' ? 'gradebook.php' : 'grades.php') . '?classroom_id=' . $classroomId);
if ($_SERVER['REQUEST_METHOD'] === 'POST') require_form_csrf();

if ($user['role'] === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'post_announcement') {
    $activeView = 'materials';
    $title = is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '';
    $body = is_string($_POST['body'] ?? null) ? trim($_POST['body']) : '';
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
            redirect('/QuizWeb/classroom.php?id=' . $classroom['id'] . '&tab=materials');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'post_chat_message') {
    if (!classroom_belongs_to_user($classroom, $user)) {
        flash_set('danger', 'Classroom not found or access denied.');
        redirect('/QuizWeb/dashboard.php');
    }

    $chatBody = is_string($_POST['chat_body'] ?? null) ? trim($_POST['chat_body']) : '';

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
if ($user['role'] === 'student') {
    $attemptsList = array_values(array_filter($attemptsList, fn(array $attempt): bool => (int) $attempt['student_id'] === (int) $user['id']));
}
$attemptPage = page_records($attemptsList, 'attempt_page', 10);
$leaderboard = classroom_leaderboard($classroom);
$classLearningProfile = classroom_learning_profile($classroom);
$studentClassProfile = $user['role'] === 'student'
    ? student_learning_profile((int) $user['id'], (int) $classroom['id'])
    : null;
$studentClassPracticeQuestions = $studentClassProfile ? learning_practice_questions($studentClassProfile) : [];
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
$quizCards = classroom_quiz_cards($classroom, $user, $attemptsList);
$quizFilters = classroom_quiz_filters($_GET);
$filteredQuizCards = classroom_filter_quizzes($quizCards, $quizFilters);
$quizPage = page_records($filteredQuizCards, 'quiz_page', 6);
$host = find_user_by_id((int) $classroom['teacher_id']) ?? ['name' => 'Classroom teacher', 'profile' => []];
$roster = $user['role'] === 'teacher' ? classroom_students($classroom) : [];
$completedQuizzes = count(array_filter($quizCards, static fn(array $card): bool => $card['completed']));
$progressTotal = count($quizCards);
$progressDone = $completedQuizzes;
if ($user['role'] === 'teacher') {
    $progressTotal = count($classroom['student_ids'] ?? []);
    $activeStudents = array_unique(array_column($attemptsList, 'student_id'));
    $progressDone = count(array_intersect($classroom['student_ids'] ?? [], $activeStudents));
}
$classProgress = percentage($progressDone, $progressTotal);
$upcomingQuizzes = array_values(array_filter($quizCards, static fn(array $card): bool => $card['due'] !== null && $card['due'] >= time()));
usort($upcomingQuizzes, static fn(array $a, array $b): int => $a['due'] <=> $b['due']);
$tabIcons = ['overview' => 'archive', 'quizzes' => 'Play Game Modes', 'materials' => 'document', 'results' => 'chart', 'grades' => 'star'];

render_header($classroom['name'], 'classroom-page classroom-redesign', ['/QuizWeb/assets/css/classroom.css']);
?>

<section class="classroom-banner glass">
    <div class="classroom-banner-copy">
        <span class="classroom-subject"><i aria-hidden="true"></i><?php echo esc($classroom['subject']); ?></span>
        <h1><?php echo esc($classroom['name']); ?></h1>
        <p class="lead"><?php echo esc($classroom['description'] ?: 'Gamified classroom space for your quizzes and student activity.'); ?></p>
        <div class="feature-pills">
            <span><?php echo nav_icon('document'); ?><?php echo esc(count($classroom['quizzes'] ?? [])); ?> quizzes</span>
            <span><?php echo nav_icon('Join Class'); ?><?php echo esc(count($classroom['student_ids'] ?? [])); ?> students</span>
            <span><?php echo nav_icon('Focus Practice'); ?><?php echo esc($user['role'] === 'teacher' ? 'Teaching hub' : 'Learning hub'); ?></span>
        </div>
    </div>
    <img class="classroom-banner-art" src="/QuizWeb/assets/images/classroom/hero.png" alt="" width="1536" height="1024" aria-hidden="true" fetchpriority="high">
    <div class="classroom-banner-actions">
        <div class="hero-actions">
            <div class="code-panel">
                <span>Join Code</span>
                <div class="classroom-code-value"><strong data-classroom-code><?php echo esc($classroom['code']); ?></strong><button class="classroom-icon-button" type="button" data-copy-classroom-code aria-label="Copy classroom join code"><?php echo nav_icon('copy'); ?></button></div>
                <small data-copy-code-status role="status"></small>
            </div>
            <?php if ($user['role'] === 'teacher'): ?>
                <a class="button button-primary" href="/QuizWeb/quiz_builder.php?classroom_id=<?php echo esc((string) $classroom['id']); ?>">Create Quiz Game</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="classroom-workspace">
<div class="classroom-main">
<nav class="classroom-tabs" aria-label="Classroom sections">
    <?php foreach ($classroomViews as $view => $label): ?>
        <a href="/QuizWeb/classroom.php?id=<?php echo (int) $classroom['id']; ?>&amp;tab=<?php echo esc($view); ?>" <?php echo $activeView === $view ? 'class="is-active" aria-current="page"' : ''; ?>><?php echo nav_icon($tabIcons[$view]); ?><span><?php echo esc($label); ?></span></a>
    <?php endforeach; ?>
</nav>

<?php if ($activeView === 'overview'): ?>
<?php if ($user['role'] === 'teacher'): ?>
    <section class="glass panel learning-coach-panel" id="overview">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Class learning map</span>
                <h2>Weak spots to reteach</h2>
                <p class="muted">Based on scored student attempts in this classroom.</p>
            </div>
        </div>
        <div class="learning-summary-grid">
            <article class="stat-card">
                <strong><?php echo esc((string) $classLearningProfile['overall_accuracy']); ?>%</strong>
                <span>Class accuracy</span>
            </article>
            <article class="stat-card">
                <strong><?php echo esc((string) $classLearningProfile['analyzed_attempts']); ?></strong>
                <span>Scored attempts</span>
            </article>
            <article class="stat-card">
                <strong><?php echo esc((string) count($classLearningProfile['weak_questions'])); ?></strong>
                <span>Weak questions</span>
            </article>
        </div>

        <div class="learning-grid">
            <article class="learning-card">
                <h3>Question heatlist</h3>
                <?php if ($classLearningProfile['weak_questions']): ?>
                    <div class="learning-list">
                        <?php foreach (array_slice($classLearningProfile['weak_questions'], 0, 4) as $item): ?>
                            <div class="learning-item">
                                <div>
                                    <strong><?php echo esc($item['quiz_title'] . ' - ' . $item['level_label']); ?></strong>
                                    <span><?php echo esc($item['prompt']); ?></span>
                                </div>
                                <div class="insight-meter" aria-label="<?php echo esc((string) $item['accuracy']); ?> percent accuracy">
                                    <span style="width: <?php echo esc((string) $item['accuracy']); ?>%"></span>
                                </div>
                                <small><?php echo esc($item['accuracy'] . '% accuracy; missed by ' . $item['missed_student_count'] . ' student' . ((int) $item['missed_student_count'] === 1 ? '' : 's')); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">No weak class pattern yet. More student attempts will make this panel useful.</p>
                <?php endif; ?>
            </article>

            <article class="learning-card">
                <h3>Difficulty bands</h3>
                <?php if ($classLearningProfile['weak_levels']): ?>
                    <div class="skill-pill-row">
                        <?php foreach ($classLearningProfile['weak_levels'] as $level): ?>
                            <span class="skill-pill"><?php echo esc($level['label'] . ': ' . $level['accuracy'] . '%'); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="muted">Use these bands to decide which items need review before the next game.</p>
                <?php else: ?>
                    <p class="muted">No difficulty band is below the 75% watch line yet.</p>
                <?php endif; ?>
            </article>
        </div>
    </section>
<?php else: ?>
    <section class="glass panel learning-coach-panel" id="overview">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Your learning map</span>
                <h2>Class-specific focus</h2>
                <p class="muted"><?php echo esc($studentClassProfile['trend_message']); ?></p>
            </div>
            <?php if ($studentClassPracticeQuestions): ?>
                <a class="button button-primary" href="/QuizWeb/practice.php?return=<?php echo rawurlencode('/QuizWeb/classroom.php?id=' . $classroom['id']); ?>">Practice Weak Items</a>
            <?php endif; ?>
        </div>
        <div class="learning-summary-grid">
            <article class="stat-card">
                <strong><?php echo esc((string) $studentClassProfile['overall_accuracy']); ?>%</strong>
                <span>Your accuracy here</span>
            </article>
            <article class="stat-card">
                <strong><?php echo esc((string) $studentClassProfile['questions_seen']); ?></strong>
                <span>Questions analyzed</span>
            </article>
            <article class="stat-card">
                <strong><?php echo esc((string) count($studentClassProfile['focus_items'])); ?></strong>
                <span>Focus items</span>
            </article>
        </div>

        <?php if ($studentClassProfile['focus_items']): ?>
            <div class="learning-list compact-learning-list">
                <?php foreach (array_slice($studentClassProfile['focus_items'], 0, 3) as $item): ?>
                    <div class="learning-item">
                        <div>
                            <strong><?php echo esc($item['level_label'] . ' - ' . $item['quiz_title']); ?></strong>
                            <span><?php echo esc($item['prompt']); ?></span>
                        </div>
                        <small><?php echo esc($item['guidance']); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="muted">No weak item is visible in this classroom yet. Play a quiz to generate a class-specific recommendation.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php endif; ?>

<?php if ($activeView === 'materials'): ?>
<section class="glass panel newsfeed-panel" id="materials">
    <div class="section-heading">
        <div>
            <span class="eyebrow"><?php echo esc($user['role'] === 'teacher' ? 'Class updates' : 'Newsfeed'); ?></span>
            <h2>Materials &amp; announcements</h2>
        </div>
    </div>

    <?php if ($user['role'] === 'teacher'): ?>
        <form method="post" enctype="multipart/form-data" class="stack-form announcement-form">
            <input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>">
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

<?php endif; ?>

<?php if ($chatErrors): ?>
<section class="glass panel chat-panel has-errors">
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
            <input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>">
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

<?php endif; ?>

<?php if ($activeView === 'quizzes'): ?>
<section class="glass panel classroom-quiz-panel" id="quizzes" data-classroom-quizzes>
    <form method="get" action="/QuizWeb/classroom.php" class="classroom-quiz-controls" data-quiz-controls>
        <input type="hidden" name="id" value="<?php echo (int) $classroomId; ?>">
        <input type="hidden" name="tab" value="quizzes">
        <input type="hidden" name="quiz_filter" value="<?php echo esc($quizFilters['filter']); ?>" data-current-quiz-filter>
        <div class="section-heading">
            <div><span class="eyebrow"><?php echo esc($user['role'] === 'teacher' ? 'Quiz management' : 'Available games'); ?></span><h2><?php echo esc($user['role'] === 'teacher' ? 'Classroom quizzes' : 'Play your assigned quiz modes'); ?></h2></div>
            <div class="quiz-search-tools">
                <label class="classroom-quiz-search"><?php echo nav_icon('search'); ?><input type="search" name="q" value="<?php echo esc($quizFilters['search']); ?>" maxlength="200" placeholder="Search quizzes..." aria-label="Search classroom quizzes"><button type="submit" aria-label="Search quizzes"><?php echo nav_icon('arrow-right'); ?></button></label>
                <details class="quiz-sort-menu"><summary class="classroom-icon-button" aria-label="Sort quizzes" title="Sort quizzes"><?php echo nav_icon('filter'); ?></summary><div><label>Sort quizzes<select name="quiz_sort"><option value="assigned" <?php echo $quizFilters['sort'] === 'assigned' ? 'selected' : ''; ?>>Assigned order</option><option value="title" <?php echo $quizFilters['sort'] === 'title' ? 'selected' : ''; ?>>Title A–Z</option><option value="due" <?php echo $quizFilters['sort'] === 'due' ? 'selected' : ''; ?>>Due date</option></select></label><button class="button button-secondary" type="submit">Apply</button></div></details>
            </div>
        </div>
        <div class="classroom-quiz-filters" aria-label="Filter quizzes">
            <?php $quickFilters = ['all' => 'All'] + ($user['role'] === 'student' ? ['todo' => 'To Do', 'completed' => 'Completed'] : []) + ['standard' => 'Standard Quiz', 'time_attack' => 'Time Attack', 'flip_match' => 'Flip Match', 'crossword' => 'Crossword']; ?>
            <?php foreach ($quickFilters as $filter => $label): ?><button class="quiz-filter<?php echo $quizFilters['filter'] === $filter ? ' is-active' : ''; ?>" type="submit" name="quiz_filter" value="<?php echo esc($filter); ?>" data-quiz-filter="<?php echo esc($filter); ?>" aria-pressed="<?php echo $quizFilters['filter'] === $filter ? 'true' : 'false'; ?>"><?php echo esc($label); ?></button><?php endforeach; ?>
            <details class="quiz-more-filters"><summary class="quiz-filter<?php echo isset($modes[$quizFilters['filter']]) && !isset($quickFilters[$quizFilters['filter']]) ? ' is-active' : ''; ?>">More <?php echo nav_icon('chevron'); ?></summary><div><?php foreach ($modes as $type => $mode): if (isset($quickFilters[$type])) continue; ?><button type="submit" name="quiz_filter" value="<?php echo esc($type); ?>" data-quiz-filter="<?php echo esc($type); ?>" aria-pressed="<?php echo $quizFilters['filter'] === $type ? 'true' : 'false'; ?>"><?php echo esc($mode['label']); ?></button><?php endforeach; ?></div></details>
        </div>
    </form>
    <p class="classroom-filter-status" data-quiz-filter-status role="status"><?php echo count($filteredQuizCards); ?> of <?php echo count($quizCards); ?> quizzes</p>
    <div class="quiz-grid" data-quiz-list>
        <?php foreach ($quizPage['items'] as $card): $quiz = $card['quiz']; $best = $card['best']; $cardGame = score_game_type((string) $quiz['game_type']); $playUrl = '/QuizWeb/play.php?classroom_id=' . $classroomId . '&quiz_id=' . (int) $quiz['id']; $editUrl = '/QuizWeb/quiz_builder.php?classroom_id=' . $classroomId . '&quiz_id=' . (int) $quiz['id']; ?>
            <article class="quiz-card assigned-game-card game-score-type mode-<?php echo esc($quiz['game_type']); ?>" data-game-type="<?php echo esc($cardGame['type']); ?>">
                <div class="quiz-card-topline">
                    <span class="game-type-badge"><?php echo esc($cardGame['label']); ?></span>
                    <?php if ($user['role'] === 'student' && $card['completed']): ?><span class="quiz-status is-completed"><?php echo nav_icon('check'); ?>Completed</span>
                    <?php elseif (in_array($card['due_status'], ['soon', 'past'], true)): ?><span class="quiz-status is-due"><?php echo nav_icon('calendar'); ?><?php echo $card['due_status'] === 'past' ? 'Past due' : 'Due soon'; ?></span>
                    <?php else: ?><span class="quiz-status"><?php echo $user['role'] === 'teacher' ? 'Published' : 'New'; ?></span><?php endif; ?>
                </div>
                <div class="quiz-card-content">
                    <img class="quiz-illustration" src="<?php echo esc(classroom_game_art($quiz['game_type'])); ?>" alt="" aria-hidden="true" width="160" height="144" loading="lazy" decoding="async">
                    <div class="quiz-card-copy">
                        <h3 class="quiz-card-title"><?php echo esc($quiz['title']); ?></h3>
                        <p class="quiz-card-description"><?php echo esc($quiz['description'] ?: ($modes[$quiz['game_type']]['description'] ?? 'Custom quiz game ready to play.')); ?></p>
                        <div class="card-meta"><span><?php echo nav_icon('document'); ?><?php echo $card['questions']; ?> <?php echo $quiz['game_type'] === 'crossword' ? 'words' : ($quiz['game_type'] === 'flip_match' ? 'pairs' : 'questions'); ?></span><span><?php echo nav_icon('star'); ?><?php echo $card['points']; ?> pts</span><span title="Estimated play time"><?php echo nav_icon('clock'); ?>~<?php echo $card['minutes']; ?> min</span></div>
                    </div>
                </div>
                <div class="quiz-card-footer">
                    <?php if ($user['role'] === 'student'): ?>
                        <?php if ($best): ?><div class="quiz-best-score"><span>Your Best Score</span><div><strong><?php echo (int) $best['score']; ?> / <?php echo (int) $best['max_score']; ?></strong><progress max="100" value="<?php echo $card['percent']; ?>" aria-label="Best score for <?php echo esc($quiz['title']); ?>"></progress><b><?php echo $card['percent']; ?>%</b></div></div>
                        <?php else: ?><div class="quiz-unplayed"><strong>Not attempted yet</strong><small>Start the quiz to earn points!</small></div><?php endif; ?>
                    <?php endif; ?>
                    <?php if ($card['due'] !== null): ?><div class="quiz-due-date<?php echo in_array($card['due_status'], ['soon', 'past'], true) ? ' is-due' : ''; ?>"><?php echo nav_icon('calendar'); ?><span>Due <?php echo esc(date('M j, Y', $card['due'])); ?></span><strong><?php echo $card['due_status'] === 'past' ? 'Submissions still open' : ($card['days_left'] === 0 ? 'Due today' : $card['days_left'] . ' day' . ($card['days_left'] === 1 ? '' : 's') . ' left'); ?></strong></div><?php endif; ?>
                    <div class="quiz-card-actions">
                        <a class="button button-primary" href="<?php echo esc($playUrl); ?>"><?php echo nav_icon('play'); ?><?php echo $user['role'] === 'teacher' ? 'Preview' : ($best ? 'Play Again' : 'Start Quiz'); ?></a>
                        <?php if ($user['role'] === 'teacher'): ?><a class="button button-secondary" href="<?php echo esc($editUrl); ?>"><?php echo nav_icon('document'); ?>Edit Quiz</a><?php elseif ($best): ?><a class="button button-secondary" href="/QuizWeb/results.php?id=<?php echo (int) $best['id']; ?>"><?php echo nav_icon('chart'); ?>View Results</a><?php endif; ?>
                        <details class="quiz-card-menu"><summary class="classroom-icon-button" aria-label="More options for <?php echo esc($quiz['title']); ?>"><?php echo nav_icon('more'); ?></summary><nav aria-label="Quiz actions"><a href="<?php echo esc($playUrl); ?>">Open activity</a><?php if ($user['role'] === 'teacher'): ?><a href="<?php echo esc($editUrl); ?>">Edit activity</a><?php elseif ($best): ?><a href="/QuizWeb/results.php?id=<?php echo (int) $best['id']; ?>">Review best attempt</a><?php endif; ?><a href="/QuizWeb/classroom.php?id=<?php echo $classroomId; ?>&amp;tab=results">Class results</a></nav></details>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$quizPage['items']): ?><div class="classroom-quizzes-empty"><?php echo nav_icon('search'); ?><h3><?php echo $quizCards ? 'No matching quizzes' : 'No quizzes yet'; ?></h3><p><?php echo $quizCards ? 'Try another search or game filter.' : ($user['role'] === 'teacher' ? 'Create your first game for this classroom.' : 'Your teacher has not published any quiz games yet.'); ?></p><?php if ($quizCards): ?><a class="button button-secondary" href="/QuizWeb/classroom.php?id=<?php echo $classroomId; ?>&amp;tab=quizzes">Show all quizzes</a><?php elseif ($user['role'] === 'teacher'): ?><a class="button button-primary" href="/QuizWeb/quiz_builder.php?classroom_id=<?php echo $classroomId; ?>">Create Quiz Game</a><?php endif; ?></div><?php endif; ?>
    </div>
    <div data-quiz-pagination><?php render_pagination($quizPage); ?></div>
</section>

<?php endif; ?>

<?php if ($activeView === 'results'): ?>
<section class="glass panel leaderboard-panel" id="results">
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
            <?php foreach ($attemptPage['items'] as $attempt): ?>
                <?php $student = find_user_by_id((int) $attempt['student_id']); ?>
                <div class="recent-item">
                    <strong><?php echo esc(($student['name'] ?? 'Student') . ' - ' . $attempt['quiz_title']); ?></strong>
                    <span><?php echo esc($attempt['score'] . '/' . $attempt['max_score'] . ' in ' . max(1, (int) $attempt['elapsed_seconds']) . 's'); ?></span>
                    <a class="button button-secondary" href="/QuizWeb/results.php?id=<?php echo (int) $attempt['id']; ?>">Review result</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="muted">No quiz attempts yet for this classroom.</p>
        <?php endif; ?>
    </div>
    <?php render_pagination($attemptPage); ?>
</section>

<?php endif; ?>
</div>
<aside class="classroom-aside" aria-label="Classroom information">
    <section class="glass panel classroom-host-panel">
        <span class="eyebrow">Teacher</span><h2>Classroom host</h2>
        <div class="classroom-host-card"><div class="classroom-host-identity"><?php render_profile_avatar($host, 'classroom-host-avatar'); ?><div><strong><?php echo esc($host['name']); ?></strong><span><?php echo esc($classroom['subject']); ?></span></div><button type="button" class="classroom-icon-button" data-classroom-message aria-label="Open classroom conversation"><?php echo nav_icon('message'); ?></button></div><p>Stay ready for new quiz rounds and classroom activities.</p></div>
    </section>
    <section class="glass panel classroom-progress-panel">
        <h2>Class progress</h2>
        <div class="classroom-progress-content"><div class="classroom-progress-ring"><svg viewBox="0 0 120 120" aria-hidden="true"><circle class="progress-ring-track" cx="60" cy="60" r="48"/><circle class="progress-ring-value" cx="60" cy="60" r="48" stroke-dasharray="301.6" stroke-dashoffset="<?php echo round(301.6 * (100 - $classProgress) / 100, 2); ?>"/></svg><strong><?php echo $classProgress; ?>%</strong></div><div><strong><?php echo $progressDone; ?> / <?php echo $progressTotal; ?></strong><span><?php echo nav_icon('chart'); ?><?php echo $user['role'] === 'teacher' ? 'Students active' : 'Quizzes completed'; ?></span></div></div>
    </section>
    <?php if ($upcomingQuizzes): ?>
        <section class="glass panel classroom-upcoming-panel"><div class="classroom-aside-heading"><h2>Upcoming</h2><a href="/QuizWeb/classroom.php?id=<?php echo $classroomId; ?>&amp;tab=quizzes&amp;quiz_sort=due">View all</a></div><ul class="classroom-upcoming-list"><?php foreach (array_slice($upcomingQuizzes, 0, 3) as $upcoming): $upQuiz = $upcoming['quiz']; ?><li><a href="/QuizWeb/play.php?classroom_id=<?php echo $classroomId; ?>&amp;quiz_id=<?php echo (int) $upQuiz['id']; ?>"><img src="<?php echo esc(classroom_game_art($upQuiz['game_type'])); ?>" alt="" width="40" height="40" loading="lazy"><div><strong><?php echo esc($upQuiz['title']); ?></strong><small><?php echo esc($modes[$upQuiz['game_type']]['label'] ?? 'Quiz'); ?> · <?php echo $upcoming['points']; ?> pts</small></div><div class="upcoming-due<?php echo $upcoming['due_status'] === 'soon' ? ' is-due' : ''; ?>"><time datetime="<?php echo esc($upQuiz['due_at']); ?>"><?php echo esc(date('M j', $upcoming['due'])); ?></time><small><?php echo $upcoming['days_left'] === 0 ? 'Due today' : $upcoming['days_left'] . ' days left'; ?></small></div></a></li><?php endforeach; ?></ul></section>
    <?php endif; ?>
    <section class="glass panel classroom-announcements-panel"><div class="classroom-aside-heading"><h2><?php echo nav_icon('message'); ?>Announcements</h2><?php if ($announcements): ?><a href="/QuizWeb/classroom.php?id=<?php echo $classroomId; ?>&amp;tab=materials">View all</a><?php endif; ?></div><?php if ($announcements): ?><ul class="classroom-announcement-list"><?php foreach (array_slice($announcements, 0, 2) as $announcement): ?><li><a href="/QuizWeb/classroom.php?id=<?php echo $classroomId; ?>&amp;tab=materials"><strong><?php echo esc($announcement['title']); ?></strong><time datetime="<?php echo esc($announcement['created_at']); ?>"><?php echo esc(format_date($announcement['created_at'])); ?></time></a></li><?php endforeach; ?></ul><?php else: ?><div class="classroom-announcement-empty"><div aria-hidden="true"><?php echo nav_icon('document'); ?></div><strong>No new announcements</strong><p>You're all caught up!</p></div><?php endif; ?></section>
    <?php if ($user['role'] === 'teacher'): ?><section class="glass panel classroom-roster-panel"><span class="eyebrow">Students</span><h2>Class roster</h2><div class="classroom-roster-list"><?php foreach ($roster as $student): ?><div><?php render_profile_avatar($student, 'classroom-member-avatar'); ?><div><strong><?php echo esc($student['name']); ?></strong><small><?php echo esc($student['email']); ?></small></div></div><?php endforeach; ?><?php if (!$roster): ?><p>No students have joined this classroom yet.</p><?php endif; ?></div></section><?php endif; ?>
</aside>
</div>
<?php render_footer(['/QuizWeb/assets/js/classroom.js']); ?>
