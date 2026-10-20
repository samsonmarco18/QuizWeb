<?php
if ($user['role'] === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_classroom') {
    $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
    $subject = is_string($_POST['subject'] ?? null) ? trim($_POST['subject']) : '';
    $description = is_string($_POST['description'] ?? null) ? trim($_POST['description']) : '';

    if ($name === '' || $subject === '') {
        $errors[] = 'Classroom name and subject are required.';
    }
    if (mb_strlen($description) > 500) $errors[] = 'Keep the classroom description to 500 characters.';
    try {
        $academicMeta = academic_validate_meta($_POST, academic_settings());
        $templateId = $_POST['grading_template'] ?? '';
        if (!is_string($templateId) || (!isset(grading_templates()[$templateId]) && $templateId !== 'custom')) throw new InvalidArgumentException('Choose a grading template or Custom.');
        $creationConfig = grading_validate_config($_POST['creation_config'] ?? (grading_templates()[$templateId]['config'] ?? null), grading_empty_book());
        if (strlen($name) > 190 || strlen($subject) > 190) throw new InvalidArgumentException('Classroom and subject names must be under 190 characters.');
    } catch (InvalidArgumentException $ex) { $errors[] = $ex->getMessage(); }

    if (!$errors) {
        $classroom = [
            'id' => 0,
            'teacher_id' => $user['id'],
            'name' => $name,
            'subject' => $subject,
            'description' => $description,
            'code' => generate_join_code(),
            'student_ids' => [],
            'quizzes' => [],
            'announcements' => [],
            'chat_messages' => [],
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ];
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if (DB_DRIVER === 'pgsql') $pdo->exec('LOCK TABLE classrooms IN EXCLUSIVE MODE');
            else $pdo->query('SELECT id FROM classrooms FOR UPDATE')->fetchAll();
            $classroom['id'] = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM classrooms')->fetchColumn();
            insert_classroom_record($pdo, $classroom);
            academic_save_meta($pdo, (int) $classroom['id'], $academicMeta);
            $newBook = grading_empty_book(); $newBook['config'] = $creationConfig;
            grading_write($pdo, (int) $classroom['id'], $newBook);
            grading_audit($pdo, (int) $classroom['id'], (int) $user['id'], 'config', null, $newBook['config']);
            $pdo->commit();
            flash_set('success', 'Classroom created. Share the join code with your students.');
            redirect('/QuizWeb/classroom.php?id=' . $classroom['id']);
        } catch (Throwable $exception) {
            $pdo->rollBack(); error_log('Classroom creation failed: ' . $exception->getMessage());
            $errors[] = 'Could not create this classroom. Please try again.';
        }
    }
}

