# QuizWeb / CHALK

For the complete teacher, student, and administrator workflows, game mechanics,
scoring formulas, leaderboards, and grade publication, see
[System workflow guide](docs/SYSTEM_WORKFLOW.md).

For the new activity templates and unsaved crossword preview, see
[Activity builder](docs/ACTIVITY_BUILDER.md).

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

## Render deployment and sample accounts

The live website is **https://chalk-web.onrender.com/QuizWeb/**. Sign in at
[Render login](https://chalk-web.onrender.com/QuizWeb/login.php).

| Role | Email | Password |
| --- | --- | --- |
| Administrator | admin@chalk.local | ChalkAdmin!2026 |
| Teacher | elena.cruz@chalk.demo | Sample123! |
| Student | ava.mendoza@chalk.demo | Sample123! |

After admin sign-in, the dashboard redirects to
[Administration](https://chalk-web.onrender.com/QuizWeb/admin.php). The email's
.local suffix is simply an account name; the account is stored in Render PostgreSQL.

Render starts the Docker image from the deployed Git revision. Its startup command
runs scripts/bootstrap_render.php inside Render before Apache accepts requests.
This applies the single database/schema.sql file, creates the sample administrator
and its audit entry atomically, and provisions three teachers, five students, three
classrooms, quizzes, deadlines, and sample results in the connected PostgreSQL.
An advisory lock serializes overlapping bootstrap runs. Redeploying preserves
existing passwords, class members, quizzes, deadlines, and attempts. The five demo
students are added to the demo classrooms if missing; real members are retained.
Existing conflicting account roles cause provisioning to fail rather than grant
privileges. Changed passwords and suspended sample accounts are preserved.

Startup retries PostgreSQL provisioning up to 20 times and fails the deployment
if it cannot finish. Web requests never run account provisioning. The deployment
bootstrap refuses to run outside Render. No local database setup or manual SQL
import is required to obtain these accounts on Render.

The Render Blueprint in render.yaml connects chalk-web to chalk-postgres using
QUIZWEB_DB_HOST, QUIZWEB_DB_PORT, QUIZWEB_DB_NAME, QUIZWEB_DB_USER, and
QUIZWEB_DB_PASS. Existing Render services must use this Dockerfile/startup command
and track this repository's main branch. Pushes deploy automatically when the
service's auto-deploy setting is enabled; otherwise use Deploy latest commit.
The Blueprint health check is /QuizWeb/health.php, which checks PostgreSQL and
reports the Render Git revision without exposing credentials.

Verify the deployed revision and all three logins with:

```bash
python tests/render_smoke_test.py --revision YOUR_DEPLOYED_COMMIT_SHA
```

The test uses HTTPS sessions on Render, checks persisted sample data and role
permissions, and logs out each account. It does not create accounts or alter grades.
Existing accounts with rotated passwords retain those passwords; the sample test
credentials apply to newly provisioned accounts.

## Legacy XAMPP setup

- XAMPP with Apache, PHP, and PostgreSQL PDO support
- Project folder located at `C:\xampp\htdocs\QuizWeb`
- Browser access to `http://localhost/QuizWeb/`

## Setup

1. Open the XAMPP Control Panel.
2. Start `Apache` and PostgreSQL (the application uses PostgreSQL, not MySQL).
3. Open `http://localhost/QuizWeb/` in your browser.
4. Register one teacher account and one student account.

Create a PostgreSQL database named `quizweb` and apply `database/schema.sql` before starting the app. Docker Compose and Render handle this automatically.

### One main PostgreSQL file

All PostgreSQL tables, indexes, legacy upgrades, and the sample admin insert live
in **[database/schema.sql](database/schema.sql)**. Render imports it automatically
through the deployment bootstrap described above. A full manual import also
creates the admin if its email is unused, and never resets existing credentials.
Web requests execute only the schema section. For a separate custom administrator,
run scripts/create_admin.php inside the intended service with
QUIZWEB_ADMIN_EMAIL and QUIZWEB_ADMIN_PASSWORD supplied through its environment.

## Teacher Test Flow

### Classroom messenger

The floating chat button opens classroom conversations. Messages send without a page reload, and conversations refresh every five seconds while the tab is visible. Search and the Unread filter work across the current user's classrooms.

- Enter sends; Shift+Enter adds a line break. Failed sends retain the draft.
- File and Image attach up to four files (10 MB each, also subject to PHP upload limits). Downloads require classroom membership.
- Link shares an HTTP/HTTPS URL; the smile button inserts emoji.
- Poll accepts 2–6 distinct options. Each member can vote once and change their vote.
- Repeated submission of the same send request does not create another message.

Run `php tests/chat_test.php` for validation, permissions, and voting checks. Add `--database` with PostgreSQL running and `pdo_pgsql` enabled to test persistence in a rolled-back transaction.

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

On Render, the web container runs the admin-and-sample bootstrap during startup after PostgreSQL becomes available. The three teachers, five students, classrooms, quizzes, deadlines, and attempts are therefore real persisted PostgreSQL records—there is no dashboard load button. Redeploying does not duplicate the seeded attempts.

All newly created student and teacher demo accounts use the password `Sample123!`. The script prints each demo email and is safe to rerun without duplicating its quiz attempts.

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
