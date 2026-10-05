<?php
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
function check_image(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aC1sAAAAASUVORK5CYII=';
check_image(activity_match_image($png) === $png, 'Valid raster image survives normalization.');
foreach (['https://example.com/image.png', 'data:image/svg+xml;base64,' . base64_encode('<svg/>'), 'data:image/png;base64,' . base64_encode('not an image'), 'data:image/jpeg;base64,' . explode(',', $png)[1], $png . str_repeat('A', 140000)] as $invalid) {
    try { activity_match_image($invalid); throw new LogicException('Unsafe image accepted.'); }
    catch (InvalidArgumentException $expected) {}
}
$pairs = [
    ['prompt' => 'First image', 'answer' => 'First definition', 'prompt_image' => $png, 'answer_image' => '', 'points' => 15],
    ['prompt' => 'Second term', 'answer' => 'Second image', 'prompt_image' => '', 'answer_image' => $png, 'points' => 20],
];
$ready = prepare_activity_questions('flip_match', $pairs);
check_image(!$ready['errors'], 'Text-to-image and image-to-text pairs are accepted.');
check_image($ready['questions'][0]['prompt_image'] === $png && $ready['questions'][1]['answer_image'] === $png, 'Both image faces persist in saved questions.');
check_image(activity_answer_is_correct($ready['questions'][0], 'First definition'), 'Images do not change normal server grading.');
$pairs[1]['prompt_image'] = $png;
check_image((bool) prepare_activity_questions('flip_match', $pairs)['errors'], 'Ambiguous duplicate image cards must be rejected.');
echo "Matching raster validation, unsafe/oversized image rejection, both card faces, saved fields, grading, and duplicate images passed.\n";
