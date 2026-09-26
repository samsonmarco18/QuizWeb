<?php

require_once dirname(__DIR__, 3) . '/includes/app.php';

unset($_SESSION['user_id']);
flash_set('success', 'You have been logged out.');
redirect('/QuizWeb/login.php');
