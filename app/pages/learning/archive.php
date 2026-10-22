<?php
require_once dirname(__DIR__, 3) . '/includes/layout.php';
$user = require_role('student');
$collection = 'archive';
require dirname(__DIR__, 3) . '/includes/material_collection.php';