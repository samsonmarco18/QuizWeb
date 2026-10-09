<?php

function academic_default_settings(): array {
    $year = (int) date('Y');
    return ['years' => [($year - 1) . '–' . $year, $year . '–' . ($year + 1)],
        'semesters' => ['First Semester', 'Second Semester', 'Summer'], 'average_method' => 'mean',
        'gpa_scale' => [], 'status_policy' => ['incomplete' => 'pending', 'withdrawn' => 'pending']];
}
function academic_settings(?PDO $pdo = null): array {
    $query = ($pdo ?? db())->query("SELECT data FROM academic_settings WHERE id = 'institution'");
    $raw = $query->fetchColumn();
    return array_replace(academic_default_settings(), $raw === false ? [] : db_json_decode($raw));
}
function academic_validate_settings(array $input): array {
    $out = academic_default_settings();
    foreach (['years', 'semesters'] as $key) {
        $values = $input[$key] ?? [];
        if (!is_array($values) || !$values || count($values) > 30) throw new InvalidArgumentException('Configure 1–30 academic ' . $key . '.');
        $out[$key] = array_values(array_unique(array_map(fn($v) => grading_text($v, 'Academic term', 80), $values)));
    }
    $out['average_method'] = $input['average_method'] ?? 'mean';
    if (!in_array($out['average_method'], ['mean', 'credit_gpa'], true)) throw new InvalidArgumentException('Choose a supported semester calculation.');
    $out['gpa_scale'] = [];
    foreach ($input['gpa_scale'] ?? [] as $band) {
        $out['gpa_scale'][] = ['min' => grading_number($band['min'] ?? null, 0, 100, 'Scale minimum'), 'value' => grading_number($band['value'] ?? null, 0, 10, 'GPA value')];
    }
    usort($out['gpa_scale'], fn($a, $b) => $a['min'] <=> $b['min']);
    if ($out['average_method'] === 'credit_gpa' && (!$out['gpa_scale'] || $out['gpa_scale'][0]['min'] !== 0.0
        || count(array_unique(array_column($out['gpa_scale'], 'min'))) !== count($out['gpa_scale']))) throw new InvalidArgumentException('GPA bands must have unique minimums and start at 0.');
    foreach (['incomplete', 'withdrawn'] as $status) {
        $policy = $input['status_policy'][$status] ?? 'pending';
        if (!in_array($policy, ['pending', 'exclude', 'zero'], true)) throw new InvalidArgumentException('Choose pending, exclude, or zero for special status handling.');
        $out['status_policy'][$status] = $policy;
    }
    return $out;
}
function academic_meta(array $classroom, ?PDO $pdo = null): array {
    $select = ($pdo ?? db())->prepare('SELECT data FROM classroom_academics WHERE classroom_id = ?');
    $select->execute([(int) $classroom['id']]);
    $raw = $select->fetchColumn();
    return array_replace(['subject_code' => '', 'academic_year' => 'Unassigned', 'semester' => 'Unassigned', 'section' => '', 'credit_units' => null, 'required' => true], $raw === false ? [] : db_json_decode($raw));
}
function academic_validate_meta(array $input, array $settings): array {
    $year = grading_text($input['academic_year'] ?? null, 'Academic year', 80);
    $semester = grading_text($input['semester'] ?? null, 'Semester', 80);
    if (!in_array($year, $settings['years'], true) || !in_array($semester, $settings['semesters'], true)) throw new InvalidArgumentException('Choose a configured academic year and semester.');
    return ['subject_code' => grading_text($input['subject_code'] ?? null, 'Subject code', 40), 'academic_year' => $year, 'semester' => $semester,
        'section' => grading_text($input['section'] ?? null, 'Section', 80),
        'credit_units' => ($input['credit_units'] ?? '') === '' ? null : grading_number($input['credit_units'], .01, 100, 'Credit units'), 'required' => ($input['required'] ?? 'yes') === 'yes'];
}
function academic_save_meta(PDO $pdo, int $id, array $meta): void {
    database_upsert_record($pdo, 'classroom_academics', 'classroom_id', ['classroom_id' => $id, 'data' => db_json_encode($meta)]);
}
function academic_periods(): array { return [['id' => 'prelim', 'name' => 'Prelim', 'weight' => 30], ['id' => 'midterm', 'name' => 'Midterm', 'weight' => 30], ['id' => 'finals', 'name' => 'Finals', 'weight' => 40]]; }
function academic_validate_periods($periods): array {
    if (!is_array($periods) || count($periods) !== 3) throw new InvalidArgumentException('Configure Prelim, Midterm, and Finals.');
    $validated = []; $total = 0;
    foreach (academic_periods() as $definition) {
        $matches = array_values(array_filter($periods, fn($p) => is_array($p) && ($p['id'] ?? '') === $definition['id']));
        if (count($matches) !== 1) throw new InvalidArgumentException('Each grading period must appear once.');
        $weight = grading_number($matches[0]['weight'] ?? null, 0, 100, 'Period weight');
        $total += (int) round($weight * 100);
        $validated[] = ['id' => $definition['id'], 'name' => $definition['name'], 'weight' => $weight];
    }
    if ($total !== 10000) throw new InvalidArgumentException('Grading-period weights must total exactly 100%.');
    return $validated;
}
function academic_period_id($value): string {
    if (!is_string($value) || !in_array($value, ['prelim', 'midterm', 'finals'], true)) throw new InvalidArgumentException('Assign the activity to Prelim, Midterm, or Finals.');
    return $value;
}
function academic_calculate(array $book, int $student, array $attempts): array {
    $periods = []; $allItems = []; $weighted = 0; $active = 0; $graded = 0; $pending = 0; $complete = true;
    foreach ($book['config']['periods'] as $period) {
        $part = $book;
        $part['items'] = array_filter($book['items'], fn($item) => ($item['period_id'] ?? '') === $period['id']);
        unset($part['overrides'][(string) $student]['overall']);
        $grade = grading_calculate_weighted($part, $student, $attempts);
        $grade['weight'] = $period['weight']; $grade['name'] = $period['name']; $grade['id'] = $period['id'];
        $grade['complete'] = $period['weight'] == 0 || ($grade['status'] === 'Complete' && !array_filter($grade['categories'], fn($c) => $c['weight'] > 0 && $c['percentage'] === null));
        if (!$grade['complete']) $complete = false;
        if ($grade['overall'] !== null && $period['weight'] > 0) { $weighted += $grade['overall'] * $period['weight']; $active += $period['weight']; }
        $graded += $grade['graded']; $pending += $grade['pending']; $allItems += $grade['items'];
        $periods[$period['id']] = $grade;
    }
    $unassigned = array_filter($book['items'], fn($item) => empty($item['archived']) && !isset($periods[$item['period_id'] ?? '']));
    if ($unassigned) $complete = false;
    $overall = $active > 0 ? round($weighted / $active, 2) : null;
    $label = null; foreach ($book['config']['scale'] as $band) if ($overall !== null && $overall >= $band['min']) $label = $band['label'];
    $special = $book['academic_statuses'][(string) $student]['status'] ?? 'enrolled';
    return ['student_id' => $student, 'overall' => $overall, 'calculated' => $overall, 'override' => null, 'categories' => [], 'periods' => $periods,
        'items' => $allItems, 'status' => $special !== 'enrolled' ? ucfirst($special) : ($complete ? 'Complete' : ($graded ? 'Partial' : 'Not Started')),
        'scale_label' => $label, 'passed' => $overall === null ? null : $overall >= $book['config']['passing'], 'active_weight' => $active,
        'pending' => $pending + count($unassigned), 'graded' => $graded, 'complete' => $complete, 'special_status' => $special];
}
function academic_review_hash(array $book, array $classroom, array $attempts): string {
    $grades = []; foreach ($classroom['student_ids'] as $id) $grades[(string) $id] = grading_calculate($book, (int) $id, $attempts);
    return hash('sha256', db_json_encode(['config' => $book['config'], 'grades' => $grades]));
}
function academic_semester_average(array $subjects, array $settings): array {
    $sum = 0; $weight = 0;
    foreach ($subjects as $subject) {
        if (empty($subject['meta']['required'])) continue;
        $grade = $subject['grade'];
        if (!$grade || empty($subject['finalized'])) return ['value' => null, 'reason' => 'Waiting for finalized, released grades in every required subject.'];
        $special = strtolower($grade['special_status'] ?? 'enrolled');
        $policy = $settings['status_policy'][$special] ?? null;
        if ($policy === 'exclude') continue;
        if ($policy === 'pending') return ['value' => null, 'reason' => 'A subject has an unresolved academic status.'];
        if ($policy !== 'zero' && (empty($grade['complete']) || $grade['overall'] === null)) return ['value' => null, 'reason' => 'A subject grade is incomplete.'];
        $value = $policy === 'zero' ? 0 : $grade['overall'];
        $units = 1;
        if ($settings['average_method'] === 'credit_gpa') {
            if (!$settings['gpa_scale'] || !is_numeric($subject['meta']['credit_units']) || $subject['meta']['credit_units'] <= 0) return ['value' => null, 'reason' => 'Configure the institutional GPA scale and subject credit units.'];
            $units = (float) $subject['meta']['credit_units']; $mapped = null;
            foreach ($settings['gpa_scale'] as $band) if ($value >= $band['min']) $mapped = $band['value'];
            if ($mapped === null) return ['value' => null, 'reason' => 'Final grade has no configured GPA equivalent.'];
            $value = $mapped;
        }
        $sum += $value * $units; $weight += $units;
    }
    return ['value' => $weight > 0 ? round($sum / $weight, 2) : null, 'reason' => $weight > 0 ? 'Calculated from finalized subject grades.' : 'No required subjects to average.'];
}
