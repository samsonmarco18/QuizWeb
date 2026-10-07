<?php

require_once __DIR__ . '/app.php';

/** Build display records from the signed-in student's own classroom attempts. */
function classroom_quiz_cards(array $classroom, array $user, array $attempts, ?int $now = null): array
{
    $now ??= time();
    $best = [];
    foreach ($attempts as $attempt) {
        if ((int) ($attempt['classroom_id'] ?? 0) !== (int) $classroom['id'] || (int) ($attempt['student_id'] ?? 0) !== (int) $user['id']) continue;
        $id = (int) ($attempt['quiz_id'] ?? 0);
        $ratio = (int) ($attempt['max_score'] ?? 0) > 0 ? (int) $attempt['score'] / (int) $attempt['max_score'] : 0;
        $previous = $best[$id] ?? null;
        $previousRatio = $previous && (int) $previous['max_score'] > 0 ? (int) $previous['score'] / (int) $previous['max_score'] : 0;
        if (!$previous || $ratio > $previousRatio || ($ratio === $previousRatio && (int) $attempt['id'] > (int) $previous['id'])) $best[$id] = $attempt;
    }
    $cards = [];
    foreach ($classroom['quizzes'] ?? [] as $quiz) {
        $attempt = $best[(int) $quiz['id']] ?? null;
        $due = !empty($quiz['due_at']) && is_string($quiz['due_at']) ? strtotime($quiz['due_at']) : false;
        $type = (string) ($quiz['game_type'] ?? 'standard');
        $questions = count($quiz['questions'] ?? []);
        $seconds = $type === 'time_attack' ? 12 : (in_array($type, ['crossword', 'fill_blank', 'emoji_quiz'], true) ? 60 : 40);
        $cards[] = [
            'quiz' => $quiz, 'best' => $attempt, 'completed' => $attempt !== null,
            'percent' => $attempt ? min(100, max(0, percentage((int) $attempt['score'], (int) $attempt['max_score']))) : 0,
            'points' => array_sum(array_map(static fn(array $question): int => (int) ($question['points'] ?? 10), $quiz['questions'] ?? [])),
            'questions' => $questions, 'minutes' => max(1, (int) ceil($questions * $seconds / 60)),
            'due' => $due === false ? null : $due,
            'due_status' => $due === false ? '' : ($due < $now ? 'past' : ($due - $now <= 3 * 86400 ? 'soon' : 'scheduled')),
            'days_left' => $due === false ? null : max(0, (int) round((strtotime(date('Y-m-d', $due)) - strtotime(date('Y-m-d', $now))) / 86400)),
        ];
    }
    return $cards;
}

function classroom_quiz_filters(array $query): array
{
    $filter = is_string($query['quiz_filter'] ?? null) ? $query['quiz_filter'] : 'all';
    if (!in_array($filter, ['all', 'todo', 'completed'], true) && !isset(game_modes()[$filter])) $filter = 'all';
    $sort = is_string($query['quiz_sort'] ?? null) ? $query['quiz_sort'] : 'assigned';
    if (!in_array($sort, ['assigned', 'title', 'due'], true)) $sort = 'assigned';
    $search = is_string($query['q'] ?? null) ? trim($query['q']) : '';
    return ['filter' => $filter, 'sort' => $sort, 'search' => substr($search, 0, 200)];
}

function classroom_filter_quizzes(array $cards, array $filters): array
{
    $modes = game_modes();
    $search = $filters['search'];
    $cards = array_values(array_filter($cards, static function (array $card) use ($filters, $modes, $search): bool {
        $type = $card['quiz']['game_type'] ?? 'standard';
        $filter = $filters['filter'];
        if ($filter === 'todo' && $card['completed']) return false;
        if ($filter === 'completed' && !$card['completed']) return false;
        if (!in_array($filter, ['all', 'todo', 'completed'], true) && $type !== $filter) return false;
        $text = ($card['quiz']['title'] ?? '') . ' ' . ($card['quiz']['description'] ?? '') . ' ' . ($modes[$type]['label'] ?? '');
        return $search === '' || (function_exists('mb_stripos') ? mb_stripos($text, $search) !== false : stripos($text, $search) !== false);
    }));
    if ($filters['sort'] === 'title') usort($cards, static fn(array $a, array $b): int => strnatcasecmp($a['quiz']['title'], $b['quiz']['title']));
    if ($filters['sort'] === 'due') usort($cards, static fn(array $a, array $b): int => ($a['due'] ?? PHP_INT_MAX) <=> ($b['due'] ?? PHP_INT_MAX));
    return $cards;
}

function classroom_game_art(string $type): string
{
    // Related written/card modes share an illustration; the badge names the mode.
    $art = ['time_attack' => 'time_attack', 'crossword' => 'crossword', 'flip_match' => 'flip_match', 'memory_flip' => 'flip_match'][$type] ?? 'standard';
    return '/QuizWeb/assets/images/classroom/' . $art . '.png';
}
