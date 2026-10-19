<?php
// Disposable database only; the normal QuizWeb database is never modified.
session_save_path(sys_get_temp_dir());
putenv('QUIZWEB_DB_DRIVER=mysql');
$testDatabase='quizweb_portal_test_'.bin2hex(random_bytes(8));
putenv('QUIZWEB_DB_NAME='.$testDatabase);
require_once __DIR__.'/../includes/layout.php';
require_once __DIR__.'/../includes/student_portal.php';
require_once __DIR__.'/../includes/grading_ui.php';
function portal_db_check(bool $ok,string $message):void { if (!$ok) throw new RuntimeException($message); }
function portal_db_reject(callable $fn,string $message):void { try { $fn(); } catch (InvalidArgumentException $expected) { return; } throw new RuntimeException($message); }
$server=new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4',DB_HOST,DB_PORT),DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE '.$testDatabase.' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
try {
    $teacher=register_user('Portal Teacher','teacher@portal.test','TestUser!2026','teacher');
    $student=register_user('Portal Student','student@portal.test','TestUser!2026','student');
    $outsider=register_user('Outsider','outside@portal.test','TestUser!2026','student');
    $class=['id'=>300,'teacher_id'=>$teacher['id'],'name'=>'Portal Class','subject'=>'Science','description'=>'Test','code'=>'PORTALQA','student_ids'=>[$student['id']],'quizzes'=>[],'announcements'=>[],'chat_messages'=>[],'created_at'=>now_iso(),'updated_at'=>now_iso()];
    insert_classroom_record(db(),$class);
    academic_save_meta(db(),300,['subject_code'=>'SCI300','academic_year'=>'2026–2027','semester'=>'First Semester','section'=>'QA','credit_units'=>3,'required'=>true]);
    $book=grading_empty_book();
    grading_apply_action($book,$class,$teacher,'config',['config'=>grading_templates()['balanced']['config']],[]);
    grading_apply_action($book,$class,$teacher,'item',['name'=>'Written response','category_id'=>'work','period_id'=>'prelim','max_score'=>100,'date'=>'2026-10-12','accept_responses'=>'yes','instructions'=>'Explain your reasoning','due_at'=>'2026-10-20T15:00'],[]);
    grading_write(db(),300,$book);
    portal_save_submission(db(),$student,300,'manual-1','<script>Student response</script>');
    portal_save_submission(db(),$student,300,'manual-1','Updated response');
    portal_db_check((int)db()->query('SELECT COUNT(*) FROM activity_submissions')->fetchColumn()===1,'Resubmission must update one response.');
    portal_db_reject(fn()=>portal_save_submission(db(),$outsider,300,'manual-1','Wrong classroom'),'Outsider submission accepted.');
    portal_db_reject(fn()=>portal_save_submission(db(),$teacher,300,'manual-1','Wrong role'),'Teacher submitted as student.');
    portal_db_reject(fn()=>portal_save_submission(db(),$student,300,'foreign','Invalid item'),'Foreign item accepted.');
    $activities=portal_activities([$class],(int)$student['id']);
    portal_db_check($activities[0]['status']==='Submitted','Submission status missing.');
    grading_mutate(db(),300,$teacher,'scores',['revision'=>$book['revision'],'item_id'=>'manual-1','scores'=>[$student['id']=>'0'],'feedback'=>[$student['id']=>'Explain the evidence.']]);
    portal_db_reject(fn()=>portal_save_submission(db(),$student,300,'manual-1','After grading'),'Graded response overwritten.');
    $book=grading_load(db(),300);
    grading_mutate(db(),300,$teacher,'review',['revision'=>$book['revision']]); $book=grading_load(db(),300);
    grading_mutate(db(),300,$teacher,'publish',['revision'=>$book['revision'],'confirm'=>'yes','final_confirm'=>'yes','ignore_warnings_hash'=>academic_review_hash($book,$class,[])]); $book=grading_load(db(),300);
    grading_mutate(db(),300,$teacher,'scores',['revision'=>$book['revision'],'item_id'=>'manual-1','scores'=>[$student['id']=>'77'],'reason'=>'Draft correction']); $book=grading_load(db(),300);
    $activities=portal_activities([$class],(int)$student['id']);
    portal_db_check($activities[0]['status']==='Graded' && $activities[0]['score']===0,'Unpublished scores must not leak into student activity views.');
    grading_mutate(db(),300,$teacher,'assign_terms',['revision'=>$book['revision'],'item_ids'=>['manual-1'],'period_id'=>'midterm','reason'=>'Correct term']);
    $book=grading_load(db(),300);
    portal_db_check($book['items']['manual-1']['period_id']==='midterm' && $book['published']['students'][(string)$student['id']]['items']['manual-1']['period_id']==='prelim','Bulk term assignment changed released snapshot.');
    $quiz=['id'=>1,'title'=>'Adaptive Bank','description'=>'Practice','game_type'=>'standard','adaptive_practice_enabled'=>true,'grade_category_id'=>'quiz','grade_period_id'=>'prelim','grade_max_score'=>100,'grade_attempt_policy'=>'highest','created_at'=>now_iso(),'questions'=>[['id'=>1,'prompt'=>'Medium question','options'=>['Yes','No','Other','None'],'correct_index'=>0,'points'=>10,'level'=>'medium','topic'=>'Science'],['id'=>2,'prompt'=>'Hard question','options'=>['Yes','No','Other','None'],'correct_index'=>0,'points'=>10,'level'=>'hard','topic'=>'Science']]];
    persist_builder_quiz(db(),300,(int)$teacher['id'],$quiz,true); $book=grading_load(db(),300);
    grading_mutate(db(),300,$teacher,'assign_terms',['revision'=>$book['revision'],'item_ids'=>['quiz-1'],'period_id'=>'finals','reason'=>'Move quiz']);
    portal_db_check(classroom_quiz(find_classroom(300),1)['grade_period_id']==='finals','Quiz builder period must stay synchronized.');
    $_SESSION['user_id']=$student['id']; $_SERVER['REQUEST_METHOD']='GET';
    $render=function(string $file,array $query):string {
        $_GET=$query; $_SERVER['PHP_SELF']='/QuizWeb/'.basename($file); ob_start(); require __DIR__.'/../'.$file; return ob_get_clean();
    };
    foreach (['home','activities','progress','achievements','announcements'] as $view) {
        $html=$render('app/pages/learning/student_portal.php',['view'=>$view]);
        portal_db_check(str_contains($html,'student-portal-page') && !str_contains($html,'Warning:'),'Portal view failed to render: '.$view);
    }
    $html=$render('app/pages/learning/student_portal.php',['view'=>'activities','classroom_id'=>300,'item_id'=>'manual-1']);
    portal_db_check(str_contains($html,'Updated response'),'Response missing.');
    portal_db_check(str_contains($html,'Explain your reasoning'),'Instructions missing.');
    $html=$render('grade_report.php',['year'=>'2026–2027','semester'=>'First Semester']);
    portal_db_check(str_contains($html,'0.00') && !str_contains($html,'77.00'),'Grade report leaked a draft correction.');
    $_SESSION['user_id']=$teacher['id'];
    $html=$render('app/pages/grading/gradebook.php',['classroom_id'=>300,'view'=>'assign']);
    portal_db_check(str_contains($html,'data-term-assignment'),'Bulk assignment page failed.');
    $html=$render('app/pages/grading/gradebook.php',['classroom_id'=>300,'view'=>'entry','item_id'=>'manual-1']);
    portal_db_check(str_contains($html,'Updated response') && str_contains($html,'Explain the evidence.'),'Teacher submission review failed.');
    echo "Live portal database checks passed: submission access/upsert, graded response protection, published privacy, term synchronization, all portal views, grade reports, and teacher review.\n";
} finally {
    $server->exec('DROP DATABASE IF EXISTS '.$testDatabase);
}
