<?php
if (getenv('QUIZWEB_ACADEMIC_HTTP_TEST') !== '1' || PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$relative = preg_replace('#^/QuizWeb/#', '', $path);
$root = realpath(__DIR__ . '/..'); $target = realpath($root . '/' . $relative);
if (!$target || !str_starts_with($target, $root . DIRECTORY_SEPARATOR) || str_starts_with($relative, 'data/') || str_starts_with($relative, 'tests/')) { http_response_code(404); exit; }
if (str_ends_with($target, '.php')) { require $target; return; }
$mime=['css'=>'text/css','js'=>'text/javascript','png'=>'image/png','svg'=>'image/svg+xml']; header('Content-Type: '.($mime[pathinfo($target,PATHINFO_EXTENSION)] ?? 'application/octet-stream')); readfile($target);
