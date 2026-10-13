<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/ai_quiz.php';
function grid_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function grid_reject(callable $call, string $message): void {
    try { $call(); } catch (RuntimeException $expected) { return; }
    throw new LogicException($message);
}
function grid_word(string $answer, string $level = 'easy'): array {
    return ['answer' => $answer, 'prompt' => 'Clue for item ' . $answer, 'topic' => 'Lesson', 'explanation' => 'Supported by the lesson.', 'level' => $level, 'preferred_direction' => 'auto'];
}
// Assert actual cell geometry and connected word paths, not just a successful
// return or pairs of words sharing letters somewhere in their answers.
function check_grid(array $prepared): void {
    grid_check(!$prepared['errors'], implode('; ', $prepared['errors']));
    $layout = $prepared['layout'];
    $questions = array_column($prepared['questions'], null, 'id');
    $occupants = [];
    foreach ($layout['placements'] as $p) {
        $answer = $questions[$p['question_id']]['answer'];
        for ($i = 0; $i < strlen($answer); $i++) {
            $row = $p['row'] + ($p['direction'] === 'down' ? $i : 0);
            $col = $p['col'] + ($p['direction'] === 'across' ? $i : 0);
            grid_check($layout['cells'][$row][$col] === $answer[$i], 'Grid spells each full answer');
            $occupants[$row . ':' . $col][] = [$p['question_id'], $p['direction']];
        }
    }
    $neighbors = [];
    foreach ($occupants as $words) {
        grid_check(count($words) <= 2, 'No cell holds three words');
        if (count($words) === 2) {
            grid_check($words[0][1] !== $words[1][1], 'Intersections are perpendicular');
            $neighbors[$words[0][0]][] = $words[1][0];
            $neighbors[$words[1][0]][] = $words[0][0];
        }
    }
    $queue = [array_key_first($questions)]; $seen = [$queue[0] => true];
    for ($head = 0; $head < count($queue); $head++) foreach ($neighbors[$queue[$head]] ?? [] as $id) {
        if (!isset($seen[$id])) { $seen[$id] = true; $queue[] = $id; }
    }
    grid_check(count($seen) === count($questions), 'Every word belongs to one connected grid');
}
// The old longest-first loop rejected UVWXY before ever trying the bridge BEV.
$bridge = [grid_word('ABCDEF', 'easy'), grid_word('UVWXY', 'medium'), grid_word('BEV', 'hard')];
$prepared = prepare_activity_questions('crossword', $bridge);
check_grid($prepared);
grid_check($prepared['layout'] === prepare_activity_questions('crossword', $bridge)['layout'], 'Preview/save remain deterministic');
$options = ai_quiz_options(['count' => 3, 'game_type' => 'crossword', 'difficulty' => 'mixed', 'language' => 'English']);
$request = ai_quiz_request($options, ai_quiz_source(str_repeat('Lesson terms and explanations. ', 6), null), 'gemini-3.8-flash');
grid_check($request['response_format']['schema']['properties']['questions']['maxItems'] === 9, 'Request extra lesson candidates');
grid_check(str_contains($request['system_instruction'], 'connected grid') && str_contains($request['system_instruction'], 'Never pad'), 'Pool stays grounded in the lesson');
// Isolated terms are excluded locally, without an extra generation call.
$pool = [grid_word('ZZZ'), ...$bridge, grid_word('QQQ')];
$draft = ai_quiz_validate(json_encode(['questions' => $pool]), $options);
grid_check(count($draft['questions']) === 3, 'Return exactly the teacher count');
grid_check(array_column($draft['questions'], 'answer') === ['ABCDEF', 'UVWXY', 'BEV'], 'Choose the connected subset, excluding isolated words');
grid_check(count(array_unique(array_column($draft['questions'], 'level'))) === 3, 'Selected subset keeps all mixed difficulty levels');
check_grid(prepare_activity_questions('crossword', $draft['questions']));
$missingHard = [grid_word('PYTHON'), grid_word('TYPE', 'medium'), grid_word('HOST'), grid_word('QQQ', 'hard')];
grid_reject(fn() => ai_quiz_validate(json_encode(['questions' => $missingHard]), $options), 'An isolated hard clue cannot satisfy mixed difficulty');
$easyOptions = array_replace($options, ['difficulty' => 'easy']);
$sameLetters = [grid_word('ABC'), grid_word('ADE'), grid_word('AFG')];
grid_reject(fn() => ai_quiz_validate(json_encode(['questions' => $sameLetters]), $easyOptions), 'Sharing letters alone does not prove a legal grid');
$tooMany = array_fill(0, 10, grid_word('PYTHON'));
grid_reject(fn() => ai_quiz_validate(json_encode(['questions' => $tooMany]), $options), 'Candidate pool size stays bounded');
$tooFew = array_slice($bridge, 0, 2);
grid_reject(fn() => ai_quiz_validate(json_encode(['questions' => $tooFew]), $options), 'Do not silently return fewer clues');
$acrossOnly = array_map(static fn($q) => array_replace($q, ['preferred_direction' => 'across']), $bridge);
grid_check((bool) prepare_activity_questions('crossword', $acrossOnly)['errors'], 'Honor teacher direction constraints');
$words = array_map('grid_word', ['UNIVERSE', 'GALAXY', 'SOLAR', 'PLANET', 'STAR', 'EARTH', 'SUN', 'MOON', 'ORBIT', 'GRAVITY', 'SPACE', 'SATURN', 'MARS', 'VENUS', 'MERCURY', 'NEPTUNE', 'URANUS', 'COSMOS', 'ASTEROID', 'COMET']);
check_grid(prepare_activity_questions('crossword', $words));
$dense = [];
foreach (range('B', 'Z') as $index => $letter) $dense[] = grid_word(str_repeat('A', 7) . $letter . str_repeat('A', 7)) + ['id' => $index + 1];
$denseResult = crossword_search($dense, count($dense));
grid_check($denseResult === null || count($denseResult['placements']) === count($dense), 'Bound repetitive searches without returning a partial puzzle');
echo "Crossword generation checks passed: bridge placement, connected candidate selection, exact count, difficulty, perpendicular intersections, deterministic preview/save and 20-word lesson grid.\n";
