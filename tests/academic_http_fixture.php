<?php
if (PHP_SAPI !== 'cli' || !preg_match('/^chalk_academic_qa_[a-f0-9]{12}$/D', getenv('QUIZWEB_DB_NAME') ?: '')) { http_response_code(404); exit(1); }
session_save_path(sys_get_temp_dir()); require_once __DIR__ . '/../includes/app.php';
$server = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (($argv[1] ?? '') === 'cleanup') { $server->exec('DROP DATABASE IF EXISTS ' . DB_NAME); exit; }
$server->exec('CREATE DATABASE ' . DB_NAME . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
$teacher = register_user('Academic Teacher', 'academic-teacher@example.test', 'TestUser!2026', 'teacher');
$student = register_user('Academic Student', 'academic-student@example.test', 'TestUser!2026', 'student');
$peer = register_user('Academic Peer', 'academic-peer@example.test', 'TestUser!2026', 'student');
$outsider = register_user('Academic Outsider', 'academic-outsider@example.test', 'TestUser!2026', 'student');
$otherTeacher = register_user('Other Teacher', 'academic-other@example.test', 'TestUser!2026', 'teacher');
$admin = register_user('Academic Admin', 'academic-admin@example.test', 'TestUser!2026', 'admin');
$quiz = ['id'=>9,'title'=>'Participation Quiz','description'=>'Tracking fixture','game_type'=>'standard','grade_category_id'=>'','questions'=>[['id'=>1,'prompt'=>'Two plus two?','options'=>['4','3','2','1'],'correct_index'=>0,'points'=>10]],'due_at'=>date(DATE_ATOM,time()-3600),'created_at'=>now_iso()];
foreach ([200=>80,201=>90,202=>70,203=>null] as $id=>$score) {
    $class = ['id'=>$id,'teacher_id'=>$teacher['id'],'name'=>'Academic Class '.$id,'subject'=>'Subject '.$id,'description'=>'Academic test fixture','code'=>'QA'.$id,'student_ids'=>[$student['id'],$peer['id']],'quizzes'=>[$quiz],'announcements'=>[],'chat_messages'=>[],'created_at'=>now_iso(),'updated_at'=>now_iso()];
    insert_classroom_record(db(),$class);
    academic_save_meta(db(),$id,['subject_code'=>'IT'.$id,'academic_year'=>'2026–2027','semester'=>$id===202?'Second Semester':'First Semester','section'=>'QA','credit_units'=>3,'required'=>$id!==203]);
    $book=grading_empty_book(); $book['config']=grading_validate_config(grading_templates()['balanced']['config'],$book);
    if ($score!==null) foreach (academic_periods() as $period) foreach ($book['config']['categories'] as $category) {
        grading_apply_action($book,$class,$teacher,'item',['name'=>$period['name'].' '.$category['name'],'period_id'=>$period['id'],'category_id'=>$category['id'],'max_score'=>100,'date'=>'2026-10-09'],[]);
        $item='manual-'.($book['next_item_id']-1);
        grading_apply_action($book,$class,$teacher,'scores',['item_id'=>$item,'scores'=>[$student['id']=>(string)$score,$peer['id']=>'95']],[]);
    }
    if ($score!==null) { grading_apply_action($book,$class,$teacher,'review',[],[]); grading_apply_action($book,$class,$teacher,'publish',['confirm'=>'yes'],[]); }
    grading_write(db(),$id,$book);
}
echo json_encode(['teacher'=>$teacher['id'],'student'=>$student['id'],'peer'=>$peer['id'],'outsider'=>$outsider['id']]);
