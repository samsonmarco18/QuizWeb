<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/student_portal.php';
$user = require_role('student');
$classes = student_classrooms((int) $user['id']);
$classroomId = (int) ($_GET['classroom_id'] ?? 0);
if ($classroomId && !in_array($classroomId, array_map(fn($c) => (int) $c['id'], $classes), true)) { http_response_code(403); exit('Classroom access denied.'); }
$scope = $classroomId ? array_values(array_filter($classes, fn($c) => (int) $c['id'] === $classroomId)) : $classes;
$monthInput = is_string($_GET['month'] ?? null) ? $_GET['month'] : '';
$month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $monthInput) && substr($monthInput, 0, 4) >= '1900' && substr($monthInput, 0, 4) <= '2100' ? $monthInput : date('Y-m');
$first = new DateTimeImmutable($month . '-01');
$now = time(); $events = []; $byDay = [];
foreach (portal_activities($scope, (int) $user['id']) as $activity) {
    $due = $activity['due'] ? strtotime($activity['due']) : false;
    if ($due === false) continue;
    $activity['timestamp'] = $due;
    $activity['deadline_status'] = in_array($activity['status'], ['Graded', 'Submitted'], true) ? 'Completed' : ($due < $now ? 'Overdue' : (date('Y-m-d', $due) === date('Y-m-d', $now) ? 'Due today' : 'Upcoming'));
    $events[] = $activity;
}
usort($events, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
foreach ($events as $event) if (date('Y-m', $event['timestamp']) === $month) $byDay[(int) date('j', $event['timestamp'])][] = $event;
$url = fn($m) => '/QuizWeb/deadlines.php?' . http_build_query(['classroom_id' => $classroomId, 'month' => $m]);
render_header('Deadlines', 'student-page student-portal-page deadlines-page', ['/QuizWeb/assets/css/student-portal.css', '/QuizWeb/assets/css/deadlines.css']);
?>
<header class="page-heading"><div><span class="eyebrow">Student schedule</span><h1>Deadlines</h1><p>Track upcoming work and past due dates. Times are in Asia/Manila.</p></div><a class="button button-secondary" href="<?php echo $classroomId ? '/QuizWeb/classroom.php?id=' . $classroomId : '/QuizWeb/student_portal.php'; ?>">&larr; Back</a></header>
<form class="portal-filters" method="get"><input type="hidden" name="month" value="<?php echo esc($month); ?>"><label>Classroom<select name="classroom_id"><option value="0">All classrooms</option><?php foreach ($classes as $class): ?><option value="<?php echo (int) $class['id']; ?>" <?php echo $classroomId === (int) $class['id'] ? 'selected' : ''; ?>><?php echo esc($class['name']); ?></option><?php endforeach; ?></select></label><button class="button button-secondary">Apply</button></form>
<section class="glass panel deadline-calendar" aria-label="Deadline calendar">
<div class="deadline-month"><a class="button button-secondary" aria-label="Previous month" href="<?php echo esc($url($first->modify('-1 month')->format('Y-m'))); ?>">&larr;</a><h2><?php echo esc($first->format('F Y')); ?></h2><a class="button button-secondary" aria-label="Next month" href="<?php echo esc($url($first->modify('+1 month')->format('Y-m'))); ?>">&rarr;</a><a href="<?php echo esc($url(date('Y-m'))); ?>">Today</a></div>
<div class="deadline-grid"><?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?><strong class="deadline-weekday"><?php echo $day; ?></strong><?php endforeach; ?>
<?php for ($i = 1; $i < (int) $first->format('N'); $i++): ?><div class="deadline-blank" aria-hidden="true"></div><?php endfor; ?>
<?php for ($day = 1; $day <= (int) $first->format('t'); $day++): $date = $month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT); ?>
<div class="deadline-day <?php echo $date === date('Y-m-d') ? 'is-today' : ''; ?>"><time datetime="<?php echo esc($date); ?>"><?php echo $day; ?></time><?php if (!empty($byDay[$day])): ?><a class="deadline-count" href="#day-<?php echo $day; ?>" aria-label="<?php echo count($byDay[$day]) . ' deadlines on ' . esc($date); ?>"><?php echo count($byDay[$day]); ?><span> due</span></a><?php endif; ?></div>
<?php endfor; ?></div>
<p class="muted">Select a date with deadlines to see its activities below.</p>
</section>
<section class="glass panel"><h2><?php echo esc($first->format('F')); ?> activities</h2>
<?php foreach ($byDay as $day => $dayEvents): ?><div class="deadline-date-group" id="day-<?php echo $day; ?>"><h3><?php echo esc($first->setDate((int) $first->format('Y'), (int) $first->format('m'), $day)->format('l, M j')); ?></h3><?php foreach ($dayEvents as $event): ?><a class="portal-row deadline-event" href="<?php echo esc($event['url']); ?>"><div><strong><?php echo esc($event['name']); ?></strong><small><?php echo esc($event['classroom']['name'] . ' · ' . date('g:i A', $event['timestamp']) . ' · ' . $event['status']); ?></small></div><span class="portal-status <?php echo $event['deadline_status'] === 'Overdue' ? 'is-overdue' : ''; ?>"><?php echo esc($event['deadline_status']); ?></span></a><?php endforeach; ?></div><?php endforeach; ?>
<?php if (!$byDay): ?><p class="muted">No deadlines this month.</p><?php endif; ?></section>
<div class="deadline-lists"><?php foreach (['Upcoming deadlines', 'Past due dates'] as $index => $heading): $group = array_values(array_filter($events, fn($e) => $index === 0 ? $e['timestamp'] >= $now : $e['timestamp'] < $now)); if ($index === 1) $group = array_reverse($group); ?><section class="glass panel"><h2><?php echo $heading; ?></h2><?php foreach ($group as $event): ?><a class="portal-row deadline-event" href="<?php echo esc($event['url']); ?>"><div><strong><?php echo esc($event['name']); ?></strong><small><?php echo esc($event['classroom']['name'] . ' · ' . date('M j, Y · g:i A', $event['timestamp'])); ?></small></div><span class="portal-status <?php echo $event['deadline_status'] === 'Overdue' ? 'is-overdue' : ''; ?>"><?php echo esc($event['deadline_status']); ?></span></a><?php endforeach; if (!$group): ?><p class="muted">No <?php echo strtolower($heading); ?>.</p><?php endif; ?></section><?php endforeach; ?></div>
<?php render_footer(); ?>
