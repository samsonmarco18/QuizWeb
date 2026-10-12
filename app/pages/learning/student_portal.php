<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/student_portal.php';
$user = require_role('student');
$views = ['home' => 'Home', 'activities' => 'Activities', 'progress' => 'Practice & progress', 'achievements' => 'Achievements', 'announcements' => 'Announcements'];
$view = is_string($_GET['view'] ?? null) && isset($views[$_GET['view']]) ? $_GET['view'] : 'home';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_form_csrf();
    try {
        portal_save_submission(db(), $user, (int) ($_POST['classroom_id'] ?? 0), is_string($_POST['item_id'] ?? null) ? $_POST['item_id'] : '', is_string($_POST['response'] ?? null) ? $_POST['response'] : '');
        flash_set('success', 'Response received. Your teacher will review and grade it.');
        redirect('/QuizWeb/student_portal.php?view=activities&classroom_id=' . (int) $_POST['classroom_id'] . '&item_id=' . rawurlencode($_POST['item_id']));
    } catch (InvalidArgumentException $exception) { $error = $exception->getMessage(); }
}
$classes = student_classrooms((int) $user['id']);
$activities = portal_activities($classes, (int) $user['id']);
$attempts = student_attempts((int) $user['id']);
$rewards = portal_rewards($attempts);
$profile = student_learning_profile((int) $user['id']);
$announcements = []; $releases = [];
foreach ($classes as $class) {
    foreach (classroom_announcements($class) as $announcement) $announcements[] = ['classroom' => $class, 'announcement' => $announcement];
    $book = grading_load(db(), (int) $class['id']);
    if (grading_published_student($book, $class, $user)) $releases[] = ['classroom' => $class, 'at' => $book['published']['at'], 'finalized' => !empty($book['published']['finalized'])];
}
usort($announcements, fn($a,$b) => strcmp($b['announcement']['created_at'], $a['announcement']['created_at']));
usort($releases, fn($a,$b) => strcmp($b['at'],$a['at']));
$pending = array_values(array_filter($activities, fn($activity) => !in_array($activity['status'], ['Graded','Submitted','Awaiting grading'], true)));
$dueSoon = count(array_filter($pending, fn($activity) => $activity['due'] && strtotime($activity['due']) >= time() && strtotime($activity['due']) <= time()+172800));
render_header('Student portal', 'student-page student-portal-page', ['/QuizWeb/assets/css/student-portal.css']);
?>
<header class="page-heading"><div><span class="eyebrow">Student portal</span><h1><?php echo esc($views[$view]); ?></h1><p>Your classrooms, learning activities, and academic progress.</p></div><a class="button button-secondary" href="/QuizWeb/join.php">Join class</a></header>
<nav class="portal-nav" aria-label="Student portal"><a href="/QuizWeb/student_portal.php" <?php echo $view === 'home' ? 'aria-current="page"' : ''; ?>>Home</a><a href="/QuizWeb/classes.php">My classes</a><?php foreach (array_slice($views,1,null,true) as $key => $label): ?><a href="/QuizWeb/student_portal.php?view=<?php echo esc($key); ?>" <?php echo $view === $key ? 'aria-current="page"' : ''; ?>><?php echo esc($label); ?></a><?php endforeach; ?><a href="/QuizWeb/grades.php">Academic records</a><a href="/QuizWeb/profile.php">Profile</a></nav>
<?php if ($error): ?><p class="inline-error" role="alert"><?php echo esc($error); ?></p><?php endif; ?>
<?php if ($view === 'home'): ?>
<div class="portal-counters"><?php foreach (['Pending activities'=>count($pending),'Due in 48 hours'=>$dueSoon,'Enrolled subjects'=>count($classes),'Released subjects'=>count($releases)] as $label=>$value): ?><article class="glass"><span><?php echo esc($label); ?></span><strong><?php echo $value; ?></strong></article><?php endforeach; ?></div>
<div class="portal-grid"><section class="glass panel"><div class="portal-heading"><h2>Next activities</h2><a href="/QuizWeb/student_portal.php?view=activities">View all</a></div><?php foreach (array_slice($pending,0,5) as $activity): ?><article class="portal-row"><div><strong><?php echo esc($activity['name']); ?></strong><small><?php echo esc($activity['classroom']['subject'] . ' · ' . $activity['component']); ?><?php echo $activity['due'] ? ' · ' . esc(format_date($activity['due'])) : ''; ?></small></div><a class="button button-secondary" href="<?php echo esc($activity['url']); ?>"><?php echo esc($activity['status']); ?></a></article><?php endforeach; if (!$pending): ?><p class="muted">You're caught up. Open a classroom or try practice.</p><?php endif; ?></section>
<section class="glass panel"><div class="portal-heading"><h2>Academic updates</h2><a href="/QuizWeb/grades.php">Academic records</a></div><?php foreach (array_slice($releases,0,4) as $release): ?><a class="portal-row" href="/QuizWeb/grades.php?classroom_id=<?php echo (int) $release['classroom']['id']; ?>"><div><strong><?php echo esc($release['classroom']['subject']); ?></strong><small><?php echo esc(format_date($release['at'])); ?></small></div><span class="portal-status"><?php echo $release['finalized'] ? 'Finalized' : 'Published'; ?></span></a><?php endforeach; if (!$releases): ?><p class="muted">Your teachers haven't released academic grades yet.</p><?php endif; ?></section>
<section class="glass panel"><div class="portal-heading"><h2>Recent announcements</h2><a href="/QuizWeb/student_portal.php?view=announcements">View all</a></div><?php foreach (array_slice($announcements,0,3) as $entry): ?><a class="portal-row" href="/QuizWeb/classroom.php?id=<?php echo (int) $entry['classroom']['id']; ?>&view=materials"><div><strong><?php echo esc($entry['announcement']['title']); ?></strong><small><?php echo esc($entry['classroom']['subject'] . ' · ' . format_date($entry['announcement']['created_at'])); ?></small></div></a><?php endforeach; if (!$announcements): ?><p class="muted">No classroom announcements yet.</p><?php endif; ?></section>
<section class="glass panel"><div class="portal-heading"><h2>Keep learning</h2><span class="portal-status">Level <?php echo $rewards['level']; ?></span></div><p><?php echo count($profile['focus_items']); ?> questions recommended for review · <?php echo $rewards['xp']; ?> XP earned.</p><div class="action-row"><a class="button button-primary" href="/QuizWeb/adaptive_practice.php">Adaptive practice</a><a class="button button-secondary" href="/QuizWeb/practice.php">Focus practice</a></div></section></div>
<?php elseif ($view === 'activities'): ?>
<?php
$selected = null;
foreach ($activities as $activity) if ($activity['id'] === ($_GET['item_id'] ?? '') && (int) $activity['classroom']['id'] === (int) ($_GET['classroom_id'] ?? 0)) $selected = $activity;
if ($selected && $selected['source'] === 'manual'): ?>
<section class="glass panel"><div class="portal-heading"><h2><?php echo esc($selected['name']); ?></h2><span class="portal-status"><?php echo esc($selected['status']); ?></span></div><p><?php echo esc($selected['classroom']['subject'] . ' · ' . $selected['component']); ?></p><p><?php echo nl2br(esc($selected['instructions'] ?: 'See classroom materials for activity instructions.')); ?></p><a href="/QuizWeb/classroom.php?id=<?php echo (int) $selected['classroom']['id']; ?>&view=materials">Open classroom materials</a><?php if ($selected['accept_responses']): ?><form method="post" class="stack-form"><input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>"><input type="hidden" name="classroom_id" value="<?php echo (int) $selected['classroom']['id']; ?>"><input type="hidden" name="item_id" value="<?php echo esc($selected['id']); ?>"><label><span>Your response</span><textarea name="response" rows="5" maxlength="10000" required <?php echo $selected['locked'] || $selected['status'] === 'Graded' ? 'readonly' : ''; ?>><?php echo esc($error && is_string($_POST['response'] ?? null) ? $_POST['response'] : ($selected['submission']['response'] ?? '')); ?></textarea></label><?php if (!$selected['locked'] && $selected['status'] !== 'Graded'): ?><button class="button button-primary">Submit response</button><?php endif; ?><?php if ($selected['submission']): ?><p class="muted">Received <?php echo esc(format_date($selected['submission']['submitted_at'])); ?>. Published scores appear in Academic records.</p><?php endif; ?></form><?php else: ?><p class="muted">Teacher-recorded activity. No portal submission is required.</p><?php endif; ?></section>
<?php endif; ?>
<?php require dirname(__DIR__,3) . '/includes/student_activity_view.php'; ?>
<?php elseif ($view === 'progress'): ?>
<section class="glass panel"><div class="portal-heading"><h2>Practice recommendations</h2><a href="/QuizWeb/student_results.php">Attempt history</a></div><div class="action-row"><a class="button button-primary" href="/QuizWeb/adaptive_practice.php">Adaptive practice</a><a class="button button-secondary" href="/QuizWeb/practice.php">Practice weak items</a></div><p class="muted">Adaptive practice changes difficulty based on your answers. Practice does not change academic grades or classroom score rankings.</p><div class="portal-counters"><?php foreach (['Accuracy'=>$profile['overall_accuracy'].'%','Questions analyzed'=>$profile['questions_seen'],'Questions to review'=>count($profile['focus_items'])] as $label=>$value): ?><article><span><?php echo esc($label); ?></span><strong><?php echo esc((string)$value); ?></strong></article><?php endforeach; ?></div><p><?php echo esc($profile['trend_message']); ?></p><?php foreach (array_slice($profile['focus_items'],0,8) as $item): ?><article class="portal-row"><div><strong><?php echo esc($item['prompt']); ?></strong><small><?php echo esc($item['quiz_title'].' · '.$item['level_label']); ?></small></div><span class="portal-status"><?php echo esc((string)$item['accuracy']); ?>% accuracy</span></article><?php endforeach; ?></section>
<section class="glass panel"><h2>Recent performance</h2><?php foreach (array_slice($attempts,0,8) as $attempt): ?><div class="portal-row"><div><strong><?php echo esc($attempt['quiz_title']); ?></strong><small><?php echo esc(format_date($attempt['played_at'])); ?></small></div><meter min="0" max="100" value="<?php echo $attempt['max_score'] ? $attempt['score']/$attempt['max_score']*100 : 0; ?>" aria-label="<?php echo esc($attempt['quiz_title']); ?> accuracy"></meter><span><?php echo esc((string)percentage((int)$attempt['score'],(int)$attempt['max_score'])); ?>%</span></div><?php endforeach; ?></section>
<?php elseif ($view === 'achievements'): require dirname(__DIR__,3) . '/includes/student_rewards_view.php'; ?>
<?php elseif ($view === 'announcements'): ?>
<section class="glass panel"><h2>Classroom updates & materials</h2><?php $announcementPage=page_records($announcements,'page',10); foreach ($announcementPage['items'] as $entry): ?><article class="portal-announcement"><small><?php echo esc($entry['classroom']['subject'].' · '.format_date($entry['announcement']['created_at'])); ?></small><h3><?php echo esc($entry['announcement']['title']); ?></h3><p><?php echo nl2br(esc(mb_strimwidth($entry['announcement']['body'] ?? '',0,300,'…'))); ?></p><a href="/QuizWeb/classroom.php?id=<?php echo (int)$entry['classroom']['id']; ?>&view=materials">Read announcement & attachments</a></article><?php endforeach; if (!$announcements): ?><p class="muted">No announcements yet.</p><?php endif; render_pagination($announcementPage); ?></section>
<?php endif; ?>
<?php render_footer(['/QuizWeb/assets/js/student-portal.js']); ?>
