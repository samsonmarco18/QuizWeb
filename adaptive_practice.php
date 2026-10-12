<?php
require_once __DIR__.'/includes/layout.php';
require_once __DIR__.'/includes/adaptive.php';
$user=require_role('student'); $classes=student_classrooms((int)$user['id']); $error='';
$classId=(int)($_GET['classroom_id'] ?? $_POST['classroom_id'] ?? 0);
$selectedClasses=$classId?array_values(array_filter($classes,fn($class)=>(int)$class['id']===$classId)):$classes;
$bank=adaptive_bank($selectedClasses);
$token=is_string($_GET['run'] ?? null)?$_GET['run']:'';
$run=$token?($_SESSION['adaptive_runs'][$token] ?? null):null;
if (isset($_GET['attempt_id'])) {
    $query=db()->prepare('SELECT * FROM attempts WHERE id = ? AND student_id = ? AND classroom_id = 0'); $query->execute([(int)$_GET['attempt_id'],$user['id']]);
    $record=$query->fetch(); $saved=$record?hydrate_attempt($record):null;
    if (!$saved || empty($saved['answers']['_adaptive_history'])) { http_response_code(404); exit('Practice result not found.'); }
    $savedBank=$saved['answers']['_quiz_snapshot']['questions']; $savedHistory=[];
    foreach ($saved['answers']['_adaptive_history'] as $index=>$entry) { $entry['index']=$index; $savedHistory[]=$entry; }
    $run=['student_id'=>(int)$user['id'],'classroom_ids'=>[],'bank'=>$savedBank,'history'=>$savedHistory,'current'=>null,'limit'=>count($savedBank),'started_at'=>time(),'attempt_id'=>$saved['id']];
}
if ($run && ((int)$run['student_id'] !== (int)$user['id'] || time()-$run['started_at']>7200)) $run=null;
if ($run) foreach ($run['classroom_ids'] as $id) if (!in_array($id,array_map(fn($class)=>(int)$class['id'],$classes),true)) { http_response_code(403); exit('This practice classroom is no longer available.'); }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_form_csrf();
    try {
        if (($_POST['action'] ?? '')==='start') {
            if (count($bank)<2 || count(array_unique(array_column($bank,'level')))<2) throw new InvalidArgumentException('Your teacher needs to enable practice questions at two or more difficulty levels.');
            foreach ($_SESSION['adaptive_runs'] ?? [] as $id=>$old) if (time()-$old['started_at']>7200) unset($_SESSION['adaptive_runs'][$id]);
            if (count($_SESSION['adaptive_runs'] ?? [])>=10) throw new InvalidArgumentException('Finish an existing practice session before starting another.');
            $token=bin2hex(random_bytes(16));
            $_SESSION['adaptive_runs'][$token]=['student_id'=>(int)$user['id'],'classroom_ids'=>array_values(array_unique(array_map('intval',array_column($bank,'classroom_id')))),'bank'=>$bank,'current'=>adaptive_next_index($bank,[],'medium'),'history'=>[],'streak'=>0,'limit'=>min(10,count($bank)),'started_at'=>time()];
            redirect('/QuizWeb/adaptive_practice.php?run='.$token);
        }
        if (!$run) throw new InvalidArgumentException('Your practice session expired. Start another round.');
        $choice=filter_var($_POST['choice'] ?? null,FILTER_VALIDATE_INT);
        $step=filter_var($_POST['step'] ?? null,FILTER_VALIDATE_INT);
        if ($choice===false || $step===false) throw new InvalidArgumentException('Select an answer.');
        adaptive_record_answer($run,$choice,$step);
        $_SESSION['adaptive_runs'][$token]=$run;
        redirect('/QuizWeb/adaptive_practice.php?run='.$token);
    } catch (InvalidArgumentException $exception) { $error=$exception->getMessage(); }
}
if ($run && $run['current']===null && !isset($run['attempt_id'])) {
    $questions=[]; $answers=[];
    foreach ($run['history'] as $entry) { $questions[]=$run['bank'][$entry['index']]; $answers[]=$entry['choice']; }
    $quiz=['id'=>0,'title'=>'Adaptive Practice','game_type'=>'focus_training','questions'=>$questions];
    $answers['_adaptive_history']=$run['history'];
    $attempt=create_attempt((int)$user['id'],0,$quiz,$answers,max(1,time()-$run['started_at']));
    $run['attempt_id']=$attempt['id']; $_SESSION['adaptive_runs'][$token]=$run;
}
render_header('Adaptive practice','student-page student-portal-page',['/QuizWeb/assets/css/student-portal.css']);
?>
<header class="page-heading"><div><span class="eyebrow">Practice & progress</span><h1>Adaptive practice</h1><p>Two correct answers raise difficulty; two incorrect answers lower it. Questions are not repeated in a round.</p></div><a class="button button-secondary" href="/QuizWeb/student_portal.php?view=progress">Back to progress</a></header>
<?php if ($error): ?><p class="inline-error" role="alert"><?php echo esc($error); ?></p><?php endif; ?>
<section class="glass panel portal-run">
<?php if (!$run): ?>
<h2>Choose a classroom</h2><form method="get" class="portal-filters"><label><span>Question bank</span><select name="classroom_id"><option value="0">All enrolled classrooms</option><?php foreach ($classes as $class): ?><option value="<?php echo (int)$class['id']; ?>" <?php echo (int)$class['id']===$classId?'selected':''; ?>><?php echo esc($class['subject']); ?></option><?php endforeach; ?></select></label><button class="button button-secondary">View bank</button></form><p><?php echo count($bank); ?> teacher-enabled questions · <?php echo count(array_unique(array_column($bank,'level'))); ?> difficulty levels.</p><p class="muted">Up to 10 questions. Your score is saved in practice history and earns daily practice XP. It does not affect academic grades or classroom score rankings. When a level runs out, the nearest available level is used.</p><form method="post"><input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>"><input type="hidden" name="action" value="start"><input type="hidden" name="classroom_id" value="<?php echo $classId; ?>"><button class="button button-primary" <?php echo count($bank)<2 || count(array_unique(array_column($bank,'level')))<2?'disabled':''; ?>>Start adaptive practice</button></form><?php if (count(array_unique(array_column($bank,'level')))<2): ?><p class="academic-notice">Ask your teacher to enable adaptive practice in Quiz Builder and provide questions at different levels.</p><?php endif; ?>
<?php else: $last=$run['history']?end($run['history']):null; ?>
<?php if ($last): $previous=$run['bank'][$last['index']]; ?><div class="portal-feedback <?php echo $last['correct']?'':'needs-work'; ?>" role="status"><h2><?php echo $last['correct']?'Correct':'Needs review'; ?></h2><p><?php echo esc($previous['prompt']); ?></p><p>Your answer: <?php echo esc($previous['options'][$last['choice']]); ?><br>Correct answer: <strong><?php echo esc($previous['options'][$previous['correct_index']]); ?></strong></p><p><?php echo esc(($previous['explanation'] ?? '') ?: learning_guidance($previous,$last['correct'],true,'standard')); ?></p><small>Target difficulty: <?php echo esc(ucfirst($last['target'])); ?></small></div><?php endif; ?>
<?php if ($run['current']===null): $correct=count(array_filter($run['history'],fn($entry)=>$entry['correct'])); ?><h2>Practice complete</h2><p><strong><?php echo $correct; ?>/<?php echo count($run['history']); ?></strong> correct · <?php echo round($correct/count($run['history'])*100); ?>% accuracy.</p><details class="portal-ranking" open><summary>Difficulty progression & answer review</summary><?php foreach ($run['history'] as $number=>$entry): $q=$run['bank'][$entry['index']]; ?><article class="portal-row"><div><strong><?php echo esc($q['prompt']); ?></strong><small><?php echo esc(ucfirst($entry['level']).' · '.($q['topic'] ?: 'Practice')); ?> · Correct answer: <?php echo esc($q['options'][$q['correct_index']]); ?></small></div><span class="portal-status"><?php echo $entry['correct']?'Correct':'Review'; ?></span></article><?php endforeach; ?></details><div class="action-row"><a class="button button-primary" href="/QuizWeb/adaptive_practice.php">Another round</a><a class="button button-secondary" href="/QuizWeb/student_portal.php?view=achievements">View achievements</a></div>
<?php else: $question=$run['bank'][$run['current']]; ?><div class="portal-heading"><h2>Question <?php echo count($run['history'])+1; ?> / <?php echo $run['limit']; ?></h2><span class="portal-status"><?php echo esc(ucfirst($question['level'])); ?></span></div><small><?php echo esc($question['topic'] ?: 'Practice'); ?></small><p><?php echo esc($question['prompt']); ?></p><form method="post" class="stack-form"><input type="hidden" name="csrf" value="<?php echo esc(form_csrf()); ?>"><input type="hidden" name="step" value="<?php echo count($run['history']); ?>"><?php foreach ($question['options'] as $index=>$option): ?><label class="portal-answer"><input type="radio" name="choice" value="<?php echo $index; ?>" required><span><?php echo esc($option); ?></span></label><?php endforeach; ?><button class="button button-primary">Check answer</button></form><?php endif; ?>
<?php endif; ?></section>
<?php render_footer(['/QuizWeb/assets/js/student-portal.js']); ?>
