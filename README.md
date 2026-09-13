# QuizWeb / CHALK

CHALK is a PHP classroom quiz system for teachers and students. Teachers create classrooms, post announcements, build quiz games, and review class performance. Students join with a code, play quiz games, see results, and get a self-learning coach that points out weak areas.

## Requirements

- XAMPP with Apache, PHP, and MySQL
- Project folder located at `C:\xampp\htdocs\QuizWeb`
- Browser access to `http://localhost/QuizWeb/`

## Setup

1. Open the XAMPP Control Panel.
2. Start `Apache` and `MySQL`.
3. Open `http://localhost/QuizWeb/` in your browser.
4. Register one teacher account and one student account.

The app automatically creates the `quizweb` MySQL database and tables when it runs. The schema is also available in `database/schema.sql` if you want to inspect or import it manually.

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
- Focus Practice builds a no-grade mini game from the student's weakest multiple-choice items.

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
- If login or saving fails, confirm MySQL is running.
- If the database needs to be recreated, drop the `quizweb` database from phpMyAdmin and reload the app.
- If uploads fail, make sure PHP file uploads are enabled in XAMPP and the `data/uploads` folder is writable.
