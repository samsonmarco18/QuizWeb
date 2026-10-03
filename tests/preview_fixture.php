<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../includes/app.php';
$prepared = prepare_activity_questions('crossword', [
    ['prompt' => 'A programming language', 'answer' => 'PYTHON', 'preferred_direction' => 'across', 'options' => [], 'level' => 'easy'],
    ['prompt' => 'A data kind', 'answer' => 'TYPE', 'preferred_direction' => 'down', 'options' => [], 'level' => 'easy'],
    ['prompt' => 'A network machine', 'answer' => 'HOST', 'preferred_direction' => 'down', 'options' => [], 'level' => 'easy'],
]);
if ($prepared['errors']) throw new RuntimeException(implode(' ', $prepared['errors']));
echo json_encode(['title' => 'Crossword preview', 'game_type' => 'crossword', 'questions' => $prepared['questions'], 'crossword_layout' => $prepared['layout']]);
