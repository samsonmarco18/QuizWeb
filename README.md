# QuizWeb / CHALK

Source files are grouped by feature under app/pages/; root PHP files preserve the
existing public URLs. See [Project structure](docs/PROJECT_STRUCTURE.md) for the
folder map, editing conventions, and verification commands.

CHALK is a PHP classroom quiz system for teachers and students. Teachers create classrooms, post announcements, build quiz games, and review class performance. Students join with a code, play quiz games, see results, and get a self-learning coach that points out weak areas.

## Local Docker setup (PostgreSQL)

The production stack uses PHP/Apache and PostgreSQL. Install Docker Desktop, then run:

```bash
docker compose up --build
```

Open `http://localhost:8080/QuizWeb/`. PostgreSQL data is stored in the named `postgres_data` volume and announcement uploads are persisted in `data/uploads`.

## Render deployment

This repository includes a Render Blueprint in `render.yaml`. Push the project to a Git repository, then create a new **Blueprint** in Render and select that repository. The Blueprint provisions the Docker web service and a managed PostgreSQL 16 database, wiring the database credentials through environment variables. The web service is available at `/QuizWeb/` after deployment.

For local non-Docker PostgreSQL configuration, copy `.env.example` and set the `QUIZWEB_DB_*` environment variables in your web-server environment. Do not commit real passwords.

## Legacy XAMPP setup

- XAMPP with Apache, PHP, and PostgreSQL PDO support
- Project folder located at `C:\xampp\htdocs\QuizWeb`
- Browser access to `http://localhost/QuizWeb/`

## Setup

1. Open the XAMPP Control Panel.
2. Start `Apache` and `MySQL`.
3. Open `http://localhost/QuizWeb/` in your browser.
4. Register one teacher account and one student account.

Create a PostgreSQL database named `quizweb` and apply `database/schema.sql` before starting the app. Docker Compose and Render handle this automatically.

## Teacher Test Flow

1. Log in as a teacher.
2. Create a classroom from the dashboard.
3. Open the classroom and copy the join code.
4. Click `Create Quiz Game`.
5. Choose a game mode:
   - Time Attack
   - Rocket Rush
   - Memory Flip
   - Treasure Dive
   - Boss Battle
   - Crossword Puzzle
   - Mastery Ladder
6. Add questions, answers, points, and levels.
7. Save the quiz.
8. Use `Preview` to check the game.

For a quick Mastery Ladder test, use `Create Sample Mastery Quiz` on the teacher dashboard.

## Student Test Flow

1. Log in as a student.
2. Click `Join Class`.
3. Enter the teacher's classroom code.
4. Open the classroom.
5. Play an available quiz game.
6. Finish the game and view the results page.
7. Check the `Learning review` section to see correct answers, missed items, and guidance.
8. Return to the dashboard and check `Self-learning coach`.
9. Open `Focus Practice` to practice weak multiple-choice items without saving a grade.

## Self-Learning Features

- Student dashboard shows overall accuracy, questions analyzed, weak items, weak difficulty bands, and a study plan.
- Results page shows a per-question learning review after each attempt.
- Classroom page shows students their class-specific weak areas.
- Teacher classroom page shows class-level weak questions and difficulty bands to reteach.
- Focus Practice builds a no-grade mini game from the student's weakest multiple-choice items. Training runs are saved to the student's activity history but do not affect classroom leaderboards.

### Sample dashboard data

Seed five students, three subject teachers, three classrooms, quizzes, and varied scores with:

```bash
php scripts/seed_sample_data.php
```

When using Docker, run it inside the web container so PostgreSQL support and database environment variables are available:

```bash
docker compose exec web php scripts/seed_sample_data.php
```

On Render, the web container runs the idempotent seeder during startup after PostgreSQL becomes available. The three teachers, five students, classrooms, quizzes, deadlines, and attempts are therefore real persisted PostgreSQL records—there is no dashboard load button. Redeploying does not duplicate the seeded attempts.

All demo accounts use the password `Sample123!`. The script prints each demo email and is safe to rerun without duplicating its quiz attempts.

## Game Testing Checklist

- Time Attack: timer counts down per question and timeout marks the item wrong.
- Rocket Rush: answers can be selected by mouse or keys `1` to `4`.
- Memory Flip: answer cards render as large card-like choices and still submit correctly.
- Treasure Dive: choices keep the themed styling and scoring works.
- Boss Battle: boss and shield health scale with quiz length.
- Crossword Puzzle: grid renders, arrow keys move between cells, and submitted words are graded.
- Mastery Ladder: Easy unlocks Medium, Medium unlocks Hard, and Hard unlocks Master only when the level accuracy reaches the mastery target.

## Notes

- Student live quiz attempts use fullscreen/focus protection. Leaving fullscreen, switching tabs, minimizing, or closing the quiz can trigger warnings and eventually mark the attempt as zero.
- Teacher previews and Focus Practice are not saved to the scoreboard.
- Announcement uploads are stored under `data/uploads/announcements`.

## Troubleshooting

- If the page does not load, confirm Apache is running and visit `http://localhost/QuizWeb/`.
- If login or saving fails, confirm PostgreSQL is running and the `pdo_pgsql` PHP extension is enabled.
- If the database needs to be recreated locally, create a new `quizweb` database and apply `database/schema.sql` again.
- If uploads fail, make sure PHP file uploads are enabled in XAMPP and the `data/uploads` folder is writable.
