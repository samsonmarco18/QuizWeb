<?php

require_once __DIR__ . '/includes/app.php';

unset($_SESSION['user_id']);
flash_set('success', 'You have been logged out.');
redirect('/QuizWeb/login.php');
