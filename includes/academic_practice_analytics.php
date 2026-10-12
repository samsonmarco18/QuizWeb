<?php
$practiceRows=[]; $members=array_column($students,null,'id');
$memberIds=array_map('intval',array_keys($members));
$practiceQuery=db()->prepare('SELECT * FROM attempts WHERE classroom_id = 0 AND game_type = ? AND student_id IN ('.($memberIds?implode(',',array_fill(0,count($memberIds),'?')):'NULL').') ORDER BY id DESC'); $practiceQuery->execute(array_merge(['focus_training'],$memberIds));
foreach ($practiceQuery->fetchAll() as $record) {
    $practice=hydrate_attempt($record); $sid=(int)$practice['student_id'];
    if (!isset($members[$sid]) || empty($practice['answers']['_adaptive_history'])) continue;
    $questions=$practice['answers']['_quiz_snapshot']['questions'] ?? []; $correct=0; $count=0; $levels=[];
    foreach ($questions as $index=>$question) {
        if ((int)($question['classroom_id'] ?? 0)!==$classroomId) continue;
        $count++; $levels[]=$question['level'];
        if (($practice['answers'][$index] ?? null)===(int)$question['correct_index']) $correct++;
    }
    if ($count) $practiceRows[]=['student'=>$members[$sid]['name'],'count'=>$count,'accuracy'=>round($correct/$count*100,2),'levels'=>implode(' → ',$levels),'at'=>$practice['played_at']];
}
$practicePage=page_records($practiceRows,'practice_page',10);
?>
<section class="glass panel"><h2>Adaptive practice monitoring</h2><p class="muted">Practice questions from this classroom only. These results do not change academic grades.</p><div class="academic-table-scroll"><table class="academic-table"><thead><tr><th>Student</th><th>Questions</th><th>Accuracy</th><th>Difficulty progression</th><th>Completed</th></tr></thead><tbody><?php foreach ($practicePage['items'] as $entry): ?><tr><th><?php echo esc($entry['student']); ?></th><td><?php echo $entry['count']; ?></td><td><?php echo esc((string)$entry['accuracy']); ?>%</td><td><?php echo esc($entry['levels']); ?></td><td><?php echo esc(format_date($entry['at'])); ?></td></tr><?php endforeach; if (!$practiceRows): ?><tr><td colspan="5">No adaptive practice recorded for this classroom yet. Enable a question bank in Quiz Builder.</td></tr><?php endif; ?></tbody></table></div><?php render_pagination($practicePage); ?></section>
