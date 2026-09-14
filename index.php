<?php

require_once __DIR__ . '/includes/app.php';

redirect(is_logged_in() ? '/QuizWeb/dashboard.php' : '/QuizWeb/login.php');
