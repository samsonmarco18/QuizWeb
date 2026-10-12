<?php
function adaptive_next_index(array $questions, array $used, string $level): ?int {
    $levels = ['easy','medium','hard','master']; $target = array_search($level,$levels,true); $target = $target === false ? 1 : $target;
    $candidates = [];
    foreach ($questions as $index=>$question) {
        if (in_array($index,$used,true)) continue;
        $difficulty = array_search($question['level'] ?? 'easy',$levels,true);
        $candidates[$index] = abs(($difficulty === false ? 0 : $difficulty)-$target);
    }
    if (!$candidates) return null;
    $distance = min($candidates); $matches = array_keys(array_filter($candidates,fn($value)=>$value===$distance));
    return $matches[random_int(0,count($matches)-1)];
}
function adaptive_record_answer(array &$run, int $choice, int $step): array {
    if ($step !== count($run['history']) || $run['current'] === null || count($run['history']) >= $run['limit']) throw new InvalidArgumentException('This answer was already recorded. Continue with the current question.');
    $index=$run['current']; $question=$run['bank'][$index];
    if (!array_key_exists($choice,$question['options'])) throw new InvalidArgumentException('Choose a valid answer.');
    $correct=$choice===(int)$question['correct_index'];
    $run['streak'] = $correct ? max(0,$run['streak'])+1 : min(0,$run['streak'])-1;
    $levels=['easy','medium','hard','master']; $actual=array_search($question['level'],$levels,true);
    $actual=$actual===false?0:$actual; $target=$actual;
    if ($run['streak']>=2) { $target=min(3,$actual+1); $run['streak']=0; }
    elseif ($run['streak']<=-2) { $target=max(0,$actual-1); $run['streak']=0; }
    $run['history'][]=['index'=>$index,'choice'=>$choice,'correct'=>$correct,'level'=>$question['level'],'target'=>$levels[$target]];
    $used=array_column($run['history'],'index');
    $run['current']=count($used)>=$run['limit']?null:adaptive_next_index($run['bank'],$used,$levels[$target]);
    return end($run['history']);
}
function adaptive_bank(array $classes): array {
    $bank=[];
    foreach ($classes as $class) foreach ($class['quizzes'] as $quiz) {
        if (empty($quiz['adaptive_practice_enabled']) || activity_uses_text_answers($quiz['game_type']) || $quiz['game_type']==='crossword') continue;
        foreach ($quiz['questions'] as $question) {
            if (count($question['options'] ?? [])<2 || !isset($question['options'][$question['correct_index'] ?? -1])) continue;
            $bank[]=array_replace($question,['classroom_id'=>$class['id'],'quiz_id'=>$quiz['id'],'topic'=>($question['topic'] ?? '') ?: $quiz['title']]);
        }
    }
    return $bank;
}
