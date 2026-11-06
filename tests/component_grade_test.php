<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
function component_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function component_reject(callable $action): void { try { $action(); } catch (InvalidArgumentException $e) { return; } throw new RuntimeException('Expected validation rejection.'); }
$book = grading_empty_book(); $class = ['id'=>1,'teacher_id'=>5,'student_ids'=>[1,2]]; $teacher=['id'=>5,'role'=>'teacher'];
grading_apply_action($book,$class,$teacher,'config',['config'=>grading_templates()['balanced']['config']],[]);
grading_apply_action($book,$class,$teacher,'add_component',['name'=>'Class Participation','weight'=>'10','component_weights'=>['quiz'=>'30','work'=>'20','exam'=>'40'],'reason'=>'Participation has its own weight.'],[]);
$participation = $book['config']['categories'][3]['id'];
component_check(count($book['config']['categories'])===4,'New component must create its own weighted category.');
$save=function($period,$scores,$reason='') use (&$book,$class,$teacher) { return grading_apply_action($book,$class,$teacher,'component_scores',['period_id'=>$period,'component_scores'=>$scores,'reason'=>$reason],[]); };
$save('prelim',[1=>['quiz'=>'80','work'=>'90','exam'=>'70',$participation=>'100'],2=>[$participation=>'0']]);
$row=grading_calculate($book,1,[]);
component_check(abs($row['periods']['prelim']['overall']-80)<.001,'Participation must contribute 10 percent to selected period.');
component_check($row['periods']['prelim']['status']==='Complete','Manual components can complete a period without activities.');
component_check($row['periods']['midterm']['overall']===null&&$row['overall']===null,'Period scores must never leak into other periods or create a premature final.');
component_check(grading_calculate($book,2,[])['periods']['prelim']['categories'][3]['percentage']===0.0,'Zero is a recorded grade.');
$before=$book; component_reject(fn()=>$save('prelim',[1=>['quiz'=>'95',$participation=>'101']],'Correction'));component_check($book===$before,'Invalid cell must roll back the whole grid.');
component_reject(fn()=>$save('prelim',[1=>['quiz'=>'-1']])); component_reject(fn()=>$save('prelim',[99=>['quiz'=>'50']]));
component_reject(fn()=>$save('prelim',[1=>['unknown'=>'50']]));component_reject(fn()=>$save('prelim',[1=>['quiz'=>'95']]));
$save('prelim',[1=>['quiz'=>'95']],'Correct a transcription.');
component_check(grading_calculate($book,1,[])['periods']['prelim']['overall']===84.5,'Corrected component updates selected period.');
$save('prelim',[1=>['quiz'=>'']],'Restore calculated grade.');
component_check(grading_calculate($book,1,[])['periods']['prelim']['categories'][0]['percentage']===null,'Clearing a manual grade restores ungraded without activities.');
$save('prelim',[1=>['quiz'=>'80']]);
foreach(['midterm','finals'] as $period) $save($period,[1=>['quiz'=>'80','work'=>'90','exam'=>'70',$participation=>'100']]);
component_check(grading_calculate($book,1,[])['overall']===80.0,'Manual components feed semester calculation.');
$book['items']['quiz-1']=['id'=>'quiz-1','quiz_id'=>1,'source'=>'quiz','name'=>'Quiz','category_id'=>'quiz','period_id'=>'prelim','max_score'=>100,'attempt_policy'=>'first'];
$attempts=[['id'=>1,'quiz_id'=>1,'student_id'=>1,'score'=>20,'max_score'=>100,'game_type'=>'standard','played_at'=>'2026-11-06','answers'=>[]]];
component_check(grading_calculate($book,1,$attempts)['periods']['prelim']['categories'][0]['percentage']===80.0,'Manual grade replaces automatic percentage without double counting.');
$save('prelim',[1=>['quiz'=>'']],'Use quiz calculation.');
component_check(grading_calculate($book,1,$attempts)['periods']['prelim']['categories'][0]['percentage']===20.0,'Clearing restores quiz percentage.');
component_reject(fn()=>grading_apply_action($book,$class,['id'=>1,'role'=>'student'],'component_scores',['period_id'=>'prelim','component_scores'=>[1=>['quiz'=>'100']]],[]));
$book['locked']=true;component_reject(fn()=>$save('prelim',[1=>['quiz'=>'90']]));
echo "Dynamic component, participation weighting, direct grid save, validation, atomicity, period isolation, zero, correction, automatic restore and locks passed.\n";
