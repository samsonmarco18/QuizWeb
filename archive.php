<?php
require_once __DIR__ . '/includes/layout.php';
$user = require_role('student');
render_header('Archive', 'student-page archive-page');
?>
<header class="page-heading"><div><span class="eyebrow">Classrooms</span><h1>Archive</h1><p>Past classes will be kept here when archiving is available.</p></div></header>
<section class="glass panel compact-panel"><div class="empty-state"><?php echo nav_icon('archive'); ?><h2>No archived classes</h2><p>Your active classrooms remain under My Classes.</p><a class="button button-secondary" href="/QuizWeb/dashboard.php#joined-classrooms">View Classes</a></div></section>
<?php render_footer(); ?>
