<?php
$submissionQuery=db()->prepare('SELECT response,submitted_at FROM activity_submissions WHERE classroom_id = ? AND item_id = ? AND student_id = ?');
$submissionQuery->execute([$classroomId,$submissionItemId,$submissionStudentId]); $submission=$submissionQuery->fetch();
?>
<details class="academic-submission"><summary><?php echo $submission ? 'Submitted response · '.esc(format_date($submission['submitted_at'])) : 'No response submitted'; ?></summary><?php if ($submission): ?><p><?php echo nl2br(esc($submission['response'])); ?></p><?php endif; ?><label><span>Feedback (shown with the published grade release)</span><textarea name="feedback[<?php echo $submissionStudentId; ?>]" maxlength="2000" rows="2"><?php echo esc(is_string($_POST['feedback'][$submissionStudentId] ?? null) ? $_POST['feedback'][$submissionStudentId] : ($book['feedback'][$submissionItemId][(string)$submissionStudentId] ?? '')); ?></textarea></label></details>
