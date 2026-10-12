<?php
// Copy to gemini.local.php for XAMPP; this local file is ignored by Git.
if (!defined('QUIZWEB_AI_CONFIG')) { http_response_code(404); exit; }
return [
    'api_key' => '', // Paste the key here locally. Never share it in chat.
    'model' => 'gemini-3.8-flash',
];
