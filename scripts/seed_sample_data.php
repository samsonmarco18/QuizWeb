<?php

if (PHP_SAPI === 'cli') {
    session_save_path(sys_get_temp_dir());
}

require_once __DIR__ . '/../includes/app.php';

if (PHP_SAPI === 'cli' && !in_array('pgsql', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "The pdo_pgsql PHP extension is required to seed the PostgreSQL database.\n");
    exit(1);
}

function seed_sample_data(?array $currentStudent = null): array
{

$password = 'Sample123!';
$teacherSeeds = [
    ['Dr. Elena Cruz', 'elena.cruz@chalk.demo', 'Science'],
    ['Prof. Marco Reyes', 'marco.reyes@chalk.demo', 'Mathematics'],
    ['Ms. Sofia Santos', 'sofia.santos@chalk.demo', 'English'],
];
$studentSeeds = [
    ['Ava Mendoza', 'ava.mendoza@chalk.demo'],
    ['Liam Garcia', 'liam.garcia@chalk.demo'],
    ['Mia Flores', 'mia.flores@chalk.demo'],
    ['Noah Ramos', 'noah.ramos@chalk.demo'],
    ['Zoe Bautista', 'zoe.bautista@chalk.demo'],
];

if ($currentStudent && ($currentStudent['role'] ?? '') === 'student') {
    $studentSeeds[0] = [$currentStudent['name'], $currentStudent['email']];
}

foreach ($teacherSeeds as [$name, $email, $subject]) {
    if (!find_user_by_email($email)) {
        register_user($name, $email, $password, 'teacher', ['program' => $subject, 'year_level' => 'Faculty']);
    }
}
foreach ($studentSeeds as [$name, $email]) {
    if (!find_user_by_email($email)) {
        register_user($name, $email, $password, 'student', ['program' => 'General Education', 'year_level' => 'Grade 10']);
    }
}

$teachers = array_map(fn(array $seed) => find_user_by_email($seed[1]), $teacherSeeds);
$students = array_map(fn(array $seed) => find_user_by_email($seed[1]), $studentSeeds);
$studentIds = array_map(fn(array $student) => (int) $student['id'], $students);

$questionSets = [
    'Science' => [
        ['What process allows plants to make food using sunlight?', ['Respiration', 'Photosynthesis', 'Digestion', 'Fermentation'], 1],
        ['Which organelle is known as the powerhouse of the cell?', ['Nucleus', 'Ribosome', 'Mitochondrion', 'Vacuole'], 2],
        ['What force pulls objects toward Earth?', ['Friction', 'Gravity', 'Magnetism', 'Electricity'], 1],
        ['Which state of matter has a fixed volume but no fixed shape?', ['Solid', 'Liquid', 'Gas', 'Plasma'], 1],
    ],
    'Mathematics' => [
        ['What is 12 multiplied by 8?', ['86', '96', '106', '88'], 1],
        ['Solve: 3x + 6 = 18.', ['2', '3', '4', '6'], 2],
        ['What is the area of a 5 by 7 rectangle?', ['12', '24', '30', '35'], 3],
        ['What is 25% of 80?', ['15', '20', '25', '30'], 1],
    ],
    'English' => [
        ['Which word is a synonym for rapid?', ['Slow', 'Quick', 'Quiet', 'Late'], 1],
        ['Which sentence uses the correct verb?', ['She walk daily.', 'She walking daily.', 'She walks daily.', 'She walked daily tomorrow.'], 2],
        ['What is the central message of a paragraph called?', ['Caption', 'Main idea', 'Footnote', 'Setting'], 1],
        ['Which word is an adjective?', ['Carefully', 'Beauty', 'Bright', 'Run'], 2],
    ],
];

$records = classrooms();
$sampleClassrooms = [];
foreach ($teacherSeeds as $index => [$teacherName, $teacherEmail, $subject]) {
    $className = 'Grade 10 ' . $subject;
    $classroom = null;
    foreach ($records as $existing) {
        if (($existing['name'] ?? '') === $className && (int) ($existing['teacher_id'] ?? 0) === (int) $teachers[$index]['id']) {
            $classroom = $existing;
            break;
        }
    }

    if (!$classroom) {
        $preferredCode = 'DEMO' . ($index + 1);
        $preferredCodeOwner = find_classroom_by_code($preferredCode);
        $questions = [];
        foreach ($questionSets[$subject] as $questionIndex => [$prompt, $options, $correctIndex]) {
            $questions[] = [
                'id' => $questionIndex + 1,
                'prompt' => $prompt,
                'options' => $options,
                'correct_index' => $correctIndex,
                'points' => 10,
                'level' => ['easy', 'medium', 'hard', 'master'][$questionIndex],
            ];
        }
        $classroom = [
            'id' => next_id($records),
            'teacher_id' => (int) $teachers[$index]['id'],
            'name' => $className,
            'subject' => $subject,
            'description' => 'Sample ' . $subject . ' classroom with quiz and performance data.',
            'code' => $preferredCodeOwner ? generate_join_code() : $preferredCode,
            'student_ids' => $studentIds,
            'quizzes' => [[
                'id' => 1,
                'title' => $subject . ' Foundations',
                'description' => 'A four-question baseline quiz for dashboard analysis.',
                'game_type' => 'time_attack',
                'questions' => $questions,
                'created_at' => now_iso(),
                'updated_at' => now_iso(),
            ]],
            'announcements' => [],
            'chat_messages' => [],
            'created_at' => now_iso(),
            'updated_at' => now_iso(),
        ];
        $records[] = $classroom;
    } else {
        $classroom['student_ids'] = $studentIds;
        foreach ($records as $recordIndex => $record) {
            if ((int) $record['id'] === (int) $classroom['id']) {
                $records[$recordIndex] = $classroom;
                break;
            }
        }
    }
    $sampleClassrooms[] = $classroom;
}
save_classrooms($records);

$targetScores = [
    [100, 50, 75],
    [75, 100, 50],
    [50, 75, 100],
    [75, 50, 75],
    [100, 75, 50],
];

foreach ($students as $studentIndex => $student) {
    foreach ($sampleClassrooms as $classIndex => $classroom) {
        $quiz = $classroom['quizzes'][0];
        $alreadySeeded = array_filter(attempts(), fn(array $attempt) =>
            (int) $attempt['student_id'] === (int) $student['id']
            && (int) $attempt['classroom_id'] === (int) $classroom['id']
            && (int) $attempt['quiz_id'] === (int) $quiz['id']
        );
        if ($alreadySeeded) {
            continue;
        }

        $correctCount = (int) round(($targetScores[$studentIndex][$classIndex] / 100) * count($quiz['questions']));
        $answers = [];
        foreach ($quiz['questions'] as $questionIndex => $question) {
            $correct = (int) $question['correct_index'];
            $answers[$questionIndex] = $questionIndex < $correctCount ? $correct : (($correct + 1) % count($question['options']));
        }
        create_attempt((int) $student['id'], (int) $classroom['id'], $quiz, $answers, 52 + ($studentIndex * 7) + ($classIndex * 4));
    }
}

    return ['password' => $password, 'teachers' => $teacherSeeds, 'students' => $studentSeeds];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $seeded = seed_sample_data();
    echo "Sample data ready.\n";
    echo "Password for all demo accounts: {$seeded['password']}\n";
    foreach (array_merge($seeded['teachers'], $seeded['students']) as $seed) {
        echo "- {$seed[1]}\n";
    }
}
