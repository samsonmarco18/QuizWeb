<?php

function ensure_grading_schema(PDO $pdo): void
{
    ensure_database_schema($pdo);
}

function grading_empty_book(): array
{
    return ['config' => null, 'items' => [], 'scores' => [], 'overrides' => [], 'revision' => 0, 'next_item_id' => 1, 'published' => null, 'reviewed' => null, 'locked' => false, 'academic_statuses' => []];
}

function grading_templates(): array
{
    $templates = [];
    foreach ([
        'balanced' => ['Balanced', 'A mix of quizzes, coursework, and exams.', [30, 30, 40]],
        'coursework' => ['Coursework Focus', 'Give more weight to assignments and projects.', [20, 50, 30]],
        'exams' => ['Exam Focus', 'Give more weight to major examinations.', [20, 20, 60]],
    ] as $id => [$name, $description, $weights]) {
        $templates[$id] = ['name' => $name, 'description' => $description, 'config' => [
            'categories' => [
                ['id' => 'quiz', 'name' => 'Quizzes', 'weight' => $weights[0]],
                ['id' => 'work', 'name' => 'Assignments / Projects', 'weight' => $weights[1]],
                ['id' => 'exam', 'name' => 'Exams', 'weight' => $weights[2]],
            ],
            'scale' => [['min' => 0, 'label' => 'Below passing'], ['min' => 75, 'label' => 'Passed'], ['min' => 90, 'label' => 'Excellent']],
            'passing' => 75, 'missing_policy' => 'exclude', 'periods' => academic_periods(),
        ]];
    }
    return $templates;
}

function grading_load(PDO $pdo, int $classroomId): array
{
    $select = $pdo->prepare('SELECT data FROM gradebooks WHERE classroom_id = ?');
    $select->execute([$classroomId]);
    $data = $select->fetchColumn();
    return $data === false ? grading_empty_book() : array_replace(grading_empty_book(), db_json_decode($data));
}

function grading_number($value, float $minimum, float $maximum, string $label): float
{
    if ((!is_string($value) && !is_int($value) && !is_float($value)) || !is_numeric($value)) throw new InvalidArgumentException($label . ' is required.');
    $number = (float) $value;
    if (!is_finite($number) || $number < $minimum || $number > $maximum || abs($number * 100 - round($number * 100)) > .00001) {
        throw new InvalidArgumentException($label . ' must be between ' . $minimum . ' and ' . $maximum . ', with up to two decimal places.');
    }
    return $number;
}

function grading_text($value, string $label, int $max = 120): string
{
    if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $max) throw new InvalidArgumentException($label . ' is required (up to ' . $max . ' characters).');
    return trim($value);
}

function grading_validate_config($input, array $book): array
{
    if (!is_array($input)) throw new InvalidArgumentException('Invalid grading configuration.');
    $categories = $input['categories'] ?? null;
    if (!is_array($categories) || count($categories) < 1 || count($categories) > 8) throw new InvalidArgumentException('Create between 1 and 8 categories.');
    $normalized = []; $names = []; $weight = 0;
    foreach ($categories as $category) {
        if (!is_array($category) || !is_string($category['id'] ?? null) || !preg_match('/^[a-zA-Z0-9_-]{1,40}$/', $category['id'])) throw new InvalidArgumentException('Invalid category identifier.');
        $name = grading_text($category['name'] ?? null, 'Category name', 50);
        if (isset($normalized[$category['id']]) || in_array(mb_strtolower($name), $names, true)) throw new InvalidArgumentException('Category names and identifiers must be unique.');
        $names[] = mb_strtolower($name);
        $percentage = grading_number($category['weight'] ?? null, 0, 100, 'Category weight');
        $weight += (int) round($percentage * 100);
        $normalized[$category['id']] = ['id' => $category['id'], 'name' => $name, 'weight' => $percentage];
    }
    if ($weight !== 10000) throw new InvalidArgumentException('Category weights must total exactly 100%.');
    foreach ($book['items'] as $item) {
        if (empty($item['archived']) && !isset($normalized[$item['category_id']])) throw new InvalidArgumentException('Reassign or archive activities before removing their category.');
    }
    $scale = $input['scale'] ?? null;
    if (!is_array($scale) || count($scale) < 1 || count($scale) > 10) throw new InvalidArgumentException('Create between 1 and 10 grade-scale bands.');
    $bands = []; $starts = []; $labels = [];
    foreach ($scale as $band) {
        if (!is_array($band)) throw new InvalidArgumentException('Invalid grade-scale band.');
        $min = grading_number($band['min'] ?? null, 0, 100, 'Band minimum');
        if (in_array($min, $starts, true)) throw new InvalidArgumentException('Grade-scale minimums must be unique.');
        $starts[] = $min;
        $label = grading_text($band['label'] ?? null, 'Band label', 50);
        if (in_array(mb_strtolower($label), $labels, true)) throw new InvalidArgumentException('Grade-scale labels must be unique.');
        $labels[] = mb_strtolower($label);
        $bands[] = ['min' => $min, 'label' => $label];
    }
    usort($bands, fn($a, $b) => $a['min'] <=> $b['min']);
    if ($bands[0]['min'] !== 0.0) throw new InvalidArgumentException('The first grade-scale band must start at 0.');
    $missing = $input['missing_policy'] ?? 'exclude';
    if (!in_array($missing, ['exclude', 'zero'], true)) throw new InvalidArgumentException('Choose a supported missing-score policy.');
    $result = ['method' => 'weighted_categories', 'category_method' => 'points', 'categories' => array_values($normalized), 'scale' => $bands,
        'passing' => grading_number($input['passing'] ?? null, 0, 100, 'Passing grade'), 'missing_policy' => $missing];
    if (isset($input['periods'])) $result['periods'] = academic_validate_periods($input['periods']);
    return $result;
}

function grading_category(array $book, string $id): ?array
{
    foreach ($book['config']['categories'] ?? [] as $category) if ($category['id'] === $id) return $category;
    return null;
}

function grading_attempt_result(array $attempts, string $policy): ?array
{
    $valid = array_values(array_filter($attempts, static function ($attempt) {
        if (!is_array($attempt) || (float) ($attempt['max_score'] ?? 0) <= 0 || in_array($attempt['game_type'] ?? '', ['focus_training', 'practice'], true)
            || !empty($attempt['preview_mode']) || !empty($attempt['answers']['_preview'])) return false;
        $snapshot = $attempt['answers']['_quiz_snapshot'] ?? [];
        if (!is_array($snapshot)) return false;
        // Legacy attempts can be included when the teacher categorizes their quiz.
        return !array_key_exists('grade_category_id', $snapshot) || $snapshot['grade_category_id'] !== '';
    }));
    if (!$valid) return null;
    usort($valid, fn($a, $b) => strcmp($a['played_at'] ?? '', $b['played_at'] ?? '') ?: ((int) $a['id'] <=> (int) $b['id']));
    $percentage = static fn($attempt) => max(0, min(100, (float) $attempt['score'] / (float) $attempt['max_score'] * 100));
    if ($policy === 'average') return ['percentage' => array_sum(array_map($percentage, $valid)) / count($valid), 'attempt' => null, 'count' => count($valid)];
    $selected = $policy === 'latest' ? $valid[count($valid) - 1] : $valid[0];
    if ($policy === 'highest') foreach ($valid as $attempt) if ($percentage($attempt) > $percentage($selected)) $selected = $attempt;
    return ['percentage' => $percentage($selected), 'attempt' => $selected, 'count' => count($valid)];
}

function grading_calculate_weighted(array $book, int $studentId, array $attempts): array
{
    $config = $book['config']; $categories = []; $items = []; $pending = 0; $graded = 0;
    foreach ($config['categories'] as $category) $categories[$category['id']] = $category + ['earned' => 0.0, 'possible' => 0.0, 'items' => [], 'missing' => 0];
    foreach ($book['items'] as $id => $item) {
        if (!empty($item['archived']) || !isset($categories[$item['category_id']])) continue;
        $calculated = null; $history = []; $raw = null;
        if ($item['source'] === 'quiz') {
            $history = array_values(array_filter($attempts, fn($attempt) => (int) $attempt['student_id'] === $studentId && (int) $attempt['quiz_id'] === (int) $item['quiz_id']));
            $raw = grading_attempt_result($history, $item['attempt_policy']);
            if ($raw) $calculated = $raw['percentage'] * $item['max_score'] / 100;
        } else {
            $calculated = $book['scores'][$id][(string) $studentId] ?? null;
        }
        $override = $book['overrides'][(string) $studentId][$id] ?? null;
        $score = $override !== null ? $override['value'] : $calculated;
        $state = $score === null ? ($item['source'] === 'manual' ? 'Not Yet Graded' : 'Missing') : 'Graded';
        if ($score === null) { $pending++; $categories[$item['category_id']]['missing']++; } else $graded++;
        $row = $item + ['calculated' => $calculated, 'score' => $score, 'percentage' => $score === null ? null : $score / $item['max_score'] * 100,
            'status' => $state, 'override' => $override, 'attempt_count' => $raw['count'] ?? 0,
            'raw_score' => $raw['attempt']['score'] ?? null, 'raw_max' => $raw['attempt']['max_score'] ?? null];
        $items[$id] = $row; $categories[$item['category_id']]['items'][] = $row;
        if ($score !== null || $config['missing_policy'] === 'zero') {
            $categories[$item['category_id']]['earned'] += $score ?? 0;
            $categories[$item['category_id']]['possible'] += $item['max_score'];
        }
    }
    $weighted = 0.0; $activeWeight = 0.0;
    foreach ($categories as &$category) {
        $category['percentage'] = $category['possible'] > 0 ? $category['earned'] / $category['possible'] * 100 : null;
        $category['contribution'] = $category['percentage'] === null ? null : $category['percentage'] * $category['weight'] / 100;
        if ($category['percentage'] !== null && $category['weight'] > 0) { $weighted += $category['contribution']; $activeWeight += $category['weight']; }
    }
    unset($category);
    $calculated = $activeWeight > 0 ? round($weighted / $activeWeight * 100, 2) : null;
    $override = $book['overrides'][(string) $studentId]['overall'] ?? null;
    $overall = $override['value'] ?? $calculated;
    $label = null;
    foreach ($config['scale'] as $band) if ($overall !== null && $overall >= $band['min']) $label = $band['label'];
    foreach ($categories as &$category) $category['current_contribution'] = $category['contribution'] === null || $activeWeight <= 0 ? null : $category['contribution'] / $activeWeight * 100;
    unset($category);
    $status = !$items ? 'Not Graded' : ($pending ? ($graded ? 'Partial' : 'Missing') : 'Complete');
    return ['student_id' => $studentId, 'overall' => $overall, 'calculated' => $calculated, 'override' => $override, 'categories' => array_values($categories),
        'items' => $items, 'status' => $status, 'scale_label' => $label, 'passed' => $overall === null ? null : $overall >= $config['passing'],
        'active_weight' => $activeWeight, 'pending' => $pending, 'graded' => $graded];
}

function grading_calculate(array $book, int $studentId, array $attempts): array
{
    if (!$book['config']) throw new InvalidArgumentException('Set up the grading structure first.');
    if (!empty($book['config']['periods'])) return academic_calculate($book, $studentId, $attempts);
    $methods = ['weighted_categories' => 'grading_calculate_weighted'];
    $method = $book['config']['method'];
    if (!isset($methods[$method])) throw new RuntimeException('Unsupported grading method.');
    return $methods[$method]($book, $studentId, $attempts);
}

function grading_write(PDO $pdo, int $classroomId, array $book): void
{
    database_upsert_record($pdo, 'gradebooks', 'classroom_id', [
        'classroom_id' => $classroomId, 'data' => db_json_encode($book), 'updated_at' => now_iso(),
    ]);
}

function grading_audit(PDO $pdo, int $classroomId, int $actorId, string $action, $previous, $next, ?int $studentId = null, ?string $itemId = null): void
{
    $insert = $pdo->prepare('INSERT INTO grade_changes (classroom_id, student_id, item_id, actor_id, action, previous_value, new_value, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$classroomId, $studentId, $itemId, $actorId, $action, json_encode($previous), json_encode($next), now_iso()]);
}

function grading_require_teacher(array $classroom, array $actor): void
{
    if (($actor['role'] ?? '') !== 'teacher' || (int) $classroom['teacher_id'] !== (int) ($actor['id'] ?? 0)) throw new InvalidArgumentException('Only this classroom’s teacher can change grades.');
}

function grading_student_id(array $classroom, $value): int
{
    if ((!is_string($value) && !is_int($value)) || !ctype_digit((string) $value) || !in_array((int) $value, $classroom['student_ids'], true)) throw new InvalidArgumentException('Student is not enrolled in this classroom.');
    return (int) $value;
}

function grading_apply_action(array &$book, array $classroom, array $actor, string $action, array $input, array $attempts): array
{
    grading_require_teacher($classroom, $actor);
    if (!empty($book['locked']) && $action !== 'unlock') throw new InvalidArgumentException('Grades are locked. Unlock with a correction reason first.');
    $changes = [];
    if ($action === 'config') {
        $config = grading_validate_config($input['config'] ?? null, $book);
        if (!empty($book['config']['periods']) && empty($config['periods'])) throw new InvalidArgumentException('Keep Prelim, Midterm, and Finals once academic periods are configured.');
        $configReason = (!empty($book['scores']) || $book['published'] || $attempts) ? grading_text($input['reason'] ?? null, 'Grading configuration change reason', 500) : '';
        if (!empty($config['periods'])) {
            foreach ($book['items'] as $id => &$item) {
                if (!empty($item['archived'])) continue;
                $oldItem = $item;
                $item['period_id'] = academic_period_id($input['period_assignments'][$id] ?? $item['period_id'] ?? null);
                if ($oldItem !== $item) $changes[] = ['period_assignment', $oldItem, ['item' => $item, 'reason' => $configReason], null, $id];
            }
            unset($item);
        }
        $changes[] = ['config', $book['config'], ['config' => $config, 'reason' => $configReason], null, null]; $book['config'] = $config;
    } elseif ($action === 'item') {
        $id = is_string($input['item_id'] ?? null) ? $input['item_id'] : '';
        $old = $id !== '' ? ($book['items'][$id] ?? null) : null;
        if ($id !== '' && (!$old || $old['source'] !== 'manual')) throw new InvalidArgumentException('Manual activity not found.');
        $category = is_string($input['category_id'] ?? null) ? $input['category_id'] : '';
        if (!grading_category($book, $category)) throw new InvalidArgumentException('Choose a classroom grading category.');
        $max = grading_number($input['max_score'] ?? null, .01, 100000, 'Maximum score');
        $date = $input['date'] ?? '';
        $parsed = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Enter a valid activity date.');
        if (!$old) $id = 'manual-' . $book['next_item_id']++;
        foreach ($book['scores'][$id] ?? [] as $score) if ($score !== null && $score > $max) throw new InvalidArgumentException('Maximum score cannot be lower than an existing score.');
        foreach ($book['overrides'] as $overrides) if (($overrides[$id]['value'] ?? 0) > $max) throw new InvalidArgumentException('Maximum score cannot be lower than an adjusted score.');
        $item = ['id' => $id, 'source' => 'manual', 'name' => grading_text($input['name'] ?? null, 'Activity name'), 'category_id' => $category,
            'max_score' => $max, 'date' => $date, 'archived' => $old['archived'] ?? false];
        if (!empty($book['config']['periods'])) $item['period_id'] = academic_period_id($input['period_id'] ?? null);
        if ($old && $old !== $item && !empty($book['scores'][$id])) $item['correction_reason'] = grading_text($input['reason'] ?? null, 'Correction reason', 500);
        $book['items'][$id] = $item; $changes[] = ['item', $old, $item, null, $id];
    } elseif ($action === 'scores') {
        $id = is_string($input['item_id'] ?? null) ? $input['item_id'] : '';
        $item = $book['items'][$id] ?? null;
        if (!$item || $item['source'] !== 'manual' || !empty($item['archived'])) throw new InvalidArgumentException('Choose an active manual activity.');
        if (!is_array($input['scores'] ?? null) || count($input['scores']) > 100) throw new InvalidArgumentException('Invalid score entry batch.');
        $validated = [];
        foreach ($input['scores'] as $student => $value) {
            $student = grading_student_id($classroom, $student);
            $validated[$student] = $value === '' ? null : grading_number($value, 0, $item['max_score'], 'Student score');
        }
        foreach ($validated as $student => $score) {
            $old = $book['scores'][$id][(string) $student] ?? null;
            if ($old !== $score) {
                $reason = $old !== null ? grading_text($input['reason'] ?? null, 'Correction reason', 500) : '';
                $book['scores'][$id][(string) $student] = $score; $changes[] = ['score', $old, ['value' => $score, 'reason' => $reason], $student, $id];
            }
        }
    } elseif ($action === 'archive') {
        $id = is_string($input['item_id'] ?? null) ? $input['item_id'] : '';
        if ($id === 'overall') throw new InvalidArgumentException('Final grades are calculated from assessments. Correct an assessment instead.');
        if (!isset($book['items'][$id])) throw new InvalidArgumentException('Activity not found.');
        if (($input['confirm'] ?? '') !== 'yes') throw new InvalidArgumentException('Confirm archiving this activity.');
        $old = $book['items'][$id]; $book['items'][$id]['archived'] = true;
        $changes[] = ['archive', $old, $book['items'][$id], null, $id];
    } elseif (in_array($action, ['override', 'restore'], true)) {
        $student = grading_student_id($classroom, $input['student_id'] ?? '');
        $id = is_string($input['item_id'] ?? null) ? $input['item_id'] : '';
        if ($id === 'overall' && $action === 'override') throw new InvalidArgumentException('Final grades are calculated from assessments. Correct an assessment instead.');
        $item = $book['items'][$id] ?? null;
        if ($id !== 'overall' && (!$item || !empty($item['archived']))) throw new InvalidArgumentException('Grade item not found.');
        $old = $book['overrides'][(string) $student][$id] ?? null;
        $next = null;
        if ($action === 'override') {
            $grade = grading_calculate($book, $student, $attempts);
            $reason = grading_text($input['reason'] ?? null, 'Correction reason', 500);
            if (mb_strlen($reason) > 500) throw new InvalidArgumentException('Keep adjustment notes under 500 characters.');
            $next = ['value' => grading_number($input['value'] ?? null, 0, $id === 'overall' ? 100 : $item['max_score'], 'Adjusted grade'),
                'calculated_at_change' => $id === 'overall' ? $grade['calculated'] : ($grade['items'][$id]['calculated'] ?? null),
                'actor_id' => (int) $actor['id'], 'changed_at' => now_iso(), 'reason' => $reason];
            $book['overrides'][(string) $student][$id] = $next;
        } else { $reason = grading_text($input['reason'] ?? null, 'Correction reason', 500); unset($book['overrides'][(string) $student][$id]); $next = ['value' => null, 'reason' => $reason]; }
        $changes[] = [$action, $old, $next, $student, $id];
    } elseif ($action === 'review') {
        if (!$book['config']) throw new InvalidArgumentException('Set up grading first.');
        $book['reviewed'] = ['hash' => academic_review_hash($book, $classroom, $attempts), 'at' => now_iso(), 'actor_id' => (int) $actor['id']];
        $changes[] = ['review', null, $book['reviewed'], null, null];
    } elseif ($action === 'lock') {
        if (!$book['published'] || ($input['confirm'] ?? '') !== 'yes') throw new InvalidArgumentException('Publish grades and confirm finalizing first.');
        if (($book['published']['hash'] ?? '') !== academic_review_hash($book, $classroom, $attempts)) throw new InvalidArgumentException('Draft grades changed. Review and publish them again before finalizing.');
        foreach ($book['published']['students'] as $row) if (($row['special_status'] ?? 'enrolled') === 'enrolled' && empty($row['complete'])) throw new InvalidArgumentException('All grading periods and weighted categories must be complete before finalizing.');
        $book['locked'] = true; $book['published']['finalized'] = true;
        $changes[] = ['lock', false, true, null, null];
    } elseif ($action === 'unlock') {
        $reason = grading_text($input['reason'] ?? null, 'Correction reason', 500);
        $book['locked'] = false; if ($book['published']) $book['published']['finalized'] = false;
        $changes[] = ['unlock', true, ['locked' => false, 'reason' => $reason], null, null];
    } elseif ($action === 'academic_status') {
        $student = grading_student_id($classroom, $input['student_id'] ?? '');
        $status = $input['academic_status'] ?? '';
        if (!in_array($status, ['enrolled', 'incomplete', 'withdrawn'], true)) throw new InvalidArgumentException('Choose a supported academic status.');
        $reason = grading_text($input['reason'] ?? null, 'Status reason', 500);
        $old = $book['academic_statuses'][(string) $student] ?? null;
        $book['academic_statuses'][(string) $student] = ['status' => $status, 'reason' => $reason];
        $changes[] = ['academic_status', $old, $book['academic_statuses'][(string) $student], $student, null];
    } elseif ($action === 'publish') {
        if (!$book['config'] || ($input['confirm'] ?? '') !== 'yes') throw new InvalidArgumentException('Confirm publishing this grade release.');
        $hash = academic_review_hash($book, $classroom, $attempts);
        if (!empty($book['config']['periods']) && ($book['reviewed']['hash'] ?? '') !== $hash) throw new InvalidArgumentException('Review the current calculated grades before publishing.');
        $rows = [];
        foreach ($classroom['student_ids'] as $student) $rows[(string) $student] = grading_calculate($book, (int) $student, $attempts);
        $old = $book['published'];
        $book['published'] = ['at' => now_iso(), 'actor_id' => (int) $actor['id'], 'config' => $book['config'], 'students' => $rows, 'hash' => $hash, 'finalized' => false];
        $changes[] = ['publish', $old, $book['published'], null, null];
    } elseif ($action === 'unpublish') {
        if (($input['confirm'] ?? '') !== 'yes') throw new InvalidArgumentException('Confirm hiding the published grades.');
        $changes[] = ['unpublish', $book['published'], null, null, null]; $book['published'] = null;
    } else throw new InvalidArgumentException('Unknown gradebook action.');
    if (!in_array($action, ['review', 'publish', 'lock'], true)) $book['reviewed'] = null;
    $book['revision']++;
    return $changes;
}

function grading_mutate(PDO $pdo, int $classroomId, array $actor, string $action, array $input): void
{
    $pdo->beginTransaction();
    try {
        $select = $pdo->prepare('SELECT * FROM classrooms WHERE id = ? FOR UPDATE'); $select->execute([$classroomId]);
        $record = $select->fetch(); if (!$record) throw new InvalidArgumentException('Classroom not found.');
        $classroom = hydrate_classroom($record); grading_require_teacher($classroom, $actor);
        $book = grading_load($pdo, $classroomId);
        if (filter_var($input['revision'] ?? null, FILTER_VALIDATE_INT) !== $book['revision']) throw new InvalidArgumentException('The gradebook changed in another window. Reload before saving.');
        $query = $pdo->prepare('SELECT * FROM attempts WHERE classroom_id = ? ORDER BY id'); $query->execute([$classroomId]);
        $attempts = array_map('hydrate_attempt', $query->fetchAll());
        $changes = grading_apply_action($book, $classroom, $actor, $action, $input, $attempts);
        grading_write($pdo, $classroomId, $book);
        foreach ($changes as [$type, $old, $next, $student, $item]) grading_audit($pdo, $classroomId, (int) $actor['id'], $type, $old, $next, $student, $item);
        $pdo->commit();
    } catch (Throwable $error) { $pdo->rollBack(); throw $error; }
}

function grading_sync_quiz(PDO $pdo, int $classroomId, int $actorId, array $quiz): void
{
    // Caller holds the classroom lock, and this runs inside the quiz transaction.
    $book = grading_load($pdo, $classroomId); $category = $quiz['grade_category_id'] ?? '';
    if (!empty($book['locked']) && ($category !== '' || isset($book['items']['quiz-' . (int) $quiz['id']]))) throw new InvalidArgumentException('Unlock the gradebook with a correction reason before changing graded activities.');
    $id = 'quiz-' . (int) $quiz['id']; $old = $book['items'][$id] ?? null;
    $correctionReason = '';
    if ($old && (($old['category_id'] ?? '') !== $category || ($old['max_score'] ?? null) != ($quiz['grade_max_score'] ?? null)
        || ($old['attempt_policy'] ?? '') !== ($quiz['grade_attempt_policy'] ?? '') || ($old['period_id'] ?? '') !== ($quiz['grade_period_id'] ?? ''))) {
        $recorded = $pdo->prepare('SELECT COUNT(*) FROM attempts WHERE classroom_id = ? AND quiz_id = ?'); $recorded->execute([$classroomId, (int) $quiz['id']]);
        if ($recorded->fetchColumn()) $correctionReason = grading_text($quiz['grade_correction_reason'] ?? null, 'Quiz grading correction reason', 500);
    }
    if ($category === '') {
        if (!$old) return;
        $book['items'][$id]['archived'] = true;
    } else {
        if (!grading_category($book, $category)) throw new InvalidArgumentException('The grading category changed. Reload the quiz builder.');
        $max = grading_number($quiz['grade_max_score'] ?? null, .01, 100000, 'Grade maximum score');
        foreach ($book['overrides'] as $overrides) if (($overrides[$id]['value'] ?? 0) > $max) throw new InvalidArgumentException('Restore or reduce adjusted scores before reducing the maximum score.');
        $policy = $quiz['grade_attempt_policy'] ?? 'highest';
        if (!in_array($policy, ['highest', 'latest', 'first', 'average'], true)) throw new InvalidArgumentException('Invalid attempt grading policy.');
        $book['items'][$id] = ['id' => $id, 'source' => 'quiz', 'quiz_id' => (int) $quiz['id'], 'name' => $quiz['title'], 'category_id' => $category,
            'max_score' => $max, 'date' => substr($quiz['created_at'], 0, 10), 'attempt_policy' => $policy, 'archived' => false];
        if (!empty($book['config']['periods'])) $book['items'][$id]['period_id'] = academic_period_id($quiz['grade_period_id'] ?? null);
    }
    $book['reviewed'] = null; $book['revision']++; grading_write($pdo, $classroomId, $book);
    grading_audit($pdo, $classroomId, $actorId, 'quiz_grading', $old, ['item' => $book['items'][$id], 'reason' => $correctionReason], null, $id);
}

function grading_published_student(array $book, array $classroom, array $user): ?array
{
    if (($user['role'] ?? '') !== 'student' || !classroom_belongs_to_user($classroom, $user)) throw new InvalidArgumentException('You cannot view these grades.');
    return $book['published']['students'][(string) $user['id']] ?? null;
}
