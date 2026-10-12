<?php
/** Reward totals are derived from saved attempts: retries cannot duplicate XP. */
function portal_rewards(array $attempts): array {
    $best = []; $days = []; $mastered = []; $improved = false; $first = [];
    usort($attempts, fn($a, $b) => [$a['played_at'], $a['id']] <=> [$b['played_at'], $b['id']]);
    foreach ($attempts as $attempt) {
        if (!empty($attempt['answers']['_disqualified']) || $attempt['max_score'] <= 0) continue;
        $percent = $attempt['score'] / $attempt['max_score'] * 100;
        if ((int) $attempt['classroom_id'] === 0) {
            $day = substr($attempt['played_at'], 0, 10);
            $days[$day] = true;
            continue;
        }
        $key = $attempt['classroom_id'] . ':' . $attempt['quiz_id'];
        $first[$key] ??= $percent;
        if ($percent >= $first[$key] + 15) $improved = true;
        $best[$key] = max($best[$key] ?? 0, $percent);
        if ($percent >= 90) $mastered[$key] = true;
    }
    $xp = count($days) * 10;
    foreach ($best as $percent) $xp += 20 + (int) floor($percent / 10);
    $badges = [
        ['id' => 'first', 'name' => 'First Assessment', 'description' => 'Complete one classroom assessment.', 'earned' => count($best) > 0, 'cell' => 0],
        ['id' => 'master', 'name' => 'Quiz Master', 'description' => 'Reach 90% on three classroom quizzes.', 'earned' => count($mastered) >= 3, 'cell' => 1],
        ['id' => 'improve', 'name' => 'Growing Strong', 'description' => 'Improve a quiz result by 15 percentage points.', 'earned' => $improved, 'cell' => 2],
        ['id' => 'practice', 'name' => 'Practice Habit', 'description' => 'Finish practice on five different days.', 'earned' => count($days) >= 5, 'cell' => 3],
    ];
    return ['xp' => $xp, 'level' => intdiv($xp, 100) + 1, 'progress' => $xp % 100, 'badges' => $badges, 'completed' => count($best)];
}

function portal_activity_status(bool $recorded, bool $submitted, ?string $due, bool $started = false): string {
    if ($recorded) return 'Graded';
    if ($submitted) return 'Submitted';
    if ($due && strtotime($due) !== false && strtotime($due) < time()) return 'Overdue';
    return $started ? 'In progress' : 'Not started';
}

function portal_weekly_leaderboard(array $students, array $attempts): array {
    $start = strtotime('monday this week 00:00:00'); $rows=[];
    foreach ($students as $student) {
        $own=array_values(array_filter($attempts,fn($attempt)=>(int)$attempt['student_id']===(int)$student['id'] && strtotime($attempt['played_at']) >= $start));
        $reward=portal_rewards($own); $graded=array_filter($own,fn($attempt)=>empty($attempt['answers']['_disqualified']) && $attempt['max_score']>0);
        $percentages=array_map(fn($attempt)=>$attempt['score']/$attempt['max_score']*100,$graded);
        $rows[]=['student'=>$student,'total_score'=>$reward['xp'],'average_percent'=>$percentages?round(array_sum($percentages)/count($percentages),2):0,'rank'=>0];
    }
    usort($rows,fn($a,$b)=>[$b['total_score'],$a['student']['name'],$a['student']['id']]<=>[$a['total_score'],$b['student']['name'],$b['student']['id']]);
    foreach ($rows as $index=>&$row) $row['rank']=$index+1;
    unset($row); return $rows;
}

function portal_activities(array $classes, int $studentId): array {
    $result = []; $pdo = db();
    foreach ($classes as $class) {
        $book = grading_load($pdo, (int) $class['id']);
        $published = grading_published_student($book, $class, ['id' => $studentId, 'role' => 'student']);
        $query = $pdo->prepare('SELECT * FROM activity_submissions WHERE classroom_id = ? AND student_id = ?'); $query->execute([$class['id'], $studentId]);
        $submissions = array_column($query->fetchAll(), null, 'item_id');
        $runs = $pdo->prepare('SELECT quiz_id FROM quiz_runs WHERE classroom_id = ? AND student_id = ? AND completed_at IS NULL'); $runs->execute([$class['id'], $studentId]);
        $started = array_flip($runs->fetchAll(PDO::FETCH_COLUMN));
        $attempts = classroom_attempts((int) $class['id']);
        $own = array_values(array_filter($attempts, fn($attempt) => (int) $attempt['student_id'] === $studentId));
        $quizzes = array_column($class['quizzes'], null, 'id');
        $items = array_filter($book['items'], fn($item) => empty($item['archived']));
        foreach ($class['quizzes'] as $quiz) if (!isset($items['quiz-' . $quiz['id']]) && empty($book['items']['quiz-' . $quiz['id']]['archived'])) $items['quiz-' . $quiz['id']] = ['id' => 'quiz-' . $quiz['id'], 'name' => $quiz['title'], 'source' => 'quiz', 'quiz_id' => $quiz['id'], 'category_id' => '', 'period_id' => $quiz['grade_period_id'] ?? ''];
        foreach ($items as $id => $item) {
            $quiz = $item['source'] === 'quiz' ? ($quizzes[$item['quiz_id']] ?? null) : null;
            if ($item['source'] === 'quiz' && !$quiz) continue;
            $last = null;
            if ($quiz) foreach ($own as $attempt) if ((int) $attempt['quiz_id'] === (int) $quiz['id'] && (!$last || (int) $attempt['id'] > (int) $last['id'])) $last = $attempt;
            $releasedScore = $published['items'][$id]['score'] ?? null;
            $due = $quiz['due_at'] ?? $item['due_at'] ?? null;
            $status = portal_activity_status($quiz ? $last !== null : $releasedScore !== null, isset($submissions[$id]), $due, $quiz && isset($started[$quiz['id']]));
            if (!$quiz && empty($item['accept_responses']) && $releasedScore === null) $status='Awaiting grading';
            $result[] = ['id' => $id, 'classroom' => $class, 'meta' => academic_meta($class), 'name' => $item['name'], 'component' => grading_category($book, $item['category_id'])['name'] ?? 'Quiz', 'period' => $item['period_id'] ?? '', 'due' => $due, 'status' => $status, 'source' => $item['source'], 'instructions'=>$item['instructions'] ?? '', 'accept_responses'=>!empty($item['accept_responses']), 'score' => $quiz ? ($last['score'] ?? null) : $releasedScore, 'maximum' => $quiz ? ($last['max_score'] ?? null) : ($item['max_score'] ?? null), 'locked' => !empty($book['locked']), 'url' => $quiz ? ($last ? '/QuizWeb/results.php?id=' . $last['id'] : '/QuizWeb/play.php?classroom_id=' . $class['id'] . '&quiz_id=' . $quiz['id']) : '/QuizWeb/student_portal.php?view=activities&classroom_id=' . $class['id'] . '&item_id=' . rawurlencode($id), 'submission' => $submissions[$id] ?? null];
        }
    }
    usort($result, fn($a, $b) => [in_array($a['status'], ['Graded','Submitted']), $a['due'] ?? '9999', $a['name']] <=> [in_array($b['status'], ['Graded','Submitted']), $b['due'] ?? '9999', $b['name']]);
    return $result;
}

function portal_save_submission(PDO $pdo, array $student, int $classroomId, string $itemId, string $response): void {
    if ($student['role'] !== 'student' || trim($response) === '' || mb_strlen($response) > 10000) throw new InvalidArgumentException('Enter a response of 1–10,000 characters.');
    $pdo->beginTransaction();
    try {
        $query = $pdo->prepare('SELECT * FROM classrooms WHERE id = ? FOR UPDATE'); $query->execute([$classroomId]);
        $record = $query->fetch(); $class = $record ? hydrate_classroom($record) : null;
        if (!$class || !classroom_belongs_to_user($class, $student)) throw new InvalidArgumentException('Classroom access denied.');
        $book = grading_load($pdo, $classroomId); $item = $book['items'][$itemId] ?? null;
        if (!$item || $item['source'] !== 'manual' || empty($item['accept_responses']) || !empty($item['archived']) || !empty($book['locked'])) throw new InvalidArgumentException('This activity is unavailable or locked.');
        if (isset($book['scores'][$itemId][(string) $student['id']]) || isset($book['overrides'][(string) $student['id']][$itemId])) throw new InvalidArgumentException('This response has already been graded.');
        $sql = 'INSERT INTO activity_submissions (classroom_id,item_id,student_id,response,submitted_at) VALUES (?,?,?,?,?) ' . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'ON DUPLICATE KEY UPDATE response=VALUES(response),submitted_at=VALUES(submitted_at)' : 'ON CONFLICT (classroom_id,item_id,student_id) DO UPDATE SET response=EXCLUDED.response,submitted_at=EXCLUDED.submitted_at');
        $pdo->prepare($sql)->execute([$classroomId,$itemId,$student['id'],trim($response),now_iso()]);
        $pdo->commit();
    } catch (Throwable $error) { $pdo->rollBack(); throw $error; }
}
