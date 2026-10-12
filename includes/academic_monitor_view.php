<?php
$monitorPeriods = $book['config']['periods'] ?? [['id' => '', 'name' => 'All activities', 'weight' => 100]];
foreach ($monitorPeriods as $monitorPeriod):
    $componentRows = []; $readyComponents = 0;
    foreach ($book['config']['categories'] as $category) {
        $activities = array_filter($book['items'], fn($activity) => empty($activity['archived']) && $activity['category_id'] === $category['id'] && ($monitorPeriod['id'] === '' || ($activity['period_id'] ?? '') === $monitorPeriod['id']));
        $recorded = 0; $missing = 0;
        foreach ($students as $student) foreach ($activities as $id => $activity) {
            $score = $rows[(int) $student['id']]['items'][$id]['score'] ?? null;
            if ($score === null) $missing++; else $recorded++;
        }
        $required = $category['weight'] > 0 && $monitorPeriod['weight'] > 0;
        $ready = count($activities) > 0 && count($students) > 0 && $missing === 0;
        if (!$required || $ready) $readyComponents++;
        $componentRows[] = compact('category', 'activities', 'recorded', 'missing', 'required', 'ready');
    }
?>
<section class="academic-monitor-term"><div class="academic-panel-heading"><h3><?php echo esc($monitorPeriod['name']); ?> <small><?php echo esc(grade_display($monitorPeriod['weight'])); ?>% weight</small></h3><span class="academic-status <?php echo $readyComponents === count($componentRows) ? 'is-complete' : 'is-pending'; ?>"><?php echo $readyComponents; ?>/<?php echo count($componentRows); ?> components ready</span></div>
<div class="academic-table-scroll"><table class="academic-table"><thead><tr><th>Component</th><th>Activities</th><th>Recorded scores</th><th>Missing scores</th><th>Readiness</th><th>Action</th></tr></thead><tbody>
<?php foreach ($componentRows as $component): $category = $component['category']; ?>
<tr><th><?php echo esc($category['name']); ?><small><?php echo esc(grade_display($category['weight'])); ?>% component weight</small></th><td><?php echo count($component['activities']); ?></td><td><?php echo $component['recorded']; ?> / <?php echo count($component['activities']) * count($students); ?></td><td><?php echo $component['missing']; ?></td><td><span class="academic-status <?php echo $component['ready'] ? 'is-complete' : 'is-pending'; ?>"><?php echo !$component['required'] ? 'Optional (0% weight)' : (!$component['activities'] ? 'No activities yet' : (!count($students) ? 'No students enrolled' : ($component['missing'] ? 'Scores needed' : 'Complete'))); ?></span></td><td><a class="button button-secondary" href="<?php echo esc($url . '&view=items&category_id=' . rawurlencode($category['id']) . '&period=' . rawurlencode($monitorPeriod['id'])); ?>">Add / manage activities</a></td></tr>
<?php if ($component['activities']): ?><tr><td colspan="6"><div class="academic-monitor-activities"><?php foreach ($component['activities'] as $id => $activity): $activityMissing = count(array_filter($students, fn($student) => ($rows[(int) $student['id']]['items'][$id]['score'] ?? null) === null)); ?><a href="<?php echo esc($activity['source'] === 'manual' ? $url . '&view=entry&item_id=' . rawurlencode($id) : '/QuizWeb/quiz_builder.php?classroom_id=' . $classroomId . '&quiz_id=' . (int) $activity['quiz_id']); ?>"><?php echo esc($activity['name']); ?> <span><?php echo $activityMissing; ?> missing<?php echo $activity['source'] === 'quiz' ? ' · Quiz' : ''; ?></span></a><?php endforeach; ?></div></td></tr><?php endif; ?>
<?php endforeach; ?></tbody></table></div></section>
<?php endforeach; ?>
<?php if (empty($book['config']['periods'])): ?><p class="academic-notice">Grading periods are not configured. <a href="<?php echo esc($url . '&view=setup'); ?>">Configure Prelim, Midterm, and Finals</a> to monitor each term separately.</p><?php endif; ?>
