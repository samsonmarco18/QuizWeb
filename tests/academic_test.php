<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php'; require_once __DIR__ . '/../includes/participation.php';
function academic_check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function academic_reject(callable $action, string $message): void { try { $action(); } catch (InvalidArgumentException $ex) { return; } throw new RuntimeException($message); }
$book = grading_empty_book(); $class = ['id' => 1, 'teacher_id' => 5, 'student_ids' => [1,2]]; $teacher = ['id' => 5, 'role' => 'teacher'];
$config = grading_templates()['balanced']['config'];
grading_apply_action($book, $class, $teacher, 'config', ['config' => $config], []);
$scores = ['prelim' => [80,90,70], 'midterm' => [90,80,85], 'finals' => [100,95,90]];
foreach ($scores as $period => $values) foreach ($book['config']['categories'] as $i => $category) {
    grading_apply_action($book, $class, $teacher, 'item', ['name' => $period . '-' . $category['id'], 'category_id' => $category['id'], 'period_id' => $period, 'max_score' => 100, 'date' => '2026-10-09'], []);
    $id = 'manual-' . ($book['next_item_id'] - 1);
    grading_apply_action($book, $class, $teacher, 'scores', ['item_id' => $id, 'scores' => [1 => (string) $values[$i], 2 => '']], []);
}
$grade = grading_calculate($book, 1, []);
academic_check($grade['periods']['prelim']['overall'] === 79.0 && $grade['periods']['midterm']['overall'] === 85.0 && $grade['periods']['finals']['overall'] === 94.5 && $grade['overall'] === 87.0, 'Two-level weighted grading is wrong.');
academic_check($grade['complete'] && $grade['status'] === 'Complete', 'Fully assessed subject should be complete.');
academic_check(grading_calculate($book, 2, [])['overall'] === null, 'Unrecorded scores must not create a grade.');
$bad = $config; $bad['periods'][2]['weight'] = 39.99;
academic_reject(fn() => grading_validate_config($bad, $book), 'Incorrect period total accepted.');
academic_reject(function () use (&$book,$class,$teacher) { grading_apply_action($book,$class,$teacher,'item',['name'=>'No period','category_id'=>'quiz','max_score'=>100,'date'=>'2026-10-09'],[]); }, 'Assessment without period accepted.');
academic_reject(function () use (&$book,$class,$teacher) { grading_apply_action($book,$class,$teacher,'override',['student_id'=>1,'item_id'=>'overall','value'=>100,'reason'=>'Bypass'],[]); }, 'Direct final entry accepted.');
academic_reject(function () use (&$book,$class,$teacher) { grading_apply_action($book,$class,$teacher,'scores',['item_id'=>'manual-1','scores'=>[1=>'99']],[]); }, 'Score correction without reason accepted.');
academic_reject(function () use (&$book,$class,$teacher) { grading_apply_action($book,$class,$teacher,'publish',['confirm'=>'yes'],[]); }, 'Unreviewed academic release accepted.');
grading_apply_action($book,$class,$teacher,'review',[],[]);
grading_apply_action($book,$class,$teacher,'publish',['confirm'=>'yes'],[]);
academic_check(grading_published_student($book,$class,['id'=>1,'role'=>'student'])['overall'] === 87.0, 'Published snapshot wrong.');
academic_reject(function () use (&$book,$class,$teacher) { grading_apply_action($book,$class,$teacher,'lock',['confirm'=>'yes'],[]); }, 'Incomplete class finalized.');
grading_apply_action($book,$class,$teacher,'academic_status',['student_id'=>2,'academic_status'=>'incomplete','reason'=>'Awaiting assessment'],[]);
grading_apply_action($book,$class,$teacher,'review',[],[]); grading_apply_action($book,$class,$teacher,'publish',['confirm'=>'yes'],[]);
grading_apply_action($book,$class,$teacher,'lock',['confirm'=>'yes'],[]);
academic_check($book['locked'] && $book['published']['finalized'], 'Finalization did not lock grades.');
academic_reject(function () use (&$book,$class,$teacher) { grading_apply_action($book,$class,$teacher,'scores',['item_id'=>'manual-1','scores'=>[1=>'99'],'reason'=>'Locked edit'],[]); }, 'Locked grades edited.');
academic_reject(function () use (&$book,$class,$teacher) { grading_apply_action($book,$class,$teacher,'unlock',[],[]); }, 'Unlock without reason accepted.');
grading_apply_action($book,$class,$teacher,'unlock',['reason'=>'Recheck assessment'],[]);
academic_check(!$book['locked'] && !$book['published']['finalized'], 'Reopened grades still count as finalized.');
grading_apply_action($book,$class,$teacher,'review',[],[]);
grading_apply_action($book,$class,$teacher,'scores',['item_id'=>'manual-1','scores'=>[1=>'90'],'reason'=>'Scoring correction'],[]);
academic_reject(function () use (&$book,$class,$teacher) { grading_apply_action($book,$class,$teacher,'publish',['confirm'=>'yes'],[]); }, 'Changed grades published without new review.');
academic_check($book['published']['students'][1]['overall'] === 87.0, 'Draft correction changed published snapshot.');

$settings = academic_default_settings();
$subject = ['meta'=>['required'=>true,'credit_units'=>3], 'grade'=>['overall'=>80,'complete'=>true,'special_status'=>'enrolled'], 'finalized'=>true];
$other = $subject; $other['grade']['overall'] = 90; $other['meta']['credit_units'] = 1;
academic_check(academic_semester_average([$subject,$other],$settings)['value'] === 85.0, 'Requested mean incorrectly weighted credit units.');
$other['finalized'] = false; academic_check(academic_semester_average([$subject,$other],$settings)['value'] === null, 'Unfinalized subject averaged.');
$other['finalized'] = true; $other['grade']['special_status']='withdrawn';
academic_check(academic_semester_average([$subject,$other],$settings)['value'] === null, 'Unresolved status averaged.');
$settings['status_policy']['withdrawn']='exclude'; academic_check(academic_semester_average([$subject,$other],$settings)['value']===80.0,'Withdrawal exclusion ignored.');
$settings['status_policy']['withdrawn']='zero'; academic_check(academic_semester_average([$subject,$other],$settings)['value']===40.0,'Withdrawal zero policy ignored.');
$other['grade']['special_status']='enrolled'; $settings['average_method']='credit_gpa'; $settings['gpa_scale']=[['min'=>0,'value'=>0],['min'=>75,'value'=>2],['min'=>90,'value'=>1]];
academic_check(academic_semester_average([$subject,$other],$settings)['value'] === 1.75, 'Configured GPA equivalents or credit weights ignored.');
$settings['gpa_scale']=[]; academic_check(academic_semester_average([$subject,$other],$settings)['value']===null,'Percentage used as unconfigured GPA.');

$now=strtotime('2026-10-09T12:00:00+08:00'); $quiz=['id'=>2,'due_at'=>'2026-10-09T11:00:00+08:00'];
$students=array_map(fn($id)=>['id'=>$id,'name'=>'Student '.$id],[1,2,3,4]);
$attempts=[['id'=>1,'student_id'=>1,'quiz_id'=>2,'score'=>8,'max_score'=>10,'played_at'=>'2026-10-09T10:00:00+08:00'],['id'=>2,'student_id'=>1,'quiz_id'=>2,'score'=>9,'max_score'=>10,'played_at'=>'2026-10-09T11:30:00+08:00']];
$runs=[['student_id'=>2,'quiz_id'=>2,'last_seen'=>'2026-10-09T11:59:00+08:00','completed_at'=>null],['student_id'=>3,'quiz_id'=>2,'last_seen'=>'2026-10-09T11:00:00+08:00','completed_at'=>null]];
$participation=participation_rows($class,$quiz,$students,$attempts,$runs,$now);
academic_check($participation[0]['status']==='Completed' && $participation[0]['count']===2 && $participation[0]['score']===9 && $participation[0]['late'],'Retakes, scores, or late submission tracked incorrectly.');
academic_check($participation[1]['status']==='Taking Quiz' && $participation[2]['status']==='Overdue / Missing','Live run or stale activity state incorrect.');
$quiz['due_at']=''; academic_check(participation_rows($class,$quiz,$students,$attempts,$runs,$now)[3]['status']==='Not Taken','No-deadline participation wrong.');
echo "Academic periods, weighted finals, correction reasons, publication snapshots, review/lock integrity, semester mean/GPA eligibility, special statuses, and real participation states passed.\n";
