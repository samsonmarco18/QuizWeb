<?php
$manualAssessments = array_filter($grade['items'], fn($assessment) => $assessment['source'] === 'manual' && (empty($book['config']['periods']) || ($assessment['period_id'] ?? '') === $selectedPeriod));
?>
<section class="glass panel academic-score-editor"><div class="academic-panel-heading"><div><h2>Manual grade input<?php echo !empty($book['config']['periods']) ? ' ? ' . esc(ucfirst($selectedPeriod)) : ''; ?></h2><p class="muted">Encode participation, examinations, and other manual assessments. Saved scores contribute to their category's weighted grade. Blank means ungraded; zero is a recorded score.</p></div></div>
<?php if (!empty($book['locked'])): ?><p class="academic-notice">This release is locked. Reopen it from Grade release with a correction reason before editing.</p>
<?php else: ?>
<div class="action-row academic-manual-shortcuts"><a class="button button-secondary" href="<?php echo esc($url . '&view=items&participation=1&period=' . $selectedPeriod); ?>">Add participation assessment</a><a class="button button-secondary" href="<?php echo esc($url . '&view=items&exam=1&period=' . $selectedPeriod); ?>">Add examination</a></div>
<?php if (!$manualAssessments): ?><p>No manual assessments in this period yet. Add an assessment, select its grading category and maximum score, then enter student grades.</p>
<?php else: ?>
<form method="post" class="stack-form" data-grade-dirty><?php render_grade_form_fields($book); ?><input type="hidden" name="action" value="student_scores"><input type="hidden" name="student_id" value="<?php echo $studentId; ?>"><input type="hidden" name="period_id" value="<?php echo esc($selectedPeriod); ?>">
<div class="academic-table-scroll"><table class="academic-table academic-manual-inputs"><thead><tr><th>Assessment / component</th><th>Score</th><th>Maximum</th><th>Status</th></tr></thead><tbody>
<?php foreach ($manualAssessments as $id => $assessment): $savedScore = $book['scores'][$id][(string) $studentId] ?? null; $postedScore = $error ? ($_POST['manual_scores'][$id] ?? null) : null; ?>
<tr><th scope="row"><?php echo esc($assessment['name']); ?><small><?php echo esc(grading_category($book, $assessment['category_id'])['name'] ?? ''); ?></small></th><td><input aria-label="<?php echo esc($assessment['name'] . ' score'); ?>" type="number" name="manual_scores[<?php echo esc($id); ?>]" min="0" max="<?php echo esc((string) $assessment['max_score']); ?>" step="0.01" placeholder="Ungraded" value="<?php echo esc(is_string($postedScore) ? $postedScore : ($savedScore === null ? '' : (string) $savedScore)); ?>"></td><td><?php echo esc(grade_display($assessment['max_score'])); ?></td><td><?php echo esc($assessment['status']); ?><?php if ($assessment['override']): ?><small>Adjustment active. Restore automatic grade below to use this saved score.</small><?php endif; ?></td></tr>
<tr><td colspan="4"><?php $submissionItemId = $id; $submissionStudentId = $studentId; $submissionFeedbackName = 'manual_feedback[' . $id . ']'; $submissionPostedFeedback = $_POST['manual_feedback'][$id] ?? null; require __DIR__ . '/academic_submission_view.php'; unset($submissionFeedbackName, $submissionPostedFeedback); ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<label><span>Correction reason (required when changing a saved score)</span><input name="reason" maxlength="500" value="<?php echo esc($error && is_string($_POST['reason'] ?? null) ? $_POST['reason'] : ''); ?>"></label>
<div class="academic-save-bar"><p>Save changes to update the teacher's draft. Publish the release from Gradebook to update students' view.</p><button class="button button-primary">Save Changes</button></div>
</form><?php endif; ?>
<?php endif; ?></section>
